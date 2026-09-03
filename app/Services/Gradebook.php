<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\GradeScale;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * A student's results for a term, and the averages drawn from them.
 *
 * One rule governs everything here: **only approved marks count**. A mark a
 * teacher has entered but the academic office has not signed off is not a
 * result yet, and must never reach a parent or contribute to an average. Every
 * query below filters on `status = approved`, and that is the reason this lives
 * in one service rather than being rebuilt in each screen that needs it - three
 * copies of that filter is three chances to forget it.
 *
 * Averages are weighted by each assessment's `weight`, so a final examination
 * counts for more than a class exercise, and are computed from percentages so
 * papers marked out of 40 and out of 100 can be averaged together at all.
 */
class Gradebook
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * One student's approved results for one term, subject by subject.
     *
     * @return array{
     *     subjects: Collection<int, array<string, mixed>>,
     *     average: ?float,
     *     grade: ?string,
     *     passed: ?bool,
     *     assessments: int
     * }
     */
    public function termResults(Student $student, Term $term): array
    {
        $scores = AssessmentScore::query()
            ->where('student_id', $student->id)
            ->whereHas('assessment', fn ($query) => $query
                ->where('term_id', $term->id)
                ->where('status', 'approved'))
            ->with(['assessment.subject:id,name,code', 'assessment.teacher:id,first_name,last_name'])
            ->get();

        $subjects = $scores
            ->filter(fn (AssessmentScore $score) => $score->assessment?->subject !== null)
            ->groupBy(fn (AssessmentScore $score) => $score->assessment->subject_id)
            ->map(function (Collection $group) {
                $first = $group->first()->assessment;

                $percentage = $this->weightedAverage($group);

                return [
                    'subject' => $first->subject,
                    'teacher' => $first->teacher,
                    'assessments' => $group->map(fn (AssessmentScore $score) => [
                        'title' => $score->assessment->title,
                        'type' => $score->assessment->type,
                        'score' => (float) $score->score,
                        'max' => (float) $score->assessment->max_score,
                        'weight' => (float) $score->assessment->weight,
                        'percentage' => $score->percentage(),
                        'remark' => $score->remark,
                    ])->values(),
                    'average' => $percentage,
                    'grade' => $this->gradeFor($percentage),
                    'passed' => $percentage === null ? null : $percentage >= $this->passMark(),
                ];
            })
            ->sortBy(fn (array $row) => $row['subject']->name)
            ->values();

        /*
         | The overall average is the mean of the subject averages, not of every
         | individual mark. Otherwise a subject that happened to be assessed six
         | times would count six times as heavily towards the term result as one
         | assessed twice, which is not what a report card means by "average".
         */
        $subjectAverages = $subjects->pluck('average')->filter(fn ($value) => $value !== null);

        $overall = $subjectAverages->isEmpty()
            ? null
            : round($subjectAverages->sum() / $subjectAverages->count(), 2);

        return [
            'subjects' => $subjects,
            'average' => $overall,
            'grade' => $this->gradeFor($overall),
            'passed' => $overall === null ? null : $overall >= $this->passMark(),
            'assessments' => $scores->count(),
        ];
    }

    /**
     * Every term of a year, so a parent sees progress rather than a snapshot.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function yearResults(Student $student, Collection $terms): Collection
    {
        return $terms->map(function (Term $term) use ($student) {
            $results = $this->termResults($student, $term);

            return [
                'term' => $term,
                'average' => $results['average'],
                'grade' => $results['grade'],
                'passed' => $results['passed'],
                'subjects' => $results['subjects'],
                'assessments' => $results['assessments'],
            ];
        })->values();
    }

    /**
     * A whole class's approved averages for a term, for the admin gradebook.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sectionResults(int $sectionId, Term $term): Collection
    {
        $students = Student::inSection($sectionId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $rows = $students->map(function (Student $student) use ($term) {
            $results = $this->termResults($student, $term);

            return [
                'student' => $student,
                'average' => $results['average'],
                'grade' => $results['grade'],
                'passed' => $results['passed'],
                'subjects' => $results['subjects'],
            ];
        });

        /*
         | Position is worked out here rather than stored, so it is always
         | consistent with the marks currently approved. Students on the same
         | average share a position ("joint 3rd"), and the next position skips
         | accordingly - ranking two identical results differently would be an
         | invention, not a measurement.
         */
        $ordered = $rows->filter(fn (array $row) => $row['average'] !== null)
            ->sortByDesc('average')
            ->values();

        $positions = [];
        $previous = null;
        $position = 0;

        foreach ($ordered as $index => $row) {
            if ($row['average'] !== $previous) {
                $position = $index + 1;
                $previous = $row['average'];
            }

            $positions[$row['student']->id] = $position;
        }

        return $rows->map(function (array $row) use ($positions, $ordered) {
            $row['position'] = $positions[$row['student']->id] ?? null;
            $row['class_size'] = $ordered->count();

            return $row;
        })->values();
    }

    /** The subjects a term's approved marks actually cover, for table headings. */
    public function subjectsTaught(int $sectionId, Term $term): Collection
    {
        return Assessment::query()
            ->where('section_id', $sectionId)
            ->where('term_id', $term->id)
            ->where('status', 'approved')
            ->with('subject:id,name,code')
            ->get()
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * The score at or above which a student has passed.
     *
     * The published grade scale wins where a school has one, and the numeric
     * setting is only the fallback for a school that has not set a scale up.
     *
     * They are two ways of saying the same thing, and letting both speak
     * produced a real contradiction: with a pass mark of 50 and a scale whose
     * bottom band was F for 0-59, a student on 58% was shown as "above the pass
     * mark" and graded F on the same row. A parent reading that cannot tell
     * whether their child passed, which is the one thing the row exists to say.
     *
     * The bottom band - the one with the lowest minimum - is taken as the fail
     * band, so the pass mark is the band above it. A school that wants two
     * failing grades (E and F) is not expressible this way; that needs a flag
     * on the band itself, and until it exists this is the honest reading of a
     * conventional scale rather than a guess dressed up as one.
     */
    public function passMark(): int
    {
        $bands = GradeScale::orderBy('min_score')->get();

        if ($bands->count() < 2) {
            return (int) $this->settings->get('grading_pass_mark');
        }

        return (int) $bands->skip(1)->first()->min_score;
    }

    /** True when the configured pass mark disagrees with the grade scale. */
    public function passMarkDisagreesWithScale(): bool
    {
        $bands = GradeScale::orderBy('min_score')->get();

        if ($bands->count() < 2) {
            return false;
        }

        return (int) $this->settings->get('grading_pass_mark') !== (int) $bands->skip(1)->first()->min_score;
    }

    /** The school's own letter for a percentage, or null if it has no scale. */
    public function gradeFor(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        return GradeScale::orderBy('sequence')
            ->get()
            ->first(fn (GradeScale $band) => $percentage >= $band->min_score && $percentage <= $band->max_score)
            ?->grade;
    }

    /**
     * Weighted mean of a set of scores, as a percentage.
     *
     * Percentages rather than raw marks, because a test out of 40 and an exam
     * out of 100 cannot otherwise be averaged. A weight of zero or missing is
     * treated as 1, so an assessment set up without one still counts.
     */
    protected function weightedAverage(Collection $scores): ?float
    {
        $totalWeight = 0.0;
        $total = 0.0;

        foreach ($scores as $score) {
            $percentage = $score->percentage();

            if ($percentage === null) {
                continue;
            }

            $weight = (float) ($score->assessment->weight ?: 1);

            if ($weight <= 0) {
                $weight = 1;
            }

            $total += $percentage * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($total / $totalWeight, 2) : null;
    }
}
