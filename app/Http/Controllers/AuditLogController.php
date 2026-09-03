<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only view of the activity trail. Entries are never editable from the
 * application; the model itself refuses updates and deletes.
 */
class AuditLogController extends Controller
{
    public function index(Request $request, SchoolContext $context): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'module' => $request->string('module')->trim()->toString(),
            'action' => $request->string('action')->trim()->toString(),
        ];

        $query = AuditLog::query()->with('user:id,name');

        // Platform administrators working outside a school see every school's
        // trail; everyone else sees only their own school's.
        if ($schoolId = $context->schoolId()) {
            $query->where('school_id', $schoolId);
        } elseif (! $context->isUnrestricted()) {
            $query->whereRaw('1 = 0');
        }

        $logs = $query
            ->when($filters['search'], fn ($q, $search) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")))
            ->when($filters['module'], fn ($q, $module) => $q->where('module', $module))
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'modules' => AuditLog::query()
                ->when($schoolId ?? null, fn ($q, $id) => $q->where('school_id', $id))
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => AuditLog::query()
                ->when($schoolId ?? null, fn ($q, $id) => $q->where('school_id', $id))
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
