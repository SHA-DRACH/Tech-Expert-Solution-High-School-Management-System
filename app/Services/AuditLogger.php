<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Attributes never written to the trail, whatever model they appear on. */
    protected array $redacted = ['password', 'remember_token', 'api_token', 'two_factor_secret'];

    public function __construct(private readonly SchoolContext $context) {}

    public function log(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'school_id' => $subject?->getAttribute('school_id') ?? $this->context->schoolId(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'module' => $module,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $this->clean($oldValues),
            'new_values' => $this->clean($newValues),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512) ?: null,
        ]);
    }

    protected function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $values = collect($values)->except($this->redacted)->all();

        return $values === [] ? null : $values;
    }
}
