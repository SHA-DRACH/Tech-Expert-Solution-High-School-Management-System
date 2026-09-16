<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Period grades, semester averages and the yearly average.
 *
 * The arithmetic of a Liberian high-school grade sheet:
 *
 *   period grade      the marks for that period added up (period test,
 *                     quiz, assignment, attendance) over the marks possible
 *   semester average  (1st + 2nd + 3rd period + semester exam) / 4
 *   yearly average    (first semester + second semester) / 2
 *
 * It lives in one service because the teacher's screen, the class summary and
 * the Excel file must never disagree about a child's average.
 *
 * **An average is only given when everything it is made of exists.** A semester
 * average worked out from two periods and no exam is a number that looks
 * official and is not - it is higher or lower than the real one will be, and a
 * parent who sees it cannot tell. So a missing part leaves the average blank,
 * and the sheet shows exactly which part is missing.
 *
 * `$approvedOnly` separates the official record from a teacher's working copy.
 * Parents, report cards and the default export see approved marks only; a
 * teacher entering marks sees what they have entered, labelled provisional.
 */
class PeriodGrades
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /** What each part of a period grade, and the exam, is marked out of. */
    public function maxFor(string $type): int
    {
        return match ($type) {
            'period_test' => (int) $this->settings->get('grading_max_period_test'),
            'quiz' => (int) $this->settings->get('grading_max_quiz'),
            'assignment' => (int) $this->settings->get('grading_max_assignment'),
            'attendance' => (int) $this->settings->get('grading_max_attendance'),
            Assessment::SEMESTER_EXAM => (int) $this->settings->get('grading_max_semester_exam'),
            default => 100,
        };
    }

    /**
     * The year's marking periods, numbered 1-6 in calendar order.
     *
     * @return Collection<int, Term> keyed by period number
     */
    public function periods(AcademicYear $year): Collection
    {
        return Term::where('academic_year_id', $year->id)
            ->whereNotNull('semester')
            ->orderBy('sequence')
            ->get()
            ->values()
            ->mapWithKeys(fn (Term $term, int $index) => [$index + 1 => $term]);
    }

    /**
     * The period the school is in: the one marked current, else the one whose
     * dates include today, else the first.
     */
    public function currentPeriod(AcademicYear $year): ?Term
    {
        $periods = $this->periods($year);
        $today = now()->toDateString();

        return $periods->firstWhere('is_current', true)
            ?? $periods->first(fn (Term $term) => $term->starts_on && $term->ends_on
                && $term->starts_on->toDateString() <= $today && $term->ends_on->toDateString() >= $today)
            ?? $periods->first();
    }

    /** @return Collection<int, Semester> keyed by semester number */
    public function semesters(AcademicYear $year): Collection
    {
        return Semester::where('academic_year_id', $year->id)
            ->orderBy('number')
            ->get()
            ->keyBy('number');
    }

    /**
     * One subject's grade sheet for a class: every student, every period.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Student>  $students
     * @return Collection<int, array{
     *     student: \App\Models\Student,
     *     periods: array<int, ?float>,
     *     exams: array<int, ?float>,
     *     semesters: array<int, ?float>,
     *     yearly: ?float
     * }>
     */
    public function subjectSheet(Section $section, Subject $subject, AcademicYear $year, Collection $students, bool $approvedOnly = true): Collection
    {
        $periods = $this->periods($year);
        $semesters = $this->semesters($year);

        $assessments = Assessment::query()
            ->where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where(fn ($query) => $query
                ->whereIn('term_id', $periods->pluck('id'))
                ->orWhereIn('semester_id', $semesters->pluck('id')))
            ->get();

        $scores = $assessments->isEmpty() || $students->isEmpty()
            ? collect()
            : AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('student_id');

        return $students->map(function ($student) use ($periods, $semesters, $assessments, $scores, $approvedOnly) {
            $mine = ($scores->get($student->id) ?? collect())->keyBy('assessment_id');

            $periodGrades = [];

            foreach ($periods as $number => $term) {
                $periodGrades[$number] = $this->percentage(
                    $assessments->where('term_id', $term->id)
                        ->where('type', '!=', Assessment::SEMESTER_EXAM),
                    $mine,
                    $approvedOnly,
                );
            }

            $exams = [];
            $semesterAverages = [];

            foreach ([1, 2] as $semesterNumber) {
                $semester = $semesters->get($semesterNumber);

                $exams[$semesterNumber] = $semester
                    ? $this->percentage(
                        $assessments->where('semester_id', $semester->id)
                            ->where('type', Assessment::SEMESTER_EXAM),
                        $mine,
                        $approvedOnly,
                    )
                    : null;

                $parts = collect($periods)
                    ->filter(fn (Term $term) => (int) $term->semester === $semesterNumber)
                    ->keys()
                    ->map(fn (int $number) => $periodGrades[$number]);

                $semesterAverages[$semesterNumber] = $this->completeMean(
                    $parts->push($exams[$semesterNumber]),
                    Semester::PERIODS_PER_SEMESTER + 1,
                );
            }

            return [
                'student' => $student,
                'periods' => $periodGrades,
                'exams' => $exams,
                'semesters' => $semesterAverages,
                'yearly' => $this->completeMean(collect($semesterAverages), 2),
            ];
        })->values();
    }

    /**
     * Marks obtained over marks possible, as a percentage - or null.
     *
     * Added up, the way a Liberian teacher works a period grade out by hand:
     * a period test out of 40, a quiz, an assignment and attendance out of 20
     * each, and the grade is the total. No weights. An earlier version weighted
     * each piece of work, and a test recorded under the old term system with a
     * weight of 1 quietly turned 34 + 17 + 18 + 19 into 87.53 instead of 88 -
     * a number no teacher could reproduce and no parent could check.
     *
     * A missing mark makes the grade incomplete rather than quietly smaller or
     * larger. If a quiz was set and one child has no mark for it, the rest would
     * give that child a grade built from different work to everyone else's; a
     * blank sends the teacher back to the missing mark instead. Work that was
     * never set in a period is simply not part of its grade.
     */
    protected function percentage(Collection $assessments, Collection $scoresByAssessment, bool $approvedOnly = false): ?float
    {
        $obtained = 0.0;
        $possible = 0.0;

        foreach ($assessments as $assessment) {
            if ((float) $assessment->max_score <= 0) {
                continue;
            }

            // The official grade waits for the whole period to be signed off,
            // not just whichever part the office reached first.
            if ($approvedOnly && $assessment->status !== 'approved') {
                return null;
            }

            $score = $scoresByAssessment->get($assessment->id);

            if ($score === null || $score->score === null) {
                return null;
            }

            $obtained += (float) $score->score;
            $possible += (float) $assessment->max_score;
        }

        return $possible > 0 ? round($obtained / $possible * 100, 2) : null;
    }

    /** The mean, but only when every one of the expected parts is present. */
    protected function completeMean(Collection $parts, int $expected): ?float
    {
        $present = $parts->filter(fn ($value) => $value !== null);

        if ($parts->count() < $expected || $present->count() < $expected) {
            return null;
        }

        return round($present->sum() / $expected, 2);
    }

    /** "78.5" rather than "78.50", and a dash for nothing. */
    public static function format(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
