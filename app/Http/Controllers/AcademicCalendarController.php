<?php

namespace App\Http\Controllers;

use App\Actions\SetUpPeriods;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Academic years and terms (spec sections 26 and 27).
 *
 * Almost everything the platform records hangs off a year and a term:
 * enrolments, attendance, assessments, report cards, fee structures and
 * invoices all carry one. Until this screen existed they could only be created
 * by a seeder, which meant a live school could not roll into its next academic
 * year at all - the permission catalogue had promised "manage years, terms,
 * classes, sections and subjects" since the beginning, and only the second half
 * of that sentence was true.
 */
class AcademicCalendarController extends Controller
{
    public function index(SchoolContext $context): View
    {
        $this->authorizeCalendar();

        return view('settings.academic-years', [
            'school' => $context->school(),
            'years' => AcademicYear::with(['terms', 'semesters'])
                ->withCount(['terms', 'enrollments'])
                ->orderByDesc('starts_on')
                ->get(),
        ]);
    }

    /* ----------------------------------------------------------- the year */

    public function storeYear(Request $request, AuditLogger $audit, SetUpPeriods $periods): RedirectResponse
    {
        $this->authorizeCalendar();

        $data = $this->validateYear($request);
        unset($data['periods']);

        $year = AcademicYear::create($data + ['is_current' => false]);

        // The first year a school creates is the one it is working in; there is
        // nothing else it could be, and leaving it unset would leave every
        // "current year" lookup on the platform falling back silently.
        if (AcademicYear::count() === 1) {
            $year->update(['is_current' => true]);
        }

        $audit->log('created', 'Academics', "Academic year {$year->name} was created.", $year);

        // Six periods in two semesters, laid out in the same step, so a new year
        // is ready for marks the moment it exists.
        if ($request->boolean('periods')) {
            $periods->handle($year->fresh());

            return back()->with('status', "Academic year {$year->name} was created with six periods in two semesters. Set each period's dates below.");
        }

        return back()->with('status', "Academic year {$year->name} was created.");
    }

    public function updateYear(Request $request, AcademicYear $academicYear, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($academicYear);

        $data = $this->validateYear($request, $academicYear);

        // Narrowing a year must not orphan terms that already sit outside it,
        // or the calendar would claim a term the year does not contain.
        $strays = $academicYear->terms()
            ->where(fn ($query) => $query
                ->whereDate('starts_on', '<', $data['starts_on'])
                ->orWhereDate('ends_on', '>', $data['ends_on']))
            ->pluck('name');

        if ($strays->isNotEmpty()) {
            throw ValidationException::withMessages([
                'starts_on' => 'These terms would fall outside the year: '.$strays->join(', ').'.',
            ]);
        }

        $original = $academicYear->only(array_keys($data));

        $academicYear->update($data);

        $audit->log('updated', 'Academics', "Academic year {$academicYear->name} was updated.", $academicYear, $original, $data);

        return back()->with('status', 'Academic year updated.');
    }

    /** Move the school into a year. Exactly one is current at a time. */
    public function makeYearCurrent(AcademicYear $academicYear, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($academicYear);

        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);

            $academicYear->update(['is_current' => true]);

            /*
             | The current term must live inside the current year. Leaving a
             | term from the previous year marked current would file new
             | attendance and new marks against a year the school has left.
             */
            Term::whereNot('academic_year_id', $academicYear->id)->update(['is_current' => false]);

