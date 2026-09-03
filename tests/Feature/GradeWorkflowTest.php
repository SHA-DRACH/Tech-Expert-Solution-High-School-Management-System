<?php

namespace Tests\Feature;

use App\Actions\GenerateReportCards;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec sections 36-38: entry, approval, and the report cards built from the
 * approved marks.
 */
class GradeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $section;

    protected Subject $subject;

    protected Teacher $teacher;

    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => now()->subMonths(2),
            'ends_on' => now()->addMonths(8),
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

        foreach ([['A', 90, 100], ['B', 80, 89], ['C', 70, 79], ['D', 60, 69], ['F', 0, 59]] as $i => [$grade, $min, $max]) {
            GradeScale::create([
                'school_id' => $this->school->id,
                'grade' => $grade,
                'min_score' => $min,
                'max_score' => $max,
                'sequence' => $i + 1,
            ]);
        }

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

        $this->teacherUser = $this->userFor($this->school, ['grades.enter', 'exams.view']);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacherUser->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);

        TeachingAssignment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    protected function enrolStudents(int $count = 3)
    {
        return collect(range(1, $count))->map(function (int $i) {
            $student = Student::factory()->create([
                'school_id' => $this->school->id,
                'first_name' => 'Student'.$i,
            ]);

            Enrollment::create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'academic_year_id' => $this->year->id,
                'school_class_id' => $this->section->school_class_id,
                'section_id' => $this->section->id,
                'status' => 'active',
            ]);

            return $student;
        });
    }

    protected function makeAssessment(string $status = 'draft', int $max = 100): Assessment
    {
        return Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'First Class Test',
            'type' => 'test',
            'max_score' => $max,
            'weight' => 1,
            'status' => $status,
        ]);
    }

    public function test_a_teacher_can_create_an_assessment_for_a_class_they_teach(): void
    {
        $this->actingAs($this->teacherUser)
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->subject->id,
                'title' => 'Mid-Term Test',
                'type' => 'test',
                'max_score' => 50,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'title' => 'Mid-Term Test',
            'status' => 'draft',
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_a_teacher_cannot_set_work_for_a_class_they_do_not_teach(): void
    {
        $otherClass = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 11', 'level' => 11]);
        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $otherClass->id,
            'name' => '11A',
        ]);

        $this->actingAs($this->teacherUser)
            ->post(route('assessments.store'), [
                'section_id' => $otherSection->id,
                'subject_id' => $this->subject->id,
                'title' => 'Not mine',
                'type' => 'test',
                'max_score' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assessments', ['title' => 'Not mine']);
    }

    public function test_marks_are_saved_and_a_mark_above_the_maximum_is_refused(): void
    {
        $students = $this->enrolStudents(2);
        $assessment = $this->makeAssessment(max: 50);

        $this->actingAs($this->teacherUser)
            ->put(route('assessments.scores.save', $assessment), [
                'scores' => [$students[0]->id => 45, $students[1]->id => 30],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'student_id' => $students[0]->id,
            'score' => 45,
        ]);

        // 60 is above the assessment's maximum of 50.
        $this->actingAs($this->teacherUser)
            ->put(route('assessments.scores.save', $assessment), [
                'scores' => [$students[0]->id => 60],
            ])
            ->assertSessionHasErrors('scores.'.$students[0]->id);
    }

    public function test_a_student_from_another_class_cannot_be_marked(): void
    {
        $assessment = $this->makeAssessment();
        $outsider = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($this->teacherUser)
            ->put(route('assessments.scores.save', $assessment), [
                'scores' => [$outsider->id => 80],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('assessment_scores', ['student_id' => $outsider->id]);
    }

    public function test_submitting_locks_the_marks_against_further_editing(): void
    {
        $students = $this->enrolStudents(1);
        $assessment = $this->makeAssessment();

        $this->actingAs($this->teacherUser)
            ->put(route('assessments.scores.save', $assessment), ['scores' => [$students[0]->id => 70]]);

        $this->actingAs($this->teacherUser)
            ->post(route('assessments.submit', $assessment))
            ->assertRedirect(route('assessments.index'));

        $this->assertSame('submitted', $assessment->fresh()->status);

        // Now out of the teacher's hands.
        $this->actingAs($this->teacherUser)
            ->put(route('assessments.scores.save', $assessment), ['scores' => [$students[0]->id => 95]])
            ->assertForbidden();
    }

    public function test_an_empty_mark_sheet_cannot_be_submitted(): void
    {
        $this->enrolStudents(1);
        $assessment = $this->makeAssessment();

        $this->actingAs($this->teacherUser)
            ->post(route('assessments.submit', $assessment))
            ->assertSessionHasErrors('scores');

        $this->assertSame('draft', $assessment->fresh()->status);
    }

    public function test_a_teacher_cannot_approve_their_own_marks(): void
    {
        $assessment = $this->makeAssessment('submitted');

        $this->actingAs($this->teacherUser)
            ->post(route('grades.approve', $assessment))
            ->assertForbidden();

        $this->assertSame('submitted', $assessment->fresh()->status);
    }

    public function test_the_academic_office_approves_marks_and_they_become_official(): void
    {
        $assessment = $this->makeAssessment('submitted');
        $reviewer = $this->userFor($this->school, ['grades.approve', 'exams.view']);

        $this->actingAs($reviewer)
            ->post(route('grades.approve', $assessment))
            ->assertRedirect(route('grades.approvals'));

        $assessment->refresh();

        $this->assertSame('approved', $assessment->status);
        $this->assertSame($reviewer->id, $assessment->approved_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grades_approved',
            'module' => 'Examinations & grades',
        ]);
    }

    public function test_rejecting_sends_the_work_back_with_a_reason(): void
    {
        $assessment = $this->makeAssessment('submitted');
        $reviewer = $this->userFor($this->school, ['grades.approve']);

        // A reason is required.
        $this->actingAs($reviewer)
            ->post(route('grades.reject', $assessment))
            ->assertSessionHasErrors('review_note');

        $this->actingAs($reviewer)
            ->post(route('grades.reject', $assessment), ['review_note' => 'Two marks look transposed.'])
            ->assertRedirect();

        $assessment->refresh();

        $this->assertSame('rejected', $assessment->status);
        $this->assertSame('Two marks look transposed.', $assessment->review_note);

        // Rejected work is editable again.
        $this->assertTrue($assessment->isEditable());
    }

    public function test_report_cards_are_built_only_from_approved_marks(): void
    {
        $students = $this->enrolStudents(3);

        $approved = $this->makeAssessment('approved');
        $unapproved = Assessment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Not yet approved',
            'type' => 'test',
            'max_score' => 100,
            'weight' => 1,
            'status' => 'submitted',
        ]);

        foreach ([90, 75, 55] as $index => $score) {
            AssessmentScore::create([
                'school_id' => $this->school->id,
                'assessment_id' => $approved->id,
                'student_id' => $students[$index]->id,
                'score' => $score,
            ]);

            // Deliberately different marks on the unapproved sheet: if these
            // leaked in, the averages below would not match.
            AssessmentScore::create([
                'school_id' => $this->school->id,
                'assessment_id' => $unapproved->id,
                'student_id' => $students[$index]->id,
                'score' => 10,
            ]);
        }

        $written = app(GenerateReportCards::class)->handle($this->section, $this->term);

        $this->assertSame(3, $written);

        $top = ReportCard::where('student_id', $students[0]->id)->firstOrFail();

        $this->assertEquals(90.0, (float) $top->average);
        $this->assertSame(1, $top->position);
        $this->assertSame(3, $top->class_size);
        $this->assertSame('A', $top->items->first()->grade);

        $bottom = ReportCard::where('student_id', $students[2]->id)->firstOrFail();

        $this->assertSame(3, $bottom->position);
        $this->assertSame('F', $bottom->items->first()->grade);
    }

    public function test_generated_report_cards_start_as_drafts_and_are_hidden_until_published(): void
    {
        $students = $this->enrolStudents(1);
        $assessment = $this->makeAssessment('approved');

        AssessmentScore::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assessment->id,
            'student_id' => $students[0]->id,
            'score' => 80,
        ]);

        $administrator = $this->administratorFor($this->school);

        $this->actingAs($administrator)
            ->post(route('reportcards.generate'), [
                'section_id' => $this->section->id,
                'term_id' => $this->term->id,
            ])
            ->assertRedirect();

        $card = ReportCard::firstOrFail();
        $this->assertSame('draft', $card->status);

        // A student cannot see their own card while it is still a draft.
        $studentUser = $this->userFor($this->school, []);
        $students[0]->update(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser->fresh())
            ->get(route('reportcards.show', $card))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('reportcards.publish'), [
                'section_id' => $this->section->id,
                'term_id' => $this->term->id,
            ])
            ->assertRedirect();

        $this->assertSame('published', $card->fresh()->status);

        $this->actingAs($studentUser->fresh())
            ->get(route('reportcards.show', $card))
            ->assertOk();
    }

    public function test_generating_with_no_approved_marks_reports_that_clearly(): void
    {
        $this->enrolStudents(2);
        $this->makeAssessment('submitted');

        $this->actingAs($this->administratorFor($this->school))
            ->post(route('reportcards.generate'), [
                'section_id' => $this->section->id,
                'term_id' => $this->term->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, ReportCard::count());
    }

    public function test_a_grading_scale_that_leaves_a_gap_is_refused(): void
    {
        $administrator = $this->userFor($this->school, ['exams.manage', 'exams.view']);

        $this->actingAs($administrator)
            ->put(route('examinations.scale'), [
                'bands' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100],
                    // 60-88 leaves 89 uncovered.
                    ['grade' => 'B', 'min_score' => 60, 'max_score' => 88],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 59],
                ],
            ])
            ->assertSessionHasErrors('bands');
    }

    public function test_a_valid_grading_scale_replaces_the_old_one(): void
    {
        $administrator = $this->userFor($this->school, ['exams.manage', 'exams.view']);

        $this->actingAs($administrator)
            ->put(route('examinations.scale'), [
                'bands' => [
                    ['grade' => 'Distinction', 'min_score' => 75, 'max_score' => 100, 'remark' => 'Excellent'],
                    ['grade' => 'Credit', 'min_score' => 50, 'max_score' => 74, 'remark' => 'Good'],
                    ['grade' => 'Fail', 'min_score' => 0, 'max_score' => 49, 'remark' => 'Fail'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, GradeScale::count());
        $this->assertSame('Distinction', GradeScale::forScore(80)->grade);
    }

    public function test_an_assessment_from_another_school_cannot_be_opened(): void
    {
        $otherSchool = $this->createSchool();

        $theirAssessment = Assessment::withoutGlobalScopes()->create([
            'school_id' => $otherSchool->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'title' => 'Theirs',
            'type' => 'test',
            'max_score' => 100,
            'weight' => 1,
            'status' => 'submitted',
        ]);

        $this->actingAs($this->administratorFor($this->school))
            ->get(route('assessments.scores', $theirAssessment))
            ->assertNotFound();
    }
}
