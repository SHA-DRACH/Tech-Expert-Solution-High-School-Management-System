<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\CsvExporter;
use App\Support\Money;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports (spec section 57).
 *
 * Every export is permission-gated and tenant-scoped like the screen it comes
 * from, and each one is written to the audit trail because exporting is how
 * data leaves the building.
 */
class ExportController extends Controller
{
    public function students(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('students.export'), 403);

        $year = AcademicYear::active();

        // Every filter the screen offers has to be honoured here too, or the
        // file quietly holds more students than the list it was exported from.
        $students = Student::with(['currentEnrollment.section.schoolClass', 'guardians'])
            ->search($request->string('search')->trim()->toString())
            ->when($request->string('status')->trim()->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('class') ?: null, fn ($q, $class) => $q->whereHas(
                'enrollments',
                fn ($inner) => $inner
                    ->where('school_class_id', $class)
                    ->when($year, fn ($e) => $e->where('academic_year_id', $year->id))
            ))
            ->orderBy('last_name')
            ->get();

        $audit->log('exported', 'Students', $students->count().' student records were exported.');

        return $csv->stream(
            'students-'.now()->format('Y-m-d'),
            ['Student number', 'First name', 'Last name', 'Gender', 'Date of birth', 'Class', 'Status', 'Parent or guardian', 'Guardian phone'],
            $students->map(fn (Student $student) => [
                $student->student_number,
                $student->first_name,
                $student->last_name,
                $student->gender,
                $student->date_of_birth?->toDateString(),
                $student->currentEnrollment?->section?->full_name,
                $student->status,
                $student->guardians->map->full_name->join('; '),
                $student->guardians->pluck('phone')->filter()->join('; '),
            ]),
        );
    }

    public function guardians(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('guardians.view'), 403);

        $guardians = Guardian::with('students')->orderBy('last_name')->get();

        $audit->log('exported', 'Parents & guardians', $guardians->count().' guardian records were exported.');

        return $csv->stream(
            'guardians-'.now()->format('Y-m-d'),
            ['First name', 'Last name', 'Phone', 'Email', 'Occupation', 'Children'],
            $guardians->map(fn (Guardian $guardian) => [
                $guardian->first_name,
                $guardian->last_name,
                $guardian->phone,
                $guardian->email,
                $guardian->occupation,
                $guardian->students->map->full_name->join('; '),
            ]),
        );
    }

    public function attendance(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('attendance.report'), 403);

        $from = $request->date('from') ?? now()->subMonth();
        $to = $request->date('to') ?? now();

        $records = AttendanceRecord::with(['student:id,first_name,last_name,student_number', 'section.schoolClass'])
            ->whereBetween('recorded_on', [$from, $to])
            ->orderBy('recorded_on')
            ->get();

        $audit->log('exported', 'Attendance',
            $records->count().' attendance records were exported for '.
            $from->format('j M Y').' to '.$to->format('j M Y').'.');

        return $csv->stream(
            'attendance-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d'),
            ['Date', 'Student number', 'Student', 'Class', 'Status', 'Note'],
            $records->map(fn (AttendanceRecord $record) => [
                $record->recorded_on->toDateString(),
                $record->student?->student_number,
                $record->student?->full_name,
                $record->section?->full_name,
                $record->status,
                $record->remark,
            ]),
        );
    }

    /**
     * The staff list, matching whatever filters the screen is showing.
     *
     * The columns are deliberately the same ones the importer reads back, so an
     * export can be edited in a spreadsheet and re-imported without a school
     * having to rearrange it by hand.
     */
    public function teachers(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('teachers.view'), 403);

        $teachers = Teacher::with(['department:id,name', 'user:id,email'])
            ->search($request->string('search')->trim()->toString())
            ->when($request->integer('department') ?: null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->string('status')->trim()->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_name')
            ->get();

        $audit->log('exported', 'Teachers', $teachers->count().' staff records were exported.');

        return $csv->stream(
            'teachers-'.now()->format('Y-m-d'),
            [
                'staff_number', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
                'phone', 'email', 'address', 'department', 'employment_type', 'hired_on',
                'experience_years', 'status', 'has_login', 'published',
            ],
            $teachers->map(fn (Teacher $teacher) => [
                $teacher->staff_number,
                $teacher->first_name,
                $teacher->middle_name,
                $teacher->last_name,
                $teacher->gender,
                $teacher->date_of_birth?->toDateString(),
                $teacher->phone,
                $teacher->email,
                $teacher->address,
                $teacher->department?->name,
                $teacher->employment_type,
                $teacher->hired_on?->toDateString(),
                $teacher->experience_years,
                $teacher->status,
                $teacher->user_id ? 'yes' : 'no',
                $teacher->is_public ? 'yes' : 'no',
            ]),
        );
    }

    public function payments(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('finance.report'), 403);

        $payments = Payment::with(['student:id,first_name,last_name,student_number', 'receivedBy:id,name'])
            ->orderBy('paid_on')
            ->get();

        $audit->log('exported', 'Finance', $payments->count().' payment records were exported.');

        return $csv->stream(
            'payments-'.now()->format('Y-m-d'),
            ['Receipt', 'Date', 'Student number', 'Student', 'Amount', 'Method', 'Reference', 'Received by'],
            $payments->map(fn (Payment $payment) => [
                $payment->receipt_number,
                $payment->paid_on->toDateString(),
                $payment->student?->student_number,
                $payment->student?->full_name,
                Money::format($payment->amount_minor, false),
                $payment->method,
                $payment->reference,
                $payment->receivedBy?->name,
            ]),
        );
    }

    public function outstanding(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('finance.report'), 403);

        $invoices = Invoice::outstanding()
            ->with(['student.guardians'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceMinor() > 0);

        $audit->log('exported', 'Finance', $invoices->count().' outstanding invoices were exported.');

        return $csv->stream(
            'outstanding-fees-'.now()->format('Y-m-d'),
            ['Invoice', 'Student number', 'Student', 'Total', 'Paid', 'Balance', 'Due', 'Guardian phone'],
            $invoices->map(fn (Invoice $invoice) => [
                $invoice->invoice_number,
                $invoice->student?->student_number,
                $invoice->student?->full_name,
                Money::format($invoice->total_minor, false),
                Money::format($invoice->paid_minor, false),
                Money::format($invoice->balanceMinor(), false),
                $invoice->due_on?->toDateString(),
                $invoice->student?->guardians->pluck('phone')->filter()->join('; '),
            ]),
        );
    }
}
