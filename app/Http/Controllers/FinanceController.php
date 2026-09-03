<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function invoices(Request $request): View
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
        ];

        $invoices = Invoice::query()
            ->with('student:id,first_name,last_name,student_number')
            ->when($filters['search'], fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($s) => $s
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%"))))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->latest('issued_on')
            ->paginate(20)
            ->withQueryString();

        return view('finance.invoices', [
            'invoices' => $invoices,
            'filters' => $filters,
            'statuses' => Invoice::STATUSES,
            'totalMinor' => (int) Invoice::sum('total_minor'),
            'collectedMinor' => (int) Payment::sum('amount_minor'),
            'outstandingMinor' => Invoice::outstanding()->get()->sum(fn (Invoice $i) => $i->balanceMinor()),
        ]);
    }

    public function payments(Request $request): View
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);

        return view('finance.payments', [
            'payments' => Payment::with(['student:id,first_name,last_name,student_number', 'receivedBy:id,name'])
                ->latest('paid_on')
                ->paginate(20),
            'collectedMinor' => (int) Payment::sum('amount_minor'),
            'thisMonthMinor' => (int) Payment::whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_minor'),
        ]);
    }

    public function createPayment(Request $request): View
    {
        abort_unless($request->user()->hasPermission('payments.record'), 403);

        return view('finance.record-payment', [
            'invoices' => Invoice::outstanding()
                ->with('student:id,first_name,last_name,student_number')
                ->latest('issued_on')
                ->get(),
            'methods' => Payment::METHODS,
        ]);
    }

    public function storePayment(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.record'), 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Resolved through the scoped query: an invoice from another school
        // simply does not exist here.
        $invoice = Invoice::findOrFail($data['invoice_id']);

        $amountMinor = Money::toMinor($data['amount']);

        if ($amountMinor > $invoice->balanceMinor()) {
            return back()
                ->withErrors(['amount' => 'That is more than the outstanding balance of '.Money::format($invoice->balanceMinor()).'.'])
                ->withInput();
        }

        $payment = DB::transaction(function () use ($invoice, $amountMinor, $data, $request) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'guardian_id' => $invoice->student?->guardians()->value('guardians.id'),
                'receipt_number' => $this->nextReceiptNumber(),
                'amount_minor' => $amountMinor,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_on' => $data['paid_on'],
                'received_by' => $request->user()->id,
                'note' => $data['note'] ?? null,
            ]);

            $invoice->refreshTotals();

            return $payment;
        });

        $audit->log('payment_recorded', 'Finance',
            'Payment of '.Money::format($amountMinor).' recorded against invoice '.$invoice->invoice_number.'.',
            $payment);

        return redirect()
            ->route('payments.receipt', $payment)
            ->with('status', 'Payment recorded. Receipt '.$payment->receipt_number.' is ready.');
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        abort_unless($payment->school_id === $request->user()->school_id || $request->user()->isSuperAdministrator(), 403);

        $payment->load(['student', 'guardian', 'invoice', 'receivedBy']);

        return view('finance.receipt', [
            'payment' => $payment,
            'school' => $request->user()->school,
        ]);
    }

    protected function nextReceiptNumber(): string
    {
        $prefix = 'RCP-'.now()->year.'-';

        $highest = Payment::where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = $highest ? ((int) substr($highest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
