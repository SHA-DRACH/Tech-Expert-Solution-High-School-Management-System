<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\Notifier;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** Marks that are worth telling a family about the same day. */
    protected const ALERT_ON = ['absent', 'late'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.view'), 403);

        $date = $request->date('date') ?? now();
        $sectionId = $request->integer('section') ?: null;

        $sections = Section::with('schoolClass')->get()->sortBy('full_name');

        $section = $sectionId ? Section::find($sectionId) : $sections->first();

        $students = $section
            ? Student::inSection($section->id)->orderBy('last_name')->get()
            : collect();

        $existing = $section
            ? AttendanceRecord::where('section_id', $section->id)
                ->whereDate('recorded_on', $date->toDateString())
                ->get()
                ->keyBy('student_id')
            : collect();

        return view('attendance.index', [
            'sections' => $sections,
            'section' => $section,
            'students' => $students,
            'existing' => $existing,
            'date' => $date,
            'statuses' => AttendanceRecord::STATUSES,
            'summary' => $this->summary($date),
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.record'), 403);

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'array'],
            'status.*' => [Rule::in(AttendanceRecord::STATUSES)],
        ]);

        $section = Section::findOrFail($data['section_id']);
        $year = AcademicYear::active();
        $term = Term::active();

        abort_unless($year !== null, 422, 'Set up an academic year before recording attendance.');

        /*
         | Alerts go out only where the mark actually changed to absent or
         | late. Re-saving a register must not send a family the same message
         | again, so the previous status is compared before notifying.
         */
        $toAlert = [];

        DB::transaction(function () use ($data, $section, $year, $term, $request, &$toAlert) {
            foreach ($data['status'] as $studentId => $status) {
                // Only students actually in this section may be marked.
                $student = Student::inSection($section->id)->find($studentId);

                if ($student === null) {
                    continue;
                }

                /*
                 | Matched with whereDate rather than updateOrCreate on the
                 | date column. The `date` cast stores "2026-09-02 00:00:00",
                 | which MySQL coerces back to a DATE on comparison but SQLite
                 | does not, so an exact match would miss the existing row and
                 | try to insert a duplicate.
                 */
                $existing = AttendanceRecord::where('student_id', $student->id)
                    ->whereDate('recorded_on', $data['date'])
                    ->first();

                $previousStatus = $existing?->status;

                $record = $existing ?? new AttendanceRecord([
                    'student_id' => $student->id,
                    'recorded_on' => $data['date'],
                ]);

                $record->fill([
                    'school_id' => $section->school_id,
                    'section_id' => $section->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term?->id,
                    'status' => $status,
                    'recorded_by' => $request->user()->id,
                ])->save();

                if (in_array($status, self::ALERT_ON, true) && $previousStatus !== $status) {
                    $toAlert[] = $record->id;
                }
            }
        });

        $alerted = $this->sendAlerts($toAlert, $notifier);

        $message = 'Attendance saved for '.$section->full_name.'.';

        if ($alerted > 0) {
            $message .= ' '.$alerted.' '.Str::plural('family', $alerted).' notified.';
        }

        return back()->with('status', $message);
    }

    /**
     * @param  array<int, int>  $recordIds
     * @return int the number of families told
     */
    protected function sendAlerts(array $recordIds, Notifier $notifier): int
    {
        if ($recordIds === []) {
            return 0;
        }

        // Some schools tell a guardian the same day; others handle absence
        // face to face and would rather the software stayed quiet.
        if (! app(SchoolSettings::class)->get('attendance_notify_guardians')) {
            return 0;
        }

        $records = AttendanceRecord::whereIn('id', $recordIds)
            ->with('student.guardians')
            ->get();

        $before = \Illuminate\Notifications\DatabaseNotification::count();

        $records->each(fn (AttendanceRecord $record) => $notifier->attendanceAlert($record));

        return \Illuminate\Notifications\DatabaseNotification::count() - $before;
    }

    /** @return array<string, int> */
    protected function summary($date): array
    {
        $counts = AttendanceRecord::whereDate('recorded_on', $date->toDateString())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceRecord::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }
}
