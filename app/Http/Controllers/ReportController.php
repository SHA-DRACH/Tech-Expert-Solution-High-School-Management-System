<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return view('reports.index', [
            'enrollmentByClass' => SchoolClass::withCount('enrollments')->orderBy('level')->get(),
            'studentsByStatus' => Student::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'studentsByGender' => Student::selectRaw('gender, COUNT(*) as total')->groupBy('gender')->pluck('total', 'gender'),
            'admissionsByStatus' => Admission::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'attendanceByStatus' => AttendanceRecord::where('recorded_on', '>=', now()->subDays(30))
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'collectedMinor' => (int) Payment::sum('amount_minor'),
            'invoicedMinor' => (int) Invoice::sum('total_minor'),
            'outstandingMinor' => Invoice::outstanding()->get()->sum(fn (Invoice $i) => $i->balanceMinor()),
        ]);
    }
}
