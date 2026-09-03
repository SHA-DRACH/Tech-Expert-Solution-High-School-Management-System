<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

/**
 * Teachers may only touch work for a section and subject they actually teach.
 * That link lives in `teaching_assignments`, never in the request.
 */
class AssessmentPolicy
{
    use ChecksSchoolOwnership;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['exams.view', 'grades.enter', 'grades.approve']);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        if (! $this->ownsRecord($user, $assessment)) {
            return false;
        }

        return $user->hasAnyPermission(['exams.view', 'grades.approve'])
            || $this->teaches($user, $assessment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('grades.enter') || $user->hasPermission('exams.manage');
    }

    /**
     * Once work is submitted it is out of the teacher's hands until the
     * academic office sends it back.
     */
    public function update(User $user, Assessment $assessment): bool
    {
        return $this->ownsRecord($user, $assessment)
            && $assessment->isEditable()
            && ($this->teaches($user, $assessment) || $user->hasPermission('exams.manage'));
    }

    public function enterScores(User $user, Assessment $assessment): bool
    {
        return $this->ownsRecord($user, $assessment)
            && $assessment->isEditable()
            && $user->hasPermission('grades.enter')
            && $this->teaches($user, $assessment);
    }

    public function submit(User $user, Assessment $assessment): bool
    {
        return $this->enterScores($user, $assessment);
    }

    /** Approval is a separate duty from entry: a teacher cannot sign off their own marks. */
    public function approve(User $user, Assessment $assessment): bool
    {
        return $this->allows($user, 'grades.approve', $assessment)
            && $assessment->status === 'submitted';
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment) && $assessment->status === 'draft';
    }

    /** Is this user the teacher assigned to the assessment's section and subject? */
    protected function teaches(User $user, Assessment $assessment): bool
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher === null) {
            return false;
        }

        if ($assessment->teacher_id === $teacher->id) {
            return true;
        }

        return TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('section_id', $assessment->section_id)
            ->where('subject_id', $assessment->subject_id)
            ->exists();
    }
}
