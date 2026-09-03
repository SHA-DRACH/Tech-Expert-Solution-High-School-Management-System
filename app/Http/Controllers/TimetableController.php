<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Timetable management (spec section 31).
 *
 * The grid can be viewed by class or by teacher. Every change is checked for
 * clashes first: a teacher cannot be in two rooms at once, a class cannot sit
 * two lessons at once, and a room cannot hold two classes at once.
 */
class TimetableController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('timetable.view'), 403);

        $view = $request->string('view')->trim()->toString() ?: 'class';

        $sections = Section::with('schoolClass')->get()->sortBy('full_name')->values();
        $teachers = Teacher::where('status', 'active')->orderBy('last_name')->get();

        $section = $view === 'class'
            ? ($request->integer('section') ? Section::find($request->integer('section')) : $sections->first())
            : null;

        $teacher = $view === 'teacher'
            ? ($request->integer('teacher') ? Teacher::find($request->integer('teacher')) : $teachers->first())
            : null;

        $entries = TimetableEntry::query()
            ->with(['subject:id,name', 'teacher:id,first_name,last_name', 'section.schoolClass'])
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->when($teacher, fn ($query) => $query->where('teacher_id', $teacher->id))
            ->when(! $section && ! $teacher, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        return view('timetable.index', [
            'mode' => $view,
            'sections' => $sections,
            'teachers' => $teachers,
            'section' => $section,
            'teacher' => $teacher,
            'entriesByDay' => $entries->groupBy('day_of_week'),
            'days' => TimetableEntry::DAYS,
            'subjects' => Subject::orderBy('name')->get(),
            'canManage' => $request->user()->hasPermission('timetable.manage'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('timetable.manage'), 403);

        $data = $this->validated($request);

        $section = Section::findOrFail($data['section_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        $teacher = isset($data['teacher_id']) ? Teacher::findOrFail($data['teacher_id']) : null;

        $year = AcademicYear::active();

        abort_unless($year !== null, 422, 'Set up an academic year before building a timetable.');

        $this->guardAgainstClashes($data, $section, $teacher);

        $entry = TimetableEntry::create([
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher?->id,
            'day_of_week' => $data['day_of_week'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'room' => $data['room'] ?? null,
        ]);

        $audit->log('created', 'Timetable',
            "A {$subject->name} lesson was added to {$section->full_name} on ".TimetableEntry::DAYS[$data['day_of_week']].'.',
            $entry);

        return back()->with('status', 'Lesson added to the timetable.');
    }

    public function update(Request $request, TimetableEntry $timetableEntry, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('timetable.manage'), 403);
        abort_unless($timetableEntry->school_id === $request->user()->school_id, 403);

        $data = $this->validated($request);

        $section = Section::findOrFail($data['section_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        $teacher = isset($data['teacher_id']) ? Teacher::findOrFail($data['teacher_id']) : null;

        $this->guardAgainstClashes($data, $section, $teacher, $timetableEntry->id);

        $timetableEntry->update([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher?->id,
            'day_of_week' => $data['day_of_week'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'room' => $data['room'] ?? null,
        ]);

        $audit->log('updated', 'Timetable', 'A timetable lesson was changed.', $timetableEntry);

        return back()->with('status', 'Lesson updated.');
    }

    public function destroy(Request $request, TimetableEntry $timetableEntry, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('timetable.manage'), 403);
        abort_unless($timetableEntry->school_id === $request->user()->school_id, 403);

        $timetableEntry->delete();

        $audit->log('deleted', 'Timetable', 'A timetable lesson was removed.');

        return back()->with('status', 'Lesson removed.');
    }

    /* ------------------------------------------------------------------ */

    protected function validated(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'day_of_week' => ['required', Rule::in(array_keys(TimetableEntry::DAYS))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:40'],
        ], [
            'ends_at.after' => 'The lesson must end after it starts.',
        ]);
    }

    /**
     * Refuse a lesson that would double-book a class, a teacher or a room.
     *
     * Two lessons overlap when each starts before the other ends, which is
     * cheaper and more reliable than comparing every boundary case by hand.
     */
    protected function guardAgainstClashes(array $data, Section $section, ?Teacher $teacher, ?int $ignoreId = null): void
    {
        $overlapping = TimetableEntry::query()
            ->where('day_of_week', $data['day_of_week'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));

        $classClash = (clone $overlapping)->where('section_id', $section->id)->first();

        if ($classClash) {
            throw ValidationException::withMessages([
                'starts_at' => $section->full_name.' already has '.
                    ($classClash->subject?->name ?? 'a lesson').' at that time.',
            ]);
        }

        if ($teacher) {
            $teacherClash = (clone $overlapping)->where('teacher_id', $teacher->id)->first();

            if ($teacherClash) {
                throw ValidationException::withMessages([
                    'teacher_id' => $teacher->full_name.' is already teaching '.
                        ($teacherClash->section?->full_name ?? 'another class').' at that time.',
                ]);
            }
        }

        if (! empty($data['room'])) {
            $roomClash = (clone $overlapping)->where('room', $data['room'])->first();

            if ($roomClash) {
                throw ValidationException::withMessages([
                    'room' => $data['room'].' is already in use by '.
                        ($roomClash->section?->full_name ?? 'another class').' at that time.',
                ]);
            }
        }
    }
}
