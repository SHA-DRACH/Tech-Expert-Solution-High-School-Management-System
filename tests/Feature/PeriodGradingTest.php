<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Term;
use App\Services\PeriodGrades;

/**
 * Six periods, two semesters, and the grade sheet teachers enter marks on.
 *
 * Three things are defended here: the arithmetic a Liberian grade sheet uses,
 * the locks on who may write which mark and when, and that the semester exam
 * is closed to teachers until the administration opens it.
 */
class PeriodGradingTest extends PeriodGradingTestCase
{
    /* ------------------------------------------------------ the calendar */

    public function test_setting_up_creates_six_periods_in_two_semesters(): void
    {
        $this->setUpPeriods();

        $periods = app(PeriodGrades::class)->periods($this->year);

        $this->assertCount(6, $periods);
        $this->assertSame([1, 1, 1, 2, 2, 2], $periods->pluck('semester')->map(fn ($s) => (int) $s)->values()->all());
        $this->assertSame('1st period', $periods[1]->label());
        $this->assertSame('6th period', $periods[6]->label());
        $this->assertSame(2, Semester::where('academic_year_id', $this->year->id)->count());
    }

    /** The marks already filed against a school's old terms must not move. */
    public function test_existing_terms_become_the_first_periods_and_keep_their_marks(): void
    {
        $term = Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'First Term', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15', 'is_current' => true,
        ]);

        $assessment = Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'term_id' => $term->id,
            'section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'title' => 'Old test',
            'type' => 'test', 'max_score' => 100, 'weight' => 1, 'status' => 'approved',
        ]);

        $this->setUpPeriods();

        $this->assertSame($term->id, $this->period(1)->id);
        $this->assertSame($term->id, $assessment->fresh()->term_id);
        $this->assertSame(6, Term::where('academic_year_id', $this->year->id)->count());
    }

    /** A converted "Third Term" must not become the current period in September. */
    public function test_after_setup_the_current_period_is_the_one_containing_today(): void
    {
        Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'Third Term', 'sequence' => 3, 'starts_on' => '2027-03-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $this->setUpPeriods();

        // 16 September 2026 falls in the 1st of six equal periods from 1 September.
        $this->assertTrue($this->period(1)->fresh()->is_current);
        $this->assertSame(1, Term::where('is_current', true)->count());
    }

    public function test_setting_up_again_does_not_overwrite_the_dates_the_school_set(): void
    {
        $this->setUpPeriods();

        $first = $this->period(1);
        $first->update(['ends_on' => '2026-10-09']);

        $this->setUpPeriods();

        $this->assertSame('2026-10-09', $first->fresh()->ends_on->toDateString());
    }

    public function test_setting_up_periods_needs_the_calendar_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['academics.view']))
            ->post(route('periods.setup', $this->year))
            ->assertForbidden();

        $this->assertSame(0, Term::count());
    }

    public function test_period_dates_must_not_overlap_another_period(): void
    {
        $this->setUpPeriods();

        $this->actingAs($this->admin())
            ->put(route('periods.update', $this->period(1)), [
                'starts_on' => '2026-09-01',
                'ends_on' => $this->period(2)->ends_on->toDateString(),
            ])
            ->assertSessionHasErrors('starts_on');
    }

    /* ----------------------------------------------------- the arithmetic */

    /** Parts out of 40/20/20/20: the period grade is simply their sum. */
    public function test_a_period_grade_is_the_marks_added_up(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $this->save($this->teacherUser(), $this->key(1), [
            'period_test' => [$mary->id => 32],
            'quiz' => [$mary->id => 15],
            'assignment' => [$mary->id => 18],
            'attendance' => [$mary->id => 20],
        ])->assertSessionHasNoErrors();

        $row = app(PeriodGrades::class)->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]), approvedOnly: false)->first();

        $this->assertSame(85.0, $row['periods'][1]);
    }

    /**
     * Regression, found in the browser: older work adopted as a column carried
     * a weight of 1, and 34 + 17 + 18 + 19 came out as 87.53. The grade is the
     * marks added up, whatever weight an assessment happens to have.
     */
    public function test_an_assessments_weight_does_not_change_the_added_up_grade(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'term_id' => $this->period(1)->id,
            'section_id' => $this->section->id, 'subject_id' => $this->maths->id,
            'title' => 'Old assignment', 'type' => 'assignment', 'max_score' => 20, 'weight' => 1, 'status' => 'draft',
        ]);

        $this->save($this->teacherUser(), $this->key(1), [
            'period_test' => [$mary->id => 34],
            'quiz' => [$mary->id => 17],
            'assignment' => [$mary->id => 18],
            'attendance' => [$mary->id => 19],
        ])->assertSessionHasNoErrors();

        $row = app(PeriodGrades::class)->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]), approvedOnly: false)->first();

        $this->assertSame(88.0, $row['periods'][1]);
    }

    /**
     * A child missing a mark in work the class sat gets no grade rather than a
     * grade built from different work to everyone else's.
     */
    public function test_a_missing_mark_leaves_the_period_grade_blank(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $ben = $this->student('S-2', 'Ben');

        $this->save($this->teacherUser(), $this->key(1), [
            'period_test' => [$mary->id => 30, $ben->id => 30],
            'quiz' => [$mary->id => 20],
        ]);

        $rows = app(PeriodGrades::class)->subjectSheet($this->section, $this->maths, $this->year, collect([$mary, $ben]), approvedOnly: false);

        $this->assertSame(83.33, $rows[0]['periods'][1]);
        $this->assertNull($rows[1]['periods'][1]);
    }

    public function test_semester_and_yearly_averages_follow_the_liberian_formula(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $grades = [1 => 70, 2 => 80, 3 => 90, 4 => 60, 5 => 70, 6 => 80];

        foreach ($grades as $number => $grade) {
            $this->makeApproved($mary, 'period_test', $this->period($number), null, $grade);
        }

        $this->makeApproved($mary, Assessment::SEMESTER_EXAM, null, $this->semester(1), 100);
        $this->makeApproved($mary, Assessment::SEMESTER_EXAM, null, $this->semester(2), 50);

        $row = app(PeriodGrades::class)->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]))->first();

        $this->assertSame(85.0, $row['semesters'][1]);   // (70 + 80 + 90 + 100) / 4
        $this->assertSame(65.0, $row['semesters'][2]);   // (60 + 70 + 80 + 50) / 4
        $this->assertSame(75.0, $row['yearly']);         // (85 + 65) / 2
    }

    public function test_a_semester_average_waits_for_the_exam(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        foreach ([1, 2, 3] as $number) {
            $this->makeApproved($mary, 'period_test', $this->period($number), null, 80);
        }

        $row = app(PeriodGrades::class)->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]))->first();

        $this->assertSame(80.0, $row['periods'][3]);
        $this->assertNull($row['semesters'][1]);
        $this->assertNull($row['yearly']);
    }

    /** The official record is approved marks only. */
    public function test_unapproved_marks_are_not_part_of_the_official_grade(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $this->save($this->teacherUser(), $this->key(1), ['period_test' => [$mary->id => 40]]);

        $grades = app(PeriodGrades::class);

        $this->assertNull($grades->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]))->first()['periods'][1]);
        $this->assertSame(100.0, $grades->subjectSheet($this->section, $this->maths, $this->year, collect([$mary]), approvedOnly: false)->first()['periods'][1]);
    }

    /* ---------------------------------------------------- the grade sheet */

    public function test_the_grade_sheet_lists_every_student_in_the_class(): void
    {
        $this->setUpPeriods();
        $this->student('S-1', 'Mary');
        $this->student('S-2', 'Ben');

        $this->actingAs($this->teacherUser())
            ->get(route('gradesheet.index', ['section' => $this->section->id, 'subject' => $this->maths->id, 'sheet' => $this->key(1)]))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertSee('Ben Doe')
            ->assertSee('Period test')
            ->assertSee('Attendance')
            ->assertSee('name="marks[period_test]['.Student::where('student_number', 'S-1')->value('id').']"', false);
    }

    /** No setup step: typing the first mark creates the column behind it. */
    public function test_saving_creates_the_period_columns_with_the_schools_mark_allocation(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $this->save($this->teacherUser(), $this->key(1), ['quiz' => [$mary->id => 12]])->assertRedirect();

        $quiz = Assessment::where('type', 'quiz')->firstOrFail();

        $this->assertSame($this->period(1)->id, $quiz->term_id);
        $this->assertSame(20, (int) $quiz->max_score);
        $this->assertSame('draft', $quiz->status);
        $this->assertSame($this->teacher->id, $quiz->teacher_id);
        $this->assertSame(12.0, (float) AssessmentScore::where('student_id', $mary->id)->value('score'));
    }

    public function test_a_teacher_cannot_mark_a_class_they_do_not_teach(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $stranger = $this->userFor($this->school, ['grades.enter']);

        $this->save($stranger, $this->key(1), ['period_test' => [$mary->id => 30]])->assertNotFound();

        $this->assertSame(0, AssessmentScore::count());
    }

    /** The dashboard lists each class the teacher marks, with a way straight in. */
    public function test_the_teacher_dashboard_lists_classes_to_mark_this_period(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $this->student('S-2', 'Ben');
        $teacher = $this->teacherUser(['dashboard.view']);

        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => 15]]);

        $this->actingAs($teacher)->get(route('teaching.dashboard'))
            ->assertOk()
            ->assertSee('Enter marks')
            ->assertSee('Grade 9A')
            ->assertSee('Mathematics')
            ->assertSee('1 of 2')
            ->assertSee(route('gradesheet.index', [
                'section' => $this->section->id, 'subject' => $this->maths->id, 'sheet' => $this->key(1),
            ]));
    }

    /**
     * Regression. An account with grades.enter and no teacher record used to
     * count as unrestricted, and was shown every class's students and marks -
     * on this sheet and on the older mark sheet alike.
     */
    public function test_an_account_with_no_teacher_record_sees_no_classes(): void
    {
        $this->setUpPeriods();
        $this->student('S-1', 'Mary');
        $user = $this->userFor($this->school, ['grades.enter']);

        foreach ([route('gradesheet.index'), route('marks.index')] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertDontSee('Mary Doe')
                ->assertSee('You have no classes to mark');
        }
    }

    /** All or nothing: one bad mark and nothing from the sheet is kept. */
    public function test_a_mark_above_the_maximum_saves_nothing(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $ben = $this->student('S-2', 'Ben');

        $this->save($this->teacherUser(), $this->key(1), [
            'period_test' => [$mary->id => 35, $ben->id => 41],
        ])->assertSessionHasErrors('marks');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_a_teacher_cannot_enter_marks_for_a_period_that_has_not_started(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $this->save($this->teacherUser(), $this->key(4), ['period_test' => [$mary->id => 30]])
            ->assertSessionHasErrors('marks');

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_blank_means_no_mark_and_clears_an_existing_one(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();

        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => 10]]);
        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => '']]);

        $this->assertSame(0, AssessmentScore::count());
    }

    public function test_submitted_marks_are_out_of_the_teachers_hands(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();

        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => 10]]);

        $this->actingAs($teacher)->post(route('gradesheet.submit', [
            'section' => $this->section->id, 'subject' => $this->maths->id, 'sheet' => $this->key(1),
        ]))->assertRedirect();

        $this->assertSame('submitted', Assessment::where('type', 'quiz')->value('status'));

        $this->save($teacher, $this->key(1), ['quiz' => [$mary->id => 19]])->assertSessionHasErrors('marks');
        $this->assertSame(10.0, (float) AssessmentScore::value('score'));
    }

    /* --------------------------------------------------- the semester exam */

    public function test_exam_marks_are_closed_to_teachers_until_the_administration_opens_them(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();
        $exam = 'exam:'.$this->semester(1)->id;

        $this->save($teacher, $exam, [Assessment::SEMESTER_EXAM => [$mary->id => 70]])->assertSessionHasErrors('marks');
        $this->assertSame(0, AssessmentScore::count());

        $this->actingAs($this->admin())
            ->patch(route('semesters.exam-entry', $this->semester(1)), ['open' => 1])
            ->assertRedirect();

        $this->save($teacher, $exam, [Assessment::SEMESTER_EXAM => [$mary->id => 70]])->assertSessionHasNoErrors();

        $examAssessment = Assessment::where('type', Assessment::SEMESTER_EXAM)->firstOrFail();
        $this->assertSame($this->semester(1)->id, $examAssessment->semester_id);
        $this->assertNull($examAssessment->term_id, 'The exam belongs to the semester, not to the 3rd period.');
    }

    public function test_closing_exam_entry_locks_it_again_without_losing_marks(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');
        $teacher = $this->teacherUser();
        $semester = $this->semester(1);
        $semester->update(['exam_entry_open' => true]);

        $this->save($teacher, 'exam:'.$semester->id, [Assessment::SEMESTER_EXAM => [$mary->id => 70]]);

        $this->actingAs($this->admin())->patch(route('semesters.exam-entry', $semester), ['open' => 0]);

        $this->save($teacher, 'exam:'.$semester->id, [Assessment::SEMESTER_EXAM => [$mary->id => 90]])->assertSessionHasErrors('marks');
        $this->assertSame(70.0, (float) AssessmentScore::value('score'));
    }

    public function test_only_someone_granted_it_may_open_exam_entry(): void
    {
        $this->setUpPeriods();

        $this->actingAs($this->userFor($this->school, ['academics.manage', 'grades.enter']))
            ->patch(route('semesters.exam-entry', $this->semester(1)), ['open' => 1])
            ->assertForbidden();

        $this->assertFalse($this->semester(1)->exam_entry_open);
    }

    public function test_opening_exam_entry_is_audited_and_teachers_are_told(): void
    {
        $this->setUpPeriods();
        $teacher = $this->teacherUser();

        $this->actingAs($this->userFor($this->school, ['grades.exam_entry']))
            ->patch(route('semesters.exam-entry', $this->semester(1)), ['open' => 1]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'exam_entry_opened']);
        $this->assertSame(1, $teacher->notifications()->count());
    }

    /** The academic office can always enter exam marks, open or not. */
    public function test_the_academic_office_is_not_bound_by_the_exam_lock(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        $this->save($this->userFor($this->school, ['grades.approve']), 'exam:'.$this->semester(1)->id, [
            Assessment::SEMESTER_EXAM => [$mary->id => 88],
        ])->assertSessionHasNoErrors();

        $this->assertSame(88.0, (float) AssessmentScore::value('score'));
    }

    /* ---------------------------------------------------------- the year */

    public function test_the_year_sheet_shows_every_period_and_average(): void
    {
        $this->setUpPeriods();
        $mary = $this->student('S-1');

        foreach ([1, 2, 3] as $number) {
            $this->makeApproved($mary, 'period_test', $this->period($number), null, 80);
        }
        $this->makeApproved($mary, Assessment::SEMESTER_EXAM, null, $this->semester(1), 60);

        $this->actingAs($this->userFor($this->school, ['reportcards.view']))
            ->get(route('gradesheet.summary', ['section' => $this->section->id, 'subject' => $this->maths->id]))
            ->assertOk()
            ->assertSee('1st period')
            ->assertSee('6th period')
            ->assertSee('Yearly')
            ->assertSee('75');   // (80 + 80 + 80 + 60) / 4
    }

}
