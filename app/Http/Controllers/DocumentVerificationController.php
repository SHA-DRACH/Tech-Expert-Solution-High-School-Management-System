<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What a scanned QR code lands on.
 *
 * A printed receipt is only a claim that money was paid. This is what turns it
 * into something checkable: scan the code and the school's own records either
 * confirm the document or they do not.
 *
 * Deliberately **public** — the person checking a receipt is often a parent
 * standing at a counter, or a bursar at another institution reading an
 * admission letter, and neither has an account here. That makes what it shows
 * the whole design problem, and the rule is: enough to confirm the document in
 * your hand is genuine, and nothing you could not already read off it.
 *
 * So: the reference, the date, the amount, and a first name with an initial.
 * Never a full name, an address, a phone number, a subject mark or a balance.
 * Someone who guesses a reference learns nothing they did not have; someone
 * holding the paper can confirm every line on it.
 */
class DocumentVerificationController extends Controller
{
    /** The document kinds a code may point at. */
    public const TYPES = ['receipt', 'admission', 'report-card', 'student-record', 'grade-sheet', 'progress-report'];

    public function __invoke(Request $request, string $type, string $reference, SchoolContext $context): View
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $school = $context->school();

        abort_unless($school !== null, 404);

        return view('documents.verify', [
            'school' => $school,
            'type' => $type,
            'reference' => $reference,
            'document' => match ($type) {
                'receipt' => $this->receipt($reference),
                'admission' => $this->admission($reference),
                'report-card' => $this->reportCard($reference),
                'student-record' => $this->studentRecord($reference),
                'grade-sheet' => $this->progressDocument($reference, 'Grade sheet', period: true),
                'progress-report' => $this->progressDocument($reference, 'Periodic progress report', period: false),
            },
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function receipt(string $reference): ?array
    {
        $payment = Payment::where('receipt_number', $reference)->with('student')->first();

        if ($payment === null) {
            return null;
        }

        return [
            'heading' => 'Payment receipt',
            'rows' => [
                'Receipt number' => $payment->receipt_number,
                'Student' => $this->maskName($payment->student?->first_name, $payment->student?->last_name),
                'Amount' => Money::format($payment->amount_minor),
                'Paid on' => $payment->paid_on?->format('j F Y'),
                'Method' => $payment->method,
            ],
        ];
    }

    protected function admission(string $reference): ?array
    {
        $admission = Admission::where('application_number', $reference)->first();

        if ($admission === null) {
            return null;
        }

        return [
            'heading' => 'Admission application',
            'rows' => [
                'Application number' => $admission->application_number,
                'Applicant' => $this->maskName($admission->student_first_name, $admission->student_last_name),
                'Status' => \Illuminate\Support\Str::headline($admission->status),
                'Applied on' => $admission->created_at?->format('j F Y'),
            ],
        ];
    }

    protected function reportCard(string $reference): ?array
    {
        // The reference is the card's own id; there is no separate number.
        $card = ReportCard::published()
            ->whereKey((int) $reference)
            ->with(['student', 'term', 'academicYear'])
            ->first();

        if ($card === null) {
            return null;
        }

        return [
            'heading' => 'Report card',
            'rows' => [
                'Student' => $this->maskName($card->student?->first_name, $card->student?->last_name),
                'Period' => ($card->term?->name ?? 'Full year').' · '.$card->academicYear?->name,
                'Issued' => $card->published_at?->format('j F Y'),
            ],
            /*
             | No marks, no average, no position. Confirming a card is genuine
             | does not require republishing a child's results to whoever
             | scanned the code.
             */
            'note' => 'Marks are not shown here. Sign in to the parent or student portal to see results.',
        ];
    }

    /**
     * A student record sheet, checked by its student number.
     *
     * Deliberately the thinnest of the four. A record sheet is handed to
     * another school or an employer, and what they need confirmed is that this
     * number belongs to a real student here and whether that student is still
     * on the roll. Class, guardians, results and address are all on the paper
     * they are holding; none of them belong to whoever guesses a number.
     */
    protected function studentRecord(string $reference): ?array
    {
        $student = \App\Models\Student::where('student_number', $reference)->first();

        if ($student === null) {
            return null;
        }

        return [
            'heading' => 'Student record',
            'rows' => [
                'Student number' => $student->student_number,
                'Student' => $this->maskName($student->first_name, $student->last_name),
                'Status' => \Illuminate\Support\Str::headline($student->status),
            ],
            'note' => 'Only enrolment status is confirmed here. Class, results and contact details are not shown.',
        ];
    }

    /**
     * A grade sheet or progress report, referenced as "<student number>.<id>".
     *
     * Confirms the student and the period or year the paper is for - never a
     * mark. Before the last dot is the student; after it, the period (grade
     * sheet) or the academic year (progress report).
     */
    protected function progressDocument(string $reference, string $heading, bool $period): ?array
    {
        $dot = strrpos($reference, '.');

        if ($dot === false) {
            return null;
        }

        $student = \App\Models\Student::where('student_number', substr($reference, 0, $dot))->first();
        $id = (int) substr($reference, $dot + 1);

        $when = $period
            ? \App\Models\Term::whereNotNull('semester')->with('academicYear')->find($id)
            : \App\Models\AcademicYear::find($id);

        if ($student === null || $when === null) {
            return null;
        }

        return [
            'heading' => $heading,
            'rows' => [
                'Student number' => $student->student_number,
                'Student' => $this->maskName($student->first_name, $student->last_name),
                ($period ? 'Period' : 'Academic year') => $period
                    ? ucfirst($when->label()).' · '.$when->academicYear?->name
                    : $when->name,
            ],
            'note' => 'Marks are not shown here. Sign in to the parent or student portal to see results.',
        ];
    }

    /** "Mary D." — enough to match the paper, not enough to identify a child. */
    protected function maskName(?string $first, ?string $last): string
    {
        $first = trim((string) $first);
        $last = trim((string) $last);

        if ($first === '' && $last === '') {
            return '—';
        }

        return trim($first.' '.($last !== '' ? \Illuminate\Support\Str::upper($last[0]).'.' : ''));
    }
}
