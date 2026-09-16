<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Services\DocumentCode;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printed documents and the public check behind their QR codes.
 *
 * Two things are being protected here. A receipt, a letter or a grade sheet
 * has to be checkable by someone with no account - otherwise the code is
 * decoration - and the check has to give away nothing the person holding the
 * paper does not already have. Both halves are asserted.
 */
class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Student $student;

    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool(['domain' => 'verify-test.test']);

        app(SchoolContext::class)->setSchool($this->school);

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
        ]);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);
    }

    /** The host has to resolve to a school, exactly as a scanned code would. */
    protected function scan(string $type, string $reference)
    {
        return $this->get('http://verify-test.test/verify/'.$type.'/'.rawurlencode($reference));
    }

    protected function payment(): Payment
    {
        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'invoice_number' => 'INV-2026-00001',
            'issued_on' => now()->subDays(5),
            'due_on' => now()->addDays(5),
            'total_minor' => 500000,
            'status' => 'issued',
        ]);

        return Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'student_id' => $this->student->id,
            'receipt_number' => 'RCP-2026-00001',
            'amount_minor' => 250000,
            'method' => 'Cash',
            'paid_on' => now()->toDateString(),
        ]);
    }

    public function test_a_receipt_can_be_verified_by_anyone_holding_it(): void
    {
        $payment = $this->payment();

        $this->scan('receipt', $payment->receipt_number)
            ->assertOk()
            ->assertSee('RCP-2026-00001')
            ->assertSee('Mary D.');
    }

    /**
     * The privacy half. Guessing a reference must not become a way to read a
     * child's full name off the school's records.
     */
    public function test_verification_never_reveals_a_full_name(): void
    {
        $payment = $this->payment();

        $this->scan('receipt', $payment->receipt_number)
            ->assertOk()
            ->assertDontSee('Mary Doe');
    }

    public function test_an_unknown_reference_reports_nothing_found_rather_than_erroring(): void
    {
        $this->scan('receipt', 'RCP-2026-99999')->assertOk();
    }

    public function test_an_unknown_document_type_is_not_found(): void
    {
        $this->scan('passport', 'anything')->assertNotFound();
    }

    /** A code on paper is worthless if reading it needs an account. */
    public function test_the_check_needs_no_account(): void
    {
        $payment = $this->payment();

        $this->assertGuest();

        $this->scan('receipt', $payment->receipt_number)->assertOk();
    }

    public function test_a_receipt_prints_with_its_code_and_nothing_else(): void
    {
        $payment = $this->payment();

        $user = $this->userFor($this->school, ['payments.view']);

        $this->actingAs($user)->get(route('payments.receipt', $payment))
            ->assertOk()
            // The print stylesheet shows only what is inside .printable.
            ->assertSee('printable', false)
            ->assertSee('<svg', false);
    }

    /* ------------------------------------------------------------------ */
    /* Admission letters                                                   */
    /* ------------------------------------------------------------------ */

    protected function admission(string $status): Admission
    {
        return Admission::create([
            'school_id' => $this->school->id,
            'application_number' => 'APP-2026-0007',
            'status' => $status,
            'student_first_name' => 'Mary',
            'student_last_name' => 'Doe',
            'intended_class' => 'Grade 10',
            'academic_year' => '2026/2027',
            'guardian_name' => 'John Doe',
            'guardian_phone' => '+231770000000',
        ]);
    }

    public function test_an_approved_application_produces_a_printable_letter(): void
    {
        $admission = $this->admission('approved');

        $user = $this->userFor($this->school, ['admissions.view']);

        $this->actingAs($user)->get(route('admissions.letter', $admission))
            ->assertOk()
            ->assertSee('APP-2026-0007')
            ->assertSee('Grade 10')
            ->assertSee('printable', false)
            ->assertSee('<svg', false);
    }

    /**
     * The one that matters: a letter is proof of a place, so it must not exist
     * for an application that has not been given one.
     */
    public function test_no_letter_exists_before_a_place_is_offered(): void
    {
        $user = $this->userFor($this->school, ['admissions.view']);

        foreach (['pending', 'under_review', 'rejected', 'cancelled'] as $status) {
            $admission = $this->admission($status);

            $this->actingAs($user)->get(route('admissions.letter', $admission))
                ->assertNotFound();

            $admission->delete();
        }
    }

    public function test_reading_an_application_is_required_to_print_its_letter(): void
    {
        $admission = $this->admission('approved');

        $this->actingAs($this->userFor($this->school, []))
            ->get(route('admissions.letter', $admission))
            ->assertForbidden();
    }

    public function test_an_admission_letter_can_be_verified(): void
    {
        $this->admission('approved');

        $this->scan('admission', 'APP-2026-0007')
            ->assertOk()
            ->assertSee('APP-2026-0007')
            ->assertSee('Mary D.')
            ->assertDontSee('Mary Doe');
    }

    /* ------------------------------------------------------------------ */
    /* Report cards                                                        */
    /* ------------------------------------------------------------------ */

    protected function reportCard(string $status): ReportCard
    {
        return ReportCard::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'average' => 78.5,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    public function test_a_published_report_card_carries_a_verification_code(): void
    {
        $card = $this->reportCard('published');

        $user = $this->userFor($this->school, ['reportcards.view']);

        $this->actingAs($user)->get(route('reportcards.show', $card))
            ->assertOk()
            ->assertSee('printable', false)
            ->assertSee('<svg', false);
    }

    /**
     * A draft can still change and the family has not been given it. Printing
     * a code on one would promise a check the school cannot honour.
     */
    public function test_a_draft_report_card_carries_no_code(): void
    {
        $card = $this->reportCard('draft');

        $user = $this->userFor($this->school, ['reportcards.view', 'reportcards.generate']);

        $this->actingAs($user)->get(route('reportcards.show', $card))
            ->assertOk()
            ->assertDontSee('Scan to verify this report card');
    }

    public function test_verifying_a_report_card_shows_no_marks(): void
    {
        $card = $this->reportCard('published');

        $this->scan('report-card', (string) $card->getKey())
            ->assertOk()
            ->assertSee('Mary D.')
            ->assertSee('Marks are not shown');
    }

    public function test_a_draft_report_card_cannot_be_verified(): void
    {
        $card = $this->reportCard('draft');

        // Not an error - simply not a document this school has issued.
        $this->scan('report-card', (string) $card->getKey())
            ->assertOk()
            ->assertDontSee('Mary D.');
    }

    /* ------------------------------------------------------------------ */
    /* The generator itself                                                */
    /* ------------------------------------------------------------------ */

    public function test_the_code_encodes_an_absolute_url_so_a_phone_can_follow_it(): void
    {
        $url = app(DocumentCode::class)->verifyUrl('receipt', 'RCP-2026-00001');

        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('/verify/receipt/RCP-2026-00001', $url);
    }

    /**
     * The SVG is dropped into an HTML document, where an XML declaration part
     * way down the page is invalid.
     */
    public function test_the_svg_carries_no_xml_declaration(): void
    {
        $svg = app(DocumentCode::class)->svg('https://example.test/verify');

        $this->assertStringStartsWith('<svg', trim((string) $svg));
    }

    /** A blank reference should yield no code rather than an unscannable box. */
    public function test_an_empty_value_produces_no_code(): void
    {
        $this->assertNull(app(DocumentCode::class)->svg('   '));
    }
}
