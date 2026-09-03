<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Guardian;
use App\Models\NewsPost;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The gallery, the optional login on a create-person form, and the teacher's
 * navigation.
 */
class GalleryAndPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        Storage::fake('public');
    }

    protected function image(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 400);
    }

    /* ------------------------------------------------------------ gallery */

    public function test_an_image_is_uploaded_with_a_title_and_a_description(): void
    {
        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->post(route('gallery.store'), [
                'images' => [$this->image()],
                'title' => 'Grade 9 science practical',
                'caption' => 'For the academics page, showing the laboratory in use.',
                'album' => 'Academics',
                'is_published' => '1',
            ])
            ->assertRedirect();

        $item = GalleryItem::firstOrFail();

        $this->assertSame('Grade 9 science practical', $item->title);
        $this->assertSame('For the academics page, showing the laboratory in use.', $item->caption);
        $this->assertSame('Academics', $item->album);
        $this->assertTrue($item->is_published);

        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_an_image_without_a_title_or_description_is_refused(): void
    {
        /*
         | Both are required on purpose. An untitled photograph cannot be
         | searched for later, and a screen reader announces nothing at all,
         | because the title is what becomes the alt text on the public site.
         */
        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->post(route('gallery.store'), ['images' => [$this->image()]])
            ->assertSessionHasErrors(['title', 'caption']);

        $this->assertSame(0, GalleryItem::count());
    }

    public function test_several_images_at_once_get_numbered_titles(): void
    {
        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->post(route('gallery.store'), [
                'images' => [$this->image('a.png'), $this->image('b.png'), $this->image('c.png')],
                'title' => 'Sports day',
                'caption' => 'Photographs from the inter-house competition.',
            ]);

        // Otherwise three rows share one title and cannot be told apart.
        $this->assertSame(
            ['Sports day (1)', 'Sports day (2)', 'Sports day (3)'],
            GalleryItem::orderBy('position')->pluck('title')->all(),
        );

        // Positions are distinct, so the order is stable.
        $this->assertSame(3, GalleryItem::distinct()->count('position'));
    }

    public function test_an_album_defaults_to_general(): void
    {
        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->post(route('gallery.store'), [
                'images' => [$this->image()],
                'title' => 'The school gate',
                'caption' => 'Front of the building.',
            ]);

        $this->assertSame('General', GalleryItem::first()->album);
    }

    public function test_an_image_can_be_renamed_and_hidden(): void
    {
        $item = GalleryItem::create([
            'school_id' => $this->school->id,
            'title' => 'Old title',
            'caption' => 'Old description',
            'image_path' => 'gallery/x.png',
            'album' => 'General',
            'is_published' => true,
        ]);

        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->put(route('gallery.update', $item), [
                'title' => 'New title',
                'caption' => 'New description',
                'album' => 'Events',
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('New title', $item->title);
        $this->assertSame('Events', $item->album);

        // The checkbox was not submitted, which means "not published".
        $this->assertFalse($item->is_published);
    }

    public function test_removing_an_image_deletes_the_file_too(): void
    {
        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->post(route('gallery.store'), [
                'images' => [$this->image()],
                'title' => 'To be removed',
                'caption' => 'A description.',
            ]);

        $item = GalleryItem::firstOrFail();
        $path = $item->image_path;

        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->delete(route('gallery.destroy', $item))
            ->assertRedirect();

        $this->assertSame(0, GalleryItem::count());

        // Orphaned files would quietly fill the disk.
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_gallery_needs_the_website_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('gallery.index'))
            ->assertForbidden();
    }

    public function test_an_image_from_another_school_cannot_be_touched(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = GalleryItem::create([
            'school_id' => $other->id,
            'title' => 'Theirs',
            'caption' => 'Their description',
            'image_path' => 'gallery/theirs.png',
        ]);

        $this->actingAs($this->userFor($this->school, ['website.manage']))
            ->delete(route('gallery.destroy', $foreign))
            ->assertNotFound();

        $this->assertNotNull(GalleryItem::withoutGlobalScopes()->find($foreign->id));
    }

    /* ------------------------------------------------- the website listing */

    public function test_the_website_screen_loads_with_news_that_has_an_author(): void
    {
        $author = $this->userFor($this->school, ['website.manage']);

        NewsPost::create([
            'school_id' => $this->school->id,
            'created_by' => $author->id,
            'title' => 'Term begins',
            'slug' => 'term-begins',
            'body' => 'The new term starts on Monday.',
            'published_at' => now(),
            'is_published' => true,
        ]);

        /*
         | The listing prints each post's author. Without the relation eager
         | loaded this threw a LazyLoadingViolationException and the whole
         | screen 500'd, because lazy loading is disabled in development.
         */
        $this->actingAs($author)
            ->get(route('website.index'))
            ->assertOk()
            ->assertSee('Term begins');
    }

    /* ------------------------------------------------------ portal access */

    protected function roleId(string $slug): int
    {
        return Role::inCurrentSchool()->where('slug', $slug)->value('id');
    }

    public function test_a_teacher_can_be_created_with_a_login_in_one_go(): void
    {
        $this->actingAs($this->userFor($this->school, ['teachers.create', 'users.create']))
            ->post(route('teachers.store'), [
                'first_name' => 'Musu',
                'last_name' => 'Sirleaf',
                'account_email' => 'musu@example.test',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
                'account_role_id' => $this->roleId('teacher'),
            ])
            ->assertRedirect();

        $teacher = Teacher::firstOrFail();

        $this->assertNotNull($teacher->user_id, 'The staff record must be linked to the new account.');

        $account = User::find($teacher->user_id);

        $this->assertSame('musu@example.test', $account->email);
        $this->assertTrue(Hash::check('a-long-enough-password', $account->password));
    }

    public function test_leaving_the_login_section_blank_creates_the_person_only(): void
    {
        // The common case: a school enrols many people and gives out few logins.
        $this->actingAs($this->userFor($this->school, ['teachers.create', 'users.create']))
            ->post(route('teachers.store'), ['first_name' => 'Joseph', 'last_name' => 'Weah'])
            ->assertRedirect();

        $this->assertNull(Teacher::firstOrFail()->user_id);
        $this->assertSame(0, User::where('email', 'like', '%example.test')->count());
    }

    public function test_a_student_can_be_created_with_a_login(): void
    {
        $this->actingAs($this->userFor($this->school, ['students.view', 'students.create', 'users.create']))
            ->post(route('students.store'), [
                'first_name' => 'Ada',
                'last_name' => 'Kollie',
                'account_email' => 'ada@example.test',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
                'account_role_id' => $this->roleId('student'),
            ])
            ->assertRedirect();

        $this->assertNotNull(Student::firstOrFail()->user_id);
    }

    public function test_a_guardian_can_be_created_with_a_login(): void
    {
        $this->actingAs($this->userFor($this->school, ['guardians.view', 'guardians.create', 'users.create']))
            ->post(route('guardians.store'), [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'account_email' => 'john@example.test',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
                'account_role_id' => $this->roleId('parent-guardian'),
            ])
            ->assertRedirect();

        $this->assertNotNull(Guardian::firstOrFail()->user_id);
    }

    public function test_a_password_needs_an_email_and_a_role(): void
    {
        $this->actingAs($this->userFor($this->school, ['teachers.create', 'users.create']))
            ->post(route('teachers.store'), [
                'first_name' => 'Grace',
                'last_name' => 'Kollie',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
            ])
            ->assertSessionHasErrors(['account_email', 'account_role_id']);

        $this->assertSame(0, Teacher::count());
    }

    public function test_mismatched_passwords_are_refused(): void
    {
        $this->actingAs($this->userFor($this->school, ['teachers.create', 'users.create']))
            ->post(route('teachers.store'), [
                'first_name' => 'Grace',
                'last_name' => 'Kollie',
                'account_email' => 'grace@example.test',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'something-else-entirely',
                'account_role_id' => $this->roleId('teacher'),
            ])
            ->assertSessionHasErrors('account_password');
    }

    public function test_creating_a_login_needs_the_user_create_permission(): void
    {
        /*
         | Adding a person and creating an account are different authorities.
         | Someone who may do the first must not get the second for free by
         | filling in a section of the same form.
         */
        $this->actingAs($this->userFor($this->school, ['teachers.create']))
            ->post(route('teachers.store'), [
                'first_name' => 'Grace',
                'last_name' => 'Kollie',
                'account_email' => 'grace@example.test',
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
                'account_role_id' => $this->roleId('teacher'),
            ])
            ->assertForbidden();

        $this->assertSame(0, Teacher::count());
    }

    public function test_a_failed_login_does_not_leave_a_half_made_person(): void
    {
        $existing = User::factory()->create(['school_id' => $this->school->id, 'email' => 'taken@example.test']);

        $this->actingAs($this->userFor($this->school, ['teachers.create', 'users.create']))
            ->post(route('teachers.store'), [
                'first_name' => 'Grace',
                'last_name' => 'Kollie',
                'account_email' => $existing->email,
                'account_password' => 'a-long-enough-password',
                'account_password_confirmation' => 'a-long-enough-password',
                'account_role_id' => $this->roleId('teacher'),
            ])
            ->assertSessionHasErrors('account_email');

        // A staff record whose account failed to be created is worse than
        // neither, so the whole thing is refused together.
        $this->assertSame(0, Teacher::count());
    }

    /* ------------------------------------------------ teacher navigation */

    public function test_a_teacher_lands_on_their_own_dashboard(): void
    {
        $user = $this->userFor($this->school, ['dashboard.view', 'grades.enter']);

        Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Emmanuel',
            'last_name' => 'Toe',
            'status' => 'active',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('portal'))->assertRedirect(route('teaching.dashboard'));
    }

    public function test_a_teacher_gets_a_sidebar_with_the_work_they_can_do(): void
    {
        $user = $this->userFor($this->school, [
            'dashboard.view', 'grades.enter', 'attendance.view', 'attendance.record', 'reportcards.view',
        ]);

        Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Emmanuel',
            'last_name' => 'Toe',
            'status' => 'active',
            'user_id' => $user->id,
        ]);

        $html = $this->actingAs($user)->get(route('teaching.dashboard'))->assertOk()->getContent();

        /*
         | The teacher portal used to be a five-link top bar, which left a
         | teacher holding grades.enter and attendance.record with no way to
         | reach Assessments, Attendance or Report cards at all.
         */
        foreach (['My teaching', 'My classes', 'My timetable', 'Attendance', 'Assessments', 'Report cards'] as $label) {
            $this->assertStringContainsString($label, $html, "The sidebar should offer {$label}.");
        }

        // Their Overview is their own, not the school-wide dashboard.
        $this->assertStringContainsString(route('teaching.dashboard'), $html);
        $this->assertStringNotContainsString('href="'.route('dashboard').'"', $html);
    }

    public function test_a_non_teacher_does_not_get_the_teaching_section(): void
    {
        $html = $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('My teaching', $html);
        $this->assertStringContainsString('href="'.route('dashboard').'"', $html);
    }
}
