<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\GradeScale;
use App\Models\ReportCard;
use App\Models\ReportCardItem;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds report cards for a section and term (spec section 38).
 *
 * Only approved assessments count. Work a teacher has entered but not had
 * signed off never reaches a report card, which is the whole point of the
 * approval step.
 *
 * Each subject mark is the weighted average of that subject's assessments,
 * expressed as a percentage so subjects marked out of different totals can be
 * compared. Positions are ranked within the section, sharing a place on a tie.
 */
class GenerateReportCards
{
    /** @return int the number of report cards written */
    public function handle(Section $section, Term $term, ?string $status = 'draft'): int
    {
        $students = Student::inSection($section->id)->orderBy('last_name')->get();

        if ($students->isEmpty()) {
            return 0;
        }

        $assessments = Assessment::approved()
            ->where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->get();

        if ($assessments->isEmpty()) {
            return 0;
        }

        $scores = AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $scale = GradeScale::orderBy('sequence')->get();

        // Every student's per-subject percentages, worked out once so positions
        // can be ranked across the section afterwards.
        $results = $students->mapWithKeys(fn (Student $student) => [
            $student->id => $this->subjectAverages($assessments, $scores->get($student->id, collect())),
        ]);

        $overall = $results->map(
            fn (Collection $subjects) => $subjects->isEmpty() ? null : round($subjects->avg(), 2)
        );

        $ranking = $this->rank($overall);
        $subjectRanking = $this->rankPerSubject($results);

        $attendance = $this->attendanceFor($students, $term);

        return DB::transaction(function () use (
            $students, $section, $term, $results, $overall, $ranking,
            $subjectRanking, $attendance, $scale, $status
        ) {
            $written = 0;

            foreach ($students as $student) {
                $subjects = $results[$student->id];

                if ($subjects->isEmpty()) {
                    continue;
                }

                $card = ReportCard::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year_id' => $term->academic_year_id,
                        'term_id' => $term->id,
                    ],
                    [
                        'school_id' => $section->school_id,
                        'school_class_id' => $section->school_class_id,
                        'section_id' => $section->id,
                        'average' => $overall[$student->id],
                        'position' => $ranking[$student->id] ?? null,
                        'class_size' => $overall->filter()->count(),
                        'days_present' => $attendance[$student->id]['present'] ?? null,
                        'days_total' => $attendance[$student->id]['total'] ?? null,
                        'status' => $status,
                        'published_at' => $status === 'published' ? now() : null,
                    ],
                );

                // Rewritten each run so a regenerated card never keeps a stale subject.
                $card->items()->delete();

                foreach ($subjects as $subjectId => $percentage) {
                    $band = $this->bandFor($scale, $percentage);

                    ReportCardItem::create([
                        'school_id' => $section->school_id,
                        'report_card_id' => $card->id,
                        'subject_id' => $subjectId,
                        'score' => $percentage,
                        'grade' => $band?->grade,
                        'remark' => $band?->remark,
                        'position' => $subjectRanking[$subjectId][$student->id] ?? null,
                    ]);
                }

                $written++;
            }

            return $written;
        });
    }

    /**
     * Weighted average per subject, as a percentage.
     *
     * @return Collection<int, float> subject id => percentage
     */
    protected function subjectAverages(Collection $assessments, Collection $studentScores): Collection
    {
        $byAssessment = $studentScores->keyBy('assessment_id');

        return $assessments
            ->groupBy('subject_id')
            ->map(function (Collection $subjectAssessments) use ($byAssessment) {
                $weighted = 0.0;
                $weight = 0.0;

                foreach ($subjectAssessments as $assessment) {
                    $score = $byAssessment->get($assessment->id)?->score;

                    if ($score === null || ! $assessment->max_score) {
                        continue;
                    }

                    $percentage = ((float) $score / $assessment->max_score) * 100;
                    $assessmentWeight = (float) ($assessment->weight ?: 1);

                    $weighted += $percentage * $assessmentWeight;
                    $weight += $assessmentWeight;
                }

                return $weight > 0 ? round($weighted / $weight, 2) : null;
            })
            ->filter(fn (?float $average) => $average !== null);
    }

    /**
     * Rank highest first, sharing a place on a tie: 1, 2, 2, 4.
     *
     * @return array<int, int> student id => position
     */
    protected function rank(Collection $averages): array
    {
        $ordered = $averages->filter()->sortDesc();

        $positions = [];
        $place = 0;
        $seen = 0;
        $previous = null;

        foreach ($ordered as $studentId => $average) {
            $seen++;

            if ($average !== $previous) {
                $place = $seen;
                $previous = $average;
            }

            $positions[$studentId] = $place;
        }

        return $positions;
    }

    /** @return array<int, array<int, int>> subject id => (student id => position) */
    protected function rankPerSubject(Collection $results): array
    {
        $bySubject = [];

        foreach ($results as $studentId => $subjects) {
            foreach ($subjects as $subjectId => $percentage) {
                $bySubject[$subjectId][$studentId] = $percentage;
            }
        }

        return collect($bySubject)->map(fn (array $scores) => $this->rank(collect($scores)))->all();
    }

    /** @return array<int, array{present: int, total: int}> */
    protected function attendanceFor(Collection $students, Term $term): array
    {
        $records = AttendanceRecord::whereIn('student_id', $students->pluck('id'))
            ->when($term->starts_on, fn ($query) => $query->where('recorded_on', '>=', $term->starts_on))
            ->when($term->ends_on, fn ($query) => $query->where('recorded_on', '<=', $term->ends_on))
            ->get(['student_id', 'status']);

        return $records
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => [
                'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                'total' => $rows->count(),
            ])
            ->all();
    }

    protected function bandFor(Collection $scale, float $percentage): ?GradeScale
    {
        return $scale->first(
            fn (GradeScale $band) => $percentage >= $band->min_score && $percentage <= $band->max_score
        );
    }
}
