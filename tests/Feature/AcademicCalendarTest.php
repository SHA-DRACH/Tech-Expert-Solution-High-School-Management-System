<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Term;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec sections 26 and 27: academic years and terms.
 *
 * These carry more weight than most settings. Everything the platform records
 * is filed against a year and a term, so the rules they enforce - one current
 * year, one current term inside it, no overlaps, and no deletion of anything
 * holding history - are what keep the rest of the data answerable.
 */
class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function registrar()
    {
        return $this->userFor($this->school, ['academics.view', 'academics.manage']);
    }

    protected function year(array $attributes = []): AcademicYear
    {
        return AcademicYear::create(array_merge([
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => false,
        ], $attributes));
    }

    protected function term(AcademicYear $year, array $attributes = []): Term
    {
        return Term::create(array_merge([
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'name' => 'First Term',
            'sequence' => 1,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-15',
            'is_current' => false,
        ], $attributes));
    }

    /* --------------------------------------------------------------- years */

    public function test_a_registrar_can_open_an_academic_year(): void
    {
        $this->actingAs($this->registrar())
            ->post(route('settings.years.store'), [
                'name' => '2026 / 2027',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', [
            'school_id' => $this->school->id,
            'name' => '2026 / 2027',
            // The very first year a school creates is the one it is in; there
            // is nothing else it could be.
            'is_current' => true,
        ]);
    }

    public function test_the_second_year_is_not_made_current_automatically(): void
    {
        $this->year(['is_current' => true]);

        $this->actingAs($this->registrar())
            ->post(route('settings.years.store'), [
                'name' => '2027 / 2028',
                'starts_on' => '2027-09-01',
                'ends_on' => '2028-06-30',
            ])
            ->assertRedirect();

        $this->assertSame('2026 / 2027', AcademicYear::where('is_current', true)->value('name'));
    }

    public function test_years_may_not_overlap(): void
    {
        $this->year();

        $this->actingAs($this->registrar())
            ->post(route('settings.years.store'), [
                'name' => '2027 / 2028',
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-12-31',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(1, AcademicYear::count());
    }

    public function test_a_year_must_end_after_it_starts(): void
    {
        $this->actingAs($this->registrar())
            ->post(route('settings.years.store'), [
                'name' => 'Backwards',
                'starts_on' => '2027-06-30',
                'ends_on' => '2026-09-01',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_only_one_year_is_current_at_a_time(): void
    {
        $first = $this->year(['is_current' => true]);
        $second = $this->year(['name' => '2027 / 2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30']);

        $this->actingAs($this->registrar())
            ->post(route('settings.years.current', $second))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
    }

    public function test_moving_year_clears_a_current_term_from_the_year_left_behind(): void
    {
        $first = $this->year(['is_current' => true]);
        $oldTerm = $this->term($first, ['is_current' => true]);

        $second = $this->year(['name' => '2027 / 2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30']);
        $newTerm = $this->term($second, ['starts_on' => '2027-09-01', 'ends_on' => '2027-12-15']);

        $this->actingAs($this->registrar())
            ->post(route('settings.years.current', $second))
            ->assertRedirect();

        // Leaving last year's term current would file new attendance and new
        // marks against a year the school has already left.
        $this->assertFalse($oldTerm->fresh()->is_current);
        $this->assertTrue($newTerm->fresh()->is_current);
    }

    public function test_a_year_holding_records_cannot_be_deleted(): void
    {
        $year = $this->year();

        $this->term($year);

        \App\Models\FeeStructure::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'name' => 'Grade 7 — First Term',
            'is_active' => true,
        ]);

        $this->actingAs($this->registrar())
            ->delete(route('settings.years.destroy', $year))
            ->assertSessionHasErrors('year');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_the_current_year_cannot_be_deleted(): void
    {
        $year = $this->year(['is_current' => true]);

        $this->actingAs($this->registrar())
            ->delete(route('settings.years.destroy', $year))
            ->assertSessionHasErrors('year');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_an_empty_year_can_be_deleted(): void
    {
        $year = $this->year();

        $this->actingAs($this->registrar())
            ->delete(route('settings.years.destroy', $year))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
    }

    /* --------------------------------------------------------------- terms */

    public function test_a_term_can_be_added_to_a_year(): void
    {
        $year = $this->year(['is_current' => true]);

        $this->actingAs($this->registrar())
            ->post(route('settings.terms.store', $year), [
                'name' => 'First Term',
                'sequence' => 1,
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('terms', [
            'academic_year_id' => $year->id,
            'name' => 'First Term',
            'is_current' => true,
        ]);
    }

    public function test_a_term_must_fall_inside_its_year(): void
    {
        $year = $this->year();

        $this->actingAs($this->registrar())
            ->post(route('settings.terms.store', $year), [
                'name' => 'Stray Term',
                'sequence' => 1,
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-12-15',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(0, Term::count());
    }

    public function test_terms_in_the_same_year_may_not_overlap(): void
    {
        $year = $this->year();

        $this->term($year);

        $this->actingAs($this->registrar())
            ->post(route('settings.terms.store', $year), [
                'name' => 'Second Term',
                'sequence' => 2,
                'starts_on' => '2026-12-01',
                'ends_on' => '2027-03-15',
            ])
            ->assertSessionHasErrors('starts_on');
    }

    public function test_two_terms_may_not_share_a_position(): void
    {
        $year = $this->year();

        $this->term($year);

        $this->actingAs($this->registrar())
            ->post(route('settings.terms.store', $year), [
                'name' => 'Second Term',
                'sequence' => 1,
                'starts_on' => '2027-01-05',
                'ends_on' => '2027-03-15',
            ])
            ->assertSessionHasErrors('sequence');
    }

    public function test_narrowing_a_year_that_would_orphan_a_term_is_refused(): void
    {
        $year = $this->year();

        $this->term($year);

        $this->actingAs($this->registrar())
            ->put(route('settings.years.update', $year), [
                'name' => '2026 / 2027',
                'starts_on' => '2026-10-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertSame('2026-09-01', $year->fresh()->starts_on->toDateString());
    }

    public function test_a_term_outside_the_current_year_cannot_be_made_current(): void
    {
        $current = $this->year(['is_current' => true]);
        $this->term($current, ['is_current' => true]);

        $other = $this->year(['name' => '2027 / 2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30']);
        $otherTerm = $this->term($other, ['starts_on' => '2027-09-01', 'ends_on' => '2027-12-15']);

        $this->actingAs($this->registrar())
            ->post(route('settings.terms.current', $otherTerm))
            ->assertSessionHasErrors('term');

        $this->assertFalse($otherTerm->fresh()->is_current);
    }

    /* ------------------------------------------------------- authorisation */

    public function test_managing_the_calendar_needs_the_academics_permission(): void
    {
        $user = $this->userFor($this->school, ['dashboard.view']);

        $this->actingAs($user)->get(route('settings.years.index'))->assertForbidden();

        $this->actingAs($user)
            ->post(route('settings.years.store'), [
                'name' => 'Sneaky', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
            ])
            ->assertForbidden();
    }

    public function test_a_year_from_another_school_cannot_be_touched(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = AcademicYear::create([
            'school_id' => $other->id,
            'name' => 'Their Year',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ]);

        // Route model binding is already tenant-scoped, so this never resolves;
        // the assertion is that it fails closed rather than falling through.
        $this->actingAs($this->registrar())
            ->post(route('settings.years.current', $foreign))
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_current);
    }
}
