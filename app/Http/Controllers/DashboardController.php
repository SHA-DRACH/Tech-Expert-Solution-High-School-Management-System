<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AdminDashboard;
use App\Support\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SchoolContext $context, AdminDashboard $dashboard): View
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), 403);

        // A super administrator who has not entered a school sees the platform
        // roll-up instead of one school's numbers.
        if (! $context->hasSchool()) {
            return view('dashboard.platform', [
                'metrics' => $this->platformMetrics(),
                'schools' => School::withCount(['users', 'students'])->orderBy('name')->get(),
            ]);
        }

        $user = $request->user();

        return view('dashboard.index', [
            'school' => $context->school(),
            'academicYear' => $dashboard->currentYear(),
            'metrics' => $dashboard->metrics($user),
            'enrollmentTrend' => $dashboard->enrollmentTrend(),
            'attendanceTrend' => $user->hasPermission('attendance.view') ? $dashboard->attendanceTrend() : [],
            'feeBreakdown' => $user->hasPermission('payments.view') ? $dashboard->feeBreakdown() : [],
            'admissionFunnel' => $user->hasPermission('admissions.view') ? $dashboard->admissionFunnel() : [],
            'recentActivity' => $dashboard->recentActivity($user),
            'upcomingEvents' => $dashboard->upcomingEvents(),
            'announcements' => $dashboard->latestAnnouncements(),
        ]);
    }

    /** @return array<int, array{label: string, value: string, note: string}> */
    protected function platformMetrics(): array
    {
        return [
            ['label' => 'Schools', 'value' => (string) School::count(), 'note' => 'Tenants on the platform'],
            ['label' => 'Active schools', 'value' => (string) School::where('is_active', true)->count(), 'note' => 'Currently operating'],
            ['label' => 'Accounts', 'value' => (string) User::count(), 'note' => 'Across every school'],
            ['label' => 'Students', 'value' => (string) Student::acrossSchools()->count(), 'note' => 'Across every school'],
        ];
    }
}
