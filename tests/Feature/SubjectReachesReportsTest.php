<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * A subject placed under a class appears on that class's grade sheets and
 * report cards - including for the students who were enrolled before it was.
 *
 * It used to reach the class but not the children already in it: missing from
 * their papers, and impossible for a teacher to mark.
 */
class SubjectReachesReportsTest extends PeriodGradingTestCase
{
    protected function enrolledWithSubjects(string $number, string $first): \App\Models\Student
    {
        $student = $this->student($number, $first);

        // As enrolment records it: every subject the class offers today.
        foreach ($this->section->schoolClass->subjects()->pluck('subjects.id') as $subjectId) {
            DB::table('student_subject')->insert([
                'school_id' => $this->school->id, 'student_id' => $student->id, 'subject_id' => $subjectId,
                'academic_year_id' => $this->year->id, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
            ]);
        }

        return $student;
    }

    protected function placeUnderClass(Subject $subject): void
    {
        $this->actingAs($this->admin())->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'code' => $subject->code,
            'class_ids' => [$this->section->school_class_id],
        ])->assertSessionHasNoErrors();
    }

    public function test_a_subject_placed_under_a_class_reaches_the_students_already_in_it(): void
    {
        $this->setUpPeriods();
        $mary = $this->enrolledWithSubjects('S-1', 'Mary');

        $french = Subject::create(['school_id' => $this->school->id, 'name' => 'French', 'code' => 'FRE']);
        $this->placeUnderClass($french);

        $this->assertTrue(
            $mary->subjects()->where('subjects.id', $french->id)->exists(),
            'Mary was enrolled before French was added, and should now take it.'
        );

        // A teacher can now mark her in it...
        $this->actingAs($this->admin())
            ->get(route('gradesheet.index', ['section' => $this->section->id, 'subject' => $french->id, 'sheet' => $this->key(1)]))
            ->assertOk()
            ->assertSee('Mary Doe');

        // ...and it is on her papers.
        $this->actingAs($this->admin())
            ->get(route('progress.report-cards', ['section' => $this->section->id, 'student' => $mary->id]))
            ->assertOk()
            ->assertSee('French');

        $this->actingAs($this->admin())
            ->get(route('progress.grade-sheets', ['section' => $this->section->id, 'period' => $this->period(1)->id, 'student' => $mary->id]))
            ->assertOk()
            ->assertSee('French');
    }

    public function test_a_new_subject_created_under_a_class_reaches_its_students(): void
    {
        $this->setUpPeriods();
        $mary = $this->enrolledWithSubjects('S-1', 'Mary');

        $this->actingAs($this->admin())->post(route('subjects.store'), [
            'name' => 'Agriculture', 'code' => 'AGR', 'class_ids' => [$this->section->school_class_id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($mary->subjects()->where('subjects.name', 'Agriculture')->exists());
    }

    /** Its grade counts towards the average once approved. */
    public function test_the_new_subjects_grade_is_part_of_the_average(): void
    {
        $this->setUpPeriods();
        $mary = $this->enrolledWithSubjects('S-1', 'Mary');
        $french = Subject::create(['school_id' => $this->school->id, 'name' => 'French', 'code' => 'FRE']);
        $this->placeUnderClass($french);

        foreach ([[$this->maths, 80], [$french, 60]] as [$subject, $score]) {
            $assessment = Assessment::create([
                'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'term_id' => $this->period(1)->id,
                'section_id' => $this->section->id, 'subject_id' => $subject->id,
                'title' => 'Test', 'type' => 'period_test', 'max_score' => 100, 'weight' => 100, 'status' => 'approved',
            ]);
            AssessmentScore::create(['school_id' => $this->school->id, 'assessment_id' => $assessment->id, 'student_id' => $mary->id, 'score' => $score]);
        }

        $report = app(\App\Services\ProgressReport::class)->forClass($this->section, $this->year);

        $this->assertSame(70.0, $report['students'][$mary->id]['averages']['p1']);
    }

    /** Taking a subject off a class does not rewrite what students took. */
    public function test_unticking_a_class_leaves_students_records_alone(): void
    {
        $this->setUpPeriods();
        $mary = $this->enrolledWithSubjects('S-1', 'Mary');

        $this->actingAs($this->admin())->put(route('subjects.update', $this->maths), [
            'name' => $this->maths->name, 'code' => $this->maths->code, 'class_ids' => [],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($mary->subjects()->where('subjects.id', $this->maths->id)->exists());
    }

    /** The repair for students who missed a subject before this was fixed. */
    public function test_the_repair_fills_only_subjects_added_after_enrolment(): void
    {
        $this->setUpPeriods();
        $mary = $this->enrolledWithSubjects('S-1', 'Mary');

        // An elective offered when Mary enrolled that she deliberately does not take.
        $elective = Subject::create(['school_id' => $this->school->id, 'name' => 'Latin', 'code' => 'LAT']);
        DB::table('class_subject')->insert([
            'school_id' => $this->school->id, 'school_class_id' => $this->section->school_class_id,
            'subject_id' => $elective->id, 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);

        // A subject placed under the class later, by the old code (class only).
        $late = Subject::create(['school_id' => $this->school->id, 'name' => 'French', 'code' => 'FRE']);
        DB::table('class_subject')->insert([
            'school_id' => $this->school->id, 'school_class_id' => $this->section->school_class_id,
            'subject_id' => $late->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (include database_path('migrations/2026_09_16_000003_backfill_subjects_added_after_enrolment.php'))->up();

        $this->assertTrue($mary->subjects()->where('subjects.id', $late->id)->exists());
        $this->assertFalse($mary->subjects()->where('subjects.id', $elective->id)->exists(), 'A deliberately skipped elective stays skipped.');
    }
}
