<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Automatically records create / update / delete activity for a model.
 *
 * Set $auditModule on the model to name the area it belongs to, and
 * $auditIgnored to skip noisy columns.
 */
trait RecordsAuditTrail
{
    public static function bootRecordsAuditTrail(): void
    {
        static::created(fn ($model) => $model->recordAudit('created', $model->auditDescription('created'), null, $model->auditableAttributes($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $model->recordAudit(
                'updated',
                $model->auditDescription('updated'),
                array_intersect_key($model->getOriginal(), $changes),
                $changes,
            );
        });

        static::deleted(fn ($model) => $model->recordAudit('deleted', $model->auditDescription('deleted')));
    }

    public function recordAudit(string $action, string $description, ?array $old = null, ?array $new = null): void
    {
        app(AuditLogger::class)->log($action, $this->auditModule(), $description, $this, $old, $new);
    }

    public function auditModule(): string
    {
        return property_exists($this, 'auditModule') ? $this->auditModule : class_basename($this);
    }

    public function auditDescription(string $action): string
    {
        return trim(class_basename($this).' '.$this->auditLabel().' was '.$action.'.');
    }

    /** Human-readable identifier for this record in the trail. */
    public function auditLabel(): string
    {
        foreach (['full_name', 'name', 'title', 'student_number', 'application_number'] as $attribute) {
            if ($value = $this->getAttribute($attribute)) {
                return '"'.$value.'"';
            }
        }

        return '#'.$this->getKey();
    }

    public function auditableAttributes(array $attributes): array
    {
        $ignored = array_merge(
            ['updated_at', 'created_at', 'remember_token', 'password'],
            property_exists($this, 'auditIgnored') ? $this->auditIgnored : [],
        );

        return collect($attributes)->except($ignored)->all();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
