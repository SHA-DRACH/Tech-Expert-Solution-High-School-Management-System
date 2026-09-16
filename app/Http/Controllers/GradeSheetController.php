<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMarkingScope;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\GradeSheet;
use App\Services\GradeSheetWorkbook;
use App\Services\PeriodGrades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The period grade sheet: pick a class, a subject and a period, type the marks.
 *
 * Rows are the students who take the subject; columns are the parts of a period
 * grade - period test, quiz, assignment, attendance - or, for a semester, the
 * examination. The same sheet goes out as an Excel file and comes back in, and
 * both routes write through `GradeSheet`, so the locks are identical.
 *
 * Every id in the query string is re-resolved through the tenant-scoped models
 * and then checked against what this person may mark (§59): a teacher who
 * edits the URL to another class's id gets that class refused, not opened.
 */
class GradeSheetController extends Controller
{
    use ResolvesMarkingScope;

    public function __construct(
        private readonly GradeSheet $gradeSheet,
        private readonly PeriodGrades $grades,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->context($request);

        $students = $context['section'] && $context['subject']
            ? $this->roster($context['section'], $context['subject'])
            : collect();

        $assessments = $context['ready']
            ? $this->gradeSheet->assessments($context['section'], $context['subject'], $context['sheet'])
            : collect();

        $columns = $context['sheet'] ? $this->gradeSheet->columns($context['sheet']) : [];

        $locks = collect($columns)->map(fn ($column, $type) => $context['ready']
            ? $this->gradeSheet->lockReason($request->user(), $context['section'], $context['subject'], $context['sheet'], $assessments->get($type))
            : null);

        // The teacher's working figure, from whatever they have entered so far.
        $provisional = $context['ready'] && $context['year']
            ? $this->grades->subjectSheet($context['section'], $context['subject'], $context['year'], $students, approvedOnly: false)
                ->keyBy(fn (array $row) => $row['student']->id)
            : collect();

        return view('gradesheet.index', $context + [
            'students' => $students,
            'columns' => $columns,
            'assessments' => $assessments,
            'marks' => $this->gradeSheet->marks($assessments, $students),
            'locks' => $locks,
            'provisional' => $provisional,
            'periodNumber' => $context['sheet'] instanceof Term ? $context['sheet']->periodNumber() : null,
            // Other work already recorded in this period - older tests, or
            // anything set from Assessments - still counts towards the grade,
            // and the sheet says so rather than showing an unexplained number.
            'otherWork' => $context['ready'] && $context['sheet'] instanceof Term
                ? \App\Models\Assessment::where('section_id', $context['section']->id)
                    ->where('subject_id', $context['subject']->id)
                    ->where('term_id', $context['sheet']->id)
                    ->whereNotIn('id', $assessments->pluck('id'))
                    ->orderBy('id')
                    ->get(['id', 'title', 'status'])
                : collect(),
            'canSubmit' => $assessments->contains(fn ($a) => $a->isEditable()) && $locks->filter()->count() < count($columns),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->context($request, strict: true);

        $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['array'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->gradeSheet->write(
            $request->user(),
            $context['section'],
            $context['subject'],
            $context['sheet'],
            $request->input('marks'),
            $this->roster($context['section'], $context['subject']),
            $request->input('reason'),
        );

        return redirect()->to($this->sheetUrl($context))->with('status', $this->outcome($result));
    }

    public function submit(Request $request): RedirectResponse
    {
        $context = $this->context($request, strict: true);

        $count = $this->gradeSheet->submit($request->user(), $context['section'], $context['subject'], $context['sheet']);

        return redirect()->to($this->sheetUrl($context))->with('status', $count > 0
            ? "{$count} ".Str::plural('column', $count).' sent to the academic office for approval.'
            : 'Nothing to submit: enter marks first, or they have already been submitted.');
    }

    /** One Excel workbook for the class and period, a worksheet per subject. */
    public function download(Request $request, GradeSheetWorkbook $workbook): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('grades.export'), 403, 'Downloading grade sheets is not switched on for your account.');

        $context = $this->context($request, strict: true, needsSubject: false);

        $subjects = $context['subjects'];

        abort_if($subjects->isEmpty(), 404, 'There are no subjects you can mark in this class.');

        $book = $workbook->build(
            $request->user()->school,
            $context['section'],
            $context['sheet'],
            $subjects,
            fn (Subject $subject) => $this->roster($context['section'], $subject),
        );

        $name = Str::slug($this->gradeSheet->title($context['section'], null, $context['sheet'])).'.xlsx';

        app(AuditLogger::class)->log('exported', 'Examinations & grades',
            'The grade sheet for '.$this->gradeSheet->title($context['section'], null, $context['sheet']).' was downloaded.');

        return response()->streamDownload(function () use ($book) {
            IOFactory::createWriter($book, 'Xlsx')->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Read a filled-in workbook back.
     *
     * All or nothing across every subject in the file. If one mark anywhere is
     * refused, nothing from the file is kept and every problem is listed - so a
     * teacher never has to work out which half of their upload went in.
     */
    public function upload(Request $request, GradeSheetWorkbook $workbook): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('grades.import'), 403, 'Uploading grade sheets is not switched on for your account.');

        $context = $this->context($request, strict: true, needsSubject: false);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], ['file.mimes' => 'Upload the Excel file (.xlsx) downloaded from this page.']);

        $data = $workbook->read($request->file('file'));

        // The file's own label, checked against the page it was uploaded on.
        // It authorises nothing; it only stops a 9B file landing on 9A.
        if ($data['section'] !== $context['section']->id || $data['sheet'] !== $workbook->sheetKey($context['sheet'])) {
            throw ValidationException::withMessages(['file' => 'This file is for a different class or period. Open that class and period, then upload it there.']);
        }

        $allowed = $context['subjects']->keyBy('id');
        $problems = [];
        $saved = 0;
        $cleared = 0;

        DB::beginTransaction();

        try {
            foreach ($data['subjects'] as $subjectId => $sheetData) {
                $subject = $allowed->get($subjectId);

                if ($subject === null) {
                    $problems[] = "{$sheetData['title']}: you cannot enter marks for this subject in {$context['section']->full_name}.";

                    continue;
                }

                $roster = $this->roster($context['section'], $subject);
                $byNumber = $roster->keyBy(fn ($student) => (string) $student->student_number);
                $marks = [];

                foreach ($sheetData['rows'] as $row) {
                    $student = $byNumber->get($row['number']);

                    if ($student === null) {
                        $problems[] = "{$subject->name}, row {$row['line']}: student number {$row['number']} is not in this class for {$subject->name}.";

                        continue;
                    }

                    foreach ($row['marks'] as $type => $value) {
                        $marks[$type][$student->id] = $value;
                    }
                }

                if ($marks === []) {
                    continue;
                }

                try {
                    $result = $this->gradeSheet->write($request->user(), $context['section'], $subject, $context['sheet'], $marks, $roster);
                    $saved += $result['saved'];
                    $cleared += $result['cleared'];
                } catch (ValidationException $e) {
                    foreach ($e->errors()['marks'] ?? [] as $message) {
                        $problems[] = "{$subject->name}: {$message}";
                    }
                }
            }

            if ($problems !== []) {
                DB::rollBack();

                return redirect()->to($this->sheetUrl($context))
                    ->withErrors(['file' => 'Nothing from the file was saved. Fix these and upload it again:'])
                    ->with('uploadProblems', $problems);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        app(AuditLogger::class)->log('imported', 'Examinations & grades',
            'Marks were uploaded for '.$this->gradeSheet->title($context['section'], null, $context['sheet']).": {$saved} saved, {$cleared} cleared.");

        return redirect()->to($this->sheetUrl($context))
            ->with('status', 'File uploaded. '.$this->outcome(['saved' => $saved, 'cleared' => $cleared]));
    }

    /**
     * The whole year for one class and subject: six periods, two exams, both
     * semester averages and the yearly average.
     */
    public function summary(Request $request): View
    {
        $context = $this->context($request, allowViewers: true);

        // The official record unless someone who can mark asks for the working copy.
        $official = ! $context['canMark'] || $request->boolean('official');

        $rows = $context['section'] && $context['subject'] && $context['year']
            ? $this->grades->subjectSheet(
                $context['section'],
                $context['subject'],
                $context['year'],
                $this->roster($context['section'], $context['subject']),
                approvedOnly: $official,
            )
            : collect();

        return view('gradesheet.summary', $context + [
            'rows' => $rows,
            'official' => $official,
            'periods' => $context['year'] ? $this->grades->periods($context['year']) : collect(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Resolve the class, subject and sheet this request is about.
     *
     * @return array<string, mixed>
     */
    protected function context(Request $request, bool $strict = false, bool $needsSubject = true, bool $allowViewers = false): array
    {
        $user = $request->user();
        $canMark = $user->hasPermission('grades.enter') || $user->hasPermission('grades.approve');
        $canView = $canMark || ($allowViewers && $user->hasPermission('reportcards.view'));

        abort_unless($canView, 403);

        $teacher = $user->teacherProfile;
        // A teacher sees only the classes they teach; the office sees them all.
        // No teacher record narrows an account to no classes; it never widens
        // it to all of them.
        $restricted = ! $user->hasPermission('grades.approve') && ! ($allowViewers && $user->hasPermission('reportcards.view'));

        $year = AcademicYear::active();
        $periods = $year ? $this->grades->periods($year) : collect();
        $semesters = $year ? $this->grades->semesters($year) : collect();

        $sections = $this->sectionsFor($teacher, $restricted);
        $section = $request->integer('section') ? $sections->firstWhere('id', $request->integer('section')) : $sections->first();

        $subjects = $section ? $this->subjectsFor($section, $teacher, $restricted) : collect();
        $subject = $request->integer('subject') ? $subjects->firstWhere('id', $request->integer('subject')) : $subjects->first();

        $sheet = $this->resolveSheet((string) $request->input('sheet'), $periods, $semesters);

        if ($strict) {
            // A POST names exactly what it writes to. A missing or foreign id is
            // refused rather than quietly replaced by the first class on the list.
            abort_if($request->integer('section') === 0 || $section === null, 404);
            abort_if($needsSubject && ($request->integer('subject') === 0 || $subject === null), 404);
            abort_if(! $request->filled('sheet') || $sheet === null, 404);
        }

        return [
            'year' => $year,
            'periods' => $periods,
            'semesters' => $semesters,
            'sections' => $sections,
            'section' => $section,
            'subjects' => $subjects,
            'subject' => $subject,
            'sheet' => $sheet,
            'sheetKey' => $sheet ? app(GradeSheetWorkbook::class)->sheetKey($sheet) : null,
            'ready' => $section !== null && $subject !== null && $sheet !== null,
            'canMark' => $canMark,
            'canExport' => $user->hasPermission('grades.export'),
            'canImport' => $user->hasPermission('grades.import'),
            'subjectsUnavailable' => $section !== null && $subjects->isEmpty(),
        ];
    }

    /** "period:12" / "exam:3", or the period the school is currently in. */
    protected function resolveSheet(string $key, Collection $periods, Collection $semesters): Term|Semester|null
    {
        if (preg_match('/^(period|exam):(\d+)$/', $key, $m)) {
            return $m[1] === 'period'
                ? $periods->firstWhere('id', (int) $m[2])
                : $semesters->firstWhere('id', (int) $m[2]);
        }

        if ($key !== '') {
            return null;
        }

        $year = AcademicYear::active();

        return $year ? $this->grades->currentPeriod($year) : null;
    }

    /** @param array<string, mixed> $context */
    protected function sheetUrl(array $context): string
    {
        return route('gradesheet.index', array_filter([
            'section' => $context['section']?->id,
            'subject' => $context['subject']?->id,
            'sheet' => $context['sheetKey'],
        ]));
    }

    /** @param array{saved: int, cleared: int} $result */
    protected function outcome(array $result): string
    {
        return trim(
            ($result['saved'] > 0 ? $result['saved'].' '.Str::plural('mark', $result['saved']).' saved. ' : '')
            .($result['cleared'] > 0 ? $result['cleared'].' '.Str::plural('mark', $result['cleared']).' cleared.' : '')
        ) ?: 'No changes.';
    }
}
