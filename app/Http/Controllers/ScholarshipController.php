<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Scholarships and fee waivers.
 *
 * Money the school has decided not to collect is still a financial decision,
 * so every award is audited and none of them are deleted: an award that turns
 * out to be wrong is ended, which leaves the invoices it already discounted
 * explainable a year later. Section 47 and 71.10 both say the same thing about
 * financial history, and a scholarship is exactly that.
 */
class ScholarshipController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('scholarships.view'), 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
        ];

        $scholarships = Scholarship::query()
            ->with([
                'student:id,first_name,middle_name,last_name,student_number',
                'student.currentEnrollment.section.schoolClass',
                'academicYear:id,name',
                'term:id,name',
                'awardedBy:id,name',
            ])
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('scholarships.index', [
            'scholarships' => $scholarships,
            'filters' => $filters,
            'statuses' => Scholarship::STATUSES,
            'activeCount' => Scholarship::active()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('scholarships.manage'), 403);

        return view('scholarships.form', $this->formData(null));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('scholarships.manage'), 403);

        $data = $this->validated($request);

        $scholarship = Scholarship::create($data + [
            'school_id' => $request->user()->school_id,
            'awarded_by' => $request->user()->id,
        ]);

        $audit->log('created', 'Finance',
            'A scholarship was awarded to '.$scholarship->student?->full_name.' ('.$scholarship->awardLabel().').',
            $scholarship);

        return redirect()
            ->route('scholarships.index')
            ->with('status', 'Scholarship recorded. It is applied the next time fees are raised.');
    }

    public function edit(Request $request, Scholarship $scholarship): View
    {
        abort_unless($request->user()->hasPermission('scholarships.manage'), 403);
        abort_unless($scholarship->school_id === $request->user()->school_id, 403);

        return view('scholarships.form', $this->formData($scholarship));
    }

    public function update(Request $request, Scholarship $scholarship, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('scholarships.manage'), 403);
        abort_unless($scholarship->school_id === $request->user()->school_id, 403);

        $scholarship->update($this->validated($request));

        $audit->log('updated', 'Finance',
            'The scholarship for '.$scholarship->student?->full_name.' was changed.',
            $scholarship);

        return redirect()
            ->route('scholarships.index')
            ->with('status', 'Scholarship updated.');
    }

    /**
     * Ends an award rather than deleting it.
     *
     * The invoices it discounted are still on the books, and an audit that
     * cannot explain why a bill was smaller is not an audit. Ending it stops it
     * applying from here on, which is the only part anyone actually wanted.
     */
    public function end(Request $request, Scholarship $scholarship, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('scholarships.manage'), 403);
        abort_unless($scholarship->school_id === $request->user()->school_id, 403);

        $scholarship->update([
            'status' => 'ended',
            'ends_on' => $scholarship->ends_on ?? now()->toDateString(),
        ]);

        $audit->log('ended', 'Finance',
            'The scholarship for '.$scholarship->student?->full_name.' was ended.',
            $scholarship);

        return back()->with('status', 'Scholarship ended. Fees raised from now on are charged in full.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'sponsor' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(Scholarship::TYPES)],
            /*
             | Each award figure is required only for the kind of award it
             | belongs to. Requiring both would force a bursar to invent a
             | number they do not have, and it is that invented number that
             | later turns up on somebody's bill.
             */
            'percentage' => [
                Rule::requiredIf(fn () => $request->input('type') === 'percentage'),
                'nullable', 'numeric', 'min:0.01', 'max:100',
            ],
            'amount' => [
                Rule::requiredIf(fn () => $request->input('type') === 'amount'),
                'nullable', 'numeric', 'min:0.01',
            ],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'term_id' => ['nullable', 'integer', Rule::exists('terms', 'id')],
            'status' => ['required', Rule::in(Scholarship::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'percentage.required' => 'Enter the percentage of the fees this award covers.',
            'amount.required' => 'Enter the amount this award takes off the fees.',
        ]);

        /*
         | The student, the year and the term are resolved through the scoped
         | queries, so an id belonging to another school simply does not exist
         | here. Rule::exists alone would not do that - it queries the table
         | directly, global scope and all bypassed.
         */
        abort_unless(Student::whereKey($data['student_id'])->exists(), 404);

        if (! empty($data['academic_year_id'])) {
            abort_unless(AcademicYear::whereKey($data['academic_year_id'])->exists(), 404);
        }

        if (! empty($data['term_id'])) {
            abort_unless(Term::whereKey($data['term_id'])->exists(), 404);
        }

        // Only the column belonging to the chosen kind of award is written, so
        // switching a percentage award to a fixed one cannot leave a stale
        // percentage behind for the next reader to trip over.
        $data['percentage'] = $data['type'] === 'percentage' ? $data['percentage'] : null;
        $data['amount_minor'] = $data['type'] === 'amount' ? Money::toMinor($data['amount']) : null;

        unset($data['amount']);

        return $data;
    }

    /** @return array<string, mixed> */
    protected function formData(?Scholarship $scholarship): array
    {
        return [
            'scholarship' => $scholarship,
            'students' => Student::query()
                ->where('status', 'active')
                ->with('currentEnrollment.section.schoolClass')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'terms' => Term::with('academicYear:id,name')->orderBy('sequence')->get(),
            'types' => Scholarship::TYPES,
            'statuses' => Scholarship::STATUSES,
        ];
    }
}
