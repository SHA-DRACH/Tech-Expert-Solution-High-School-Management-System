<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\GradeScale;
use App\Notifications\GradesDecided;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Grade approval (spec section 37).
 *
 *   teacher enters marks -> submits -> academic office reviews
 *   -> approved, and only then do results become official
 *
 * Rejecting sends the work back to the teacher with a note rather than
 * discarding it, so nothing entered is ever lost.
 */
class GradeApprovalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('grades.approve'), 403);

        $pending = Assessment::where('status', 'submitted')
            ->with(['subject:id,name', 'section.schoolClass', 'teacher:id,first_name,last_name'])
            ->withCount('scores')
            ->orderBy('submitted_at')
            ->get();

        $recentlyDecided = Assessment::whereIn('status', ['approved', 'rejected'])
            ->with(['subject:id,name', 'section.schoolClass', 'teacher:id,first_name,last_name'])
            ->latest('approved_at')
            ->limit(10)
            ->get();

        return view('grades.approvals', [
            'pending' => $pending,
            'recentlyDecided' => $recentlyDecided,
        ]);
    }

    /** The mark sheet as the reviewer sees it, with the distribution. */
    public function show(Request $request, Assessment $assessment): View
    {
        abort_unless($request->user()->hasPermission('grades.approve'), 403);
        $this->authorize('view', $assessment);

        $assessment->load(['subject', 'section.schoolClass', 'teacher', 'term']);

        $scores = AssessmentScore::where('assessment_id', $assessment->id)
            ->with('student:id,first_name,last_name,student_number')
            ->get()
            ->sortBy(fn (AssessmentScore $score) => $score->student?->last_name);

        $scale = GradeScale::orderBy('sequence')->get();

        return view('grades.review', [
            'assessment' => $assessment,
            'scores' => $scores,
            'scale' => $scale,
            'distribution' => $this->distribution($assessment, $scores, $scale),
        ]);
    }

    public function approve(Request $request, Assessment $assessment, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        $this->authorize('approve', $assessment);

        $assessment->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => null,
        ]);

        $audit->log('grades_approved', 'Examinations & grades',
            "Marks for \"{$assessment->title}\" were approved and are now official.", $assessment);

        $notifier->notifyUser($assessment->teacher?->user, new GradesDecided($assessment, true));

        return redirect()
            ->route('grades.approvals')
            ->with('status', "Marks for \"{$assessment->title}\" are now official.");
    }

    public function reject(Request $request, Assessment $assessment, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        $this->authorize('approve', $assessment);

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Tell the teacher what needs correcting.',
        ]);

        $assessment->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        $audit->log('grades_rejected', 'Examinations & grades',
            "Marks for \"{$assessment->title}\" were sent back to the teacher.", $assessment);

        $notifier->notifyUser($assessment->teacher?->user, new GradesDecided($assessment->fresh(), false));

        return redirect()
            ->route('grades.approvals')
            ->with('status', 'Sent back to the teacher for correction.');
    }

    /**
     * How many students fell into each grade band.
     *
     * @return array<int, array{label: string, value: int}>
     */
    protected function distribution(Assessment $assessment, $scores, $scale): array
    {
        return $scale->map(function (GradeScale $band) use ($assessment, $scores) {
            $count = $scores->filter(function (AssessmentScore $score) use ($assessment, $band) {
                if ($score->score === null || ! $assessment->max_score) {
                    return false;
                }

                $percentage = ((float) $score->score / $assessment->max_score) * 100;

                return $percentage >= $band->min_score && $percentage <= $band->max_score;
            })->count();

            return ['label' => $band->grade, 'value' => $count];
        })->values()->all();
    }
}
