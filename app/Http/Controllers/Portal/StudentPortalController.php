<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Examination;
use App\Models\GradeScale;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Services\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The student portal.
 *
 * What a student may open is decided entirely by StudentAccess, which reads the
 * per-student switches an administrator sets. Nothing here is hard-coded to a
 * particular student, and every query is bound to their own record.
 */
class StudentPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $student = $this->student($request);
        $abilities = $this->access()->for($student);

        return view('portals.student.dashboard', [
            'student' => $student,
            'abilities' => $abilities,
            'enrollment' => $student->currentEnrollment,
            'attendanceRate' => $abilities['view_attendance'] ? $student->attendanceRate() : null,
            'recentGrades' => $abilities['view_grades'] ? $this->gradesFor($student, 5) : collect(),
            'todayTimetable' => $abilities['view_timetable'] ? $this->timetableFor($student, now()->dayOfWeekIso) : collect(),
            'announcements' => $this->announcements($student),
            'events' => Event::upcoming()->limit(3)->get(),
            'reportCardCount' => $abilities['view_report_cards']
                ? ReportCard::published()->where('student_id', $student->id)->count()
                : 0,
        ]);
    }

    public function grades(Request $request): View
    {
        $student = $this->student($request);

        $this->authorizeAbility($student, 'view_grades');

        return view('portals.student.grades', [
            'student' => $student,
            'abilities' => $this->access()->for($student),
            'grades' => $this->gradesFor($student, 100),
            'reportCards' => ReportCard::published()->where('student_id', $student->id)->with('term')->latest('published_at')->get(),
        ]);
    }

    public function attendance(Request $request): View
    {
        $student = $this->student($request);

        $this->authorizeAbility($student, 'view_attendance');

        return view('portals.student.attendance', [
            'student' => $student,
            'abilities' => $this->access()->for($student),
            'records' => AttendanceRecord::where('student_id', $student->id)->orderByDesc('recorded_on')->paginate(30),
            'rate' => $student->attendanceRate(),
        ]);
    }

    public function timetable(Request $request): View
    {
        $student = $this->student($request);

        $this->authorizeAbility($student, 'view_timetable');

        $sectionId = $student->currentEnrollment?->section_id;

        $entries = $sectionId
            ? TimetableEntry::where('section_id', $sectionId)
                ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
                ->orderBy('day_of_week')
                ->orderBy('starts_at')
                ->get()
                ->groupBy('day_of_week')
            : collect();

        return view('portals.student.timetable', [
            'student' => $student,
            'abilities' => $this->access()->for($student),
            'entriesByDay' => $entries,
            'days' => TimetableEntry::DAYS,
        ]);
    }

    /**
     * The subjects this student takes (spec section 23).
     *
     * Drawn from their class's own subject list, which is what decides who
     * takes what - there is no per-student subject choice - together with the
     * teacher assigned to each in this student's own section.
     */
    public function subjects(Request $request): View
    {
        $student = $this->student($request);
        $this->authorizeAbility($student, 'view_subjects');

        $section = $student->currentEnrollment?->section;

        $subjects = $section?->schoolClass?->subjects()->with('department:id,name')->orderBy('name')->get() ?? collect();

        // Only the teaching for this student's own section, so a student never
        // sees who teaches a different class.
        $teaching = $section
            ? TeachingAssignment::where('section_id', $section->id)
                ->with('teacher:id,first_name,last_name')
                ->get()
                ->groupBy('subject_id')
            : collect();

        return view('portals.student.subjects', [
            'student' => $student,
            'section' => $section,
            'subjects' => $subjects,
            'teaching' => $teaching,
            'abilities' => $this->access()->for($student),
        ]);
    }

    /** The student's own class: where they are, and who teaches it. */
    public function schoolClass(Request $request): View
    {
        $student = $this->student($request);
        $this->authorizeAbility($student, 'view_subjects');

        $section = $student->currentEnrollment?->section;

        return view('portals.student.class', [
            'student' => $student,
            'section' => $section,
            'classTeacher' => $section?->classTeacher,
            /*
             | A headcount, not a list of names. A student has no business
             | reading a roster of their classmates' records, and section 23
             | ends on "students must only see their own records".
             */
            'classmates' => $section
                ? Student::inSection($section->id)->where('status', 'active')->count()
                : 0,
            'teachers' => $section
                ? TeachingAssignment::where('section_id', $section->id)
                    ->with(['teacher:id,first_name,last_name', 'subject:id,name'])
                    ->get()
                : collect(),
            'abilities' => $this->access()->for($student),
        ]);
    }

    /** Examinations covering this student's class. */
    public function exams(Request $request): View
    {
        $student = $this->student($request);
        $this->authorizeAbility($student, 'view_grades');

        $section = $student->currentEnrollment?->section;
        $term = Term::active();

        return view('portals.student.exams', [
            'student' => $student,
            'term' => $term,
            'examinations' => Examination::query()
                ->when($term, fn ($query) => $query->where('term_id', $term->id))
                ->orderBy('starts_on')
                ->get(),
            // The papers this student will actually sit, from their own class.
            'papers' => $section
                ? Assessment::where('section_id', $section->id)
                    ->where('type', 'exam')
                    ->when($term, fn ($query) => $query->where('term_id', $term->id))
                    ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
                    ->orderBy('starts_at')
                    ->get()
                : collect(),
            'abilities' => $this->access()->for($student),
        ]);
    }

    /**
     * The student's own fees.
     *
     * `view_fees` existed as a switch on the permissions screen with nothing
     * behind it - there was no student fees page at all, so an administrator
     * could turn it on and watch nothing happen. It defaults to off, which is
     * the sensible default: many schools would rather discuss money with the
     * parent than with the child.
     */
    public function fees(Request $request): View
    {
        $student = $this->student($request);
        $this->authorizeAbility($student, 'view_fees');

        return view('portals.student.fees', [
            'student' => $student,
            'invoices' => Invoice::where('student_id', $student->id)
                ->with('items')
                ->latest('issued_on')
                ->get(),
            'payments' => Payment::where('student_id', $student->id)->latest('paid_on')->get(),
            'outstandingMinor' => $student->outstandingMinor(),
            'paidMinor' => (int) Payment::where('student_id', $student->id)->sum('amount_minor'),
            'totalMinor' => (int) Invoice::where('student_id', $student->id)->sum('total_minor'),
            'abilities' => $this->access()->for($student),
        ]);
    }

    /** Published report cards, and only this student's own. */
    public function reportCards(Request $request): View
    {
        $student = $this->student($request);
        $this->authorizeAbility($student, 'view_report_cards');

        return view('portals.student.report-cards', [
            'student' => $student,
            'cards' => ReportCard::published()
                ->where('student_id', $student->id)
                ->with(['term', 'academicYear'])
                ->latest('published_at')
                ->get(),
            'abilities' => $this->access()->for($student),
        ]);
    }

    /**
     * Announcements addressed to students. Reading only.
     *
     * Named `announcementsPage` because a protected `announcements()` helper
     * already feeds the dashboard its latest five; this is the full, paginated
     * list and they must not collide.
     */
    public function announcementsPage(Request $request): View
    {
        $student = $this->student($request);

        $section = $student->currentEnrollment?->section;

        return view('portals.student.announcements', [
            'student' => $student,
            'announcements' => Announcement::live()
                ->for('students')
                // A notice aimed at one class reaches that class only.
                ->where(fn ($query) => $query->whereNull('section_id')
                    ->when($section, fn ($q) => $q->orWhere('section_id', $section->id)))
                ->latest('published_at')
                ->paginate(15),
            'abilities' => $this->access()->for($student),
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function student(Request $request): Student
    {
        $student = $request->user()->studentProfile()->with('currentEnrollment.section.schoolClass')->first();

        abort_unless($student !== null, 403, 'This account is not linked to a student record.');

        return $student;
    }

    protected function authorizeAbility(Student $student, string $ability): void
    {
        abort_unless(
            $this->access()->allows($student, $ability),
            403,
            'Your school has not enabled this section of the student portal.'
        );
    }

    /** Approved marks only. */
    protected function gradesFor(Student $student, int $limit): Collection
    {
        return AssessmentScore::query()
            ->where('student_id', $student->id)
            ->whereHas('assessment', fn ($query) => $query->where('status', 'approved'))
            ->with(['assessment.subject', 'assessment.term'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (AssessmentScore $score) {
                $percentage = $score->percentage();

                return (object) [
                    'subject' => $score->assessment?->subject?->name,
                    'title' => $score->assessment?->title,
                    'type' => $score->assessment?->type,
                    'score' => $score->score,
                    'max' => $score->assessment?->max_score,
                    'percentage' => $percentage,
                    'grade' => $percentage === null ? null : GradeScale::forScore($percentage)?->grade,
                    'recordedOn' => $score->created_at,
                ];
            });
    }


    protected function timetableFor(Student $student, int $day): Collection
    {
        $sectionId = $student->currentEnrollment?->section_id;

        if (! $sectionId) {
            return collect();
        }

        return TimetableEntry::where('section_id', $sectionId)
            ->where('day_of_week', $day)
            ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
            ->orderBy('starts_at')
            ->get();
    }

    protected function announcements(Student $student): Collection
    {
        return Announcement::live()
            ->for('students')
            ->where(function ($query) use ($student) {
                $query->whereNull('section_id');

                if ($sectionId = $student->currentEnrollment?->section_id) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->latest('published_at')
            ->limit(5)
            ->get();
    }
    /**
     * Resolved per call rather than injected: the controller instance is cached
     * on the route, so a constructor-injected StudentAccess would keep its
     * memoised permissions alive across requests.
     */
    protected function access(): StudentAccess
    {
        return app(StudentAccess::class);
    }
}