<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\ReportCard;
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
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec sections 36, 37, 38, 45 and 58: grade entry, approval, the report card,
 * messaging and the audit trail.
 */
class GradingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $section;

    protected Subject $maths;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'First Term', 'sequence' => 1,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15', 'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9]);

        $this->section = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'A',
        ]);

        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $class->subjects()->attach($this->maths->id, ['school_id' => $this->school->id]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Grace', 'last_name' => 'Kollie', 'status' => 'active',
        ]);

        foreach ([['A', 90, 100, 'Excellent'], ['B', 80, 89, 'Very good'], ['C', 70, 79, 'Good'],
                  ['D', 60, 69, 'Satisfactory'], ['F', 0, 59, 'Fail']] as $i => [$g, $min, $max, $remark]) {
            GradeScale::create([
                'school_id' => $this->school->id, 'grade' => $g,
                'min_score' => $min, 'max_score' => $max, 'remark' => $remark, 'sequence' => $i + 1,
            ]);
        }
    }

    protected function student(string $number = 'S-1', string $first = 'Ada'): Student
    {
        $student = Student::create([
            'school_id' => $this->school->id, 'student_number' => $number,
            'first_name' => $first, 'last_name' => 'Doe', 'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->section->school_class_id,
            'section_id' => $this->section->id, 'status' => 'active',
        ]);

        return $student;
    }

    protected function teacherUser(): User
    {
        $user = $this->userFor($this->school, ['grades.enter', 'exams.view', 'messages.send']);

        $this->teacher->update(['user_id' => $user->id]);

        TeachingAssignment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
        ]);

        return $user;
    }

    protected function office(): User
    {
        return $this->userFor($this->school, [
            'grades.approve', 'grades.enter', 'reportcards.view', 'reportcards.generate', 'exams.manage',
        ]);
    }

    /* ----------------------------------------------- 36: every kind of mark */

    public function test_a_teacher_can_set_each_kind_of_assessment(): void
    {
        $user = $this->teacherUser();

        // Assignment, test, exam and "other" are all offered.
        foreach (Assessment::TYPES as $type) {
            $this->actingAs($user)
                ->post(route('assessments.store'), [
                    'section_id' => $this->section->id,
                    'subject_id' => $this->maths->id,
                    'title' => 'A '.$type,
                    'type' => $type,
                    'max_score' => 100,
                    'term_id' => $this->term->id,
                ])
                ->assertRedirect();
        }

        $this->assertEqualsCanonicalizing(
            Assessment::TYPES,
            Assessment::pluck('type')->unique()->values()->all(),
        );
    }

    public function test_an_unknown_assessment_type_is_refused(): void
    {
        $this->actingAs($this->teacherUser())
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Something else',
                'type' => 'made-up-type',
                'max_score' => 100,
            ])
            ->assertSessionHasErrors('type');
    }

    /* ------------------------------------------- 37: the approval workflow */

    public function test_the_full_workflow_from_entry_to_official_result(): void
    {
        $student = $this->student();
        $teacherUser = $this->teacherUser();
        $office = $this->office();

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id,
            'title' => 'Mid-term test', 'type' => 'test', 'max_score' => 100, 'status' => 'draft',
        ]);

        // 1. The teacher enters marks.
        $this->actingAs($teacherUser)->post(route('marks.store'), [
            'marks' => [$assessment->id => [$student->id => '85']],
        ])->assertRedirect();

        // 2. And submits them.
        $this->actingAs($teacherUser)->post(route('assessments.submit', $assessment))->assertRedirect();
        $this->assertSame('submitted', $assessment->fresh()->status);

        // Not yet a result: nothing counts until it is signed off.
        $this->assertNull(app(\App\Services\Gradebook::class)->termResults($student, $this->term)['average']);

        // 3. The academic office approves.
        $this->actingAs($office)->post(route('grades.approve', $assessment))->assertRedirect();
        $this->assertSame('approved', $assessment->fresh()->status);

        // 4. Now it is official and counts.
        $this->assertSame(85.0, app(\App\Services\Gradebook::class)->termResults($student, $this->term)['average']);

        // 5. And a report card can be generated from it.
        $this->actingAs($office)->post(route('reportcards.generate'), [
            'section_id' => $this->section->id,
            'term_id' => $this->term->id,
        ])->assertRedirect();

        $card = ReportCard::where('student_id', $student->id)->firstOrFail();

        $this->assertSame(85.0, (float) $card->average);
        $this->assertSame('B', $card->items->first()->grade);

        // Remarks come from the school's own grade scale, not invented here.
        $this->assertSame('Very good', $card->items->first()->remark);
    }

    public function test_a_teacher_cannot_approve_their_own_marks(): void
    {
        $this->student();
        $teacherUser = $this->teacherUser();

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id,
            'title' => 'Test', 'type' => 'test', 'max_score' => 100, 'status' => 'submitted',
        ]);

        // Entry and sign-off are separate duties. One person doing both is the
        // whole thing this workflow exists to prevent.
        $this->actingAs($teacherUser)
            ->post(route('grades.approve', $assessment))
            ->assertForbidden();
    }

    public function test_rejecting_returns_the_marks_to_the_teacher(): void
    {
        $student = $this->student();
        $teacherUser = $this->teacherUser();

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id,
            'title' => 'Test', 'type' => 'test', 'max_score' => 100, 'status' => 'submitted',
        ]);

        $this->actingAs($this->office())
            ->post(route('grades.reject', $assessment), ['review_note' => 'Two marks look transposed.'])
            ->assertRedirect();

        $assessment->refresh();

        $this->assertSame('rejected', $assessment->status);
        $this->assertSame('Two marks look transposed.', $assessment->review_note);

        // The teacher can edit again, which is the point of rejecting.
        $this->actingAs($teacherUser)
            ->post(route('marks.store'), ['marks' => [$assessment->id => [$student->id => '70']]])
            ->assertRedirect();

        $this->assertSame(70.0, (float) AssessmentScore::where('student_id', $student->id)->value('score'));
    }

    /* --------------------------------------------------- 38: the report card */

    protected function publishedCard(): ReportCard
    {
        $student = $this->student();

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id,
            'title' => 'Test', 'type' => 'test', 'max_score' => 100, 'status' => 'approved',
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $student->id, 'score' => 85,
        ]);

        $office = $this->office();

        $this->actingAs($office)->post(route('reportcards.generate'), [
            'section_id' => $this->section->id, 'term_id' => $this->term->id,
        ]);

        $card = ReportCard::where('student_id', $student->id)->firstOrFail();

        $this->actingAs($office)->post(route('reportcards.publish'), [
            'section_id' => $this->section->id, 'term_id' => $this->term->id,
        ]);

        return $card->fresh();
    }

    public function test_the_report_card_carries_everything_the_spec_lists(): void
    {
        $card = $this->publishedCard();

        $card->update([
            'teacher_comment' => 'A steady term of work.',
            'principal_comment' => 'Well done. Keep it up.',
        ]);

        $html = $this->actingAs($this->office())
            ->get(route('reportcards.show', $card))
            ->assertOk()
            ->getContent();

        foreach ([
            $this->school->name,                    // school name
            $card->student->full_name,              // student name
            $card->student->student_number,         // student ID
            $this->section->full_name,              // class
            $this->year->name,                      // academic year
            $this->term->name,                      // period
            'Mathematics',                          // subject
            '85',                                   // score
            'B',                                    // grade
            'Very good',                            // remark
            'Total',                                // section 36's total
            'Overall average',                      // average
            'A steady term of work.',               // teacher comment
            'Well done. Keep it up.',               // principal comment
            'Class teacher',                        // signature line
        ] as $expected) {
            $this->assertStringContainsString($expected, $html, "The card should show: {$expected}");
        }
    }

    public function test_a_parent_can_view_and_download_a_published_card(): void
    {
        $card = $this->publishedCard();

        $guardian = \App\Models\Guardian::create([
            'school_id' => $this->school->id, 'first_name' => 'John', 'last_name' => 'Doe',
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian->update(['user_id' => $parent->id]);

        $card->student->guardians()->attach($guardian->id, [
            'relationship' => 'Father', 'is_primary' => true, 'can_view_academics' => true,
        ]);

        $this->actingAs($parent)->get(route('reportcards.show', $card))->assertOk();

        $response = $this->actingAs($parent)
            ->get(route('reportcards.download', $card))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));

        // Self-contained: it must still read correctly with no server to reach.
        $body = $response->getContent();

        $this->assertStringContainsString('<style>', $body);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $body);
        $this->assertStringContainsString($card->student->full_name, $body);
    }

    public function test_an_unpublished_card_is_not_visible_to_a_family(): void
    {
        $student = $this->student();

        $card = ReportCard::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'term_id' => $this->term->id,
            'section_id' => $this->section->id, 'average' => 85, 'status' => 'draft',
        ]);

        $studentUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $student->update(['user_id' => $studentUser->id]);

        // A draft card may still change; showing it would be telling a family a
        // result that is not a result yet.
        $this->actingAs($studentUser)->get(route('reportcards.show', $card))->assertForbidden();
        $this->actingAs($studentUser)->get(route('reportcards.download', $card))->assertForbidden();
    }

    public function test_a_student_barred_from_downloading_cannot_download(): void
    {
        $card = $this->publishedCard();

        $studentUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $card->student->update(['user_id' => $studentUser->id]);

        StudentPermission::create([
            'school_id' => $this->school->id,
            'student_id' => $card->student_id,
            'ability' => 'download_report_card',
            'allowed' => false,
        ]);

        $this->actingAs($studentUser)->get(route('reportcards.show', $card))->assertOk();
        $this->actingAs($studentUser)->get(route('reportcards.download', $card))->assertForbidden();
    }

    /* ------------------------------------------------------- 45: messaging */

    public function test_a_student_cannot_message_unless_the_school_allows_it(): void
    {
        $student = $this->student();

        $studentUser = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $student->update(['user_id' => $studentUser->id]);

        /*
         | `send_messages` defaults to off. The controller used to say "parents
         | and students always have a voice", which meant this switch did
         | nothing: a school could turn student messaging off, watch it show as
         | off, and every student could still write to every teacher.
         */
        $this->actingAs($studentUser)->get(route('messages.index'))->assertForbidden();

        StudentPermission::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'ability' => 'send_messages',
            'allowed' => true,
        ]);

        $this->actingAs($studentUser)->get(route('messages.index'))->assertOk();
    }

    public function test_a_guardian_can_always_message(): void
    {
        $guardian = \App\Models\Guardian::create([
            'school_id' => $this->school->id, 'first_name' => 'John', 'last_name' => 'Doe',
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian->update(['user_id' => $parent->id]);

        // A parent reaching the school is not something a permission gates.
        $this->actingAs($parent)->get(route('messages.index'))->assertOk();
    }

    public function test_staff_need_the_messaging_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('messages.index'))
            ->assertForbidden();

        $this->actingAs($this->userFor($this->school, ['messages.send']))
            ->get(route('messages.index'))
            ->assertOk();
    }

    /* ------------------------------------------------------- 58: audit log */

    public function test_entering_grades_is_recorded_with_who_what_and_when(): void
    {
        $student = $this->student();
        $office = $this->office();

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id, 'section_id' => $this->section->id,
            'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id,
            'title' => 'Test', 'type' => 'test', 'max_score' => 100, 'status' => 'approved',
        ]);

        AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $student->id, 'score' => 55,
        ]);

        $this->actingAs($office)->post(route('marks.store'), [
            'marks' => [$assessment->id => [$student->id => '65']],
            'reason' => 'Transcription error.',
        ]);

        $log = AuditLog::latest('id')->first();

        // Section 58's list: user, school, action, module, timestamp, IP, and
        // the values on both sides of the change.
        $this->assertSame($office->id, $log->user_id);
        $this->assertSame($office->name, $log->user_name);
        $this->assertSame($this->school->id, $log->school_id);
        $this->assertSame('updated', $log->action);
        $this->assertSame('Examinations & grades', $log->module);
        $this->assertNotNull($log->created_at);
        $this->assertNotNull($log->ip_address);
        $this->assertStringContainsString('55', $log->description);
        $this->assertStringContainsString('65', $log->description);
    }

    public function test_an_audit_entry_cannot_be_changed_or_removed(): void
    {
        $this->actingAs($this->office())->post(route('marks.store'), ['marks' => []]);

        app(\App\Services\AuditLogger::class)->log('created', 'Testing', 'Something happened.');

        $log = AuditLog::latest('id')->firstOrFail();

        /*
         | Append-only, and it refuses loudly rather than quietly returning
         | false — a silent failure would let calling code believe the edit
         | worked. A trail that can be edited proves nothing, which is the
         | entire reason section 58 exists.
         */
        try {
            $log->update(['description' => 'Something else happened.']);
            $this->fail('An audit entry must not be updatable.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot be modified', $e->getMessage());
        }

        $this->assertSame('Something happened.', $log->fresh()->description);

        try {
            $log->delete();
            $this->fail('An audit entry must not be deletable.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertNotNull(AuditLog::find($log->id));
    }

    public function test_a_password_never_reaches_the_audit_trail(): void
    {
        app(\App\Services\AuditLogger::class)->log(
            'created', 'Users', 'An account was created.', null,
            null,
            ['name' => 'Someone', 'password' => 'the-real-password', 'remember_token' => 'abc'],
        );

        $log = AuditLog::latest('id')->firstOrFail();

        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }
}
