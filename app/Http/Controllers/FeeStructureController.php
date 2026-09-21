<?php

namespace App\Http\Controllers;

use App\Actions\RaiseInvoices;
use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fee structures (spec section 39).
 *
 * A structure is the price list for a class and term. Raising invoices from it
 * is a separate, deliberate step so a school can build and review the list
 * before charging any family.
 */
class FeeStructureController extends Controller
{
    /** A PDF fee schedule: private storage, 10 MB, PDF only. */
    protected const DOCUMENT_RULES = [
        'document' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        'document_on_website' => ['nullable', 'boolean'],
    ];

    protected const DOCUMENT_MESSAGES = [
        'document.mimes' => 'The fee document must be a PDF.',
        'document.mimetypes' => 'The fee document must be a PDF.',
        'document.max' => 'The fee document must be 10 MB or smaller.',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('fees.manage'), 403);

        return view('finance.fee-structures', [
            'structures' => FeeStructure::with(['items', 'schoolClass'])
                ->orderBy('name')
                ->get(),
            'classes' => SchoolClass::orderBy('level')->get(),
            'terms' => Term::orderBy('sequence')->get(),
            'categories' => FeeItem::CATEGORIES,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('fees.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'school_class_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.category' => ['required', Rule::in(FeeItem::CATEGORIES)],
            'items.*.description' => ['nullable', 'string', 'max:180'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            ...self::DOCUMENT_RULES,
        ], [
            'items.required' => 'Add at least one fee line.',
            ...self::DOCUMENT_MESSAGES,
        ]);

        $year = AcademicYear::active();

        abort_unless($year !== null, 422, 'Set up an academic year before creating fee structures.');

        // Both re-resolved through the tenant-scoped query.
        $class = isset($data['school_class_id']) ? SchoolClass::findOrFail($data['school_class_id']) : null;
        $term = isset($data['term_id']) ? Term::findOrFail($data['term_id']) : null;

        $structure = DB::transaction(function () use ($data, $year, $class, $term) {
            $structure = FeeStructure::create([
                'academic_year_id' => $year->id,
                'school_class_id' => $class?->id,
                'term_id' => $term?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            foreach ($data['items'] as $item) {
                FeeItem::create([
                    'school_id' => $structure->school_id,
                    'fee_structure_id' => $structure->id,
                    'category' => $item['category'],
                    'description' => $item['description'] ?? null,
                    'amount_minor' => Money::toMinor($item['amount']),
                ]);
            }

            return $structure;
        });

        $this->saveDocument($request, $structure);

        $audit->log('created', 'Finance',
            "Fee structure \"{$structure->name}\" was created, totalling ".Money::format($structure->totalMinor()).'.',
            $structure);

        return back()->with('status', 'Fee structure created.');
    }

    public function update(Request $request, FeeStructure $feeStructure, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('fees.manage'), 403);
        abort_unless($feeStructure->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.category' => ['required', Rule::in(FeeItem::CATEGORIES)],
            'items.*.description' => ['nullable', 'string', 'max:180'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            ...self::DOCUMENT_RULES,
            'remove_document' => ['nullable', 'boolean'],
        ], self::DOCUMENT_MESSAGES);

        $before = Money::format($feeStructure->totalMinor());

        $this->saveDocument($request, $feeStructure);

        DB::transaction(function () use ($feeStructure, $data, $request) {
            $feeStructure->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            // Rewritten wholesale: the form always posts the complete list.
            $feeStructure->items()->delete();

            foreach ($data['items'] as $item) {
                FeeItem::create([
                    'school_id' => $feeStructure->school_id,
                    'fee_structure_id' => $feeStructure->id,
                    'category' => $item['category'],
                    'description' => $item['description'] ?? null,
                    'amount_minor' => Money::toMinor($item['amount']),
                ]);
            }
        });

        $audit->log('updated', 'Finance',
            "Fee structure \"{$feeStructure->name}\" was changed.", $feeStructure,
            ['total' => $before], ['total' => Money::format($feeStructure->fresh()->totalMinor())]);

        return back()->with('status', 'Fee structure updated.');
    }

    public function destroy(Request $request, FeeStructure $feeStructure, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('fees.manage'), 403);
        abort_unless($feeStructure->school_id === $request->user()->school_id, 403);

        $name = $feeStructure->name;

        $feeStructure->items()->delete();

        if ($feeStructure->document_path) {
            Storage::disk('local')->delete($feeStructure->document_path);
        }

        $feeStructure->delete();

        $audit->log('deleted', 'Finance', "Fee structure \"{$name}\" was deleted.");

        return back()->with('status', 'Fee structure deleted.');
    }

    /**
     * Attach, replace or remove the structure's PDF, and whether it is public.
     *
     * The file goes to the private disk: it is only ever handed out through
     * FeeDocumentController, which checks who is asking.
     */
    protected function saveDocument(Request $request, FeeStructure $structure): void
    {
        $attributes = ['document_on_website' => $request->boolean('document_on_website')];

        if ($request->boolean('remove_document') && $structure->document_path) {
            Storage::disk('local')->delete($structure->document_path);
            $attributes += ['document_path' => null, 'document_name' => null, 'document_on_website' => false];
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $previous = $structure->document_path;

            $attributes['document_path'] = $file->store("schools/{$structure->school_id}/fee-documents", 'local');
            $attributes['document_name'] = Str::limit($file->getClientOriginalName(), 180, '');

            if ($previous) {
                Storage::disk('local')->delete($previous);
            }
        }

        $structure->update($attributes);
    }

    /** Raise an invoice per student from a structure. */
    public function raiseInvoices(Request $request, RaiseInvoices $action, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('invoices.manage'), 403);

        $data = $request->validate([
            'fee_structure_id' => ['required', 'integer'],
            'due_on' => ['nullable', 'date'],
        ]);

        $structure = FeeStructure::with('items')->findOrFail($data['fee_structure_id']);

        if ($structure->items->isEmpty()) {
            return back()->withErrors(['fee_structure_id' => 'That structure has no fee lines yet.']);
        }

        $result = $action->handle($structure, $data['due_on'] ?? null);

        if ($result['created'] === 0) {
            return back()->withErrors([
                'fee_structure_id' => $result['skipped'] > 0
                    ? 'Every student in that class already has an invoice for this period.'
                    : 'No students are enrolled in that class yet.',
            ]);
        }

        $audit->log('invoices_raised', 'Finance',
            "{$result['created']} invoices were raised from \"{$structure->name}\".", $structure);

        $message = "{$result['created']} invoices raised.";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} students already had one and were skipped.";
        }

        return back()->with('status', $message);
    }
}
