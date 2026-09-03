<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\MessageThread;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec sections 45 (messaging) and 46 (notifications). */
class MessagingAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => '9A',
        ]);
    }

    /** A parent account with one child enrolled in the section. */
    protected function parentWithChild(): array
    {
        $user = $this->userFor($this->school, []);
        $user->roles()->detach();
        $user->roles()->attach(Role::where('school_id', $this->school->id)->where('slug', 'parent-guardian')->firstOrFail());

        $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'user_id' => $user->id]);

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->section->school_class_id,
            'section_id' => $this->section->id,
            'status' => 'active',
        ]);

        $guardian->students()->attach($student, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'can_view_academics' => true,
            'can_view_finance' => true,
        ]);

        return [$user->fresh(), $guardian, $student];
    }

    /** A teacher who teaches the section. */
    protected function sectionTeacher(): array
    {
        $user = $this->userFor($this->school, ['messages.send']);

        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'staff_number' => 'T-'.$user->id,
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);

        $subject = Subject::firstOrCreate(
            ['school_id' => $this->school->id, 'code' => 'MTH101'],
            ['name' => 'Mathematics']
        );

        TeachingAssignment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'teacher_id' => $teacher->id,
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
        ]);

        return [$user->fresh(), $teacher];
    }

    /* ------------------------------------------------------------ messaging */

    public function test_a_parent_can_message_a_teacher_of_their_child(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $this->actingAs($parentUser)
            ->post(route('messages.store'), [
                'recipient_id' => $teacherUser->id,
                'subject' => 'About mathematics homework',
                'body' => 'Could we discuss my child progress?',
            ])
            ->assertRedirect();

        $thread = MessageThread::firstOrFail();

        $this->assertSame('About mathematics homework', $thread->subject);
        $this->assertTrue($thread->includes($parentUser));
        $this->assertTrue($thread->includes($teacherUser));

        // And the teacher was told about it.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $teacherUser->id]);
    }

    public function test_a_parent_cannot_message_another_parent(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$otherParentUser] = $this->parentWithChild();

        $this->actingAs($parentUser)
            ->post(route('messages.store'), [
                'recipient_id' => $otherParentUser->id,
                'subject' => 'Hello',
                'body' => 'Not allowed.',
            ])
            ->assertForbidden();

        $this->assertSame(0, MessageThread::count());
    }

    public function test_a_parent_cannot_message_a_teacher_who_does_not_teach_their_child(): void
    {
        [$parentUser] = $this->parentWithChild();

        // A teacher with no assignment to this parent's child's section.
        $strangerUser = $this->userFor($this->school, ['messages.send']);

        Teacher::create([
            'school_id' => $this->school->id,
            'user_id' => $strangerUser->id,
            'staff_number' => 'T-stranger',
            'first_name' => 'Other',
            'last_name' => 'Teacher',
            'status' => 'active',
        ]);

        // Give them a role so they are not counted as generic office staff.
        $strangerUser->roles()->detach();
        $strangerUser->roles()->attach(
            Role::where('school_id', $this->school->id)->where('slug', 'teacher')->firstOrFail()
        );

        $recipients = $this->actingAs($parentUser)
            ->get(route('messages.index'))
            ->assertOk();

        $recipients->assertDontSee('Other Teacher');
    }

    public function test_a_conversation_is_only_readable_by_its_participants(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $this->actingAs($parentUser)->post(route('messages.store'), [
            'recipient_id' => $teacherUser->id,
            'subject' => 'Private matter',
            'body' => 'Confidential.',
        ]);

        $thread = MessageThread::firstOrFail();

        $outsider = $this->userFor($this->school, ['messages.send']);

        $this->actingAs($outsider)
            ->get(route('messages.show', $thread))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertDontSee('Private matter');
    }

    public function test_replying_notifies_the_other_participant_only(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $this->actingAs($parentUser)->post(route('messages.store'), [
            'recipient_id' => $teacherUser->id,
            'subject' => 'A question',
            'body' => 'First message.',
        ]);

        $thread = MessageThread::firstOrFail();

        // Clear the notification raised by the opening message.
        $teacherUser->notifications()->delete();

        $this->actingAs($teacherUser)
            ->post(route('messages.reply', $thread), ['body' => 'Here is my reply.'])
            ->assertRedirect();

        $this->assertSame(2, $thread->messages()->count());

        // The parent hears about the reply; the replier does not notify themselves.
        $this->assertSame(1, $parentUser->notifications()->count());
        $this->assertSame(0, $teacherUser->fresh()->notifications()->count());
    }

    public function test_a_closed_conversation_refuses_replies(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $this->actingAs($parentUser)->post(route('messages.store'), [
            'recipient_id' => $teacherUser->id,
            'subject' => 'Done',
            'body' => 'Thanks.',
        ]);

        $thread = MessageThread::firstOrFail();

        $this->actingAs($teacherUser)->post(route('messages.close', $thread));

        $this->actingAs($parentUser)
            ->post(route('messages.reply', $thread), ['body' => 'One more thing.'])
            ->assertStatus(422);
    }

    public function test_a_thread_from_another_school_cannot_be_opened(): void
    {
        $otherSchool = $this->createSchool();

        $theirThread = MessageThread::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'subject' => 'Theirs',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        [$parentUser] = $this->parentWithChild();

        $this->actingAs($parentUser)
            ->get(route('messages.show', $theirThread))
            ->assertNotFound();
    }

    /* -------------------------------------------------------- notifications */

    public function test_publishing_an_announcement_notifies_the_audience_it_names(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)
            ->post(route('announcements.store'), [
                'title' => 'Fees due Friday',
                'body' => 'A reminder to families.',
                'category' => 'fees',
                'audience' => ['parents'],
            ])
            ->assertRedirect();

        // Addressed to parents only.
        $this->assertSame(1, $parentUser->notifications()->count());
        $this->assertSame(0, $teacherUser->notifications()->count());
    }

    public function test_a_user_only_sees_their_own_notifications(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)->post(route('announcements.store'), [
            'title' => 'Parents only notice',
            'body' => 'For families.',
            'category' => 'general',
            'audience' => ['parents'],
        ]);

        $this->actingAs($parentUser)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Parents only notice');

        $this->actingAs($teacherUser)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Parents only notice');
    }

    public function test_marking_all_read_clears_the_unread_count(): void
    {
        [$parentUser] = $this->parentWithChild();

        $this->actingAs($this->administratorFor($this->school))->post(route('announcements.store'), [
            'title' => 'Something happened',
            'body' => 'Details.',
            'category' => 'general',
            'audience' => ['parents'],
        ]);

        $this->assertSame(1, $parentUser->unreadNotifications()->count());

        $this->actingAs($parentUser)->post(route('notifications.readAll'))->assertRedirect();

        $this->assertSame(0, $parentUser->fresh()->unreadNotifications()->count());
    }

    public function test_a_notification_belonging_to_someone_else_cannot_be_opened(): void
    {
        [$parentUser] = $this->parentWithChild();
        [$teacherUser] = $this->sectionTeacher();

        $this->actingAs($this->administratorFor($this->school))->post(route('announcements.store'), [
            'title' => 'For parents',
            'body' => 'Details.',
            'category' => 'general',
            'audience' => ['parents'],
        ]);

        $notification = $parentUser->notifications()->firstOrFail();

        $this->actingAs($teacherUser)
            ->get(route('notifications.read', $notification->id))
            ->assertNotFound();
    }
}
