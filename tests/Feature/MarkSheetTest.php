<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
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
 * The mark sheet: one class, one subject, every student, every assessment.
 *
 * The rules being defended are about who may write which mark. A teacher marks
 * what they are assigned to, and only until it is submitted; the academic
 * office can still correct a mark after approval, because once marks are signed
 * off that is the only route left to fixing a transcription error.
 */
class MarkSheetTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected Term $term;

    protected Section $section;

    protected Subject $maths;

    protected Subject $english;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'name' => 'Grade 9',
            'level' => 9,
            'stage' => 'Junior High',
        ]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'A',
        ]);

        $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH']);
        $this->english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);

        // The class's own subject list is what decides who "does" a subject.
        $class->subjects()->attach(
            [$this->maths->id, $this->english->id],
            ['school_id' => $this->school->id]
        );

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);
    }

    protected function student(string $number, string $first): Student
    {
        $student = Student::create([
            'school_id' => $this->school->id,
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => 'Doe',
            'status' => 'active',
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
    }

    protected function assessment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'First Test',
            'type' => 'test',
            'max_score' => 100,
            'weight' => 1,
            'status' => 'draft',
        ], $overrides));
    }

    /** A teacher assigned to Mathematics in this section. */
    protected function teacherUser(): User
    {
        $user = $this->userFor($this->school, ['grades.enter', 'exams.view']);

        $this->teacher->update(['user_id' => $user->id]);

        TeachingAssignment::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'teacher_id' => $this->teacher->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->maths->id,
        ]);

        return $user;
    }

    protected function office(): User
    {
        return $this->userFor($this->school, ['grades.approve', 'grades.enter', 'reportcards.view']);
    }

    /* ---------------------------------------------------------- the sheet */

    public function test_the_sheet_lists_every_student_in_the_class(): void
    {
        $this->student('S-1', 'Ada');
        $this->student('S-2', 'Ben');
        $this->assessment();

        $this->actingAs($this->teacherUser())
            ->get(route('marks.index'))
            ->assertOk()
            ->assertSee('Ada Doe')
            ->assertSee('Ben Doe')
            ->assertSee('First Test');
    }

    public function test_a_student_in_another_class_is_not_listed(): void
    {
        $this->student('S-1', 'Ada');

        $otherSection = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $this->section->school_class_id,
            'name' => 'B',
        ]);

        $outsider = Student::create([
            'school_id' => $this->school->id,
            'student_number' => 'S-9',
            'first_name' => 'Elsewhere',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $outsider->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->section->school_class_id,
            'section_id' => $otherSection->id,
            'status' => 'active',
        ]);

        $this->assessment();

        $this->actingAs($this->teacherUser())
            ->get(route('marks.index', ['section' => $this->section->id, 'subject' => $this->maths->id]))
            ->assertOk()
            ->assertSee('Ada Doe')
            ->assertDontSee('Elsewhere Student');
    }

    public function test_a_teacher_only_sees_the_subjects_they_are_assigned_to(): void
    {
        $this->student('S-1', 'Ada');
        $this->assessment();

        // Assigned to Mathematics only, though the class also does English.
        $html = $this->actingAs($this->teacherUser())
            ->get(route('marks.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mathematics', $html);
        $this->assertStringNotContainsString('>English<', $html);
    }

    public function test_the_academic_office_sees_every_class_and_subject(): void
    {
        $this->student('S-1', 'Ada');
        $this->assessment();

        $html = $this->actingAs($this->office())
            ->get(route('marks.index'))
            ->assertOk()
            ->getContent();

        // Not restricted by teaching assignment.
        $this->assertStringContainsString('Mathematics', $html);
        $this->assertStringContainsString('English', $html);
    }

    /* --------------------------------------------------------- saving */

    public function test_a_teacher_saves_marks_for_the_class(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $ben = $this->student('S-2', 'Ben');
        $assessment = $this->assessment();

        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), [
                'marks' => [$assessment->id => [$ada->id => '84', $ben->id => '61.5']],
            ])
            ->assertRedirect();

        $this->assertSame(84.0, (float) AssessmentScore::where('student_id', $ada->id)->value('score'));
        $this->assertSame(61.5, (float) AssessmentScore::where('student_id', $ben->id)->value('score'));
    }

    public function test_one_student_can_be_marked_on_their_own(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $this->student('S-2', 'Ben');
        $assessment = $this->assessment();

        // The individual-entry form posts the same shape with one entry in it.
        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), ['marks' => [$assessment->id => [$ada->id => '77']]])
            ->assertRedirect();

        $this->assertSame(1, AssessmentScore::count());
        $this->assertSame(77.0, (float) AssessmentScore::where('student_id', $ada->id)->value('score'));
    }

    public function test_a_blank_mark_clears_it_rather_than_scoring_zero(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $assessment = $this->assessment();

        $user = $this->teacherUser();

        $this->actingAs($user)->post(route('marks.store'), [
            'marks' => [$assessment->id => [$ada->id => '55']],
        ]);

        $this->assertSame(1, AssessmentScore::count());

        $this->actingAs($user)->post(route('marks.store'), [
            'marks' => [$assessment->id => [$ada->id => '']],
        ]);

        // "Not marked" is not the same as zero, and must not be stored as one.
        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_mark_above_the_maximum_is_refused(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $assessment = $this->assessment(['max_score' => 20]);

        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), ['marks' => [$assessment->id => [$ada->id => '25']]])
            ->assertStatus(422);

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_teacher_cannot_mark_a_subject_they_do_not_teach(): void
    {
        $ada = $this->student('S-1', 'Ada');

        // English: the class does it, but this teacher is not assigned to it.
        $english = $this->assessment(['subject_id' => $this->english->id, 'teacher_id' => null]);

        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), ['marks' => [$english->id => [$ada->id => '90']]])
            ->assertForbidden();

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_teacher_cannot_change_a_submitted_mark(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $assessment = $this->assessment(['status' => 'submitted']);

        /*
         | Once marks are handed to the academic office they are out of the
         | teacher's hands. That is what approval means; letting them edit
         | afterwards would make the whole workflow decorative.
         */
        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), ['marks' => [$assessment->id => [$ada->id => '99']]])
            ->assertForbidden();
    }

    public function test_the_academic_office_can_correct_an_approved_mark(): void
    {
        $ada = $this->student('S-1', 'Ada');
        $assessment = $this->assessment(['status' => 'approved']);

        AssessmentScore::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assessment->id,
            'student_id' => $ada->id,
            'score' => 55,
        ]);

        $this->actingAs($this->office())
            ->post(route('marks.store'), [
                'marks' => [$assessment->id => [$ada->id => '65']],
                'reason' => 'Transcription error on the paper register.',
            ])
            ->assertRedirect();

        $this->assertSame(65.0, (float) AssessmentScore::where('student_id', $ada->id)->value('score'));

        // A grade changed after approval is exactly what a parent may ask
        // about, so the before, the after and the reason are all kept.
        $log = \App\Models\AuditLog::where('module', 'Examinations & grades')->latest('id')->first();

        $this->assertStringContainsString('55', $log->description);
        $this->assertStringContainsString('65', $log->description);
        $this->assertStringContainsString('Transcription error', $log->description);
    }

    public function test_a_student_from_another_class_cannot_be_marked(): void
    {
        $this->student('S-1', 'Ada');
        $assessment = $this->assessment();

        $outsider = Student::create([
            'school_id' => $this->school->id,
            'student_number' => 'S-9',
            'first_name' => 'Not',
            'last_name' => 'Enrolled',
            'status' => 'active',
        ]);

        $this->actingAs($this->teacherUser())
            ->post(route('marks.store'), ['marks' => [$assessment->id => [$outsider->id => '90']]])
            ->assertRedirect();

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_the_mark_sheet_needs_a_grades_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('marks.index'))
            ->assertForbidden();
    }

    /* --------------------------------------------- assessment time window */

    public function test_an_assessment_records_a_start_and_an_end_time(): void
    {
        $this->actingAs($this->office())
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Mid-term examination',
                'type' => 'exam',
                'max_score' => 100,
                'starts_at' => '2026-10-12 09:00',
                'ends_at' => '2026-10-12 10:30',
                'term_id' => $this->term->id,
            ])
            ->assertRedirect();

        $assessment = Assessment::where('title', 'Mid-term examination')->firstOrFail();

        // A date alone cannot say "opens at 09:00, closes at 10:30", which is
        // what a timed examination actually is.
        $this->assertSame('2026-10-12 09:00', $assessment->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 10:30', $assessment->ends_at->format('Y-m-d H:i'));
    }

    public function test_an_assessment_cannot_end_before_it_starts(): void
    {
        $this->actingAs($this->office())
            ->post(route('assessments.store'), [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Backwards exam',
                'type' => 'exam',
                'max_score' => 100,
                'starts_at' => '2026-10-12 10:30',
                'ends_at' => '2026-10-12 09:00',
            ])
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Assessment::where('title', 'Backwards exam')->count());
    }

    public function test_a_submission_is_late_against_the_closing_time_not_midnight(): void
    {
        $ada = $this->student('S-1', 'Ada');

        $assessment = $this->assessment([
            'starts_at' => '2026-10-12 09:00',
            'ends_at' => '2026-10-12 10:30',
        ]);

        $submission = \App\Models\AssignmentSubmission::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assessment->id,
            'student_id' => $ada->id,
            'submitted_at' => '2026-10-12 10:45',
        ]);

        // Handed in 15 minutes after it closed. Rounding to the end of the day
        // would have called this on time.
        $this->assertTrue($submission->isLate());

        $submission->update(['submitted_at' => '2026-10-12 10:15']);

        $this->assertFalse($submission->fresh()->isLate());
    }
}
