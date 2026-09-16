<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Which classes, subjects and students a person may mark.
 *
 * Shared by the mark sheet and the period grade sheet so the two can never
 * disagree about who is allowed to put a mark against which child.
 */
trait ResolvesMarkingScope
{
    /** Classes this person may mark. */
    protected function sectionsFor(?Teacher $teacher, bool $restricted): Collection
    {
        if ($restricted && $teacher === null) {
            return collect();
        }

        $query = Section::with('schoolClass');

        if ($restricted) {
            $query->whereIn('id', TeachingAssignment::where('teacher_id', $teacher->id)->pluck('section_id'));
        }

        return $query->get()->sortBy(fn (Section $s) => $s->full_name)->values();
    }

    /**
     * Subjects taught to this class.
     *
     * Drawn from the class's own subject list, which is what decides whether a
     * student "does" a subject - there is no per-student subject choice, so
     * everyone in the class takes everything attached to it.
     */
    protected function subjectsFor(Section $section, ?Teacher $teacher, bool $restricted): Collection
    {
        if ($restricted && $teacher === null) {
            return collect();
        }

        $ids = $section->schoolClass?->subjects()->pluck('subjects.id') ?? collect();

        if ($restricted) {
            $ids = $ids->intersect(
                TeachingAssignment::where('teacher_id', $teacher->id)
                    ->where('section_id', $section->id)
                    ->pluck('subject_id')
            );
        }

        return Subject::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * The students in this class who take this subject.
     *
     * Not simply the class roster. A grade may offer a subject to only some of
     * its students, and listing all thirty against an elective six of them take
     * invites a mark being entered against a child who never sat the paper.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Student>
     */
    protected function roster(Section $section, ?Subject $subject = null)
    {
        return Student::inSection($section->id)
            ->where('status', 'active')
            ->when($subject, fn ($query) => $query->where(function ($outer) use ($subject) {
                $outer
                    ->whereHas('subjects', fn ($related) => $related->where('subjects.id', $subject->id))
                    /*
                     | A student with no subjects recorded at all still appears.
                     |
                     | Filtering on the pivot alone would make anyone whose
                     | subjects were never recorded - an older record, a CSV
                     | import, a row written straight to the database - vanish
                     | from every mark sheet in the school, silently and with no
                     | error to explain it. Absence of a record is not evidence
                     | that a child takes nothing, so it falls back to what the
                     | system inferred before this table existed: everything
                     | their class offers.
                     */
                    ->orWhereDoesntHave('subjects');
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