            if ($academicYear->terms()->where('is_current', true)->doesntExist()) {
                $academicYear->terms()->orderBy('sequence')->first()?->update(['is_current' => true]);
            }
        });

        $audit->log('updated', 'Academics', "The school moved into {$academicYear->name}.", $academicYear);

        return back()->with('status', "{$academicYear->name} is now the current academic year.");
    }

    public function destroyYear(AcademicYear $academicYear, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($academicYear);

        if ($academicYear->is_current) {
            return back()->withErrors(['year' => 'The current academic year cannot be deleted. Move the school into another year first.']);
        }

        if ($used = $this->yearInUse($academicYear)) {
            return back()->withErrors(['year' => "This year cannot be deleted because it still holds {$used}. Academic history is kept, not removed."]);
        }

        $name = $academicYear->name;

        DB::transaction(function () use ($academicYear) {
            $academicYear->terms()->delete();
            $academicYear->delete();
        });

        $audit->log('deleted', 'Academics', "Empty academic year {$name} was deleted.", null);

        return back()->with('status', "Academic year {$name} was deleted.");
    }

    /* ----------------------------------------------------------- the term */

    public function storeTerm(Request $request, AcademicYear $academicYear, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($academicYear);

        $this->nameAsPeriod($request, $academicYear);

        $data = $this->validateTerm($request, $academicYear);
        $data += $this->semesterFields($academicYear, (int) $data['sequence']);

        $term = $academicYear->terms()->create($data + [
            'school_id' => $academicYear->school_id,
            'is_current' => false,
        ]);

        // The first term of the year the school is actually in is current by
        // default, for the same reason the first year is.
        if ($academicYear->is_current && Term::where('is_current', true)->doesntExist()) {
            $term->update(['is_current' => true]);
        }

        $audit->log('created', 'Academics', "Term {$term->name} was added to {$academicYear->name}.", $term);

        return back()->with('status', "{$term->name} was added.");
    }

    public function updateTerm(Request $request, Term $term, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($term);

        $this->nameAsPeriod($request, $term->academicYear);

        $data = $this->validateTerm($request, $term->academicYear, $term);
        $data += $this->semesterFields($term->academicYear, (int) $data['sequence']);

        $original = $term->only(array_keys($data));

        $term->update($data);

        $audit->log('updated', 'Academics', "Term {$term->name} was updated.", $term, $original, $data);

        return back()->with('status', 'Term updated.');
    }

    public function makeTermCurrent(Term $term, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($term);

        if (! $term->academicYear?->is_current) {
            return back()->withErrors([
                'term' => 'Only a term inside the current academic year can be made current. Move the school into '
                    .($term->academicYear?->name ?? 'that year').' first.',
            ]);
        }

        DB::transaction(function () use ($term) {
            Term::where('id', '!=', $term->id)->update(['is_current' => false]);

            $term->update(['is_current' => true]);
        });

        $audit->log('updated', 'Academics', "The school moved into {$term->name}.", $term);

        return back()->with('status', "{$term->name} is now the current term.");
    }

    public function destroyTerm(Term $term, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeCalendar();
        $this->assertOwned($term);

        if ($term->is_current) {
            return back()->withErrors(['term' => 'The current term cannot be deleted. Make another term current first.']);
        }

        if ($used = $this->termInUse($term)) {
            return back()->withErrors(['term' => "This term cannot be deleted because it still holds {$used}. Academic history is kept, not removed."]);
        }

        $name = $term->name;

        $term->delete();

        $audit->log('deleted', 'Academics', "Empty term {$name} was deleted.", null);

        return back()->with('status', "{$name} was deleted.");
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * In a year kept in periods, a period is named by its number.
     *
     * "3rd period" is not something to type: a hand-typed name would drift
     * from the number the grade sheet and the averages go by, so the name is
     * written from the number and the semester worked out from it.
     */
    protected function nameAsPeriod(Request $request, AcademicYear $year): void
    {
        if ($year->usesPeriods() && $request->filled('sequence')) {
            $request->merge(['name' => ucfirst(Term::periodName((int) $request->input('sequence')))]);
        }
    }

    /** @return array<string, int> */
    protected function semesterFields(AcademicYear $year, int $number): array
    {
        if (! $year->usesPeriods()) {
            return [];
        }

        $semester = Term::semesterForPeriod($number);
        app(SetUpPeriods::class)->semester($year, $semester);

        return ['semester' => $semester];
    }

    protected function authorizeCalendar(): void
    {
        abort_unless(request()->user()?->hasPermission('academics.manage'), 403);
    }

    /**
     * The global scope already limits what a route model can bind to, but a
     * missing scope would fail open. Section 59: verify on the backend, never
     * trust an id that arrived from the frontend.
     */
    protected function assertOwned(AcademicYear|Term $model): void
    {
        abort_unless($model->school_id === request()->user()->school_id, 403);
    }

    protected function validateYear(Request $request, ?AcademicYear $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ], [], ['ends_on' => 'end date', 'starts_on' => 'start date']);

        $duplicate = AcademicYear::where('name', $data['name'])
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => "There is already a year called {$data['name']}."]);
        }

        // Two overlapping years would make "which year is this date in?"
        // unanswerable, and enrolment and reporting both ask it.
        $overlap = AcademicYear::when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_on' => "These dates overlap {$overlap->name} ({$overlap->starts_on->format('j M Y')} to {$overlap->ends_on->format('j M Y')}).",
            ]);
        }

        return $data;
    }

    protected function validateTerm(Request $request, AcademicYear $year, ?Term $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sequence' => ['required', 'integer', 'min:1', 'max:6'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ], [], ['ends_on' => 'end date', 'starts_on' => 'start date']);

        if ($data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages([
                'starts_on' => "A term must fall inside {$year->name} ({$year->starts_on->format('j M Y')} to {$year->ends_on->format('j M Y')}).",
            ]);
        }

        $siblings = $year->terms()
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()));

        if ((clone $siblings)->where('sequence', $data['sequence'])->exists()) {
            throw ValidationException::withMessages(['sequence' => "Term {$data['sequence']} already exists in {$year->name}."]);
        }

        $overlap = (clone $siblings)
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_on' => "These dates overlap {$overlap->name} ({$overlap->starts_on->format('j M Y')} to {$overlap->ends_on->format('j M Y')}).",
            ]);
        }

        return $data;
    }

    /**
     * What a year still holds, phrased for the person reading the error.
     * Returns null when the year is genuinely empty and safe to remove.
     */
    protected function yearInUse(AcademicYear $year): ?string
    {
        $counts = collect([
            'enrolment' => $year->enrollments()->count(),
            'invoice' => DB::table('invoices')->where('academic_year_id', $year->id)->count(),
            'assessment' => DB::table('assessments')->where('academic_year_id', $year->id)->count(),
            'report card' => DB::table('report_cards')->where('academic_year_id', $year->id)->count(),
            'fee structure' => DB::table('fee_structures')->where('academic_year_id', $year->id)->count(),
        ])->filter();

        return $this->phrase($counts);
    }

    protected function termInUse(Term $term): ?string
    {
        $counts = collect([
            'attendance record' => DB::table('attendance_records')->where('term_id', $term->id)->count(),
            'assessment' => DB::table('assessments')->where('term_id', $term->id)->count(),
            'invoice' => DB::table('invoices')->where('term_id', $term->id)->count(),
            'report card' => DB::table('report_cards')->where('term_id', $term->id)->count(),
            'examination' => DB::table('examinations')->where('term_id', $term->id)->count(),
        ])->filter();

        return $this->phrase($counts);
    }

    /** "3 enrolments and 12 invoices" */
    protected function phrase(\Illuminate\Support\Collection $counts): ?string
    {
        if ($counts->isEmpty()) {
            return null;
        }

        $parts = $counts->map(fn (int $count, string $noun) => $count.' '.\Illuminate\Support\Str::plural($noun, $count))->values();

        return $parts->count() === 1
            ? $parts->first()
            : $parts->slice(0, -1)->join(', ').' and '.$parts->last();
    }
}
