<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\TimetableEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * A student's weekly class schedule: day, start, end, subject, teacher.
 *
 * Built from the class timetable, so a child and their parent always see the
 * same week, and the file they download is the week they are looking at.
 */
class ClassSchedule
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * The student's lessons, grouped by ISO day (1 = Monday).
     *
     * @return Collection<int, Collection<int, TimetableEntry>>
     */
    public function week(Student $student): Collection
    {
        $sectionId = $student->currentEnrollment?->section_id;

        if (! $sectionId) {
            return collect();
        }

        return TimetableEntry::where('section_id', $sectionId)
            ->with(['subject:id,name', 'teacher:id,first_name,middle_name,last_name'])
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('day_of_week');
    }

    /**
     * The days to show: the school's teaching days, plus any day that has a
     * lesson on it anyway, so a Saturday class is never silently hidden.
     *
     * @return array<int, string>
     */
    public function days(Collection $week): array
    {
        $school = array_map('intval', (array) $this->settings->get('attendance_school_days'));

        return collect(TimetableEntry::DAYS)
            ->filter(fn (string $name, int $day) => in_array($day, $school, true) || $week->has($day))
            ->all();
    }

    /** "8:00 AM", the way a timetable is read aloud. */
    public static function time(?string $value): string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : '';
    }

    /** The lesson running right now, if any. */
    public function now(Collection $week): ?TimetableEntry
    {
        $time = now()->format('H:i:s');

        return ($week->get(now()->dayOfWeekIso) ?? collect())
            ->first(fn (TimetableEntry $entry) => $entry->starts_at <= $time && $entry->ends_at > $time);
    }

    public function workbook(Student $student, ?School $school): Spreadsheet
    {
        $week = $this->week($student);
        $days = $this->days($week);
        $class = $student->currentEnrollment?->section?->full_name ?? '';

        $book = new Spreadsheet;
        $book->getProperties()->setCreator($school?->name ?? '')->setTitle('Class schedule · '.$student->full_name);

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Weekly schedule');

        $sheet->setCellValueExplicit('A1', (string) ($school?->name ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', 'Class schedule · '.$student->full_name.($class ? ' · '.$class : ''), DataType::TYPE_STRING);
        $sheet->setCellValue('A3', 'Downloaded '.now()->format('j F Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->fromArray(['Day', 'Starts', 'Ends', 'Subject', 'Teacher', 'Room'], null, 'A5');
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        ]);

        $row = 6;

        foreach ($days as $day => $name) {
            foreach ($week->get($day) ?? [] as $entry) {
                $sheet->setCellValue('A'.$row, $name);
                $sheet->setCellValue('B'.$row, self::time($entry->starts_at));
                $sheet->setCellValue('C'.$row, self::time($entry->ends_at));
                $sheet->setCellValueExplicit('D'.$row, (string) $entry->subject?->name, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.$row, (string) $entry->teacher?->full_name, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F'.$row, (string) $entry->room, DataType::TYPE_STRING);
                $row++;
            }
        }

        if ($row === 6) {
            $sheet->setCellValue('A6', 'No lessons have been published for this class yet.');
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $book;
    }
}
