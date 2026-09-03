<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Student;
use App\Notifications\GradesAwaitingApproval;
use App\Services\AuditLogger;
use App\Services\CsvImporter;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A teacher uploading a class's marks as a spreadsheet (spec sections 36-37).
 *
 * Marks are matched to students by student number, not by name. Two children
 * called Mary Doe in one section is not an edge case in a Liberian school, and
 * awarding one of them the other's mark is the single worst thing this feature
 * could do.
 *
 * Uploading **locks the marks**. Once a file is confirmed the scores are written
 * and the assessment is submitted for approval in the same transaction, so the
 * teacher can no longer change them: `AssessmentPolicy::enterScores()` requires
 * the assessment to be editable, and a submitted one is not. If a mark is wrong
 * the academic office rejects the assessment, which returns it to the teacher to
 * correct - a reviewable route, rather than a quiet edit after the fact.
 */
class MarkImportController extends Controller
{
    public function create(Assessment $assessment): View
    {
        $this->authorize('enterScores', $assessment);

        $assessment->load(['section.schoolClass', 'subject', 'term']);

        return view('assessments.import', [
            'assessment' => $assessment,
            'students' => $this->roster($assessment),
            'preview' => session('mark_preview'),
        ]);
    }

    /** Step one: read the file and show exactly what would be recorded. */
    public function preview(Request $request, Assessment $assessment, CsvImporter $importer): RedirectResponse
    {
        $this->authorize('enterScores', $assessment);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:5120'],
        ], [], ['file' => 'CSV file']);

        $result = $importer->read($request->file('file'));

        if ($result['error'] !== null) {
            return back()->withErrors(['file' => $result['error']]);
        }

        $missing = $importer->missingColumns($result['headings'], ['student_number', 'score']);

        if ($missing !== []) {
            return back()->withErrors([
                'file' => 'That file needs a '.collect($missing)->join(' and a ').' column. '
                    .'Download the mark sheet below to get a file with the right headings and your class already in it.',
            ]);
        }

        $checked = $this->check($assessment, $result['rows']);

        if ($checked['valid'] === []) {
            return back()
                ->with('mark_preview', ['assessment_id' => $assessment->id] + $checked)
                ->withErrors(['file' => 'No row in that file could be matched to a student in this class.']);
        }

        return back()->with('mark_preview', ['assessment_id' => $assessment->id] + $checked);
    }

    /** Step two: write the marks and hand them to the academic office. */
    public function store(Assessment $assessment, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        $this->authorize('enterScores', $assessment);

        $preview = session('mark_preview');

        if (! is_array($preview) || (int) ($preview['assessment_id'] ?? 0) !== $assessment->id || empty($preview['valid'])) {
            return redirect()
                ->route('assessments.marks.import', $assessment)
                ->withErrors(['file' => 'That preview has expired. Upload the file again.']);
        }

        // Re-checked at the moment of writing rather than trusted from the
        // session, which is data that came back from the browser.
        $checked = $this->check($assessment, collect($preview['valid']));

        $written = 0;

        DB::transaction(function () use ($assessment, $checked, &$written) {
            foreach ($checked['valid'] as $row) {
                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $row['student_id']],
                    [
                        'school_id' => $assessment->school_id,
                        'score' => $row['score'],
                        'remark' => $row['remark'] ?: null,
                        'recorded_by' => auth()->id(),
                    ],
                );

                $written++;
            }

            /*
             | Locked in the same transaction that writes them. Saving the marks
             | and leaving the assessment editable would let a teacher upload a
             | file and then quietly change a mark afterwards, which is exactly
             | what the upload is supposed to prevent.
             */
            $assessment->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'review_note' => null,
            ]);
        });

        $audit->log(
            'submitted',
            'Examinations & grades',
            "{$written} marks were uploaded for \"{$assessment->title}\" and submitted for approval.",
            $assessment,
        );

        $notifier->notifyPermission('grades.approve', new GradesAwaitingApproval($assessment));

        return redirect()
            ->route('assessments.scores', $assessment)
            ->with('status', "{$written} marks uploaded and submitted for approval. They are locked until the academic office reviews them.");
    }

    /** A mark sheet with the class already in it, ready to fill in. */
    public function template(Assessment $assessment, \App\Services\CsvExporter $csv)
    {
        $this->authorize('enterScores', $assessment);

        $existing = AssessmentScore::where('assessment_id', $assessment->id)
            ->pluck('score', 'student_id');

        return $csv->stream(
            'marks-'.\Illuminate\Support\Str::slug($assessment->title).'-'.now()->format('Y-m-d'),
            ['student_number', 'student_name', 'score', 'remark'],
            $this->roster($assessment)->map(fn (Student $student) => [
                $student->student_number,
                // Present for the teacher to read while filling the sheet in.
                // Matching is done on the number alone: two children can share
                // a name, and they must never share a mark.
                $student->full_name,
                $existing[$student->id] ?? '',
                '',
            ]),
        );
    }

    /* ------------------------------------------------------------ checking */

    /** @return \Illuminate\Database\Eloquent\Collection<int, Student> */
    protected function roster(Assessment $assessment)
    {
        return Student::inSection($assessment->section_id)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Validate every row on its own against this class's roster.
     *
     * @return array{valid: array<int, array<string, mixed>>, problems: array<int, array{line: int, reference: string, reason: string}>}
     */
    protected function check(Assessment $assessment, Collection $rows): array
    {
        $roster = $this->roster($assessment)->keyBy(fn (Student $s) => strtolower(trim($s->student_number)));

        $valid = [];
        $problems = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = (int) ($row['__line'] ?? 0);
            $number = strtolower(trim((string) ($row['student_number'] ?? '')));
            $raw = trim((string) ($row['score'] ?? ''));

            if ($number === '') {
                $problems[] = ['line' => $line, 'reference' => '(blank)', 'reason' => 'No student number.'];

                continue;
            }

            $student = $roster->get($number);

            if ($student === null) {
                $problems[] = [
                    'line' => $line,
                    'reference' => $row['student_number'],
                    // Named plainly: a number from another class is a different
                    // mistake from a number that does not exist at all.
                    'reason' => 'No active student with that number is in this class.',
                ];

                continue;
            }

            if (isset($seen[$number])) {
                $problems[] = [
                    'line' => $line,
                    'reference' => $row['student_number'],
                    'reason' => "This student also appears on line {$seen[$number]} of the file.",
                ];

                continue;
            }

            // A blank score is "not marked yet", not zero. Recording it as zero
            // would fail a child who simply has not sat the paper.
            if ($raw === '') {
                $problems[] = [
                    'line' => $line,
                    'reference' => $student->full_name,
                    'reason' => 'No mark given. Left blank rather than recorded as zero.',
                ];

                continue;
            }

            if (! is_numeric($raw)) {
                $problems[] = [
                    'line' => $line,
                    'reference' => $student->full_name,
                    'reason' => "\"{$raw}\" is not a number.",
                ];

                continue;
            }

            $score = (float) $raw;

            if ($score < 0 || $score > (float) $assessment->max_score) {
                $problems[] = [
                    'line' => $line,
                    'reference' => $student->full_name,
                    'reason' => "A mark of {$raw} is outside 0 to {$assessment->max_score}.",
                ];

                continue;
            }

            $seen[$number] = $line;

            $valid[] = [
                '__line' => $line,
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'student_name' => $student->full_name,
                'score' => $score,
                'remark' => trim((string) ($row['remark'] ?? '')),
            ];
        }

        return ['valid' => $valid, 'problems' => $problems];
    }
}
