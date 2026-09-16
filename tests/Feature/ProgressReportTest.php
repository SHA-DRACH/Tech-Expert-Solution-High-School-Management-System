<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\PeriodConduct;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Subject;
use App\Models\User;

/**
 * The grade sheet each period and the periodic progress report at year end.
 *
 * What is printed must be the official record (approved marks), the rank must
 * be relative to the whole class, and the paper must only reach the child, a
 * cleared parent, or staff.
 */
class ProgressReportTest extends PeriodGradingTestCase
{
    protected Subject $english;

    protected Student $mary;

    protected Student $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPeriods();

        $this->english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);
        $this->section->schoolClass->subjects()->attach([$this->english->id], ['school_id' => $this->school->id]);
        $this->section->schoolClass->update(['stage' => 'Junior High']);
        $this->section->update(['class_teacher_id' => $this->teacher->id]);

        $this->mary = $this->student('S-1', 'Mary');
        $this->ben = $this->student('S-2', 'Ben');
    }

    protected function grade(Student $student, Subject $subject, int $period, float $score, string $status = 'approved'): void
    {
        $assessment = Assessment::firstOrCreate([
            'section_id' => $this->section->id, 'subject_id' => $subject->id,
            'term_id' => $this->period($period)->id, 'type' => 'period_test',
        ], [
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'title' => 'Test', 'max_score' => 100, 'weight' => 100, 'status' => $status,
        ]);

        \App\Models\AssessmentScore::create([
            'school_id' => $this->school->id, 'assessment_id' => $assessment->id,
            'student_id' => $student->id, 'score' => $score,
        ]);
    }

    protected function staff(): User
    {
        return $this->userFor($this->school, ['reportcards.view']);
    }

    /* ---------------------------------------------------------- grade sheet */

    public function test_the_period_grade_sheet_follows_the_template(): void
    {
        $this->grade($this->mary, $this->maths, 1, 90);
        $this->grade($this->mary, $this->english, 1, 80);
        $this->grade($this->ben, $this->maths, 1, 70);
        $this->grade($this->ben, $this->english, 1, 60);

        AttendanceRecord::create([
            'school_id' => $this->school->id, 'student_id' => $this->mary->id, 'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id, 'recorded_on' => '2026-09-10', 'status' => 'present',
        ]);
        AttendanceRecord::create([
            'school_id' => $this->school->id, 'student_id' => $this->mary->id, 'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id, 'recorded_on' => '2026-09-11', 'status' => 'absent',
        ]);
        PeriodConduct::create(['school_id' => $this->school->id, 'student_id' => $this->mary->id, 'term_id' => $this->period(1)->id, 'conduct' => 'Good']);

        $this->actingAs($this->staff())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(1)->id, 'student' => $this->mary->id]))
            ->assertOk()
            ->assertSeeInOrder(['Junior High Grade Sheet', 'Mary Doe', 'First Semester Grade Sheet', '1ST PD'])
            ->assertSeeInOrder(['English', '80', 'Mathematics', '90'])
            ->assertSeeInOrder(['Average (%)', '85'])
            ->assertSeeInOrder(['Class Rank', '1', '/2'])
            ->assertSeeInOrder(['Day(s) Present', '1', 'Day(s) Absent', '1', 'Conduct', 'Good'])
            ->assertSee('Class Sponsor — Grace Kollie')
            ->assertSee('Principal')
            ->assertSee('<svg', false);
    }

    /** The exam and the semester average join the sheet in its last period. */
    public function test_the_third_period_sheet_carries_the_exam_and_semester_average(): void
    {
        $this->actingAs($this->staff())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(3)->id, 'student' => $this->mary->id]))
            ->assertOk()
            ->assertSeeInOrder(['1ST PD', '2ND PD', '3RD PD', 'EXAM', 'AVE.']);

        $this->actingAs($this->staff())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(2)->id, 'student' => $this->mary->id]))
            ->assertOk()
            ->assertDontSee('EXAM');
    }

    public function test_unapproved_marks_are_not_printed(): void
    {
        $this->grade($this->mary, $this->maths, 1, 97, status: 'submitted');

        $this->actingAs($this->staff())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(1)->id, 'student' => $this->mary->id]))
            ->assertOk()
            ->assertDontSee('97');
    }

    /** No average from half the subjects - it would be copied onto paper. */
    public function test_the_average_waits_for_every_subject(): void
    {
        $this->grade($this->mary, $this->maths, 1, 93.25);

        $this->actingAs($this->staff())
            ->get(route('progress.index', ['section' => $this->section->id, 'period' => $this->period(1)->id]))
            ->assertOk()
            ->assertDontSee('93.25');
    }

    public function test_the_whole_class_prints_one_sheet_per_student(): void
    {
        $html = $this->actingAs($this->staff())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(1)->id]))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertSee('Ben Doe')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'class="sheet"'));
    }

    /* ---------------------------------------------------------- report card */

    public function test_the_year_end_report_card_follows_the_template(): void
    {
        \App\Models\SchoolSetting::create(['school_id' => $this->school->id, 'key' => 'reportcard_remark', 'value' => 'Damaged or Lost $5.00USD']);
        $this->school->update(['motto' => 'Better Education, Brighter Future']);

        $this->grade($this->mary, $this->maths, 1, 90);
        $this->grade($this->mary, $this->english, 1, 80);
        $this->grade($this->ben, $this->maths, 1, 95);
        $this->grade($this->ben, $this->english, 1, 95);

        $this->actingAs($this->staff())
            ->get(route('progress.report-cards', ['section' => $this->section->id, 'student' => $this->mary->id]))
            ->assertOk()
            ->assertSee('Junior High Division Periodic Progress Report')
            ->assertSeeInOrder(["Student's Name", 'Doe, Mary', 'Grade', 'Year', '2026 / 2027'], false)
            ->assertSeeInOrder(['1st', '2nd', '3rd', 'Exam', 'Ave.', '4th', '5th', '6th', 'Exam', 'Ave.', 'Yrly'])
            ->assertSeeInOrder(['Average', '85', 'No. of Students', '2', 'Rank', '2'])
            ->assertSee('100 Means Perfect')
            ->assertSee('is failure and Requires attention')
            ->assertSee('Parent/Guardian')
            ->assertSee('Registrar')
            ->assertSee('Better Education, Brighter Future')
            ->assertSee('Damaged or Lost $5.00USD');
    }

    /* ---------------------------------------------------------- who sees it */

    public function test_staff_need_permission_to_print(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('progress.report-cards', ['section' => $this->section->id]))
            ->assertForbidden();
    }

    public function test_a_student_sees_only_their_own_papers(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->mary->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('student.gradesheet'))
            ->assertOk()->assertSee('Mary Doe')->assertDontSee('Ben Doe');

        $this->actingAs($user)->get(route('student.progress', ['student' => $this->ben->id]))
            ->assertOk()->assertSee('Doe, Mary')->assertDontSee('Doe, Ben');
    }

    public function test_the_school_can_withhold_report_cards_from_a_student(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $this->mary->update(['user_id' => $user->id]);

        StudentPermission::create([
            'school_id' => $this->school->id, 'student_id' => $this->mary->id,
            'ability' => 'view_report_cards', 'allowed' => false,
        ]);

        $this->actingAs($user)->get(route('student.progress'))->assertForbidden();
    }

    public function test_a_parent_sees_their_childs_papers_only_when_cleared(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $guardian = Guardian::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '1']);
        $guardian->students()->attach($this->mary->id, ['relationship' => 'Father', 'can_view_academics' => true]);

        $this->actingAs($user)->get(route('parent.progress', ['child' => $this->mary->id]))->assertOk()->assertSee('Doe, Mary');

        // Another family's child id falls back to their own.
        $this->actingAs($user)->get(route('parent.gradesheet', ['child' => $this->ben->id]))->assertOk()->assertDontSee('Ben Doe');

        $guardian->students()->updateExistingPivot($this->mary->id, ['can_view_academics' => false]);
        $this->actingAs($user)->get(route('parent.progress'))->assertForbidden();
    }

    /* -------------------------------------------------------------- conduct */

    public function test_the_class_sponsor_records_conduct(): void
    {
        $sponsor = $this->teacherUser(['reportcards.view']);

        $this->actingAs($sponsor)->post(route('progress.conduct', $this->section), [
            'period' => $this->period(1)->id,
            'conduct' => [$this->mary->id => 'Very good', $this->ben->id => 'Fair'],
        ])->assertRedirect();

        $this->assertSame('Very good', PeriodConduct::where('student_id', $this->mary->id)->value('conduct'));
    }

    public function test_a_teacher_who_is_not_the_sponsor_cannot_record_conduct(): void
    {
        $this->section->update(['class_teacher_id' => null]);

        $this->actingAs($this->teacherUser(['reportcards.view']))->post(route('progress.conduct', $this->section), [
            'period' => $this->period(1)->id,
            'conduct' => [$this->mary->id => 'Poor'],
        ])->assertForbidden();

        $this->assertSame(0, PeriodConduct::count());
    }

    public function test_conduct_cannot_be_written_for_a_child_outside_the_class(): void
    {
        $outsider = Student::create([
            'school_id' => $this->school->id, 'student_number' => 'S-9', 'first_name' => 'Out', 'last_name' => 'Sider', 'status' => 'active',
        ]);

        $this->actingAs($this->admin())->post(route('progress.conduct', $this->section), [
            'period' => $this->period(1)->id,
            'conduct' => [$outsider->id => 'Poor'],
        ]);

        $this->assertSame(0, PeriodConduct::count());
    }

    /* --------------------------------------------------------- verification */

    public function test_a_grade_sheet_can_be_verified_without_its_marks(): void
    {
        $this->grade($this->mary, $this->maths, 1, 88);
        $this->school->update(['domain' => 'progress-test.test']);

        $this->get('http://progress-test.test/verify/grade-sheet/S-1.'.$this->period(1)->id)
            ->assertOk()
            ->assertSee('Mary D.')
            ->assertSee('1st period')
            ->assertDontSee('88')
            ->assertDontSee('Mary Doe');
    }
}
