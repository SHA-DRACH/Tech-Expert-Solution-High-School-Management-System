<?php

namespace App\Http\Controllers;

use App\Actions\CreateSchoolUser;
use App\Actions\GrantPortalAccess;
use App\Models\Department;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherQualification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('teachers.view'), 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'department' => $request->integer('department') ?: null,
            'status' => $request->string('status')->trim()->toString(),
        ];

        $teachers = Teacher::query()
            ->with('department:id,name')
            ->withCount('teachingAssignments')
            ->search($filters['search'])
            ->when($filters['department'], fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('teachers.index', [
            'teachers' => $teachers,
            'filters' => $filters,
            'departments' => Department::orderBy('name')->get(),
            'statuses' => Teacher::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('teachers.create'), 403);

        // The login half of the form is only offered to someone who could
        // create an account on its own screen too.
        $roles = $request->user()->hasPermission('users.create')
            ? Role::inCurrentSchool()->orderBy('name')->get()
            : collect();

        return view('teachers.create', [
            'departments' => Department::orderBy('name')->get(),
            'roles' => $roles,
            'defaultRole' => $roles->firstWhere('slug', 'teacher'),
        ]);
    }

    public function store(Request $request, GrantPortalAccess $access): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teachers.create'), 403);

        // Creating an account is a different authority from adding a staff
        // record, so the form only offers it to someone who holds both.
        abort_if(
            GrantPortalAccess::wanted($request) && ! $request->user()->hasPermission('users.create'),
            403,
        );

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'department_id' => ['nullable', 'integer'],
            'employment_type' => ['nullable', 'string', 'max:40'],
            'hired_on' => ['nullable', 'date'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ] + GrantPortalAccess::rules(), GrantPortalAccess::messages(), GrantPortalAccess::attributes());

        // Resolved through the scoped query so a department from another school
        // cannot be attached.
        $department = isset($data['department_id'])
            ? Department::findOrFail($data['department_id'])
            : null;

        $teacher = DB::transaction(function () use ($data, $department, $request, $access) {
            $attributes = collect($data)
                ->except(['photo', 'department_id', 'account_email', 'account_password', 'account_role_id'])
                ->all();

            $attributes['department_id'] = $department?->id;
            $attributes['staff_number'] = $this->nextStaffNumber();
            $attributes['status'] = 'active';
            $attributes['is_public'] = $request->boolean('is_public');

            if ($request->hasFile('photo')) {
                $attributes['photo_path'] = $request->file('photo')->store('teachers', 'public');
            }

            $teacher = Teacher::create($attributes);

            // Inside the same transaction: a staff record that exists while
            // its half-made account does not would be worse than neither.
            $access->handle($request, $teacher->full_name, ['teacher_id' => $teacher->id]);

            return $teacher;
        });

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('status', $teacher->fresh()->user_id
                ? "{$teacher->full_name} was added and can sign in as {$request->input('account_email')}."
                : "{$teacher->full_name} was added to the staff list.");
    }

    public function show(Request $request, Teacher $teacher): View
    {
        abort_unless($request->user()->hasPermission('teachers.view'), 403);
        abort_unless($teacher->school_id === $request->user()->school_id || $request->user()->isSuperAdministrator(), 403);

        $teacher->load([
            'department',
            'qualifications',
            'user',
            'teachingAssignments.section.schoolClass',
            'teachingAssignments.subject',
        ]);

        return view('teachers.show', [
            'teacher' => $teacher,
            'qualificationTypes' => TeacherQualification::TYPES,
            // Offered on the record itself when the person has no login, since
            // that is where the absence is actually noticed.
            'roles' => $teacher->user_id === null && $request->user()->hasPermission('users.create')
                ? Role::inCurrentSchool()->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, Teacher $teacher): View
    {
        abort_unless($request->user()->hasPermission('teachers.update'), 403);
        $this->assertOwned($request, $teacher);

        return view('teachers.edit', [
            'teacher' => $teacher,
            'departments' => Department::orderBy('name')->get(),
            'statuses' => Teacher::STATUSES,
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teachers.update'), 403);
        $this->assertOwned($request, $teacher);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'department_id' => ['nullable', 'integer'],
            'employment_type' => ['nullable', 'string', 'max:40'],
            'hired_on' => ['nullable', 'date'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Teacher::STATUSES)],
            'is_public' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Resolved through the scoped query, so a department id belonging to
        // another school does not resolve and cannot be attached.
        $department = filled($data['department_id'] ?? null)
            ? Department::findOrFail($data['department_id'])
            : null;

        $original = $teacher->only(array_keys(collect($data)->except('photo')->all()));

        DB::transaction(function () use ($teacher, $data, $department, $request) {
            $attributes = collect($data)->except(['photo', 'department_id'])->all();

            $attributes['department_id'] = $department?->id;
            $attributes['is_public'] = $request->boolean('is_public');

            if ($request->hasFile('photo')) {
                $previous = $teacher->photo_path;

                $attributes['photo_path'] = $request->file('photo')->store('teachers', 'public');

                if ($previous) {
                    Storage::disk('public')->delete($previous);
                }
            }

            $teacher->update($attributes);
        });

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('status', "{$teacher->full_name}'s record was updated.");
    }

    /**
     * Staff records are archived, never deleted (spec section 71.10).
     *
     * A teacher's name is attached to marks they entered, lessons they taught
     * and report cards they signed. Removing the record would leave that
     * history pointing at nothing, so the record is soft-deleted and stays
     * reachable to anyone auditing the year it belongs to.
     */
    public function destroy(Request $request, Teacher $teacher, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teachers.archive'), 403);
        $this->assertOwned($request, $teacher);

        $name = $teacher->full_name;

        DB::transaction(function () use ($teacher) {
            // The employment status is left as the school recorded it - a
            // teacher may be archived while "on_leave" or "resigned", and
            // overwriting it would destroy the reason they left. Being archived
            // is the soft delete itself. Only the public listing is withdrawn.
            $teacher->update(['is_public' => false]);
            $teacher->delete();
        });

        $audit->log('archived', 'Teachers', "{$name} was archived.", $teacher);

        return redirect()
            ->route('teachers.index')
            ->with('status', "{$name} was archived. Their record and history are kept.");
    }

    public function restore(Request $request, int $teacher, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teachers.archive'), 403);

        // Route model binding excludes trashed rows, so this one is fetched by
        // hand - and still through the tenant-scoped query.
        $record = Teacher::onlyTrashed()->findOrFail($teacher);

        $this->assertOwned($request, $record);

        $record->restore();

        $audit->log('restored', 'Teachers', "{$record->full_name} was restored to the staff list.", $record);

        return back()->with('status', "{$record->full_name} was restored.");
    }

    /**
     * Give a member of staff a login, from their own record.
     *
     * Adding a teacher creates the person, not the account, and reaching Users
     * → Create account to finish the job is a step that is easy to not know
     * about: five of this school's six teachers had no login and nothing on
     * their record said so. This is the same action, offered where the gap is
     * visible.
     */
    public function createLogin(Request $request, Teacher $teacher, CreateSchoolUser $action): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.create'), 403);
        $this->assertOwned($request, $teacher);

        if ($teacher->user_id !== null) {
            return back()->withErrors(['login' => $teacher->full_name.' already has an account.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role_id' => ['required', 'integer'],
        ], [
            'password.min' => 'The password must be at least 12 characters.',
            'email.unique' => 'That email address already has an account.',
        ]);

        // Re-resolved through the tenant-scoped query, never trusted as an id
        // that arrived from the form.
        $role = Role::inCurrentSchool()->findOrFail($data['role_id']);

        $user = $action->handle([
            'name' => $teacher->full_name,
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $role->id,
            'teacher_id' => $teacher->id,
        ]);

        return back()->with('status',
            "{$teacher->full_name} can now sign in as {$user->email}. Give them the password you set — it is not shown again.");
    }

    protected function assertOwned(Request $request, Teacher $teacher): void
    {
        abort_unless(
            $teacher->school_id === $request->user()->school_id || $request->user()->isSuperAdministrator(),
            403,
        );
    }

    public function storeQualification(Request $request, Teacher $teacher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teachers.update'), 403);
        abort_unless($teacher->school_id === $request->user()->school_id || $request->user()->isSuperAdministrator(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(TeacherQualification::TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'institution' => ['nullable', 'string', 'max:180'],
            'field' => ['nullable', 'string', 'max:120'],
            'awarded_year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'authority' => ['nullable', 'string', 'max:180'],
            'is_public' => ['boolean'],
        ]);

        $teacher->qualifications()->create($data + [
            'school_id' => $teacher->school_id,
            'is_public' => $request->boolean('is_public'),
        ]);

        return back()->with('status', 'Qualification recorded.');
    }

    /** Sequential staff number within the school, e.g. GFI-T-006. */
    protected function nextStaffNumber(): string
    {
        $prefix = request()->user()->school->numberPrefix().'-T-';

        $highest = Teacher::withTrashed()
            ->where('staff_number', 'like', $prefix.'%')
            ->orderByDesc('staff_number')
            ->value('staff_number');

        $next = $highest ? ((int) substr($highest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
