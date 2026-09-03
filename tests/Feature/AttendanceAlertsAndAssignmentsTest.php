<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spec sections 33 (attendance notifications) and 23 (assignment submission),
 * plus the scheduled fee reminders from section 46.
 */
class AttendanceAlertsAndAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $section;

    protected Subject $subject;

    protected Student $student;

    protected User $guardianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => now()->subMonths(2),
            'ends_on' => now()->addMonths(9),
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'starts_on' => now()->subMonths(2),
            'ends_on' => now()->addMonth(),
            'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => '9A',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathematics',
            'code' => 'MTH101',
        ]);

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $class->id,
            'section_id' => $this->section->id,
            'status' => 'active',
        ]);

        // A guardian with a portal account, cleared for academics and finance.
        $this->guardianUser = $this->userFor($this->school, []);

        $guardian = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->guardianUser->id,
        ]);

        $guardian->students()->attach($this->student, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'can_view_academics' => true,
            'can_view_finance' => true,
        ]);
    }

    protected function recordAttendance(string $status): void
    {
        $this->actingAs($this->userFor($this->school, ['attendance.view', 'attendance.record']))
            ->post(route('attendance.store'), [
                'section_id' => $this->section->id,
                'date' => now()->toDateString(),
                'status' => [$this->student->id => $status],
            ]);
    }

    /* ---------------------------------------------------- attendance alerts */

    public function test_marking_a_student_absent_tells_their_guardian(): void
    {
        $this->recordAttendance('absent');

        $this->assertSame(1, $this->guardianUser->notifications()->count());

        $notification = $this->guardianUser->notifications()->first();

        $this->assertSame('Attendance alert', $notification->data['title']);
        $this->assertStringContainsString('Mary Doe', $notification->data['body']);
        $this->assertStringContainsString('absent', $notification->data['body']);
    }

    public function test_marking_a_student_present_tells_nobody(): void
    {
        $this->recordAttendance('present');

        $this->assertSame(0, $this->guardianUser->notifications()->count());
    }

    public function test_saving_the_same_register_twice_does_not_alert_twice(): void
    {
        $this->recordAttendance('absent');
        $this->recordAttendance('absent');

        // The mark did not change the second time, so no second message.
        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_correcting_a_mark_to_absent_does_alert(): void
    {
        $this->recordAttendance('present');
        $this->assertSame(0, $this->guardianUser->notifications()->count());

        // A teacher fixing a mistake is a genuine change worth telling a family.
        $this->recordAttendance('absent');
        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_a_guardian_not_cleared_for_academics_is_not_alerted(): void
    {
        $otherUser = $this->userFor($this->school, []);

        $guardian = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $otherUser->id,
        ]);

        $guardian->students()->attach($this->student, [
            'relationship' => 'Uncle',
            'is_primary' => false,
            'can_view_academics' => false,
            'can_view_finance' => false,
        ]);

        $this->recordAttendance('absent');

        $this->assertSame(1, $this->guardianUser->notifications()->count());
        $this->assertSame(0, $otherUser->notifications()->count());
    }

    /* ------------------------------------------------------- fee reminders */

    protected function outstandingInvoice(): Invoice
    {
        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'invoice_number' => 'INV-'.now()->year.'-00001',
            'issued_on' => now()->subDays(20),
            'due_on' => now()->addDays(2),
            'total_minor' => Money::toMinor(15000),
            'status' => 'issued',
        ]);

        InvoiceItem::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'category' => 'Tuition',
            'amount_minor' => Money::toMinor(15000),
        ]);

        return $invoice;
    }

    public function test_the_reminder_command_notifies_guardians_of_money_owed(): void
    {
        $this->outstandingInvoice();

        $this->artisan('gsms:fee-reminders')->assertSuccessful();

        $this->assertSame(1, $this->guardianUser->notifications()->count());

        $notification = $this->guardianUser->notifications()->first();

        $this->assertSame('Fees outstanding', $notification->data['title']);
    }

    public function test_the_reminder_command_does_not_chase_the_same_invoice_twice(): void
    {
        $this->outstandingInvoice();

        $this->artisan('gsms:fee-reminders')->assertSuccessful();
        $this->artisan('gsms:fee-reminders')->assertSuccessful();

        $this->assertSame(1, $this->guardianUser->notifications()->count());
    }

    public function test_a_settled_invoice_is_not_chased(): void
    {
        $invoice = $this->outstandingInvoice();

        $invoice->update(['paid_minor' => $invoice->total_minor, 'status' => 'paid']);

        $this->artisan('gsms:fee-reminders')->assertSuccessful();

        $this->assertSame(0, $this->guardianUser->notifications()->count());
    }

    public function test_a_dry_run_sends_nothing(): void
    {
        $this->outstandingInvoice();

        $this->artisan('gsms:fee-reminders --dry-run')->assertSuccessful();

        $this->assertSame(0, $this->guardianUser->notifications()->count());
    }

    /* ------------------------------------------------ assignment submission */

    protected function assignment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'title' => 'Essay on Liberian history',
            'type' => 'assignment',
            'max_score' => 20,
            'weight' => 1,
            'ends_at' => now()->addDays(3),
            'status' => 'draft',
        ], $overrides));
    }

    protected function studentAccount(): User
    {
        $user = $this->userFor($this->school, []);

        $user->roles()->detach();
        $user->roles()->attach(
            Role::where('school_id', $this->school->id)->where('slug', 'student')->firstOrFail()
        );

        $this->student->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    protected function allowSubmitting(): void
    {
        StudentPermission::updateOrCreate(
            ['school_id' => $this->school->id, 'student_id' => null, 'ability' => 'submit_assignments'],
            ['allowed' => true],
        );
    }

    public function test_submitting_is_refused_while_the_school_keeps_it_switched_off(): void
    {
        // 'submit_assignments' is off by default in the catalogue.
        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), ['body' => 'My essay.'])
            ->assertForbidden();

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_a_student_can_submit_written_work_once_it_is_switched_on(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), ['body' => 'My essay about Liberia.'])
            ->assertRedirect();

        $submission = AssignmentSubmission::firstOrFail();

        $this->assertSame($this->student->id, $submission->student_id);
        $this->assertSame('My essay about Liberia.', $submission->body);
        $this->assertNotNull($submission->submitted_at);
        $this->assertFalse($submission->isLate());
    }

    public function test_an_attachment_is_stored_privately(): void
    {
        Storage::fake('local');

        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), [
                'attachment' => UploadedFile::fake()->create('essay.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::firstOrFail();

        Storage::disk('local')->assertExists($submission->attachment_path);
        $this->assertSame('essay.pdf', $submission->original_name);
    }

    public function test_an_empty_submission_is_refused(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), [])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_resubmitting_replaces_rather_than_duplicates(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)->post(route('student.assignments.submit', $assignment), ['body' => 'First try.']);
        $this->actingAs($user)->post(route('student.assignments.submit', $assignment), ['body' => 'Better answer.']);

        $this->assertSame(1, AssignmentSubmission::count());
        $this->assertSame('Better answer.', AssignmentSubmission::first()->body);
    }

    public function test_a_student_cannot_submit_to_another_class_assignment(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();

        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->section->school_class_id,
            'name' => '9B',
        ]);

        $theirAssignment = $this->assignment(['section_id' => $otherSection->id]);

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $theirAssignment), ['body' => 'Not my class.'])
            ->assertForbidden();

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_a_marked_assignment_is_closed_to_further_submissions(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment(['status' => 'approved']);

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), ['body' => 'Too late.'])
            ->assertStatus(422);
    }

    public function test_work_submitted_after_the_due_date_is_flagged_late(): void
    {
        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment(['ends_at' => now()->subDays(2)]);

        $this->actingAs($user)->post(route('student.assignments.submit', $assignment), ['body' => 'Sorry it is late.']);

        $this->assertTrue(AssignmentSubmission::firstOrFail()->isLate());
    }

    public function test_another_student_cannot_download_someone_elses_submission(): void
    {
        Storage::fake('local');

        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('mine.pdf', 50, 'application/pdf'),
        ]);

        $submission = AssignmentSubmission::firstOrFail();

        // A classmate with their own student account.
        $classmate = Student::factory()->create(['school_id' => $this->school->id]);
        $classmateUser = $this->userFor($this->school, []);
        $classmate->update(['user_id' => $classmateUser->id]);

        $this->actingAs($classmateUser->fresh())
            ->get(route('submissions.download', $submission))
            ->assertForbidden();
    }

    public function test_the_teacher_marking_the_work_can_download_it(): void
    {
        Storage::fake('local');

        $this->allowSubmitting();

        $user = $this->studentAccount();
        $assignment = $this->assignment();

        $this->actingAs($user)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('mine.pdf', 50, 'application/pdf'),
        ]);

        $submission = AssignmentSubmission::firstOrFail();

        $teacherUser = $this->userFor($this->school, ['grades.enter']);

        $this->actingAs($teacherUser)
            ->get(route('submissions.download', $submission))
            ->assertOk();
    }

    public function test_a_submission_from_another_school_cannot_be_downloaded(): void
    {
        $otherSchool = $this->createSchool();

        $theirSubmission = AssignmentSubmission::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'assessment_id' => $this->assignment()->id,
            'student_id' => $this->student->id,
            'body' => 'Theirs',
            'attachment_path' => 'submissions/other/file.pdf',
            'submitted_at' => now(),
        ]);

        $teacherUser = $this->userFor($this->school, ['grades.enter']);

        $this->actingAs($teacherUser)
            ->get(route('submissions.download', $theirSubmission))
            ->assertNotFound();
    }
}
