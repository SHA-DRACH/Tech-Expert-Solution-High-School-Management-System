<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\ResolvesMarkingScope;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Services\PeriodGrades;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The teacher portal.
 *
 * A teacher reaches only the sections and subjects they hold a teaching
 * assignment for. Every lookup goes through that assignment table rather than
 * trusting a section id from the request.
 */
class TeacherPortalController extends Controller
{
    use ResolvesMarkingScope;

    public function dashboard(Request $request): View
    {
        $teacher = $this->teacher($request);
        $assignments = $this->assignments($teacher);

        $sectionIds = $assignments->pluck('section_id')->unique();

        return view('portals.teacher.dashboard', [
            'teacher' => $teacher,
            'assignments' => $assignments,
            'sectionCount' => $sectionIds->count(),
            'subjectCount' => $assignments->pluck('subject_id')->unique()->count(),
            'studentCount' => $this->studentQuery($sectionIds)->count(),
            'pendingGrades' => Assessment::where('teacher_id', $teacher->id)
                ->whereIn('status', ['draft', 'submitted'])
                ->count(),
            'todayTimetable' => TimetableEntry::where('teacher_id', $teacher->id)
                ->where('day_of_week', now()->dayOfWeekIso)
                ->with(['subject:id,name', 'section.schoolClass'])
                ->orderBy('starts_at')
                ->get(),
            /*
             | An attendance *rate*, not a count of rows. The dashboard used to
             | compute a raw record count and then never display it, which was
             | just as well: "412 records" tells a teacher nothing, where "91%
             | over the last 30 days" tells them whether their classes are
             | turning up.
             */
            'attendanceRate' => $this->attendanceRate($sectionIds),
            'markedToday' => AttendanceRecord::whereIn('section_id', $sectionIds)
                ->whereDate('recorded_on', now()->toDateString())
                ->distinct()
                ->count('section_id'),
            'recentAssessments' => Assessment::where('teacher_id', $teacher->id)
                ->with(['subject:id,name', 'section.schoolClass'])
                ->latest()
                ->limit(6)
                ->get(),
            'announcements' => Announcement::live()->for('teachers')->latest('published_at')->limit(4)->get(),
            'currentPeriod' => $period = ($year = AcademicYear::active()) ? app(PeriodGrades::class)->currentPeriod($year) : null,
            'marking' => $this->markingProgress($assignments, $period),
        ]);
    }

    /**
     * Each class and subject this teacher marks, with how far the current
     * period's marks have got - the list a teacher works down at period end.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function markingProgress(Collection $assignments, ?Term $period): Collection
    {
        return $assignments
            ->filter(fn ($assignment) => $assignment->section && $assignment->subject)
            ->sortBy(fn ($assignment) => $assignment->section->full_name.' '.$assignment->subject->name)
            ->map(function ($assignment) use ($period) {
                $roster = $this->roster($assignment->section, $assignment->subject);

                $assessments = $period
                    ? Assessment::where('section_id', $assignment->section_id)
                        ->where('subject_id', $assignment->subject_id)
                        ->where('term_id', $period->id)
                        ->get()
                    : collect();

                $marked = $assessments->isEmpty() ? 0 : AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
                    ->whereIn('student_id', $roster->pluck('id'))
                    ->distinct()
                    ->count('student_id');

                return [
                    'section' => $assignment->section,
                    'subject' => $assignment->subject,
                    'students' => $roster->count(),
                    'marked' => $marked,
                    'status' => match (true) {
                        $assessments->isEmpty() => 'not started',
                        $assessments->every(fn ($a) => $a->status === 'approved') => 'approved',
                        $assessments->contains(fn ($a) => $a->status === 'submitted') => 'submitted',
                        default => 'draft',
                    },
                ];
            })
            ->values();
    }

    public function classes(Request $request): View
    {
        $teacher = $this->teacher($request);
        $assignments = $this->assignments($teacher);

        $sections = Section::whereIn('id', $assignments->pluck('section_id'))
            ->with('schoolClass')
            ->withCount('enrollments')
            ->get()
            ->map(function (Section $section) use ($assignments) {
                $section->setAttribute(
                    'my_subjects',
                    $assignments->where('section_id', $section->id)->pluck('subject.name')->filter()->values()
                );

                return $section;
            });

        return view('portals.teacher.classes', [
            'teacher' => $teacher,
            'sections' => $sections,
        ]);
    }

    public function students(Request $request): View
    {
        $teacher = $this->teacher($request);
        $assignments = $this->assignments($teacher);
        $sectionIds = $assignments->pluck('section_id')->unique();

        $search = $request->string('search')->trim()->toString();
        $sectionFilter = $request->integer('section') ?: null;

        // A section filter is only honoured if the teacher is assigned to it.
        if ($sectionFilter && ! $sectionIds->contains($sectionFilter)) {
            abort(403, 'You are not assigned to that class.');
        }

        $students = $this->studentQuery($sectionFilter ? collect([$sectionFilter]) : $sectionIds)
            ->search($search)
            ->with('currentEnrollment.section.schoolClass')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('portals.teacher.students', [
            'teacher' => $teacher,
            'students' => $students,
            'search' => $search,
            'sectionFilter' => $sectionFilter,
            'sections' => Section::whereIn('id', $sectionIds)->with('schoolClass')->get(),
        ]);
    }

    public function timetable(Request $request): View
    {
        $teacher = $this->teacher($request);

        return view('portals.teacher.timetable', [
            'teacher' => $teacher,
            'entriesByDay' => TimetableEntry::where('teacher_id', $teacher->id)
                ->with(['subject:id,name', 'section.schoolClass'])
                ->orderBy('day_of_week')
                ->orderBy('starts_at')
                ->get()
                ->groupBy('day_of_week'),
            'days' => TimetableEntry::DAYS,
        ]);
    }

    /**
     * The subjects this teacher takes, and the classes they take them to.
     *
     * The same information exists inside "My classes", arranged by class. A
     * teacher who takes one subject across six sections thinks about it the
     * other way round, and had no screen that showed it that way.
     */
    public function subjects(Request $request): View
    {
        $teacher = $this->teacher($request);
        $assignments = $this->assignments($teacher);

        $year = AcademicYear::active();
        $term = Term::active();

        $subjects = $assignments
            ->filter(fn ($assignment) => $assignment->subject !== null)
            ->groupBy('subject_id')
            ->map(function (Collection $group) use ($teacher, $term) {
                $sections = $group->pluck('section')->filter()->unique('id')->values();
                $sectionIds = $sections->pluck('id');

                return [
                    'subject' => $group->first()->subject,
                    'sections' => $sections,
                    'students' => $this->studentQuery($sectionIds)->where('status', 'active')->count(),
                    'assessments' => Assessment::where('teacher_id', $teacher->id)
                        ->where('subject_id', $group->first()->subject_id)
                        ->when($term, fn ($q) => $q->where('term_id', $term->id))
                        ->count(),
                    // Marks still with the teacher: nobody else can move these on.
                    'awaiting' => Assessment::where('teacher_id', $teacher->id)
                        ->where('subject_id', $group->first()->subject_id)
                        ->whereIn('status', ['draft', 'rejected'])
                        ->count(),
                ];
            })
            ->sortBy(fn (array $row) => $row['subject']->name)
            ->values();

        return view('portals.teacher.subjects', [
            'teacher' => $teacher,
            'subjects' => $subjects,
            'year' => $year,
            'term' => $term,
        ]);
    }

