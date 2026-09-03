<?php

namespace App\Actions;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

        DB::transaction(function () use ($structure, $students, $dueOn, &$created, &$skipped) {
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

                $invoice = Invoice::create([
                    'school_id' => $structure->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $structure->academic_year_id,
                    'term_id' => $structure->term_id,
                    'invoice_number' => $this->nextInvoiceNumber($structure),
                    'issued_on' => now()->toDateString(),
                    'due_on' => $dueOn,
                    'total_minor' => $total,
                    'status' => 'issued',
                    'note' => $structure->name,
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
