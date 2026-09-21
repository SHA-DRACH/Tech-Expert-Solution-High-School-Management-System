<?php

namespace Tests\Feature;

use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The PDF fee schedule: uploaded by finance, downloaded by the families it
 * applies to, and published on the website only when the school says so.
 */
class FeeDocumentTest extends PeriodGradingTestCase
{
    protected Student $mary;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->mary = $this->student('S-1', 'Mary');

        // Students see fees only where the school allows it.
        StudentPermission::create(['school_id' => $this->school->id, 'student_id' => $this->mary->id, 'ability' => 'view_fees', 'allowed' => true]);
    }

    protected function finance(): User
    {
        return $this->userFor($this->school, ['fees.manage', 'dashboard.view']);
    }

    protected function pdf(string $name = 'fees.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'application/pdf');
    }

    /** A structure with a PDF, for a class (or every class when null). */
    protected function structure(?int $classId = null, bool $onWebsite = false, bool $active = true): FeeStructure
    {
        $structure = FeeStructure::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'school_class_id' => $classId,
            'name' => 'Term fees', 'is_active' => $active,
        ]);

        FeeItem::create(['school_id' => $this->school->id, 'fee_structure_id' => $structure->id, 'category' => 'Tuition', 'amount_minor' => 100000]);

        $path = $this->pdf()->store("schools/{$this->school->id}/fee-documents", 'local');
        $structure->update(['document_path' => $path, 'document_name' => 'Fee schedule.pdf', 'document_on_website' => $onWebsite]);

        return $structure->fresh();
    }

    protected function studentUser(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->mary->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    protected function parentUser(bool $finance = true): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian = Guardian::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '1']);
        $guardian->students()->attach($this->mary->id, ['relationship' => 'Father', 'can_view_academics' => true, 'can_view_finance' => $finance]);

        return $user->fresh();
    }

    /* ------------------------------------------------------------ upload */

    public function test_finance_can_attach_a_pdf_when_creating_a_structure(): void
    {
        $this->actingAs($this->finance())->post(route('fees.store'), [
            'name' => 'Grade 9 fees',
            'items' => [['category' => 'Tuition', 'amount' => 5000]],
            'document' => $this->pdf('Grade 9 fee schedule.pdf'),
            'document_on_website' => 1,
        ])->assertSessionHasNoErrors();

        $structure = FeeStructure::where('name', 'Grade 9 fees')->firstOrFail();

        $this->assertSame('Grade 9 fee schedule.pdf', $structure->document_name);
        $this->assertTrue($structure->document_on_website);
        Storage::disk('local')->assertExists($structure->document_path);
        $this->assertStringNotContainsString('public', $structure->document_path);
    }

    public function test_only_pdfs_are_accepted(): void
    {
        $this->actingAs($this->finance())->post(route('fees.store'), [
            'name' => 'Grade 9 fees',
            'items' => [['category' => 'Tuition', 'amount' => 5000]],
            'document' => UploadedFile::fake()->create('fees.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, FeeStructure::count());
    }

    public function test_replacing_the_pdf_deletes_the_old_file_and_removing_it_clears_it(): void
    {
        $structure = $this->structure();
        $old = $structure->document_path;

        $payload = ['name' => $structure->name, 'is_active' => 1, 'items' => [['category' => 'Tuition', 'amount' => 1000]]];

        $this->actingAs($this->finance())->put(route('fees.update', $structure), $payload + ['document' => $this->pdf('new.pdf')])
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($old);
        $new = $structure->fresh()->document_path;
        Storage::disk('local')->assertExists($new);

        $this->actingAs($this->finance())->put(route('fees.update', $structure), $payload + ['remove_document' => 1]);

        Storage::disk('local')->assertMissing($new);
        $this->assertNull($structure->fresh()->document_path);
    }

    /* ------------------------------------------------------------ public */

    public function test_a_published_pdf_appears_on_online_services_for_anyone(): void
    {
        $structure = $this->structure(onWebsite: true);
        $this->app['auth']->forgetGuards();

        $this->get(route('online.index'))->assertSee('Fee structure documents')->assertSee(route('online.fees.download', $structure));
        $this->get(route('online.fees.download', $structure))->assertOk()->assertDownload('Fee schedule.pdf');
    }

    public function test_a_pdf_not_ticked_for_the_website_is_not_public(): void
    {
        $structure = $this->structure(onWebsite: false);
        $this->app['auth']->forgetGuards();

        $this->get(route('online.index'))->assertDontSee(route('online.fees.download', $structure));
        $this->get(route('online.fees.download', $structure))->assertNotFound();
    }

    public function test_an_inactive_structures_pdf_is_not_public(): void
    {
        $structure = $this->structure(onWebsite: true, active: false);
        $this->app['auth']->forgetGuards();

        $this->get(route('online.fees.download', $structure))->assertNotFound();
    }

    /* --------------------------------------------------- students & parents */

    public function test_a_student_sees_and_downloads_their_class_fee_pdf(): void
    {
        $structure = $this->structure($this->section->school_class_id);
        $user = $this->studentUser();

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('Fee structure documents');
        $this->actingAs($user)->get(route('student.fees'))->assertOk()->assertSee(route('fees.document.download', $structure));
        $this->actingAs($user)->get(route('fees.document.download', $structure))->assertOk()->assertDownload();
    }

    public function test_a_whole_school_pdf_reaches_every_student(): void
    {
        $structure = $this->structure(null);

        $this->actingAs($this->studentUser())->get(route('fees.document.download', $structure))->assertOk();
    }

    /** Another class's fees are not this student's business. */
    public function test_a_student_cannot_download_another_classs_fee_pdf(): void
    {
        $other = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 12', 'level' => 12]);
        $structure = $this->structure($other->id);
        $user = $this->studentUser();

        $this->actingAs($user)->get(route('student.fees'))->assertDontSee(route('fees.document.download', $structure));
        $this->actingAs($user)->get(route('fees.document.download', $structure))->assertForbidden();
    }

    public function test_a_student_the_school_does_not_show_fees_to_cannot_download(): void
    {
        $structure = $this->structure(null);
        StudentPermission::where('student_id', $this->mary->id)->update(['allowed' => false]);

        $this->actingAs($this->studentUser())->get(route('fees.document.download', $structure))->assertForbidden();
    }

    public function test_a_parent_cleared_for_fees_sees_and_downloads_the_pdf(): void
    {
        $structure = $this->structure($this->section->school_class_id);
        $user = $this->parentUser(finance: true);

        $this->actingAs($user)->get(route('parent.dashboard'))->assertOk()->assertSee('Fee structure documents');
        $this->actingAs($user)->get(route('parent.fees'))->assertOk()->assertSee(route('fees.document.download', $structure));
        $this->actingAs($user)->get(route('fees.document.download', $structure))->assertOk();
    }

    public function test_a_parent_not_cleared_for_fees_cannot_download(): void
    {
        $structure = $this->structure($this->section->school_class_id);
        $user = $this->parentUser(finance: false);

        $this->actingAs($user)->get(route('parent.dashboard'))->assertDontSee('Fee structure documents');
        $this->actingAs($user)->get(route('fees.document.download', $structure))->assertForbidden();
    }

    public function test_someone_signed_in_with_no_link_to_the_school_fees_cannot_download(): void
    {
        $structure = $this->structure(null);

        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('fees.document.download', $structure))
            ->assertForbidden();
    }
}
