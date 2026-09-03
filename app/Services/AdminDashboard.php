<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Support\Collection;

/**
 * Assembles the administrator dashboard.
 *
 * Every figure is permission-gated: a user only sees a tile if they are allowed
 * to open the module behind it, so an accountant and a registrar get different
 * dashboards from the same code.
 */
class AdminDashboard
{
    public function __construct(private readonly SchoolContext $context) {}

    /** @return array<int, array{label: string, value: string, note: string, permission: string, href: ?string}> */
    public function metrics(User $user): array
    {
        $tiles = [
            [
                'label' => 'Total students',
                'value' => (string) Student::where('status', 'active')->count(),
                'note' => 'Currently enrolled',
                'permission' => 'students.view',
                'href' => 'students.index',
            ],
            [
                'label' => 'Pending applications',
                'value' => (string) Admission::whereIn('status', ['submitted', 'pending', 'under_review', 'documents_required'])->count(),
                'note' => 'Awaiting a decision',
                'permission' => 'admissions.view',
                'href' => 'admissions.index',
            ],
            [
                'label' => 'Teachers',
                'value' => (string) Teacher::where('status', 'active')->count(),
                'note' => 'On the teaching staff',
                'permission' => 'teachers.view',
                'href' => 'teachers.index',
            ],
            [
                'label' => 'Parents & guardians',
                'value' => (string) Guardian::count(),
                'note' => 'Family records',
                'permission' => 'guardians.view',
                'href' => 'guardians.index',
            ],
            [
                'label' => 'Fees collected',
                'value' => Money::compact($this->collectedMinor()),
                'note' => 'This academic year',
                'permission' => 'payments.view',
                'href' => null,
            ],
            [
                'label' => 'Outstanding fees',
                'value' => Money::compact($this->outstandingMinor()),
                'note' => 'Still to be paid',
                'permission' => 'payments.view',
                'href' => null,
            ],
            [
                'label' => 'Attendance',
                'value' => $this->attendanceRate() === null ? 'Not recorded' : $this->attendanceRate().'%',
                'note' => 'Last 30 days',
                'permission' => 'attendance.view',
                'href' => null,
            ],
            [
                'label' => 'Grades awaiting approval',
                'value' => (string) Assessment::where('status', 'submitted')->count(),
                'note' => 'Submitted by teachers',
                'permission' => 'grades.approve',
                'href' => null,
            ],
        ];

        return array_values(array_filter(
            $tiles,
            fn (array $tile) => $user->hasPermission($tile['permission'])
        ));
    }

    public function collectedMinor(): int
    {
        return (int) Payment::sum('amount_minor');
    }

    public function outstandingMinor(): int
    {
        return (int) Invoice::outstanding()
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->balanceMinor());
    }

    /** Share of attendance marks in the last 30 days that were present or late. */
    public function attendanceRate(): ?float
    {
        $query = AttendanceRecord::where('recorded_on', '>=', now()->subDays(30));

        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        return round((clone $query)->present()->count() / $total * 100, 1);
    }

    /**
     * Enrollment over the last six months.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function enrollmentTrend(): array
    {
        $start = now()->startOfMonth()->subMonths(5);

        $counts = Student::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Student $student) => $student->created_at->format('Y-m'))
            ->map->count();

        return collect(range(0, 5))
            ->map(function (int $offset) use ($start, $counts) {
                $month = $start->copy()->addMonths($offset);

                return ['label' => $month->format('M'), 'value' => (int) ($counts[$month->format('Y-m')] ?? 0)];
            })
            ->all();
    }

    /**
     * Daily attendance rate for the last fortnight of school days.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function attendanceTrend(): array
    {
        $records = AttendanceRecord::where('recorded_on', '>=', now()->subDays(18))
            ->get(['recorded_on', 'status']);

        return $records
            ->groupBy(fn (AttendanceRecord $record) => $record->recorded_on->toDateString())
            ->sortKeys()
            ->take(-10)
            ->map(fn (Collection $day, string $date) => [
                'label' => \Illuminate\Support\Carbon::parse($date)->format('j M'),
                'value' => (int) round($day->whereIn('status', ['present', 'late'])->count() / max($day->count(), 1) * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * Fee collection by category, for the collection breakdown.
     *
     * @return array<int, array{label: string, value: int, share: float}>
     */
    public function feeBreakdown(): array
    {
        $collected = $this->collectedMinor();
        $outstanding = $this->outstandingMinor();
        $total = max($collected + $outstanding, 1);

        return [
            ['label' => 'Collected', 'value' => $collected, 'share' => round($collected / $total * 100, 1)],
            ['label' => 'Outstanding', 'value' => $outstanding, 'share' => round($outstanding / $total * 100, 1)],
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    public function admissionFunnel(): array
    {
        $counts = Admission::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect([
            'Submitted' => ['submitted', 'pending'],
            'Under review' => ['under_review', 'documents_required', 'interview_required'],
            'Approved' => ['approved'],
            'Enrolled' => ['enrolled'],
            'Rejected' => ['rejected'],
        ])->map(fn (array $statuses, string $label) => [
            'label' => $label,
            'value' => (int) collect($statuses)->sum(fn (string $status) => $counts[$status] ?? 0),
        ])->values()->all();
    }

    /** @return Collection<int, AuditLog> */
    public function recentActivity(User $user, int $limit = 8): Collection
    {
        if (! $user->hasPermission('audit.view')) {
            return collect();
        }

        return AuditLog::query()
            ->when($this->context->schoolId(), fn ($query, $id) => $query->where('school_id', $id))
            ->with('user:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Event> */
    public function upcomingEvents(int $limit = 4): Collection
    {
        return Event::upcoming()->limit($limit)->get();
    }

    /** @return Collection<int, Announcement> */
    public function latestAnnouncements(int $limit = 4): Collection
    {
        return Announcement::live()->latest('published_at')->limit($limit)->get();
    }

    public function currentYear(): ?AcademicYear
    {
        return AcademicYear::active();
    }
}