    /**
     * Work the teacher has set, and who has handed it in.
     *
     * Submissions were only visible inside one assessment's marking screen, so
     * "who still owes me work?" could not be asked across a teacher's classes
     * at all - which is the question that actually gets asked.
     */
    public function assignmentsIndex(Request $request): View
    {
        $teacher = $this->teacher($request);

        $assessments = Assessment::where('teacher_id', $teacher->id)
            ->where('type', 'assignment')
            ->with(['subject:id,name', 'section.schoolClass'])
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->get();

        $expected = $assessments
            ->pluck('section_id')
            ->unique()
            ->mapWithKeys(fn (int $sectionId) => [
                $sectionId => Student::inSection($sectionId)->where('status', 'active')->count(),
            ]);

        $submissions = AssignmentSubmission::whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->groupBy('assessment_id');

        return view('portals.teacher.assignments', [
            'teacher' => $teacher,
            'rows' => $assessments->map(function (Assessment $assessment) use ($expected, $submissions) {
                $handed = $submissions->get($assessment->id, collect());

                return [
                    'assessment' => $assessment,
                    'expected' => $expected->get($assessment->section_id, 0),
                    'handed' => $handed->count(),
                    'late' => $handed->filter(fn (AssignmentSubmission $s) => $s->isLate())->count(),
                    'closed' => $assessment->ends_at !== null && $assessment->ends_at->isPast(),
                ];
            }),
        ]);
    }

    /** Announcements addressed to teaching staff. Reading only. */
    public function announcements(Request $request): View
    {
        return view('portals.teacher.announcements', [
            'teacher' => $this->teacher($request),
            'announcements' => Announcement::live()
                ->for('teachers')
                ->with('section.schoolClass')
                ->latest('published_at')
                ->paginate(15),
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function teacher(Request $request): Teacher
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->first();

        abort_unless($teacher !== null, 403, 'This account is not linked to a teacher record.');

        return $teacher;
    }

    /** @return Collection<int, \App\Models\TeachingAssignment> */
    protected function assignments(Teacher $teacher): Collection
    {
        return $teacher->teachingAssignments()
            ->with(['section.schoolClass', 'subject:id,name'])
            ->get();
    }

    /**
     * Attendance across these classes over the last 30 days, as a percentage.
     *
     * Null rather than 0 when nothing has been recorded: a class whose register
     * has never been taken is not a class with nobody in it, and showing 0%
     * would read as a crisis rather than as missing data.
     */
    protected function attendanceRate(Collection $sectionIds): ?int
    {
        if ($sectionIds->isEmpty()) {
            return null;
        }

        $records = AttendanceRecord::whereIn('section_id', $sectionIds->all())
            ->where('recorded_on', '>=', now()->subDays(30)->toDateString())
            ->get(['status']);

        if ($records->isEmpty()) {
            return null;
        }

        // Late still counts as present: the child was in the room.
        return (int) round(($records->whereIn('status', ['present', 'late'])->count() / $records->count()) * 100);
    }

    /** Students in the given sections, and nowhere else. */
    protected function studentQuery(Collection $sectionIds)
    {
        return Student::whereHas(
            'enrollments',
            fn ($query) => $query->whereIn('section_id', $sectionIds->all())
        );
    }
}
