<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Editing and removing the everyday records.
 *
 * The rule running through all of it: anything that anchors history is
 * archived, never deleted, and the archive refuses when removing the record
 * would strand something that depends on it.
 */
class RecordCrudTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function guardian(array $overrides = []): Guardian
    {
        return Guardian::create(array_merge([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Kollie',
            'phone' => '0770000000',
        ], $overrides));
    }

    protected function student(array $overrides = []): Student
    {
        static $n = 0;
        $n++;

        return Student::create(array_merge([
            'school_id' => $this->school->id,
            'student_number' => 'S-'.$n,
            'first_name' => 'Ada',
            'last_name' => 'Kollie',
            'status' => 'active',
        ], $overrides));
    }

    /* ------------------------------------------------------------ students */

    public function test_a_student_record_can_be_edited(): void
    {
        $student = $this->student();

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.update']))
            ->put(route('students.update', $student), [
                'first_name' => 'Adaeze',
                'last_name' => 'Kollie-Toe',
                'status' => 'active',
            ])
            ->assertRedirect(route('students.show', $student));

        $student->refresh();

        $this->assertSame('Adaeze', $student->first_name);
        $this->assertSame('Kollie-Toe', $student->last_name);

        // The number is printed on report cards and quoted on invoices, and is
        // what uploaded marks are matched on. It is not editable.
        $this->assertSame('S-1', $student->student_number);

        $this->assertDatabaseHas('audit_logs', ['module' => 'Students', 'action' => 'updated']);
    }

    public function test_adding_a_guardian_does_not_drop_the_ones_already_linked(): void
    {
        $student = $this->student();
        $mother = $this->guardian(['first_name' => 'Mary']);
        $father = $this->guardian(['first_name' => 'John']);

        $student->guardians()->attach($mother->id, ['relationship' => 'Mother', 'is_primary' => true]);

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.update']))
            ->put(route('students.update', $student), [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'status' => 'active',
                'guardian_id' => $father->id,
                'relationship' => 'Father',
            ])
            ->assertRedirect();

        $this->assertCount(2, $student->fresh()->guardians);
    }

    public function test_a_student_is_archived_rather_than_deleted(): void
    {
        $student = $this->student();

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.archive']))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        // The record anchors enrolments, marks, attendance and invoices.
        $archived = Student::onlyTrashed()->find($student->id);

        $this->assertNotNull($archived);
        $this->assertSame('archived', $archived->status);
        $this->assertSame(0, Student::count());
    }

    public function test_an_archived_student_can_be_restored(): void
    {
        $student = $this->student();
        $student->delete();

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.archive']))
            ->post(route('students.restore', $student->id))
            ->assertRedirect();

        $this->assertSame(1, Student::count());
        $this->assertSame('active', $student->fresh()->status);
    }

    public function test_editing_a_student_needs_the_update_permission(): void
    {
        $student = $this->student();

        $this->actingAs($this->userFor($this->school, ['students.view']))
            ->put(route('students.update', $student), [
                'first_name' => 'Hijacked', 'last_name' => 'Name', 'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_a_student_from_another_school_cannot_be_edited(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = Student::create([
            'school_id' => $other->id,
            'student_number' => 'OTH-1',
            'first_name' => 'Their',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $this->actingAs($this->userFor($this->school, ['students.view', 'students.update']))
            ->put(route('students.update', $foreign), [
                'first_name' => 'Hijacked', 'last_name' => 'Student', 'status' => 'active',
            ])
            ->assertNotFound();

        $this->assertSame('Their', $foreign->fresh()->first_name);
    }

    /* ----------------------------------------------------------- guardians */

    public function test_a_guardian_record_can_be_edited(): void
    {
        $guardian = $this->guardian();

        $this->actingAs($this->userFor($this->school, ['guardians.view', 'guardians.update']))
            ->put(route('guardians.update', $guardian), [
                'first_name' => 'Mary',
                'last_name' => 'Kollie-Toe',
                'phone' => '0771111111',
            ])
            ->assertRedirect(route('guardians.show', $guardian));

        $this->assertSame('Kollie-Toe', $guardian->fresh()->last_name);
        $this->assertSame('0771111111', $guardian->fresh()->phone);
    }

    public function test_a_guardian_who_is_a_childs_only_contact_cannot_be_archived(): void
    {
        $guardian = $this->guardian();
        $student = $this->student();

        $student->guardians()->attach($guardian->id, ['relationship' => 'Mother', 'is_primary' => true]);

        /*
         | A guardian is who the school calls about an absence and who an
         | invoice is addressed to. Archiving the last one would leave the child
         | with nobody responsible for them.
         */
        $this->actingAs($this->userFor($this->school, ['guardians.view', 'guardians.archive']))
            ->delete(route('guardians.destroy', $guardian))
            ->assertSessionHasErrors('guardian');

        $this->assertSame(1, Guardian::count());
    }

    public function test_a_guardian_can_be_archived_once_the_child_has_another(): void
    {
        $mother = $this->guardian(['first_name' => 'Mary']);
        $father = $this->guardian(['first_name' => 'John']);
        $student = $this->student();

        $student->guardians()->attach($mother->id, ['relationship' => 'Mother', 'is_primary' => true]);
        $student->guardians()->attach($father->id, ['relationship' => 'Father', 'is_primary' => false]);

        $this->actingAs($this->userFor($this->school, ['guardians.view', 'guardians.archive']))
            ->delete(route('guardians.destroy', $father))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Guardian::onlyTrashed()->find($father->id));
        $this->assertSame(1, Guardian::count());
    }

    /* -------------------------------------------------------------- events */

    protected function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'school_id' => $this->school->id,
            'title' => 'Sports Day',
            'category' => Event::CATEGORIES[0],
            'starts_at' => now()->addWeek(),
            'status' => 'scheduled',
            'is_public' => true,
        ], $overrides));
    }

    public function test_an_event_can_be_edited(): void
    {
        $event = $this->event();

        $this->actingAs($this->userFor($this->school, ['events.manage']))
            ->put(route('events.update', $event), [
                'title' => 'Inter-house Sports Day',
                'category' => Event::CATEGORIES[0],
                'starts_at' => now()->addWeeks(2)->toDateTimeString(),
                'status' => 'scheduled',
                'location' => 'Main field',
            ])
            ->assertRedirect();

        $this->assertSame('Inter-house Sports Day', $event->fresh()->title);
        $this->assertSame('Main field', $event->fresh()->location);
    }

    public function test_cancelling_an_event_keeps_it_on_the_calendar(): void
    {
        $event = $this->event();

        $this->actingAs($this->userFor($this->school, ['events.manage']))
            ->post(route('events.cancel', $event))
            ->assertRedirect();

        // Families who already have it in their diary need to see it was
        // called off, which deleting it would not tell them.
        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertSame(1, Event::count());
    }

    public function test_an_event_can_be_deleted_outright(): void
    {
        $event = $this->event();

        $this->actingAs($this->userFor($this->school, ['events.manage']))
            ->delete(route('events.destroy', $event))
            ->assertRedirect();

        $this->assertSame(0, Event::count());
    }

    public function test_managing_events_needs_the_permission(): void
    {
        $event = $this->event();

        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->delete(route('events.destroy', $event))
            ->assertForbidden();
    }

    /* ------------------------------------------------------- announcements */

    protected function announcement(): Announcement
    {
        return Announcement::create([
            'school_id' => $this->school->id,
            'title' => 'School closes early',
            'body' => 'The school closes at 12:30 on Friday.',
            'category' => Announcement::CATEGORIES[0],
            'audience' => ['parents'],
            'published_at' => now(),
            'status' => 'published',
        ]);
    }

    public function test_an_announcement_can_be_corrected_without_notifying_again(): void
    {
        $announcement = $this->announcement();

        $before = \Illuminate\Notifications\DatabaseNotification::count();

        $this->actingAs($this->userFor($this->school, ['announcements.manage']))
            ->put(route('announcements.update', $announcement), [
                'title' => 'School closes early on Friday',
                'body' => 'The school closes at 12:30 this Friday.',
                'category' => Announcement::CATEGORIES[0],
                'audience' => ['parents', 'students'],
            ])
            ->assertRedirect(route('announcements.index'));

        $this->assertSame('School closes early on Friday', $announcement->fresh()->title);

        /*
         | Fixing a typo must not re-notify every family. Doing so trains people
         | to ignore the notifications that matter.
         */
        $this->assertSame($before, \Illuminate\Notifications\DatabaseNotification::count());
    }

    public function test_an_archived_announcement_can_be_restored(): void
    {
        $announcement = $this->announcement();

        $manager = $this->userFor($this->school, ['announcements.manage']);

        $this->actingAs($manager)->delete(route('announcements.destroy', $announcement));
        $this->assertSame('archived', $announcement->fresh()->status);

        $this->actingAs($manager)->post(route('announcements.restore', $announcement))->assertRedirect();
        $this->assertSame('published', $announcement->fresh()->status);
    }

    /* ------------------------------------------------------------ expenses */

    public function test_an_expense_can_be_corrected_and_the_change_is_recorded(): void
    {
        $expense = Expense::create([
            'school_id' => $this->school->id,
            'category' => 'Utilities',
            'description' => 'Generator fuel',
            'amount_minor' => Money::toMinor(4000),
            'spent_on' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->userFor($this->school, ['expenses.manage']))
            ->put(route('expenses.update', $expense), [
                'category' => 'Utilities',
                'description' => 'Generator fuel',
                'amount' => '4500',
                'spent_on' => now()->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(Money::toMinor(4500), $expense->fresh()->amount_minor);

        // An expense figure that changes after the fact is exactly what an
        // auditor asks about, so both figures are kept.
        $this->assertDatabaseHas('audit_logs', ['module' => 'Finance', 'action' => 'updated']);
    }

    public function test_an_expense_cannot_be_dated_in_the_future(): void
    {
        $expense = Expense::create([
            'school_id' => $this->school->id,
            'category' => 'Utilities',
            'description' => 'Generator fuel',
            'amount_minor' => Money::toMinor(4000),
            'spent_on' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->userFor($this->school, ['expenses.manage']))
            ->put(route('expenses.update', $expense), [
                'category' => 'Utilities',
                'description' => 'Generator fuel',
                'amount' => '4500',
                'spent_on' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('spent_on');
    }

    /* -------------------------------------------------- giving staff a login */

    public function test_a_teacher_can_be_given_a_login_from_their_own_record(): void
    {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Shadrach',
            'last_name' => 'Jimice',
            'status' => 'active',
        ]);

        $role = Role::inCurrentSchool()->where('name', 'Teacher')->firstOrFail();

        /*
         | Adding a teacher creates the person, not an account, and until this
         | existed nothing in the application could attach one: the account form
         | offered guardian and student links and no teacher link at all, so a
         | teacher simply could not be given a way in.
         */
        $this->actingAs($this->userFor($this->school, ['teachers.view', 'teachers.update', 'users.create']))
            ->post(route('teachers.login.store', $teacher), [
                'email' => 'shadrach@example.test',
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $teacher->refresh();

        $this->assertNotNull($teacher->user_id, 'The staff record must be linked to the new account.');

        $account = User::find($teacher->user_id);

        $this->assertSame('shadrach@example.test', $account->email);
        $this->assertTrue(Hash::check('a-long-enough-password', $account->password));
        $this->assertSame(['Teacher'], $account->roles->pluck('name')->all());
    }

    public function test_a_teacher_account_must_be_linked_to_a_staff_record(): void
    {
        $role = Role::inCurrentSchool()->where('name', 'Teacher')->firstOrFail();

        /*
         | A teacher account pointing at no staff record cannot enter a single
         | mark: AssessmentPolicy resolves the teacher through the user id,
         | finds nothing, and refuses. The account would look correct in the
         | user list and be useless in the classroom.
         */
        $this->actingAs($this->userFor($this->school, ['users.view', 'users.create']))
            ->post(route('users.store'), [
                'name' => 'Unlinked Teacher',
                'email' => 'unlinked@example.test',
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseMissing('users', ['email' => 'unlinked@example.test']);
    }

    public function test_staff_who_already_have_an_account_are_not_offered_again(): void
    {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-002',
            'first_name' => 'Already',
            'last_name' => 'Linked',
            'status' => 'active',
            'user_id' => $this->userFor($this->school, ['dashboard.view'])->id,
        ]);

        $this->actingAs($this->userFor($this->school, ['teachers.view', 'users.create']))
            ->post(route('teachers.login.store', $teacher), [
                'email' => 'second@example.test',
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
                'role_id' => Role::inCurrentSchool()->where('name', 'Teacher')->value('id'),
            ])
            ->assertSessionHasErrors('login');
    }

    public function test_creating_a_login_needs_the_user_create_permission(): void
    {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-003',
            'first_name' => 'No',
            'last_name' => 'Access',
            'status' => 'active',
        ]);

        $this->actingAs($this->userFor($this->school, ['teachers.view', 'teachers.update']))
            ->post(route('teachers.login.store', $teacher), [
                'email' => 'nope@example.test',
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
                'role_id' => Role::inCurrentSchool()->where('name', 'Teacher')->value('id'),
            ])
            ->assertForbidden();
    }
}
