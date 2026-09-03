<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The parent portal over HTTP, for the planned Flutter application
 * (spec section 64).
 *
 * The authorization rules are the same ones the web portal uses: a child is
 * always resolved through the signed-in guardian's own relationships, and the
 * per-child academic and finance flags still apply.
 */
class ParentApiController extends Controller
{
    public function children(Request $request): JsonResponse
    {
        $guardian = $this->guardian($request);

        $children = $guardian->students()->with('currentEnrollment.section.schoolClass')->get();

        return response()->json([
            'data' => $children->map(fn (Student $child) => [
                'id' => $child->id,
                'name' => $child->full_name,
                'student_number' => $child->student_number,
                'class' => $child->currentEnrollment?->section?->full_name,
                'status' => $child->status,
                'can_view_academics' => (bool) $child->pivot->can_view_academics,
                'can_view_finance' => (bool) $child->pivot->can_view_finance,
            ]),
        ]);
    }

    public function summary(Request $request, Student $child): JsonResponse
    {
        $guardian = $this->guardian($request);
        $child = $this->childOf($guardian, $child);

        return response()->json([
            'data' => [
                'id' => $child->id,
                'name' => $child->full_name,
                'student_number' => $child->student_number,
                'class' => $child->currentEnrollment?->section?->full_name,
                'attendance_rate' => $guardian->canViewAcademicsFor($child) ? $child->attendanceRate() : null,
                'outstanding' => $guardian->canViewFinanceFor($child)
                    ? $this->money($child->outstandingMinor())
                    : null,
                'report_cards' => ReportCard::published()->where('student_id', $child->id)->count(),
            ],
        ]);
    }

    public function grades(Request $request, Student $child): JsonResponse
    {
        $guardian = $this->guardian($request);
        $child = $this->childOf($guardian, $child);

        abort_unless($guardian->canViewAcademicsFor($child), 403, 'Academic records are not shared with this account.');

        $scores = AssessmentScore::where('student_id', $child->id)
            ->whereHas('assessment', fn ($query) => $query->where('status', 'approved'))
            ->with('assessment.subject')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $scores->map(function (AssessmentScore $score) {
                $percentage = $score->percentage();

                return [
                    'subject' => $score->assessment?->subject?->name,
                    'title' => $score->assessment?->title,
                    'type' => $score->assessment?->type,
                    'score' => (float) $score->score,
                    'max' => $score->assessment?->max_score,
                    'percentage' => $percentage,
                    'grade' => $percentage === null ? null : GradeScale::forScore($percentage)?->grade,
                    'recorded_on' => $score->created_at?->toDateString(),
                ];
            }),
        ]);
    }

    public function attendance(Request $request, Student $child): JsonResponse
    {
        $guardian = $this->guardian($request);
        $child = $this->childOf($guardian, $child);

        abort_unless($guardian->canViewAcademicsFor($child), 403);

        $records = AttendanceRecord::where('student_id', $child->id)
            ->orderByDesc('recorded_on')
            ->limit(90)
            ->get(['recorded_on', 'status', 'remark']);

        return response()->json([
            'meta' => ['rate' => $child->attendanceRate()],
            'data' => $records->map(fn (AttendanceRecord $record) => [
                'date' => $record->recorded_on->toDateString(),
                'status' => $record->status,
                'remark' => $record->remark,
            ]),
        ]);
    }

    public function fees(Request $request, Student $child): JsonResponse
    {
        $guardian = $this->guardian($request);
        $child = $this->childOf($guardian, $child);

        abort_unless($guardian->canViewFinanceFor($child), 403, 'Fee records are not shared with this account.');

        $invoices = Invoice::where('student_id', $child->id)->with('items')->latest('issued_on')->get();
        $payments = Payment::where('student_id', $child->id)->latest('paid_on')->get();

        return response()->json([
            'meta' => [
                'total' => $this->money((int) $invoices->sum('total_minor')),
                'paid' => $this->money((int) $payments->sum('amount_minor')),
                'outstanding' => $this->money($child->outstandingMinor()),
            ],
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'number' => $invoice->invoice_number,
                'issued_on' => $invoice->issued_on?->toDateString(),
                'due_on' => $invoice->due_on?->toDateString(),
                'status' => $invoice->status,
                'total' => $this->money($invoice->total_minor),
                'balance' => $this->money($invoice->balanceMinor()),
                'items' => $invoice->items->map(fn ($item) => [
                    'category' => $item->category,
                    'amount' => $this->money($item->amount_minor),
                ]),
            ]),
            'payments' => $payments->map(fn (Payment $payment) => [
                'receipt' => $payment->receipt_number,
                'paid_on' => $payment->paid_on?->toDateString(),
                'amount' => $this->money($payment->amount_minor),
                'method' => $payment->method,
            ]),
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $this->guardian($request);

        $announcements = Announcement::live()->for('parents')->latest('published_at')->limit(20)->get();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $announcement) => [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'category' => $announcement->category,
                'urgent' => (bool) $announcement->is_emergency,
                'published_at' => $announcement->published_at?->toIso8601String(),
            ]),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $this->guardian($request);

        return response()->json([
            'data' => Event::upcoming()->limit(20)->get()->map(fn (Event $event) => [
                'title' => $event->title,
                'category' => $event->category,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'location' => $event->location,
            ]),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'meta' => ['unread' => $user->unreadNotifications()->count()],
            'data' => $user->notifications()->limit(30)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? null,
                'body' => $notification->data['body'] ?? null,
                'category' => $notification->data['category'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function guardian(Request $request): Guardian
    {
        $guardian = $request->user()->guardianProfile;

        abort_unless($guardian !== null, 403, 'This account is not linked to a parent or guardian record.');

        return $guardian;
    }

    /**
     * Re-resolve the child through this guardian's own relationships. A child
     * id from the request that they are not linked to simply does not exist.
     */
    protected function childOf(Guardian $guardian, Student $child): Student
    {
        $linked = $guardian->students()->where('students.id', $child->id)->first();

        abort_unless($linked !== null, 404);

        return $linked;
    }

    /** @return array{minor: int, formatted: string} */
    protected function money(int $minor): array
    {
        return ['minor' => $minor, 'formatted' => Money::format($minor)];
    }
}
