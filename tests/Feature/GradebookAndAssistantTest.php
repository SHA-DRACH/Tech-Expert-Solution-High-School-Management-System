<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Assistant\SchoolAssistant;
use App\Services\Gradebook;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Results, mark upload, teaching assignments and the assistant.
 *
 * The rule almost every test here defends is the same one: **only approved
 * marks are results**. A mark a teacher has entered but the academic office has
 * not signed off must never reach a parent, never count towards an average, and
 * never appear in the gradebook.
 */
class GradebookAndAssistantTest extends TestCase
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
            'name' => 'Grade 7',
            'level' => 7,
            'stage' => 'Junior High',
        ]);

        $this->section = Section::create([
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'name' => 'A',
        ]);

        $this->maths = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathematics',
            'code' => 'MTH101',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-001',
            'first_name' => 'Grace',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);

        foreach ([['A', 90, 100], ['B', 80, 89], ['C', 70, 79], ['D', 60, 69], ['F', 0, 59]] as $i => [$g, $min, $max]) {
            GradeScale::create([
                'school_id' => $this->school->id,
                'grade' => $g,
                'min_score' => $min,
                'max_score' => $max,
                'sequence' => $i + 1,
            ]);
        }
    }

    protected function student(string $number, string $first, string $last = 'Doe'): Student
    {
        $student = Student::create([
            'school_id' => $this->school->id,
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
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

    protected function score(Assessment $assessment, Student $student, float $score): AssessmentScore
    {
        return AssessmentScore::create([
            'school_id' => $this->school->id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => $score,
        ]);
    }

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

    /* ------------------------------------------------- approved marks only */

    public function test_unapproved_marks_do_not_count_towards_an_average(): void
    {
        $student = $this->student('S-1', 'Ada');

        $this->score($this->assessment(['status' => 'draft']), $student, 90);

        $results = app(Gradebook::class)->termResults($student, $this->term);

        // A mark nobody has signed off is not a result yet.
        $this->assertNull($results['average']);
        $this->assertTrue($results['subjects']->isEmpty());
    }

    public function test_approved_marks_produce_an_average_and_a_grade(): void
    {
        $student = $this->student('S-1', 'Ada');

        $this->score($this->assessment(['status' => 'approved']), $student, 82);

        $results = app(Gradebook::class)->termResults($student, $this->term);

        $this->assertSame(82.0, $results['average']);
        $this->assertSame('B', $results['grade']);
        $this->assertTrue($results['passed']);
    }

    public function test_an_average_is_weighted_by_how_much_each_paper_counts(): void
    {
        $student = $this->student('S-1', 'Ada');

        $this->score($this->assessment(['status' => 'approved', 'title' => 'Class work', 'weight' => 1]), $student, 50);
        $this->score($this->assessment(['status' => 'approved', 'title' => 'Final exam', 'weight' => 3]), $student, 90);

        // (50*1 + 90*3) / 4 = 80, not the unweighted 70.
        $this->assertSame(80.0, app(Gradebook::class)->termResults($student, $this->term)['average']);
    }

    public function test_papers_out_of_different_totals_are_averaged_as_percentages(): void
    {
        $student = $this->student('S-1', 'Ada');

        $this->score($this->assessment(['status' => 'approved', 'max_score' => 40, 'title' => 'Quiz']), $student, 20);
        $this->score($this->assessment(['status' => 'approved', 'max_score' => 100, 'title' => 'Exam']), $student, 80);

        // 50% and 80% average to 65%, which raw marks (20 and 80) would not give.
        $this->assertSame(65.0, app(Gradebook::class)->termResults($student, $this->term)['average']);
    }

    public function test_the_overall_average_is_the_mean_of_subject_averages(): void
    {
        $student = $this->student('S-1', 'Ada');

        $english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG101']);

        // Maths assessed three times, English once.
        foreach ([90, 90, 90] as $i => $mark) {
            $this->score($this->assessment(['status' => 'approved', 'title' => "Maths {$i}"]), $student, $mark);
        }

        $this->score($this->assessment(['status' => 'approved', 'subject_id' => $english->id, 'title' => 'English']), $student, 50);

        /*
         | 70, the mean of the two subject averages (90 and 50). Averaging every
         | individual mark instead would give 80, letting the subject that
         | happened to be tested more often dominate the term result.
         */
        $this->assertSame(70.0, app(Gradebook::class)->termResults($student, $this->term)['average']);
    }

    public function test_the_pass_mark_follows_the_grade_scale_rather_than_contradicting_it(): void
    {
        /*
         | The scale's bottom band is F for 0-59, so the pass mark is 60 - the
         | minimum of the band above it. Before this, a separate numeric setting
         | could say 50 while the scale said F below 60, and a student on 58 was
         | shown as "above the pass mark" and graded F on the same row.
         */
        $gradebook = app(Gradebook::class);

        $this->assertSame(60, $gradebook->passMark());

        $student = $this->student('S-1', 'Ada');
        $this->score($this->assessment(['status' => 'approved']), $student, 58);

        $results = $gradebook->termResults($student, $this->term);

        $this->assertSame('F', $results['grade']);
        $this->assertFalse($results['passed'], 'A grade of F must not also be a pass.');
    }

    public function test_class_positions_are_shared_when_averages_are_equal(): void
    {
        $first = $this->student('S-1', 'Ada');
        $second = $this->student('S-2', 'Ben');
        $third = $this->student('S-3', 'Cee');

        $assessment = $this->assessment(['status' => 'approved']);

        $this->score($assessment, $first, 90);
        $this->score($assessment, $second, 90);
        $this->score($assessment, $third, 70);

        $rows = app(Gradebook::class)->sectionResults($this->section->id, $this->term)->keyBy(fn ($r) => $r['student']->id);

        // Joint first, and the next position skips to third.
        $this->assertSame(1, $rows[$first->id]['position']);
        $this->assertSame(1, $rows[$second->id]['position']);
        $this->assertSame(3, $rows[$third->id]['position']);
    }

    /* ------------------------------------------------------- mark upload */

    protected function csv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gsms').'.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'marks.csv', 'text/csv', null, true);
    }

    public function test_a_teacher_can_upload_marks_and_they_lock_on_upload(): void
    {
        $user = $this->teacherUser();
        $ada = $this->student('S-1', 'Ada');
        $ben = $this->student('S-2', 'Ben');

        $assessment = $this->assessment();

        $this->actingAs($user)->post(route('assessments.marks.preview', $assessment), [
            'file' => $this->csv("student_number,score\nS-1,88\nS-2,64\n"),
        ])->assertRedirect();

        // Nothing written until the teacher confirms what they were shown.
        $this->assertSame(0, AssessmentScore::count());

        $this->actingAs($user)->post(route('assessments.marks.store', $assessment))->assertRedirect();

        $this->assertSame(88.0, (float) AssessmentScore::where('student_id', $ada->id)->value('score'));
        $this->assertSame(64.0, (float) AssessmentScore::where('student_id', $ben->id)->value('score'));

        // Locked: uploading submits for approval in the same transaction.
        $this->assertSame('submitted', $assessment->fresh()->status);
    }

    public function test_a_teacher_cannot_change_marks_after_uploading_them(): void
    {
        $user = $this->teacherUser();
        $this->student('S-1', 'Ada');

        $assessment = $this->assessment();

        $this->actingAs($user)->post(route('assessments.marks.preview', $assessment), [
            'file' => $this->csv("student_number,score\nS-1,88\n"),
        ]);

        $this->actingAs($user)->post(route('assessments.marks.store', $assessment));

        // The whole point of the lock.
        $this->actingAs($user)
            ->put(route('assessments.scores.save', $assessment), ['scores' => []])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('assessments.marks.import', $assessment))
            ->assertForbidden();
    }

    public function test_marks_are_matched_by_student_number_not_by_name(): void
    {
        $user = $this->teacherUser();

        // Two children who share a name. Matching on the name would award one
        // of them the other's mark.
        $first = $this->student('S-1', 'Mary', 'Doe');
        $second = $this->student('S-2', 'Mary', 'Doe');

        $assessment = $this->assessment();

        $this->actingAs($user)->post(route('assessments.marks.preview', $assessment), [
            'file' => $this->csv("student_number,student_name,score\nS-2,Mary Doe,91\n"),
        ]);

        $this->actingAs($user)->post(route('assessments.marks.store', $assessment));

        $this->assertSame(91.0, (float) AssessmentScore::where('student_id', $second->id)->value('score'));
        $this->assertNull(AssessmentScore::where('student_id', $first->id)->first());
    }

    public function test_a_blank_mark_is_skipped_rather_than_recorded_as_zero(): void
    {
        $user = $this->teacherUser();
        $ada = $this->student('S-1', 'Ada');
        $ben = $this->student('S-2', 'Ben');

        $assessment = $this->assessment();

        $this->actingAs($user)->post(route('assessments.marks.preview', $assessment), [
            'file' => $this->csv("student_number,score\nS-1,70\nS-2,\n"),
        ]);

        $preview = session('mark_preview');

        $this->assertCount(1, $preview['valid']);
        $this->assertStringContainsString('rather than recorded as zero', $preview['problems'][0]['reason']);

        $this->actingAs($user)->post(route('assessments.marks.store', $assessment));

        // A child who has not sat the paper is not failed by a spreadsheet.
        $this->assertNull(AssessmentScore::where('student_id', $ben->id)->first());
        $this->assertNotNull(AssessmentScore::where('student_id', $ada->id)->first());
    }

    public function test_a_mark_above_the_maximum_is_refused(): void
    {
        $user = $this->teacherUser();
        $this->student('S-1', 'Ada');

        $this->actingAs($user)->post(route('assessments.marks.preview', $this->assessment()), [
            'file' => $this->csv("student_number,score\nS-1,140\n"),
        ]);

        $preview = session('mark_preview');

        $this->assertCount(0, $preview['valid']);
        $this->assertStringContainsString('outside 0 to 100', $preview['problems'][0]['reason']);
    }

    public function test_a_student_number_from_another_class_is_refused(): void
    {
        $user = $this->teacherUser();
        $this->student('S-1', 'Ada');

        // Enrolled nowhere near this section.
        Student::create([
            'school_id' => $this->school->id,
            'student_number' => 'OTHER-9',
            'first_name' => 'Not',
            'last_name' => 'Here',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('assessments.marks.preview', $this->assessment()), [
            'file' => $this->csv("student_number,score\nOTHER-9,70\n"),
        ]);

        $this->assertCount(0, session('mark_preview')['valid']);
    }

    public function test_a_teacher_without_the_assignment_cannot_upload_marks(): void
    {
        $user = $this->userFor($this->school, ['grades.enter', 'exams.view']);

        $outsider = Teacher::create([
            'school_id' => $this->school->id,
            'staff_number' => 'T-999',
            'first_name' => 'Not',
            'last_name' => 'Assigned',
            'status' => 'active',
        ]);

        $outsider->update(['user_id' => $user->id]);

        /*
         | The assessment belongs to a different teacher and this one holds no
         | TeachingAssignment for its section and subject, which are the only
         | two routes AssessmentPolicy accepts. Holding grades.enter is not
         | enough on its own - it says what kind of work you may do, not whose.
         */
        $this->actingAs($user)
            ->get(route('assessments.marks.import', $this->assessment()))
            ->assertForbidden();
    }

    /* ------------------------------------------------ teaching assignments */

    public function test_a_teacher_can_be_assigned_many_subjects_at_once(): void
    {
        $english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG101']);

        $this->actingAs($this->userFor($this->school, ['academics.view', 'academics.manage']))
            ->post(route('assignments.store'), [
                'teacher_id' => $this->teacher->id,
                'section_id' => [$this->section->id],
                'subject_id' => [$this->maths->id, $english->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, TeachingAssignment::where('teacher_id', $this->teacher->id)->count());
    }

    public function test_assigning_the_same_combination_twice_does_not_duplicate_it(): void
    {
        $manager = $this->userFor($this->school, ['academics.view', 'academics.manage']);

        $payload = [
            'teacher_id' => $this->teacher->id,
            'section_id' => [$this->section->id],
            'subject_id' => [$this->maths->id],
        ];

        $this->actingAs($manager)->post(route('assignments.store'), $payload);
        $this->actingAs($manager)->post(route('assignments.store'), $payload);

        $this->assertSame(1, TeachingAssignment::count());
    }

    public function test_removing_an_assignment_leaves_recorded_marks_alone(): void
    {
        $user = $this->teacherUser();
        $student = $this->student('S-1', 'Ada');

        $this->score($this->assessment(['status' => 'approved']), $student, 75);

        $assignment = TeachingAssignment::firstOrFail();

        $this->actingAs($this->userFor($this->school, ['academics.view', 'academics.manage']))
            ->delete(route('assignments.destroy', $assignment))
            ->assertRedirect();

        // Marks belong to the student, not to whoever was teaching at the time.
        $this->assertSame(1, AssessmentScore::count());
    }

    /* ------------------------------------------------------------ profile */

    public function test_anyone_signed_in_can_edit_their_own_profile(): void
    {
        // A parent holds no permission slugs at all and must still get here.
        $parent = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);

        $this->actingAs($parent)->get(route('profile.edit'))->assertOk();

        $this->actingAs($parent)
            ->put(route('profile.update'), ['name' => 'Renamed Parent', 'email' => 'renamed@example.test'])
            ->assertRedirect();

        $this->assertSame('Renamed Parent', $parent->fresh()->name);
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
            'password' => 'the-old-password',
        ]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'the-old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    /* ---------------------------------------------------------- assistant */

    public function test_the_assistant_answers_from_real_data(): void
    {
        $this->student('S-1', 'Ada');
        $this->student('S-2', 'Ben');

        $user = $this->userFor($this->school, ['students.view']);

        $result = app(SchoolAssistant::class)->ask($user, 'How many students are enrolled?');

        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('2 active students', $result['answer']);
    }

    public function test_the_assistant_refuses_what_the_asker_may_not_see(): void
    {
        $this->student('S-1', 'Ada');

        $user = $this->userFor($this->school, ['dashboard.view']);

        $result = app(SchoolAssistant::class)->ask($user, 'How many students are enrolled?');

        // Never a route around the permission system.
        $this->assertStringContainsString("don't have permission", $result['answer']);
        $this->assertStringNotContainsString('1 active', $result['answer']);
    }

    public function test_the_assistant_says_when_it_does_not_know(): void
    {
        $user = $this->userFor($this->school, ['students.view']);

        $result = app(SchoolAssistant::class)->ask($user, 'what is the capital of france');

        // An invented figure is worse than no answer at all.
        $this->assertFalse($result['handled']);
        $this->assertStringContainsString("don't know", $result['answer']);
    }

    public function test_a_question_about_a_child_is_not_answered_with_the_calendar(): void
    {
        $user = $this->userFor($this->school, ['reportcards.view']);

        $result = app(SchoolAssistant::class)->ask($user, 'How is my child doing this term?');

        // "term" appears in almost every question about a child, so the
        // calendar intent must not swallow them.
        $this->assertStringNotContainsString('2026 / 2027', $result['answer']);
    }

    public function test_the_assistant_endpoint_needs_a_signed_in_account(): void
    {
        $this->post(route('assistant.ask'), ['question' => 'How many students?'])
            ->assertRedirect(route('login'));
    }
}
