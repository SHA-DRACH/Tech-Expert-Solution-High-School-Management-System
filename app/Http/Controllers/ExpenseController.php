<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expense recording (spec section 39), and the income-against-spend picture
 * that makes the finance module useful rather than one-sided.
 */
class ExpenseController extends Controller
{
    public const CATEGORIES = [
        'Salaries', 'Utilities', 'Maintenance', 'Teaching materials',
        'Examinations', 'Transport', 'Furniture', 'Administration', 'Other',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('expenses.manage'), 403);

        $filters = [
            'category' => $request->string('category')->trim()->toString(),
            'from' => $request->date('from'),
            'to' => $request->date('to'),
        ];

        $query = Expense::query()
            ->with('recordedBy:id,name')
            ->when($filters['category'], fn ($q, $category) => $q->where('category', $category))
            ->when($filters['from'], fn ($q, $from) => $q->where('spent_on', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->where('spent_on', '<=', $to));

        $spentMinor = (int) (clone $query)->sum('amount_minor');
        $collectedMinor = (int) Payment::sum('amount_minor');

        return view('finance.expenses', [
            'expenses' => $query->latest('spent_on')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'categories' => self::CATEGORIES,
            'spentMinor' => $spentMinor,
            'collectedMinor' => $collectedMinor,
            'balanceMinor' => $collectedMinor - $spentMinor,
            'byCategory' => Expense::selectRaw('category, SUM(amount_minor) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->pluck('total', 'category'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('expenses.manage'), 403);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:80'],
        ], [
            'spent_on.before_or_equal' => 'An expense cannot be dated in the future.',
        ]);

        $expense = Expense::create([
            'academic_year_id' => AcademicYear::active()?->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'amount_minor' => Money::toMinor($data['amount']),
            'spent_on' => $data['spent_on'],
            'reference' => $data['reference'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $audit->log('created', 'Finance',
            'Expense of '.Money::format($expense->amount_minor).' recorded for '.$expense->category.'.',
            $expense);

        return back()->with('status', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('expenses.manage'), 403);
        abort_unless($expense->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:80'],
        ], [
            'spent_on.before_or_equal' => 'An expense cannot be dated in the future.',
        ]);

        $before = [
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => Money::format($expense->amount_minor),
            'spent_on' => $expense->spent_on?->toDateString(),
        ];

        $expense->update([
            'category' => $data['category'],
            'description' => $data['description'],
            'amount_minor' => Money::toMinor($data['amount']),
            'spent_on' => $data['spent_on'],
            'reference' => $data['reference'] ?? null,
        ]);

        /*
         | The old and new amount both go into the audit trail. An expense
         | figure that changes after the fact is exactly what an auditor asks
         | about, and "it says 4,000 now" is not an answer without the before.
         */
        $audit->log(
            'updated',
            'Finance',
            'Expense "'.$expense->description.'" was changed from '.$before['amount'].' to '.Money::format($expense->amount_minor).'.',
            $expense,
            $before,
            ['amount' => Money::format($expense->amount_minor)] + collect($data)->except('amount')->all(),
        );

        return back()->with('status', 'Expense updated. The change is recorded in the audit trail.');
    }

    public function destroy(Request $request, Expense $expense, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('expenses.manage'), 403);
        abort_unless($expense->school_id === $request->user()->school_id, 403);

        $description = $expense->description;
        $amount = Money::format($expense->amount_minor);

        $expense->delete();

        $audit->log('deleted', 'Finance', "Expense \"{$description}\" of {$amount} was removed.");

        return back()->with('status', 'Expense removed.');
    }
}
