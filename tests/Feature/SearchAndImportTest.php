<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Searching the listings, and importing people from a spreadsheet.
 *
 * The search tests describe what someone actually types: two letters, a
 * half-remembered surname, the tail of a student number, or a first name and a
 * surname together.
 */
class SearchAndImportTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function staff(): User
    {
        return $this->userFor($this->school, [
            'teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive',
            'students.view', 'users.view',
        ]);
    }

    protected function teacher(array $attributes = []): Teacher
    {
        static $n = 0;
        $n++;

        return Teacher::create(array_merge([
            'school_id' => $this->school->id,
            'staff_number' => 'GFI-T-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
            'is_public' => false,
        ], $attributes));
    }

    /* -------------------------------------------------------------- search */

    public function test_two_letters_find_a_surname(): void
    {
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie']);
        $this->teacher(['first_name' => 'Emmanuel', 'last_name' => 'Toe']);

        $this->assertSame(
            ['Grace'],
            Teacher::search('ko')->pluck('first_name')->all(),
        );
    }

    public function test_a_prefix_does_not_match_the_middle_of_a_word(): void
    {
        // "ko" must find Kollie, not Jackson: matching anywhere inside a word
        // makes a short search useless, which is the whole problem.
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie']);
        $this->teacher(['first_name' => 'Peter', 'last_name' => 'Jackson']);

        $this->assertSame(['Kollie'], Teacher::search('ko')->pluck('last_name')->all());
    }

    public function test_a_full_name_is_found_even_though_no_column_holds_it(): void
    {
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie']);

        $this->assertCount(1, Teacher::search('Grace Kollie')->get());

        // And in either order, because people type surnames first too.
        $this->assertCount(1, Teacher::search('kollie grace')->get());
    }

    public function test_every_word_typed_has_to_match_something(): void
    {
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie']);

        $this->assertCount(0, Teacher::search('grace zzzz')->get());
    }

    public function test_the_tail_of_a_staff_number_finds_the_person(): void
    {
        $this->teacher(['staff_number' => 'GFI-T-042', 'first_name' => 'Musu', 'last_name' => 'Sirleaf']);
        $this->teacher(['staff_number' => 'GFI-T-043', 'first_name' => 'Other', 'last_name' => 'Person']);

        // Nobody types a staff number from the beginning; they read the last
        // digits off a form.
        $this->assertSame(['Musu'], Teacher::search('042')->pluck('first_name')->all());
    }

    public function test_a_double_barrelled_surname_is_found_by_its_second_half(): void
    {
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie-Toe']);

        $this->assertCount(1, Teacher::search('toe')->get());
    }

    public function test_a_wildcard_character_does_not_match_everything(): void
    {
        // A bare % reaching LIKE would return the whole school, which is the
        // opposite of searching.
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie']);

        $this->assertCount(0, Teacher::search('%')->get());
        $this->assertCount(0, Teacher::search('_')->get());
    }

    public function test_searching_users_reaches_the_account_and_the_staff_number(): void
    {
        $account = User::factory()->create(['school_id' => $this->school->id, 'name' => 'Grace Kollie']);

        $this->teacher(['staff_number' => 'GFI-T-077', 'user_id' => $account->id]);

        User::factory()->create(['school_id' => $this->school->id, 'name' => 'Someone Else']);

        $this->actingAs($this->staff())
            ->get(route('users.index', ['search' => 'ko']))
            ->assertOk()
            ->assertSee('Grace Kollie')
            ->assertDontSee('Someone Else');

        $this->actingAs($this->staff())
            ->get(route('users.index', ['search' => '077']))
            ->assertOk()
            ->assertSee('Grace Kollie')
            ->assertDontSee('Someone Else');
    }

    public function test_student_search_still_reaches_a_parent_name(): void
    {
        $student = Student::create([
            'school_id' => $this->school->id,
            'student_number' => 'GFI-2026-00125',
            'first_name' => 'Ada',
            'last_name' => 'Weah',
            'status' => 'active',
        ]);

        $this->assertCount(1, Student::search('00125')->get());
        $this->assertCount(1, Student::search('ad')->get());
        $this->assertCount(0, Student::search('zz')->get());
        $this->assertTrue(Student::search('ada weah')->get()->contains($student));
    }

    /* -------------------------------------------------------------- import */

    protected function csv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gsms').'.csv';

        file_put_contents($path, $contents);

        return new UploadedFile($path, 'staff.csv', 'text/csv', null, true);
    }

    public function test_uploading_a_file_previews_without_writing_anything(): void
    {
        $response = $this->actingAs($this->staff())
            ->post(route('imports.preview', 'teachers'), [
                'file' => $this->csv("first_name,last_name,email\nGrace,Kollie,grace@example.test\n"),
            ]);

        $response->assertRedirect();

        // Nothing is written until the person confirms what they have seen.
        $this->assertSame(0, Teacher::count());

        $preview = session('import_preview');

        $this->assertCount(1, $preview['valid']);
        $this->assertSame([], $preview['problems']);
    }

    public function test_confirming_the_preview_creates_the_records(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv("first_name,last_name,email\nGrace,Kollie,grace@example.test\nEmmanuel,Toe,toe@example.test\n"),
        ]);

        $this->actingAs($staff)
            ->post(route('imports.store', 'teachers'))
            ->assertRedirect(route('teachers.index'));

        $this->assertSame(2, Teacher::count());

        /*
         | Staff numbers are sequential and never repeated within one import.
         | The prefix comes from the school's own initials, so the assertion is
         | on the invariant - two distinct, consecutive numbers - rather than on
         | a literal that only holds for one school's name.
         */
        $numbers = Teacher::orderBy('staff_number')->pluck('staff_number');
        $prefix = $this->school->numberPrefix().'-T-';

        $this->assertSame([$prefix.'001', $prefix.'002'], $numbers->all());
        $this->assertSame(2, $numbers->unique()->count());

        $this->assertDatabaseHas('audit_logs', ['module' => 'Teachers', 'action' => 'imported']);
    }

    public function test_headings_are_matched_however_they_are_capitalised(): void
    {
        $this->actingAs($this->staff())->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv("First Name,Last Name,Email\nGrace,Kollie,grace@example.test\n"),
        ]);

        $this->assertCount(1, session('import_preview')['valid']);
    }

    public function test_a_bad_row_is_skipped_and_the_rest_still_import(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv(
                "first_name,last_name,email\n"
                ."Grace,Kollie,grace@example.test\n"
                .",Nolastname,broken@example.test\n"
                ."Emmanuel,Toe,not-an-email\n"
                ."Musu,Sirleaf,musu@example.test\n"
            ),
        ]);

        $preview = session('import_preview');

        // One spreadsheet typo must not throw away the whole morning's work.
        $this->assertCount(2, $preview['valid']);
        $this->assertCount(2, $preview['problems']);

        // Reported by line number, so they can be found in the spreadsheet.
        $this->assertSame(3, $preview['problems'][0]['line']);
        $this->assertSame(4, $preview['problems'][1]['line']);

        $this->actingAs($staff)->post(route('imports.store', 'teachers'))->assertRedirect();

        $this->assertSame(2, Teacher::count());
    }

    public function test_a_duplicate_email_inside_the_file_is_caught(): void
    {
        $this->actingAs($this->staff())->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv(
                "first_name,last_name,email\n"
                ."Grace,Kollie,same@example.test\n"
                ."Emmanuel,Toe,same@example.test\n"
            ),
        ]);

        $preview = session('import_preview');

        $this->assertCount(1, $preview['valid']);
        $this->assertStringContainsString('also used on line 2', $preview['problems'][0]['reason']);
    }

    public function test_an_email_already_on_a_staff_record_is_caught(): void
    {
        $this->teacher(['email' => 'taken@example.test']);

        $this->actingAs($this->staff())->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv("first_name,last_name,email\nGrace,Kollie,taken@example.test\n"),
        ]);

        $this->assertCount(0, session('import_preview')['valid']);
        $this->assertCount(1, session('import_preview')['problems']);
    }

    public function test_a_file_missing_a_required_column_is_refused(): void
    {
        $this->actingAs($this->staff())
            ->post(route('imports.preview', 'teachers'), [
                'file' => $this->csv("first_name,email\nGrace,grace@example.test\n"),
            ])
            ->assertSessionHasErrors('file');

        $this->assertNull(session('import_preview'));
    }

    public function test_a_department_that_does_not_exist_is_left_unset_not_invented(): void
    {
        Department::create(['school_id' => $this->school->id, 'name' => 'Sciences']);

        $staff = $this->staff();

        $this->actingAs($staff)->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv(
                "first_name,last_name,department\n"
                ."Grace,Kollie,sciences\n"
                ."Emmanuel,Toe,Astrophysics\n"
            ),
        ]);

        $this->actingAs($staff)->post(route('imports.store', 'teachers'))->assertRedirect();

        // Matched case-insensitively where it exists...
        $this->assertNotNull(Teacher::where('last_name', 'Kollie')->value('department_id'));

        // ...and a typo does not quietly create a new department.
        $this->assertNull(Teacher::where('last_name', 'Toe')->value('department_id'));
        $this->assertSame(1, Department::count());
    }

    public function test_imported_staff_are_not_published_to_the_website(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('imports.preview', 'teachers'), [
            'file' => $this->csv("first_name,last_name\nGrace,Kollie\n"),
        ]);

        $this->actingAs($staff)->post(route('imports.store', 'teachers'))->assertRedirect();

        // A bulk upload must never put someone's name on the open internet as
        // a side effect.
        $this->assertFalse((bool) Teacher::first()->is_public);
    }

    public function test_importing_needs_the_create_permission(): void
    {
        $viewer = $this->userFor($this->school, ['teachers.view']);

        $this->actingAs($viewer)->get(route('imports.create', 'teachers'))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('imports.preview', 'teachers'), [
                'file' => $this->csv("first_name,last_name\nGrace,Kollie\n"),
            ])
            ->assertForbidden();
    }

    public function test_an_unknown_import_type_is_not_found(): void
    {
        $this->actingAs($this->staff())
            ->get(route('imports.create', 'nonsense'))
            ->assertNotFound();
    }

    /* -------------------------------------------------------------- export */

    public function test_the_staff_export_uses_the_columns_the_importer_reads(): void
    {
        $this->teacher(['first_name' => 'Grace', 'last_name' => 'Kollie', 'email' => 'grace@example.test']);

        $body = $this->actingAs($this->staff())
            ->get(route('exports.teachers'))
            ->assertOk()
            ->streamedContent();

        // An export edited in a spreadsheet has to import back without the
        // school rearranging the columns by hand.
        $this->assertStringContainsString('first_name,middle_name,last_name', $body);
        $this->assertStringContainsString('Grace', $body);
        $this->assertDatabaseHas('audit_logs', ['module' => 'Teachers', 'action' => 'exported']);
    }

    /* ------------------------------------------------------ teacher record */

    public function test_a_teacher_record_can_be_edited(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->staff())
            ->put(route('teachers.update', $teacher), [
                'first_name' => 'Grace',
                'last_name' => 'Kollie-Toe',
                'status' => 'on_leave',
                'is_public' => '1',
            ])
            ->assertRedirect(route('teachers.show', $teacher));

        $teacher->refresh();

        $this->assertSame('Kollie-Toe', $teacher->last_name);
        $this->assertSame('on_leave', $teacher->status);
        $this->assertTrue((bool) $teacher->is_public);
    }

    public function test_archiving_a_teacher_keeps_the_record_and_its_reason(): void
    {
        $teacher = $this->teacher(['status' => 'resigned', 'is_public' => true]);

        $this->actingAs($this->staff())
            ->delete(route('teachers.destroy', $teacher))
            ->assertRedirect(route('teachers.index'));

        $archived = Teacher::onlyTrashed()->find($teacher->id);

        $this->assertNotNull($archived, 'The record must be kept, not deleted.');

        // Why they left is part of the record and must survive archiving.
        $this->assertSame('resigned', $archived->status);
        $this->assertFalse((bool) $archived->is_public);

        $this->assertSame(0, Teacher::count());
    }

    public function test_an_archived_teacher_can_be_restored(): void
    {
        $teacher = $this->teacher();
        $teacher->delete();

        $this->actingAs($this->staff())
            ->post(route('teachers.restore', $teacher->id))
            ->assertRedirect();

        $this->assertSame(1, Teacher::count());
    }

    public function test_a_teacher_from_another_school_cannot_be_edited(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = Teacher::create([
            'school_id' => $other->id,
            'staff_number' => 'OTH-T-001',
            'first_name' => 'Their',
            'last_name' => 'Teacher',
            'status' => 'active',
        ]);

        $this->actingAs($this->staff())
            ->put(route('teachers.update', $foreign), [
                'first_name' => 'Hijacked',
                'last_name' => 'Teacher',
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->assertSame('Their', $foreign->fresh()->first_name);
    }
}
