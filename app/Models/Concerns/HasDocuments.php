<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Lets a model hold filed documents. The subject key decides which document
 * types are offered when uploading against it.
 */
trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
    }

    public function verifiedDocuments(): MorphMany
    {
        return $this->documents()->where('status', 'verified');
    }

    /** Which set of document types applies to this kind of record. */
    public function documentSubject(): string
    {
        return property_exists($this, 'documentSubject') ? $this->documentSubject : 'general';
    }
}
