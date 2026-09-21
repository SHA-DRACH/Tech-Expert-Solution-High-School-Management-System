<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\FeeStructure;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\ParentRequest;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\ClassSchedule;
use App\Services\Gradebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The parent portal.
 *
 * Every action resolves the child through the signed-in guardian's own
 * relationship, so a parent can only ever reach a child they are linked to,
 * whatever id arrives in the request.
 */
class ParentPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        return view('portals.parent.dashboard', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'summary' => $child ? $this->summaryFor($guardian, $child) : null,
            'announcements' => $this->announcementsFor($child),
            'events' => Event::upcoming()->limit(4)->get(),
            'recentGrades' => $child ? $this->recentGrades($guardian, $child, 5) : collect(),
            'openRequests' => $guardian->requests()->whereIn('status', ['open', 'in_progress'])->count(),
            // Fee schedules for this child's class, for a parent cleared to see fees.
            'feeDocuments' => $child && $guardian->canViewFinanceFor($child) ? FeeStructure::documentsFor($child) : collect(),
            'week' => $week = $this->scheduleFor($guardian, $child),
            'days' => app(ClassSchedule::class)->days($week),
        ]);
    }

    /** A child's weekly class schedule: day, time, subject and teacher. */
    public function schedule(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        abort_unless($child !== null, 404);
        $this->authorizeSchedule($guardian, $child);

        $week = $this->scheduleFor($guardian, $child);

        return view('portals.parent.schedule', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'week' => $week,
            'days' => app(ClassSchedule::class)->days($week),
        ]);
    }

    public function downloadSchedule(Request $request): StreamedResponse
    {
        $guardian = $this->guardian($request);
        $child = $this->selectedChild($request, $this->children($guardian));

        abort_unless($child !== null, 404);
        $this->authorizeSchedule($guardian, $child);

        $book = app(ClassSchedule::class)->workbook($child, $request->user()->school);

        return response()->streamDownload(
            fn () => IOFactory::createWriter($book, 'Xlsx')->save('php://output'),
            Str::slug('class-schedule-'.$child->full_name).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function grades(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        abort_unless($child !== null, 404);
        abort_unless($guardian->canViewAcademicsFor($child), 403, 'You are not cleared to view academic records for this child.');

        /*
         | Results are shown per term, subject by subject, with the average the
         | school itself calculates. Every figure comes from Gradebook, which
         | counts approved marks only - a mark a teacher has entered but the
         | academic office has not signed off is not a result, and a parent
         | seeing it would have no way of knowing it might still change.
         */
        $gradebook = app(Gradebook::class);

        $year = AcademicYear::active();

        $terms = Term::when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->orderBy('sequence')
            ->get();

        $term = $request->integer('term')
            ? $terms->firstWhere('id', $request->integer('term'))
            : ($terms->firstWhere('is_current', true) ?? $terms->last());

        return view('portals.parent.grades', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'terms' => $terms,
            'term' => $term,
            'results' => $term ? $gradebook->termResults($child, $term) : null,
            'progress' => $gradebook->yearResults($child, $terms),
            'passMark' => $gradebook->passMark(),
            'grades' => $this->recentGrades($guardian, $child, 50),
            'reportCards' => ReportCard::published()->where('student_id', $child->id)->with('term')->latest('published_at')->get(),
        ]);
    }

    /**
     * The work a child has been set, with the question the teacher wrote.
     *
     * Parents had no way to see this at all. "What has she been set?" could
     * only be answered by the child, and a question that lives on a sheet of
     * paper in a schoolbag is one a parent cannot help with.
     */
    public function assignments(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        abort_unless($child !== null, 404);
        abort_unless($guardian->canViewAcademicsFor($child), 403, 'You are not cleared to view academic records for this child.');

        $section = $child->currentEnrollment?->section_id;

        $assignments = $section
            ? Assessment::where('section_id', $section)
                ->where('type', 'assignment')
                ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
                ->orderByDesc('ends_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
            : collect();

        return view('portals.parent.assignments', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'assignments' => $assignments,
            // Whether their own child handed it in — not the rest of the class.
            'submissions' => AssignmentSubmission::where('student_id', $child->id)
                ->whereIn('assessment_id', $assignments->pluck('id'))
                ->get()
                ->keyBy('assessment_id'),
        ]);
    }

    public function attendance(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        abort_unless($child !== null, 404);
        abort_unless($guardian->canViewAcademicsFor($child), 403);

        $records = AttendanceRecord::where('student_id', $child->id)
            ->orderByDesc('recorded_on')
            ->paginate(30);

        return view('portals.parent.attendance', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'records' => $records,
            'breakdown' => $this->attendanceBreakdown($child),
            'rate' => $child->attendanceRate(),
        ]);
    }

    public function fees(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        abort_unless($child !== null, 404);
        abort_unless($guardian->canViewFinanceFor($child), 403, 'You are not cleared to view fee records for this child.');

        return view('portals.parent.fees', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'invoices' => Invoice::where('student_id', $child->id)->with('items')->latest('issued_on')->get(),
            'payments' => Payment::where('student_id', $child->id)->latest('paid_on')->get(),
            'outstandingMinor' => $child->outstandingMinor(),
            'paidMinor' => (int) Payment::where('student_id', $child->id)->sum('amount_minor'),
            'totalMinor' => (int) Invoice::where('student_id', $child->id)->sum('total_minor'),
            'feeDocuments' => FeeStructure::documentsFor($child),
        ]);
    }

    /** Teachers, limited to the professional information the school publishes. */
    public function teachers(Request $request): View
    {
        $guardian = $this->guardian($request);
        $children = $this->children($guardian);
        $child = $this->selectedChild($request, $children);

        $teachers = Teacher::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->with(['department:id,name', 'publicQualifications'])
            ->orderBy('last_name')
            ->get();

        return view('portals.parent.teachers', [
            'guardian' => $guardian,
            'children' => $children,
            'child' => $child,
            'teachers' => $teachers,
        ]);
    }

    public function requests(Request $request): View
    {
        $guardian = $this->guardian($request);

        return view('portals.parent.requests', [
            'guardian' => $guardian,
            'children' => $this->children($guardian),
            'requests' => $guardian->requests()->with('student')->latest()->paginate(15),
            'types' => ParentRequest::TYPES,
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $guardian = $this->guardian($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ParentRequest::TYPES))],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
            'student_id' => ['nullable', 'integer'],
        ]);

        // The child is re-resolved through this guardian's own children.
        $student = null;

        if (! empty($data['student_id'])) {
            $student = $guardian->students()->where('students.id', $data['student_id'])->first();

            abort_unless($student !== null, 403, 'That child is not linked to your account.');
        }

        $guardian->requests()->create([
            'school_id' => $guardian->school_id,
            'student_id' => $student?->id,
            'type' => $data['type'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'open',
        ]);

        return back()->with('status', 'Your request has been sent to the school office.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Where a child is, hour by hour, is guarded like their results: a parent
     * the school has not cleared for this child's academic records does not
     * get a map of their school day either.
     */
    protected function authorizeSchedule(Guardian $guardian, Student $child): void
    {
        abort_unless($guardian->canViewAcademicsFor($child), 403, 'You are not cleared to view this child\'s school records.');
    }

    protected function scheduleFor(Guardian $guardian, ?Student $child): Collection
    {
        return $child && $guardian->canViewAcademicsFor($child)
            ? app(ClassSchedule::class)->week($child)
            : collect();
    }

    /**
     * The guardian's children, with their class already loaded.
     *
     * Every page reads the child's class sooner or later. Fetching it here,
     * once, is what stops a page 500ing for a parent with more than one child:
     * Laravel only refuses a lazy load when the model came from a list of
     * several, so a single-child parent - and every test - never saw it.
     */
    protected function children(Guardian $guardian): Collection
    {
        return $guardian->students()->with('currentEnrollment.section.schoolClass')->get();
    }

    protected function guardian(Request $request): Guardian
    {
        $guardian = $request->user()->guardianProfile()->first();

        abort_unless($guardian !== null, 403, 'This account is not linked to a parent or guardian record.');

        return $guardian;
    }

    /**
     * Honour the child selector, but only for a child this guardian is linked
     * to. An unknown id silently falls back to their first child.
     */
    protected function selectedChild(Request $request, Collection $children): ?Student
    {
        $requested = $request->integer('child');

        return $children->firstWhere('id', $requested) ?? $children->first();
    }

    /** @return array<string, mixed> */
    protected function summaryFor(Guardian $guardian, Student $child): array
    {
        $enrollment = $child->currentEnrollment;

        return [
            'class' => $enrollment?->section?->full_name ?? $enrollment?->schoolClass?->name,
            'attendanceRate' => $guardian->canViewAcademicsFor($child) ? $child->attendanceRate() : null,
            'outstandingMinor' => $guardian->canViewFinanceFor($child) ? $child->outstandingMinor() : null,
            'reportCards' => ReportCard::published()->where('student_id', $child->id)->count(),
            'canViewAcademics' => $guardian->canViewAcademicsFor($child),
            'canViewFinance' => $guardian->canViewFinanceFor($child),
        ];
    }

    /** @return array<string, int> */
    protected function attendanceBreakdown(Student $child): array
    {
        $counts = AttendanceRecord::where('student_id', $child->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceRecord::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /**
     * Approved marks only. Work a teacher has not yet had signed off never
     * reaches a parent.
     */
    protected function recentGrades(Guardian $guardian, Student $child, int $limit): Collection
    {
        if (! $guardian->canViewAcademicsFor($child)) {
            return collect();
        }

        return AssessmentScore::query()
            ->where('student_id', $child->id)
            ->whereHas('assessment', fn ($query) => $query->where('status', 'approved'))
            ->with(['assessment.subject', 'assessment.term'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (AssessmentScore $score) {
                $percentage = $score->percentage();

                return (object) [
                    'subject' => $score->assessment?->subject?->name,
                    'title' => $score->assessment?->title,
                    'type' => $score->assessment?->type,
                    'score' => $score->score,
                    'max' => $score->assessment?->max_score,
                    'percentage' => $percentage,
                    'grade' => $percentage === null ? null : GradeScale::forScore($percentage)?->grade,
                    'remark' => $score->remark,
                    'recordedOn' => $score->created_at,
                ];
            });
    }

    /** Announcements aimed at parents, plus any for this child's own section. */
    protected function announcementsFor(?Student $child): Collection
    {
        return Announcement::live()
            ->for('parents')
            ->where(function ($query) use ($child) {
                $query->whereNull('section_id');

                if ($sectionId = $child?->currentEnrollment?->section_id) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->latest('published_at')
            ->limit(6)
            ->get();
    }
}
