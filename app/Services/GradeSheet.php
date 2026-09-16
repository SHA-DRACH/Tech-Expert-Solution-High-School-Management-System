<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Notifications\GradesAwaitingApproval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One class, one subject, one period (or one semester exam): the marks.
 *
 * Both routes by which marks arrive - typed on the screen, or uploaded from the
 * Excel file - go through `write()`. The file is not a back door: it is checked
 * against exactly the same locks as the screen, because a teacher who cannot
 * type a mark into a closed exam column must not be able to upload one either.
 *
 * The parts of a period grade are ordinary assessments, created the first time
 * a mark is entered against them. A teacher never has to "set up" a period
 * test before recording it, which is what makes the sheet usable as a register:
 * open the class, type the marks.
 */
class GradeSheet
{
    public function __construct(
        private readonly PeriodGrades $grades,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * The columns of a sheet: type => label and what it is marked out of.
     *
     * @return array<string, array{label: string, max: int}>
     */
    public function columns(Term|Semester $sheet): array
    {
        if ($sheet instanceof Semester) {
            return [Assessment::SEMESTER_EXAM => [
                'label' => 'Semester exam',
                'max' => $this->grades->maxFor(Assessment::SEMESTER_EXAM),
            ]];
        }

        return collect(Assessment::PERIOD_COMPONENTS)
            ->map(fn (string $label, string $type) => ['label' => $label, 'max' => $this->grades->maxFor($type)])
            ->all();
    }

    /** "Grade 9A · Mathematics · 1st period" - for titles and file names. */
    public function title(Section $section, ?Subject $subject, Term|Semester $sheet): string
    {
        return collect([
            $section->full_name,
            $subject?->name,
            $sheet instanceof Semester ? $sheet->name.' exam' : $sheet->label(),
        ])->filter()->implode(' · ');
    }

    /**
     * The existing assessment behind each column, keyed by type.
     *
     * @return Collection<string, Assessment>
     */
    public function assessments(Section $section, Subject $subject, Term|Semester $sheet): Collection
    {
        return Assessment::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->when(
                $sheet instanceof Semester,
                fn ($query) => $query->where('semester_id', $sheet->id),
                fn ($query) => $query->where('term_id', $sheet->id),
            )
            ->whereIn('type', array_keys($this->columns($sheet)))
            ->orderBy('id')
            ->get()
            ->unique('type')
            ->keyBy('type');
    }

    /** Marks already recorded, keyed "type.studentId". */
    public function marks(Collection $assessments, Collection $students): Collection
    {
        if ($assessments->isEmpty() || $students->isEmpty()) {
            return collect();
        }

        $typeById = $assessments->mapWithKeys(fn (Assessment $a) => [$a->id => $a->type]);

        return AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy(fn (AssessmentScore $score) => $typeById[$score->assessment_id].'.'.$score->student_id);
    }

    /** May this person put marks against this subject in this class at all? */
    public function canMark(User $user, Section $section, Subject $subject): bool
    {
        if ($user->hasPermission('grades.approve')) {
            return true;
        }

        return $user->hasPermission('grades.enter')
            && $this->teacherFor($user, $section, $subject) !== null;
    }

    /**
     * Why a column cannot be written to, or null when it can.
     *
     * Returned as a sentence rather than a boolean because the screen shows it
     * next to the locked column: "locked" alone sends a teacher to the office
     * to ask why, and the answer is almost always one of these four.
     */
    public function lockReason(User $user, Section $section, Subject $subject, Term|Semester $sheet, ?Assessment $existing): ?string
    {
        if (! $this->canMark($user, $section, $subject)) {
            return 'You do not teach this subject in this class.';
        }

        // The academic office may correct anything - after approval that is
        // the only route left to fixing a transcription error.
        if ($user->hasPermission('grades.approve')) {
            return null;
        }

        if ($sheet instanceof Semester && ! $sheet->exam_entry_open) {
            return 'Exam entry is closed. The administration opens it once the exam has been given.';
        }

        if ($sheet instanceof Term && $sheet->starts_on !== null && $sheet->starts_on->isFuture()) {
            return 'This period has not started yet.';
        }

        if ($existing !== null && ! $existing->isEditable()) {
            return $existing->status === 'approved'
                ? 'Approved. Ask the academic office to correct a mark.'
                : 'Submitted for approval.';
        }

        return null;
    }

    /**
     * Record marks. `$marks` is type => (student id => mark or blank).
     *
     * All or nothing: if any single entry is refused, nothing is written and
     * every problem comes back at once. A half-saved sheet is worse than an
     * unsaved one - the teacher cannot tell which half went in.
     *
     * @param  array<string, array<int|string, mixed>>  $marks
     * @param  \Illuminate\Support\Collection<int, Student>  $roster
     * @return array{saved: int, cleared: int}
     *
     * @throws ValidationException
     */
    public function write(User $user, Section $section, Subject $subject, Term|Semester $sheet, array $marks, Collection $roster, ?string $reason = null): array
    {
        $columns = $this->columns($sheet);
        $existing = $this->assessments($section, $subject, $sheet);
        $current = $this->marks($existing, $roster);
        $rosterById = $roster->keyBy('id');

        $problems = [];
        $changes = [];

        foreach ($marks as $type => $byStudent) {
            if (! isset($columns[$type]) || ! is_array($byStudent)) {
                $problems[] = "\"{$type}\" is not a column on this sheet.";

                continue;
            }

            $assessment = $existing->get($type);
            $max = $assessment ? (float) $assessment->max_score : (float) $columns[$type]['max'];
            $label = $columns[$type]['label'];

            foreach ($byStudent as $studentId => $raw) {
                $student = $rosterById->get((int) $studentId);

                if ($student === null) {
                    $problems[] = "A mark was given for a student who is not in {$section->full_name} for {$subject->name}.";

                    continue;
                }

                $value = is_string($raw) ? trim($raw) : $raw;
                $value = ($value === '' || $value === null) ? null : $value;
                $before = $current->get($type.'.'.$student->id)?->score;

                // Unchanged cells are not writes, so they never trip a lock -
                // a teacher re-uploading a file with a submitted column in it
                // has not tried to change that column.
                $unchanged = $value === null
                    ? $before === null
                    : (is_numeric($value) && $before !== null && (float) $before === (float) $value);

                if ($unchanged) {
                    continue;
                }

                if ($value !== null && (! is_numeric($value) || (float) $value < 0 || (float) $value > $max)) {
                    $problems[] = "{$student->full_name}, {$label}: \"{$raw}\" must be a number from 0 to ".PeriodGrades::format($max).'.';

                    continue;
                }

                if ($lock = $this->lockReason($user, $section, $subject, $sheet, $assessment)) {
                    $problems[] = "{$label}: {$lock}";

                    continue;
                }

                $changes[] = compact('type', 'student', 'value', 'before');
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['marks' => array_values(array_unique($problems))]);
        }

        $saved = 0;
        $cleared = 0;
        $corrections = [];

        DB::transaction(function () use ($changes, $user, $section, $subject, $sheet, $columns, &$existing, &$saved, &$cleared, &$corrections) {
            foreach ($changes as $change) {
                $assessment = $existing->get($change['type']);

                if ($change['value'] === null) {
                    if ($assessment) {
                        $cleared += AssessmentScore::where('assessment_id', $assessment->id)
                            ->where('student_id', $change['student']->id)
                            ->delete();
                    }

                    continue;
                }

                if ($assessment === null) {
                    $assessment = $this->createColumn($user, $section, $subject, $sheet, $change['type'], $columns[$change['type']]);
                    $existing->put($change['type'], $assessment);
                }

                if ($assessment->status === 'approved' && $change['before'] !== null) {
                    $corrections[] = "{$change['student']->full_name}: {$change['before']} → {$change['value']} for \"{$assessment->title}\"";
                }

                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $change['student']->id],
                    ['school_id' => $assessment->school_id, 'score' => $change['value'], 'recorded_by' => $user->id],
                );

                $saved++;
            }
        });

