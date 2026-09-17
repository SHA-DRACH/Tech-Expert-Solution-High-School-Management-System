<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\DocumentCode;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Section;
use App\Models\SocialLink;
use App\Models\Student;
use App\Services\Gradebook;
use App\Services\ProgressReport;
use App\Services\PublicVisibility;
use App\Services\SchoolSettings;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Online services: the things a family, an employer or another school can do
 * on the public website without an account.
 *
 *   - check an admission application's progress
 *   - check that someone is a student here
 *   - check that a grade sheet or report card is genuine
 *
 * Each answers exactly the question asked and nothing next to it. A lookup
 * that fails says the same thing whichever detail was wrong, so the page can't
 * be used to find out, one guess at a time, which half of a guess was right.
 * Every lookup is rate-limited per visitor, and the details typed in travel by
 * POST, never in a URL where they would sit in logs and browser history.
 */
class OnlineServicesController extends Controller
{
    public function __construct(
        private readonly SchoolContext $context,
        private readonly SchoolSettings $settings,
    ) {}

    public function index(): View
    {
        $school = $this->school();

        return view('public.online-services', [
            'school' => $school,
            'socialLinks' => SocialLink::orderBy('position')->get(),
            'sections' => Section::with('schoolClass')->get()->sortBy(fn (Section $s) => $s->full_name)->values(),
            'lookupByName' => (bool) $this->settings->get('online_lookup_by_name'),
            'admissionsOpen' => (bool) $this->settings->get('admissions_open'),
            'paymentInstructions' => $this->settings->get('online_payment_instructions'),
            'officeHours' => $this->settings->get('online_office_hours'),
            'showFees' => app(PublicVisibility::class)->shows('fees'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Check a student                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Is this a student of the school?
     *
     * By student ID, or by full name together with class - never by name alone,
     * which would turn the page into a directory of which children attend. The
     * answer is the membership and nothing more: a shortened name, the class,
     * and whether they are enrolled now.
     */
    public function checkStudent(Request $request): RedirectResponse
    {
        $this->school();

        $data = $request->validate([
            'by' => ['required', 'in:id,name'],
            'student_number' => ['required_if:by,id', 'nullable', 'string', 'max:40'],
            'full_name' => ['required_if:by,name', 'nullable', 'string', 'max:120'],
            'section_id' => ['required_if:by,name', 'nullable', 'integer'],
        ], [
            'student_number.required_if' => 'Enter the student ID printed on the student’s papers.',
            'full_name.required_if' => 'Enter the student’s full name.',
            'section_id.required_if' => 'Choose the student’s class.',
        ]);

        if ($data['by'] === 'name' && ! $this->settings->get('online_lookup_by_name')) {
            throw ValidationException::withMessages(['full_name' => 'This school checks students by student ID only.']);
        }

        $student = $data['by'] === 'id'
            ? Student::where('student_number', trim((string) $data['student_number']))->first()
            : $this->byNameAndClass((string) $data['full_name'], (int) $data['section_id']);

        return redirect()->to(route('online.index').'#verify-student')
            ->withInput($request->only('by', 'student_number', 'full_name', 'section_id'))
            ->with('studentResult', $student ? $this->membership($student) : ['found' => false]);
    }

    /** Exactly one student in that class this year, with exactly that name. */
    protected function byNameAndClass(string $fullName, int $sectionId): ?Student
    {
        $year = AcademicYear::active();

        if ($year === null) {
            return null;
        }

        $wanted = $this->normaliseName($fullName);

        $matches = Student::whereIn('id', Enrollment::where('section_id', $sectionId)
            ->where('academic_year_id', $year->id)
            ->pluck('student_id'))
            ->get()
            ->filter(fn (Student $student) => in_array($wanted, [
                $this->normaliseName($student->first_name.' '.$student->last_name),
                $this->normaliseName($student->full_name),
                $this->normaliseName($student->last_name.' '.$student->first_name),
            ], true));

        // Two children with the same name in one class cannot be told apart
        // from a name; the student ID can.
        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array<string, mixed> */
    protected function membership(Student $student): array
    {
        $year = AcademicYear::active();

        $enrolment = $year
            ? Enrollment::with('section.schoolClass')->where('student_id', $student->id)->where('academic_year_id', $year->id)->first()
            : null;

        $current = $student->status === 'active' && $enrolment !== null;

        return [
            'found' => true,
            'name' => $this->mask($student->first_name, $student->last_name),
            'current' => $current,
            'status' => $current ? 'Currently enrolled' : Str::headline($student->status),
            'class' => $current ? $enrolment->section?->full_name : null,
            'year' => $current ? $year->name : null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Check a document                                                    */
    /* ------------------------------------------------------------------ */

    public function checkDocument(Request $request): RedirectResponse
    {
        $this->school();

        $data = $request->validate(['code' => ['required', 'string', 'max:30']], [
            'code.required' => 'Enter the verification code printed at the bottom of the document.',
        ]);

        return redirect()->route('online.document.show', DocumentCode::normalise($data['code']));
    }

    /**
     * What the school has on record for the document a code was printed on.
     *
     * The grades are shown, because the code is random: only someone holding
     * the paper can know it, and comparing the paper against the record is the
     * only way an altered grade gets caught. They are the grades as recorded
     * now, so a paper printed before a correction will show the difference.
     */
    public function showDocument(string $code, ProgressReport $reports): View
    {
        $school = $this->school();

        $record = DocumentCode::with(['student', 'academicYear', 'term'])
            ->where('code', DocumentCode::normalise($code))
            ->first();

        $document = null;

        if ($record && $record->student && $record->academicYear) {
            $record->increment('times_checked', 1, ['last_checked_at' => now()]);

            $section = Enrollment::with('section.schoolClass')
                ->where('student_id', $record->student_id)
                ->where('academic_year_id', $record->academic_year_id)
                ->first()?->section;

            if ($section) {
                $report = $reports->forClass($section, $record->academicYear);
                $row = $report['students']->get($record->student_id);

                $document = [
                    'record' => $record,
                    'section' => $section,
                    'report' => $report,
                    'row' => $row,
                    'columns' => $this->columnsFor($record),
                ];
            }
        }

        return view('public.verify-document', [
            'school' => $school,
            'socialLinks' => SocialLink::orderBy('position')->get(),
            'code' => DocumentCode::normalise($code),
            'document' => $document,
            'passMark' => app(Gradebook::class)->passMark(),
        ]);
    }

    /** The columns printed on that document, so the check lines up with the paper. */
    protected function columnsFor(DocumentCode $record): array
    {
        if ($record->type === DocumentCode::REPORT_CARD || $record->term === null) {
            return ['p1', 'p2', 'p3', 'e1', 's1', 'p4', 'p5', 'p6', 'e2', 's2', 'y'];
        }

        $number = $record->term->periodNumber();
        $semester = (int) $record->term->semester;
        $first = ($semester - 1) * 3 + 1;

        $columns = array_map(fn (int $n) => 'p'.$n, range($first, $number));

        if ($number % 3 === 0) {
            array_push($columns, 'e'.$semester, 's'.$semester);
        }

        return $columns;
    }

    /* ------------------------------------------------------------------ */
    /* Check an application                                                */
    /* ------------------------------------------------------------------ */

    /**
     * An application's progress, for the family that made it.
     *
     * Needs the application number *and* the guardian's phone number from the
     * form. An application number alone is printed on a letter that can be
     * lost or photographed; both together belong to the family.
     */
    public function checkApplication(Request $request): RedirectResponse
    {
        $this->school();

        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
        ], [
            'application_number.required' => 'Enter the application number you were given.',
            'phone.required' => 'Enter the guardian phone number used on the application.',
        ]);

        $admission = Admission::where('application_number', trim($data['application_number']))->first();

        $matches = $admission !== null && $this->samePhone($admission->guardian_phone, $data['phone']);

        return redirect()->to(route('online.index').'#application-status')
            ->withInput($request->only('application_number'))
            ->with('applicationResult', $matches ? [
                'found' => true,
                'number' => $admission->application_number,
                'name' => $this->mask($admission->student_first_name, $admission->student_last_name),
                'class' => $admission->intended_class,
                'status' => Str::headline($admission->status),
                'tone' => match ($admission->status) {
                    'approved', 'enrolled' => 'good',
                    'rejected', 'cancelled' => 'bad',
                    'documents_required', 'interview_required' => 'action',
                    default => 'waiting',
                },
                'next' => $this->nextStep($admission->status),
                'submitted' => $admission->created_at?->format('j F Y'),
            ] : ['found' => false]);
    }

    protected function nextStep(string $status): string
    {
        return match ($status) {
            'draft', 'submitted', 'pending' => 'Your application has been received and is waiting to be reviewed.',
            'under_review' => 'The registrar is reviewing your application and documents.',
            'documents_required' => 'The school needs more documents. Please contact or visit the office with them.',
            'interview_required' => 'The school would like to meet the applicant. Please contact the office to arrange a time.',
            'approved' => 'A place has been offered. Visit the school office with the original documents to complete registration.',
            'enrolled' => 'Registration is complete. The student is enrolled.',
            'rejected' => 'The application was not successful this time. The office can tell you more.',
            'cancelled' => 'This application was cancelled. Contact the office if this is unexpected.',
            default => 'Contact the school office for more information.',
        };
    }

    /* ------------------------------------------------------------------ */

    /** The last seven digits agree: "+231 77 000 0000" and "0770000000" match. */
    protected function samePhone(?string $stored, string $given): bool
    {
        $a = preg_replace('/\D/', '', (string) $stored);
        $b = preg_replace('/\D/', '', $given);

        return strlen($a) >= 7 && strlen($b) >= 7 && substr($a, -7) === substr($b, -7);
    }

    protected function normaliseName(string $name): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /** "Mary D." - enough to confirm, not enough to identify. */
    protected function mask(?string $first, ?string $last): string
    {
        $last = trim((string) $last);

        return trim(trim((string) $first).' '.($last !== '' ? Str::upper(mb_substr($last, 0, 1)).'.' : ''));
    }

    protected function school(): School
    {
        abort_unless($this->context->hasSchool(), 404, 'No school is available at this address.');

        return $this->context->school();
    }
}
