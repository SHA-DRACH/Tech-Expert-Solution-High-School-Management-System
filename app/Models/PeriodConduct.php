<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;

/** A student's conduct for one marking period, written by the class sponsor. */
class PeriodConduct extends Model
{
    use BelongsToSchool, RecordsAuditTrail;

    public const OPTIONS = ['Excellent', 'Very good', 'Good', 'Fair', 'Poor'];

    protected string $auditModule = 'Examinations & grades';

    protected $fillable = ['school_id', 'student_id', 'term_id', 'conduct', 'recorded_by'];
}