        if ($corrections !== []) {
            $this->audit->log('updated', 'Examinations & grades',
                'Approved marks were corrected: '.implode('; ', $corrections).($reason ? '. Reason: '.$reason : ''));
        }

        return ['saved' => $saved, 'cleared' => $cleared];
    }

    /**
     * Send this sheet's marks to the academic office.
     *
     * Only columns that hold marks and are still the teacher's to change. From
     * here the ordinary approval workflow takes over.
     */
    public function submit(User $user, Section $section, Subject $subject, Term|Semester $sheet): int
    {
        $submitted = 0;

        foreach ($this->assessments($section, $subject, $sheet) as $assessment) {
            if (! $assessment->isEditable() || $assessment->scores()->doesntExist()) {
                continue;
            }

            if ($this->lockReason($user, $section, $subject, $sheet, $assessment) !== null) {
                continue;
            }

            $assessment->update(['status' => 'submitted', 'submitted_at' => now(), 'review_note' => null]);

            $this->audit->log('submitted', 'Examinations & grades',
                "Assessment \"{$assessment->title}\" was submitted for approval.", $assessment);

            $this->notifier->notifyPermission('grades.approve', new GradesAwaitingApproval($assessment));

            $submitted++;
        }

        return $submitted;
    }

    /** The teacher who teaches this subject to this class, if this user is one. */
    public function teacherFor(User $user, Section $section, Subject $subject): ?Teacher
    {
        $teacher = $user->teacherProfile;

        if ($teacher === null) {
            return null;
        }

        return TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->exists() ? $teacher : null;
    }

    /** @param array{label: string, max: int} $column */
    protected function createColumn(User $user, Section $section, Subject $subject, Term|Semester $sheet, string $type, array $column): Assessment
    {
        // Credited to the class's assigned teacher even when the office enters
        // the marks, so the teacher still sees it as their own work.
        $teacherId = TeachingAssignment::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->value('teacher_id') ?? $user->teacherProfile?->id;

        return Assessment::create([
            'school_id' => $section->school_id,
            'academic_year_id' => $sheet->academic_year_id,
            'term_id' => $sheet instanceof Term ? $sheet->id : null,
            'semester_id' => $sheet instanceof Semester ? $sheet->id : null,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherId,
            'title' => $sheet instanceof Semester
                ? $sheet->name.' examination'
                : ucfirst($sheet->label()).' '.strtolower($column['label']),
            'type' => $type,
            'max_score' => $column['max'],
            'weight' => $column['max'],
            'status' => 'draft',
        ]);
    }
}
