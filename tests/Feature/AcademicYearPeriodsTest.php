<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Semester;
use App\Models\Term;

/**
 * Creating, changing and removing a year's time frame, period by period, from
 * the Academic years page.
 */
class AcademicYearPeriodsTest extends PeriodGradingTestCase
{
    public function test_a_new_year_can_be_created_with_its_six_periods(): void
    {
        $this->actingAs($this->admin())->post(route('settings.years.store'), [
            'name' => '2027 / 2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'periods' => 1,
        ])->assertSessionHasNoErrors();

        $year = AcademicYear::where('name', '2027 / 2028')->firstOrFail();

        $this->assertSame(6, Term::where('academic_year_id', $year->id)->whereNotNull('semester')->count());
        $this->assertSame(2, Semester::where('academic_year_id', $year->id)->count());
        $this->assertSame('2027-09-01', Term::where('academic_year_id', $year->id)->where('sequence', 1)->value('starts_on')->toDateString());
        $this->assertSame('2028-06-30', Term::where('academic_year_id', $year->id)->where('sequence', 6)->value('ends_on')->toDateString());
    }

    public function test_a_year_can_still_be_created_without_periods(): void
    {
        $this->actingAs($this->admin())->post(route('settings.years.store'), [
            'name' => '2027 / 2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Term::count());
    }

    public function test_the_page_shows_the_periods_grouped_by_semester(): void
    {
        $this->setUpPeriods();

        $this->actingAs($this->admin())->get(route('settings.years.index'))
            ->assertOk()
            ->assertSee('Academic years &amp; periods', false)
            ->assertSee('First semester')
            ->assertSee('Second semester')
            ->assertSee('1st period')
            ->assertSee('6th period');
    }

    public function test_a_periods_dates_can_be_changed(): void
    {
        $this->setUpPeriods();
        $period = $this->period(2);

        $this->actingAs($this->admin())->put(route('settings.terms.update', $period), [
            'sequence' => 2,
            'starts_on' => $this->period(1)->ends_on->copy()->addDay()->toDateString(),
            'ends_on' => '2026-11-30',
        ])->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame('2026-11-30', $period->ends_on->toDateString());
        $this->assertSame('2nd period', $period->name);
        $this->assertSame(1, (int) $period->semester);
    }

    /** The name follows the number; it is not something to type. */
    public function test_a_period_cannot_be_renamed_away_from_its_number(): void
    {
        $this->setUpPeriods();
        $period = $this->period(2);

        $this->actingAs($this->admin())->put(route('settings.terms.update', $period), [
            'name' => 'Christmas term',
            'sequence' => 2,
            'starts_on' => $period->starts_on->toDateString(),
            'ends_on' => $period->ends_on->toDateString(),
        ]);

        $this->assertSame('2nd period', $period->fresh()->name);
    }

    public function test_an_empty_period_can_be_deleted_and_added_back(): void
    {
        $this->setUpPeriods();
        $period = $this->period(5);
        $starts = $period->starts_on->toDateString();
        $ends = $period->ends_on->toDateString();

        $this->actingAs($this->admin())->delete(route('settings.terms.destroy', $period))->assertSessionHasNoErrors();
        $this->assertSame(5, Term::whereNotNull('semester')->count());

        $this->actingAs($this->admin())->post(route('settings.terms.store', $this->year), [
            'sequence' => 5, 'starts_on' => $starts, 'ends_on' => $ends,
        ])->assertSessionHasNoErrors();

        $added = Term::where('sequence', 5)->firstOrFail();
        $this->assertSame('5th period', $added->name);
        $this->assertSame(2, (int) $added->semester, 'The 5th period belongs to the second semester.');
    }

    /** Deleting is refused while marks are filed against the period. */
    public function test_a_period_holding_marks_cannot_be_deleted(): void
    {
        $this->setUpPeriods();
        $period = $this->period(1);

        Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'term_id' => $period->id,
            'section_id' => $this->section->id, 'subject_id' => $this->maths->id,
            'title' => 'Quiz', 'type' => 'quiz', 'max_score' => 20, 'weight' => 20, 'status' => 'draft',
        ]);

        $period->update(['is_current' => false]);

        $this->actingAs($this->admin())->delete(route('settings.terms.destroy', $period))->assertSessionHasErrors('term');

        $this->assertNotNull($period->fresh());
    }

    public function test_the_same_period_cannot_be_added_twice(): void
    {
        $this->setUpPeriods();

        $this->actingAs($this->admin())->post(route('settings.terms.store', $this->year), [
            'sequence' => 3, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-20',
        ])->assertSessionHasErrors();

        $this->assertSame(6, Term::count());
    }

    public function test_changing_the_time_frame_needs_the_calendar_permission(): void
    {
        $this->setUpPeriods();
        $period = $this->period(2);
        $viewer = $this->userFor($this->school, ['academics.view']);

        $this->actingAs($viewer)->put(route('settings.terms.update', $period), [
            'sequence' => 2, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02',
        ])->assertForbidden();

        $this->actingAs($viewer)->delete(route('settings.terms.destroy', $period))->assertForbidden();
    }
}
