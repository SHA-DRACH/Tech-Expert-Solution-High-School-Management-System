<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ResolvesMarkingScope;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\PeriodConduct;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Everything printed on a grade sheet or a report card, for a whole class.
 *
 * Built for the class at once because two figures on every student's paper -
 * class rank and number of students - only exist relative to everyone else.
 * Working them out one student at a time would either repeat the whole class's
 * arithmetic per child or let two children's papers disagree about who came
 * first.
 *
 * Official marks only (approved), as everything a family receives is. Every
 * grade comes from PeriodGrades, so a report card can never disagree with the
 * grade sheet a teacher entered it on.
 *
 * Columns are keyed p1-p6 (periods), e1-e2 (semester exams), s1-s2 (semester
 * averages) and y (the year).
 */
class ProgressReport
{
    use ResolvesMarkingScope;

    public const COLUMNS = ['p1', 'p2', 'p3', 'e1', 's1', 'p4', 'p5', 'p6', 'e2', 's2', 'y'];

    public function __construct(private readonly PeriodGrades $grades) {}

    /**
     * @return array{
     *     section: Section, year: AcademicYear, periods: Collection, semesters: Collection,
     *     subjects: Collection, classSize: int, students: Collection
     * }
     */
    public function forClass(Section $section, AcademicYear $year): array
    {
        $section->loadMissing(['schoolClass', 'classTeacher']);

        $periods = $this->grades->periods($year);
        $semesters = $this->grades->semesters($year);

        $subjects = Subject::whereIn('id', $section->schoolClass?->subjects()->pluck('subjects.id') ?? [])
            ->orderBy('name')
            ->get();

        $roster = $this->roster($section);

        // grades[studentId][subjectId][column]
        $grades = [];
        $takes = [];

        foreach ($subjects as $subject) {
            $takers = $this->roster($section, $subject);

            foreach ($this->grades->subjectSheet($section, $subject, $year, $takers) as $row) {
                $id = $row['student']->id;
                $takes[$id][] = $subject->id;
                $grades[$id][$subject->id] = [
                    'p1' => $row['periods'][1] ?? null, 'p2' => $row['periods'][2] ?? null, 'p3' => $row['periods'][3] ?? null,
                    'e1' => $row['exams'][1], 's1' => $row['semesters'][1],
                    'p4' => $row['periods'][4] ?? null, 'p5' => $row['periods'][5] ?? null, 'p6' => $row['periods'][6] ?? null,
                    'e2' => $row['exams'][2], 's2' => $row['semesters'][2],
                    'y' => $row['yearly'],
                ];
            }
        }

        $students = $roster->map(function (Student $student) use ($grades, $takes) {
            $mine = $grades[$student->id] ?? [];
            $averages = [];

            foreach (self::COLUMNS as $column) {
                $values = collect($takes[$student->id] ?? [])->map(fn ($subjectId) => $mine[$subjectId][$column] ?? null);

                /*
                 | Only when every subject the student takes has a grade for the
                 | column. An average of the subjects that happen to be in yet
                 | is a different number from the real one, and it gets copied
                 | onto paper.
                 */
                $averages[$column] = $values->isEmpty() || $values->contains(null)
                    ? null
                    : round($values->sum() / $values->count(), 1);
            }

            return [
                'student' => $student,
                'takes' => $takes[$student->id] ?? [],
                'grades' => $mine,
                'averages' => $averages,
            ];
        });

        $ranks = $this->ranks($students);

        $attendance = $this->attendance($roster, $periods);
        $conduct = PeriodConduct::whereIn('student_id', $roster->pluck('id'))
            ->whereIn('term_id', $periods->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $students = $students->map(function (array $row) use ($ranks, $attendance, $conduct, $periods) {
            $id = $row['student']->id;
            $row['ranks'] = $ranks[$id] ?? [];
            $row['attendance'] = $attendance[$id] ?? [];
            $row['conduct'] = $periods->mapWithKeys(fn ($term, $number) => [
                $number => ($conduct->get($id) ?? collect())->firstWhere('term_id', $term->id)?->conduct,
            ])->all();

            return $row;
        })->keyBy(fn (array $row) => $row['student']->id);

        return [
            'section' => $section,
            'year' => $year,
            'periods' => $periods,
            'semesters' => $semesters,
            'subjects' => $subjects,
            'classSize' => $roster->count(),
            'students' => $students,
        ];
    }

    /**
     * Class rank per column. Equal averages share a rank ("joint 3rd") and the
     * next rank skips, the same rule the gradebook already uses.
     *
     * @return array<int, array<string, int>>
     */
    protected function ranks(Collection $students): array
    {
        $ranks = [];

        foreach (self::COLUMNS as $column) {
            $ordered = $students
                ->filter(fn (array $row) => $row['averages'][$column] !== null)
                ->sortByDesc(fn (array $row) => $row['averages'][$column])
                ->values();

            $previous = null;
            $rank = 0;

            foreach ($ordered as $index => $row) {
                if ($row['averages'][$column] !== $previous) {
                    $rank = $index + 1;
                    $previous = $row['averages'][$column];
                }

                $ranks[$row['student']->id][$column] = $rank;
            }
        }

        return $ranks;
    }

    /**
     * Days present and absent per period, counted by the period's dates.
     *
     * By date rather than by the term stored on the record: periods can be
     * redrawn after attendance was taken, and the date is what actually says
     * which period a day belongs to. Late counts as present; excused as absent.
     *
     * @return array<int, array<int, array{present: int, absent: int}>>
     */
    protected function attendance(Collection $roster, Collection $periods): array
    {
        $result = [];

        foreach ($periods as $number => $term) {
            if (! $term->starts_on || ! $term->ends_on) {
                continue;
            }

            $counts = AttendanceRecord::whereIn('student_id', $roster->pluck('id'))
                ->whereDate('recorded_on', '>=', $term->starts_on->toDateString())
                ->whereDate('recorded_on', '<=', $term->ends_on->toDateString())
                ->selectRaw('student_id, status, COUNT(*) as total')
                ->groupBy('student_id', 'status')
                ->get();

            foreach ($counts as $count) {
                $bucket = in_array($count->status, ['present', 'late'], true) ? 'present' : 'absent';
                $result[$count->student_id][$number] ??= ['present' => 0, 'absent' => 0];
                $result[$count->student_id][$number][$bucket] += (int) $count->total;
            }
        }

        return $result;
    }
}
