<?php

namespace App\Models\Scopes;

use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the school in SchoolContext.
 *
 * A request that cannot be attributed to a school leaves the context in its
 * DENIED mode, and the query is forced to return nothing rather than falling
 * back to unfiltered access.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(SchoolContext::class);

        if ($schoolId = $context->schoolId()) {
            $builder->where($model->getTable().'.school_id', $schoolId);

            return;
        }

        if ($context->isDenied()) {
            $builder->whereRaw('1 = 0');
        }

        // PLATFORM and UNRESOLVED both run unfiltered: a super administrator
        // working above the schools, and console/fixture code respectively.
    }
}
