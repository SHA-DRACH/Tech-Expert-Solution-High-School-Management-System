<?php

namespace Tests\Feature;

use App\Actions\RaiseInvoices;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Scholarship;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scholarships: who may award one, and what it does to a bill.
 *
 * The arithmetic is tested as hard as the permissions, because the failure
 * mode is not a 500 - it is a family being asked for money the school told
 * them they did not owe.
 */
class ScholarshipTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Student $student;

    protected AcademicYear $year;

    protected Term $term;

    protected SchoolClass $class;

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

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9,
        ]);

        Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'A',
        ]);

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);
    }

    protected function award(array $overrides = []): Scholarship
    {
        return Scholarship::create(array_merge([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'name' => 'Principal merit award',
            'type' => 'percentage',
            'percentage' => 50,
            'status' => 'active',
        ], $overrides));
    }

    /** A fee structure covering the whole school, worth L$20,000. */
    protected function structure(array $overrides = []): FeeStructure
    {
        $structure = FeeStructure::create(array_merge([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'name' => 'Term fees',
            'is_active' => true,
        ], $overrides));

        FeeItem::create([
            'school_id' => $this->school->id,
            'fee_structure_id' => $structure->id,
            'category' => 'Tuition',
            'description' => 'Tuition',
            'amount_minor' => Money::toMinor(20000),
        ]);

        return $structure->fresh('items');
    }

    /* ------------------------------------------------------------------ */
    /* The arithmetic                                                      */
    /* ------------------------------------------------------------------ */

    public function test_a_percentage_award_takes_that_share_off_the_bill(): void
    {
        $award = $this->award(['percentage' => 25]);

        $this->assertSame(Money::toMinor(5000), $award->discountOn(Money::toMinor(20000)));
    }

    public function test_a_fixed_award_takes_its_amount_off_the_bill(): void
    {
        $award = $this->award([
            'type' => 'amount', 'percentage' => null, 'amount_minor' => Money::toMinor(7500),
        ]);

        $this->assertSame(Money::toMinor(7500), $award->discountOn(Money::toMinor(20000)));
    }

    /**
     * An award worth more than the fee must not produce a negative balance -
     * that reads on screen as the school owing the family money.
     */
    public function test_an_award_never_exceeds_the_bill(): void
    {
        $award = $this->award([
            'type' => 'amount', 'percentage' => null, 'amount_minor' => Money::toMinor(50000),
        ]);

        $this->assertSame(Money::toMinor(20000), $award->discountOn(Money::toMinor(20000)));
    }

    public function test_a_full_waiver_leaves_nothing_owing(): void
    {
        $this->award(['percentage' => 100]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, $invoice->balanceMinor());
        $this->assertSame('paid', $invoice->status);
    }

    /* ------------------------------------------------------------------ */
    /* When an award applies                                               */
    /* ------------------------------------------------------------------ */

    public function test_an_award_reaches_the_invoice_when_fees_are_raised(): void
    {
        $this->award(['percentage' => 50]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(Money::toMinor(20000), $invoice->total_minor);
        $this->assertSame(Money::toMinor(10000), $invoice->discount_minor);
        $this->assertSame(Money::toMinor(10000), $invoice->balanceMinor());
    }

    /** "Why is this bill smaller?" has to be answerable from the bill. */
    public function test_the_invoice_names_the_award_that_discounted_it(): void
    {
        $this->award(['name' => 'Ministry bursary']);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertStringContainsString('Ministry bursary', $invoice->note);
    }

    public function test_a_suspended_award_does_not_discount_new_fees(): void
    {
        $this->award(['status' => 'suspended']);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, (int) $invoice->discount_minor);
    }

    public function test_an_award_for_another_year_is_not_applied(): void
    {
        $other = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2025 / 2026',
            'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30',
        ]);

        $this->award(['academic_year_id' => $other->id]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, (int) $invoice->discount_minor);
    }

    public function test_an_award_naming_no_year_applies_to_any_year(): void
    {
        $this->award(['academic_year_id' => null, 'percentage' => 10]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(Money::toMinor(2000), (int) $invoice->discount_minor);
    }

    public function test_an_award_that_has_not_started_is_not_applied(): void
    {
        $this->award(['starts_on' => now()->addMonth()->toDateString()]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, (int) $invoice->discount_minor);
    }

    public function test_an_expired_award_is_not_applied(): void
    {
        $this->award([
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, (int) $invoice->discount_minor);
    }

    /** Two awards on one child is ordinary; they add up, but not past the bill. */
    public function test_two_awards_stack_but_never_past_the_bill(): void
    {
        $this->award(['name' => 'Bursary', 'percentage' => 70]);
        $this->award(['name' => 'Staff child', 'percentage' => 70]);

        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(Money::toMinor(20000), (int) $invoice->discount_minor);
        $this->assertSame(0, $invoice->balanceMinor());
    }

    public function test_a_student_with_no_award_is_billed_in_full(): void
    {
        app(RaiseInvoices::class)->handle($this->structure());

        $invoice = Invoice::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame(0, (int) $invoice->discount_minor);
        $this->assertSame(Money::toMinor(20000), $invoice->balanceMinor());
    }

    /* ------------------------------------------------------------------ */
    /* The screens                                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_list_shows_the_student_the_class_and_the_award(): void
    {
        $this->award();

        $user = $this->userFor($this->school, ['scholarships.view']);

        $this->actingAs($user)->get(route('scholarships.index'))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertSee('Principal merit award')
            ->assertSee('50%');
    }

    public function test_viewing_scholarships_needs_the_view_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('scholarships.index'))
            ->assertForbidden();
    }

    public function test_awarding_needs_the_manage_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['scholarships.view']))
            ->get(route('scholarships.create'))
            ->assertForbidden();

        $this->actingAs($this->userFor($this->school, ['scholarships.view']))
            ->post(route('scholarships.store'), [
                'student_id' => $this->student->id,
                'name' => 'Sneaked in',
                'type' => 'percentage',
                'percentage' => 100,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('scholarships', ['name' => 'Sneaked in']);
    }

    public function test_an_award_can_be_recorded_from_the_form(): void
    {
        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->post(route('scholarships.store'), [
            'student_id' => $this->student->id,
            'name' => 'Ministry bursary',
            'sponsor' => 'Ministry of Education',
            'type' => 'amount',
            'amount' => 7500,
            'status' => 'active',
        ])->assertRedirect(route('scholarships.index'));

        $this->assertDatabaseHas('scholarships', [
            'student_id' => $this->student->id,
            'name' => 'Ministry bursary',
            'type' => 'amount',
            'amount_minor' => Money::toMinor(7500),
            'awarded_by' => $user->id,
        ]);
    }

    /** A percentage award needs a percentage; asking for one it cannot use is worse. */
    public function test_the_award_figure_is_required_for_the_kind_of_award_chosen(): void
    {
        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->post(route('scholarships.store'), [
            'student_id' => $this->student->id,
            'name' => 'No figure',
            'type' => 'percentage',
            'status' => 'active',
        ])->assertSessionHasErrors('percentage');

        $this->actingAs($user)->post(route('scholarships.store'), [
            'student_id' => $this->student->id,
            'name' => 'No figure',
            'type' => 'amount',
            'status' => 'active',
        ])->assertSessionHasErrors('amount');
    }

    /**
     * Switching the kind of award must not leave the old figure behind for the
     * next reader - or the next invoice - to pick up.
     */
    public function test_switching_award_kind_clears_the_figure_that_no_longer_applies(): void
    {
        $award = $this->award(['type' => 'percentage', 'percentage' => 50]);

        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->put(route('scholarships.update', $award), [
            'student_id' => $this->student->id,
            'name' => $award->name,
            'type' => 'amount',
            'amount' => 3000,
            'status' => 'active',
        ])->assertRedirect(route('scholarships.index'));

        $award->refresh();

        $this->assertNull($award->percentage);
        $this->assertSame(Money::toMinor(3000), (int) $award->amount_minor);
    }

    /**
     * Financial history is not deleted (sections 47 and 71.10). Ending an award
     * stops it applying without erasing why last term's bill was smaller.
     */
    public function test_an_award_is_ended_rather_than_deleted(): void
    {
        $award = $this->award();

        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->patch(route('scholarships.end', $award));

        $award->refresh();

        $this->assertSame('ended', $award->status);
        $this->assertNotNull($award->ends_on);
        $this->assertDatabaseHas('scholarships', ['id' => $award->id]);
    }

    public function test_awarding_is_audited(): void
    {
        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->post(route('scholarships.store'), [
            'student_id' => $this->student->id,
            'name' => 'Audited award',
            'type' => 'percentage',
            'percentage' => 40,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'module' => 'Finance',
            'action' => 'created',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Tenancy                                                             */
    /* ------------------------------------------------------------------ */

    public function test_a_student_from_another_school_cannot_be_awarded(): void
    {
        $other = $this->createSchool();

        $foreign = Student::factory()->create(['school_id' => $other->id]);

        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        // Resolved through the scoped query, so the id simply does not exist.
        $response = $this->actingAs($user)->post(route('scholarships.store'), [
            'student_id' => $foreign->id,
            'name' => 'Cross-tenant award',
            'type' => 'percentage',
            'percentage' => 100,
            'status' => 'active',
        ]);

        $this->assertNotEquals(302, $response->status());
        $this->assertDatabaseMissing('scholarships', ['name' => 'Cross-tenant award']);
    }

    public function test_another_schools_award_cannot_be_opened_or_edited(): void
    {
        $other = $this->createSchool();

        $foreignStudent = Student::factory()->create(['school_id' => $other->id]);

        $foreignAward = Scholarship::withoutGlobalScopes()->create([
            'school_id' => $other->id,
            'student_id' => $foreignStudent->id,
            'name' => 'Their award',
            'type' => 'percentage',
            'percentage' => 50,
            'status' => 'active',
        ]);

        $user = $this->userFor($this->school, ['scholarships.view', 'scholarships.manage']);

        $this->actingAs($user)->get(route('scholarships.edit', $foreignAward))->assertNotFound();

        $this->actingAs($user)->get(route('scholarships.index'))
            ->assertOk()
            ->assertDontSee('Their award');
    }
}
