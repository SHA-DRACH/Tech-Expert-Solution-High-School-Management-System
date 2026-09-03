<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\GradeScale;
use App\Models\Term;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Examination management (spec section 34) and the school's grade scale
 * (section 35). Nothing about grading is hard-coded: the bands below decide
 * every letter awarded anywhere in the system.
 */
class ExaminationController extends Controller
{
    public const TYPES = ['terminal', 'mid_term', 'mock', 'entrance', 'continuous', 'other'];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('exams.view'), 403);

        return view('examinations.index', [
            'examinations' => Examination::with('term')
                ->withCount('assessments')
                ->orderByDesc('starts_on')
                ->get(),
            'terms' => Term::with('academicYear')->orderBy('sequence')->get(),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'scale' => GradeScale::orderBy('sequence')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('exams.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(self::TYPES)],
            'term_id' => ['nullable', 'integer'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $year = AcademicYear::active();

        abort_unless($year !== null, 422, 'Set up an academic year first.');

        $term = isset($data['term_id']) ? Term::findOrFail($data['term_id']) : null;

        $examination = Examination::create([
            'academic_year_id' => $year->id,
            'term_id' => $term?->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'status' => 'scheduled',
        ]);

        $audit->log('created', 'Examinations & grades',
            "Examination \"{$examination->name}\" was scheduled.", $examination);

        return back()->with('status', 'Examination scheduled.');
    }

    public function update(Request $request, Examination $examination, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('exams.manage'), 403);
        abort_unless($examination->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(self::TYPES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $examination->update($data);

        $audit->log('updated', 'Examinations & grades',
            "Examination \"{$examination->name}\" was updated.", $examination);

        return back()->with('status', 'Examination updated.');
    }

    /**
     * Replace the grade scale wholesale.
     *
     * Validated as a set rather than row by row: the bands must cover 0-100
     * without gaps or overlaps, otherwise a score could fall through and be
     * awarded no grade at all.
     */
    public function updateScale(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('exams.manage'), 403);

        $data = $request->validate([
            'bands' => ['required', 'array', 'min:1', 'max:15'],
            'bands.*.grade' => ['required', 'string', 'max:20'],
            'bands.*.min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'bands.*.max_score' => ['required', 'integer', 'min:0', 'max:100'],
            'bands.*.remark' => ['nullable', 'string', 'max:60'],
            'bands.*.points' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $bands = collect($data['bands'])
            ->map(fn (array $band) => $band + ['remark' => null, 'points' => null])
            ->sortByDesc('min_score')
            ->values();

        if ($error = $this->scaleProblem($bands)) {
            return back()->withErrors(['bands' => $error])->withInput();
        }

        $before = GradeScale::orderBy('sequence')->get()
            ->map(fn (GradeScale $g) => $g->grade.' '.$g->min_score.'-'.$g->max_score)->all();

        DB::transaction(function () use ($bands, $request) {
            GradeScale::where('school_id', $request->user()->school_id)->delete();

            foreach ($bands as $index => $band) {
                GradeScale::create([
                    'grade' => $band['grade'],
                    'min_score' => $band['min_score'],
                    'max_score' => $band['max_score'],
                    'remark' => $band['remark'],
                    'points' => $band['points'],
                    'sequence' => $index + 1,
                ]);
            }
        });

        $after = GradeScale::orderBy('sequence')->get()
            ->map(fn (GradeScale $g) => $g->grade.' '.$g->min_score.'-'.$g->max_score)->all();

        $audit->log('updated', 'Examinations & grades', 'The grading scale was changed.', null,
            ['scale' => $before], ['scale' => $after]);

        return back()->with('status', 'Grading scale updated.');
    }

    /** @return string|null the first problem found, or null when the scale is sound */
    protected function scaleProblem($bands): ?string
    {
        foreach ($bands as $band) {
            if ($band['min_score'] > $band['max_score']) {
                return "Grade {$band['grade']} has a minimum higher than its maximum.";
            }
        }

        if ($bands->first()['max_score'] !== 100) {
            return 'The highest band must reach 100.';
        }

        if ($bands->last()['min_score'] !== 0) {
            return 'The lowest band must start at 0.';
        }

        // Walking down from the top, each band must begin exactly where the one
        // above it left off.
        foreach ($bands->sliding(2) as $pair) {
            [$upper, $lower] = [$pair->first(), $pair->last()];

            if ($lower['max_score'] + 1 !== $upper['min_score']) {
                return "Grades {$lower['grade']} and {$upper['grade']} leave a gap or overlap between {$lower['max_score']} and {$upper['min_score']}.";
            }
        }

        $duplicates = $bands->pluck('grade')->duplicates();

        if ($duplicates->isNotEmpty()) {
            return 'Grade '.$duplicates->first().' appears more than once.';
        }

        return null;
    }
}
