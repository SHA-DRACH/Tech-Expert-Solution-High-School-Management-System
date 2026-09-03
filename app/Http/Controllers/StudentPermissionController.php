<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentPermission;
use App\Services\AuditLogger;
use App\Services\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Spec section 24: an administrator controls what student accounts may open,
 * school-wide and per student, without any code change.
 */
class StudentPermissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('students.update'), 403);

        $defaults = StudentPermission::whereNull('student_id')->get()->keyBy('ability');

        return view('students.permissions', [
            'abilities' => StudentPermission::ABILITIES,
            'defaults' => collect(StudentPermission::ABILITIES)
                ->map(fn (array $definition, string $ability) => $defaults->has($ability)
                    ? (bool) $defaults[$ability]->allowed
                    : $definition['default']),
            'overridden' => Student::whereHas('permissions')->with('permissions')->get(),
        ]);
    }

    public function updateDefaults(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('students.update'), 403);

        $submitted = $request->input('abilities', []);

        DB::transaction(function () use ($submitted, $request) {
            foreach (array_keys(StudentPermission::ABILITIES) as $ability) {
                StudentPermission::updateOrCreate(
                    ['school_id' => $request->user()->school_id, 'student_id' => null, 'ability' => $ability],
                    ['allowed' => (bool) ($submitted[$ability] ?? false)],
                );
            }
        });

        $audit->log('updated', 'Student permissions', 'School-wide student portal permissions were changed.');

        return back()->with('status', 'Student portal permissions updated.');
    }

    public function edit(Request $request, Student $student): View
    {
        abort_unless($request->user()->hasPermission('students.update'), 403);
        $this->authorize('update', $student);

        return view('students.permissions-student', [
            'student' => $student,
            'abilities' => StudentPermission::ABILITIES,
            'effective' => $this->access()->for($student),
            'overrides' => $student->permissions->keyBy('ability'),
        ]);
    }

    public function update(Request $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('students.update'), 403);
        $this->authorize('update', $student);

        $submitted = $request->input('abilities', []);
        $useDefault = $request->input('use_default', []);

        DB::transaction(function () use ($student, $submitted, $useDefault) {
            foreach (array_keys(StudentPermission::ABILITIES) as $ability) {
                // Ticking "use the school default" removes the override entirely.
                if (! empty($useDefault[$ability])) {
                    $student->permissions()->where('ability', $ability)->delete();

                    continue;
                }

                StudentPermission::updateOrCreate(
                    ['school_id' => $student->school_id, 'student_id' => $student->id, 'ability' => $ability],
                    ['allowed' => (bool) ($submitted[$ability] ?? false)],
                );
            }
        });

        $audit->log('updated', 'Student permissions',
            "Portal permissions for {$student->full_name} were changed.", $student);

        return back()->with('status', 'Permissions updated for '.$student->full_name.'.');
    }

    protected function access(): StudentAccess
    {
        return app(StudentAccess::class);
    }
}