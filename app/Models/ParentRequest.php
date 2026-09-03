<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentRequest extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const TYPES = [
        'meeting' => 'Meeting request',
        'fee_inquiry' => 'Fee inquiry',
        'academic_concern' => 'Academic concern',
        'correction' => 'Student record correction',
        'leave' => 'Leave request',
        'general' => 'General inquiry',
    ];

    public const STATUSES = ['open', 'in_progress', 'answered', 'closed'];

    protected string $auditModule = 'Parent requests';

    protected $fillable = [
        'school_id', 'guardian_id', 'student_id', 'type', 'subject', 'body',
        'status', 'response', 'responded_by', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function auditLabel(): string
    {
        return '"'.$this->subject.'"';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Request';
    }
}
