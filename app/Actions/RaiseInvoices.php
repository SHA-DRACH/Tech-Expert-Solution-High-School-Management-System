<?php

namespace App\Actions;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Scholarship;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\DB;

/**
 * Turns a fee structure into one invoice per enrolled student.
 *
 * Safe to run twice: a student who already has an invoice for the same
 * structure's year and term is skipped rather than charged again.
 */
class RaiseInvoices
{
    /** @return array{created: int, skipped: int} */
    public function handle(FeeStructure $structure, ?string $dueOn = null): array
    {
        $students = $this->studentsFor($structure);

        if ($students->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        /*
         | An invoice with no due date is never chased: the reminder command
         | skips anything with a null due_on. So when the caller does not name
         | one, the school's own payment term decides it rather than leaving the
         | family with a bill that quietly never comes due.
         */
        $dueOn ??= now()->addDays((int) app(SchoolSettings::class)->get('finance_invoice_due_days'))->toDateString();

        $created = 0;
        $skipped = 0;

        /*
         | Awards are loaded once and matched in memory. A scholarship is a
         | standing decision, so it has to be applied at the moment fees are
         | raised - a family told they hold a half-fee award and then handed a
         | full bill has been told two different things by the same school.
         */
        $awards = Scholarship::active()
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        DB::transaction(function () use ($structure, $students, $dueOn, $awards, &$created, &$skipped) {
            $total = $structure->items->sum('amount_minor');

            foreach ($students as $student) {
                $alreadyBilled = Invoice::where('student_id', $student->id)
                    ->where('academic_year_id', $structure->academic_year_id)
                    ->when(
                        $structure->term_id,
                        fn ($query) => $query->where('term_id', $structure->term_id),
                        fn ($query) => $query->whereNull('term_id')
                    )
                    ->exists();

                if ($alreadyBilled) {
                    $skipped++;

                    continue;
                }

                $discount = $this->discountFor(
                    $awards->get($student->id) ?? collect(),
                    $structure,
                    $total
                );

                $invoice = Invoice::create([
                    'school_id' => $structure->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $structure->academic_year_id,
                    'term_id' => $structure->term_id,
                    'invoice_number' => $this->nextInvoiceNumber($structure),
                    'issued_on' => now()->toDateString(),
                    'due_on' => $dueOn,
                    'total_minor' => $total,
                    'discount_minor' => $discount['minor'],
                    'status' => $discount['minor'] >= $total ? 'paid' : 'issued',
                    'note' => trim($structure->name.' '.$discount['note']),
                ]);

                foreach ($structure->items as $item) {
                    InvoiceItem::create([
                        'school_id' => $structure->school_id,
                        'invoice_id' => $invoice->id,
                        'category' => $item->category,
                        'description' => $item->description,
                        'amount_minor' => $item->amount_minor,
                    ]);
                }

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * What a student's awards take off this bill.
     *
     * Several awards can stack - a sponsor's bursary alongside a staff-child
     * discount is ordinary - but never past the value of the bill, or the
     * balance goes negative and reads as the school owing the family money.
     *
     * @param  \Illuminate\Support\Collection<int, Scholarship>  $awards
     * @return array{minor: int, note: string}
     */
    protected function discountFor($awards, FeeStructure $structure, int $total): array
    {
        $applicable = $awards->filter(fn (Scholarship $award) => $award->appliesTo(
            $structure->academic_year_id,
            $structure->term_id,
        ));

        if ($applicable->isEmpty()) {
            return ['minor' => 0, 'note' => ''];
        }

        $discount = min($total, $applicable->sum(fn (Scholarship $award) => $award->discountOn($total)));

        // Named on the invoice, because "why is this bill smaller?" is the
        // first question anyone asks of a discounted one.
        $note = '(scholarship: '.$applicable->pluck('name')->implode(', ').')';

        return ['minor' => (int) $discount, 'note' => $note];
    }

    /**
     * Students the structure applies to: one class, or the whole school when
     * the structure names no class.
     */
    protected function studentsFor(FeeStructure $structure)
    {
        return Student::query()
            ->where('status', 'active')
            ->when($structure->school_class_id, fn ($query) => $query->whereHas(
                'enrollments',
                fn ($inner) => $inner
                    ->where('school_class_id', $structure->school_class_id)
                    ->where('academic_year_id', $structure->academic_year_id)
            ))
            ->get();
    }

    protected function nextInvoiceNumber(FeeStructure $structure): string
    {
        $prefix = 'INV-'.now()->year.'-';

        $highest = Invoice::where('school_id', $structure->school_id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $next = $highest ? ((int) substr($highest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
