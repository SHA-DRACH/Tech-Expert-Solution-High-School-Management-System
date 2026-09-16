<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One access switch for a student account.
 *
 * A row with a null student_id is the school-wide default for that ability; a
 * row naming a student overrides the default for that student only. Everything
 * is data, so an administrator changes what students can do without a code
 * change or a deployment.
 */
class StudentPermission extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Student permissions';

    /**
     * The switches an administrator can set, and whether a school starts with
     * them on. Adding a capability means adding it here.
     *
     * @var array<string, array{label: string, default: bool}>
     */
    public const ABILITIES = [
        'view_profile' => ['label' => 'View profile', 'default' => true],
        'edit_profile' => ['label' => 'Edit profile', 'default' => false],
        'view_grades' => ['label' => 'View grades', 'default' => true],
        'view_attendance' => ['label' => 'View attendance', 'default' => true],
        'view_timetable' => ['label' => 'View timetable', 'default' => true],
        'download_timetable' => ['label' => 'Download timetable', 'default' => true],
        'view_subjects' => ['label' => 'View subjects', 'default' => true],
        'view_assignments' => ['label' => 'View assignments', 'default' => true],
        'submit_assignments' => ['label' => 'Submit assignments', 'default' => false],
        'view_report_cards' => ['label' => 'View report cards', 'default' => true],
        'download_report_card' => ['label' => 'Download report card', 'default' => true],
        'view_fees' => ['label' => 'View fees', 'default' => false],
        'send_messages' => ['label' => 'Send messages', 'default' => false],
    ];

    protected $fillable = ['school_id', 'student_id', 'ability', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public static function label(string $ability): string
    {
        return self::ABILITIES[$ability]['label'] ?? Str::headline($ability);
    }

    public function auditLabel(): string
    {
        return '"'.self::label($this->ability).'"';
    }
}
