<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

/**
 * Documents are sensitive: birth certificates, contracts, transcripts.
 *
 * Staff need the permission covering whoever the document belongs to. A
 * student or guardian may read the student's own documents, and nothing else.
 */
class DocumentPolicy
{
    use ChecksSchoolOwnership;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['students.view', 'teachers.view']);
    }

    public function view(User $user, Document $document): bool
    {
        if (! $this->ownsRecord($user, $document)) {
            return false;
        }

        if ($user->hasPermission($this->permissionFor($document, 'view'))) {
            return true;
        }

        return $this->isOwnRecord($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['students.update', 'teachers.update']);
    }

    public function verify(User $user, Document $document): bool
    {
        return $this->ownsRecord($user, $document)
            && $user->hasPermission($this->permissionFor($document, 'update'));
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->verify($user, $document);
    }

    /** Which permission governs this document, based on who it belongs to. */
    protected function permissionFor(Document $document, string $action): string
    {
        return match ($document->documentable_type) {
            Teacher::class => "teachers.{$action}",
            default => "students.{$action}",
        };
    }

    /** The student themselves, or a guardian cleared for that child. */
    protected function isOwnRecord(User $user, Document $document): bool
    {
        if ($document->documentable_type !== Student::class) {
            return false;
        }

        $student = $document->documentable;

        if ($student === null) {
            return false;
        }

        if ($student->user_id === $user->id) {
            return true;
        }

        $guardian = $user->guardianProfile;

        return $guardian !== null && $guardian->canViewAcademicsFor($student);
    }
}
