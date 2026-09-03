<?php

namespace App\Policies;

use App\Models\AdmissionDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

class AdmissionDocumentPolicy
{
    use ChecksSchoolOwnership;

    public function view(User $user, AdmissionDocument $document): bool
    {
        return $this->allows($user, 'admissions.view', $document);
    }

    public function download(User $user, AdmissionDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function verify(User $user, AdmissionDocument $document): bool
    {
        return $this->allows($user, 'admissions.documents.verify', $document);
    }
}
