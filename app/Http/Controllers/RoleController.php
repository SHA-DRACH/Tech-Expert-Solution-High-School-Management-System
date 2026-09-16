<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\Delegation;
use App\Support\Permissions;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::inCurrentSchool()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('roles.create', [
            'catalogue' => Permissions::CATALOGUE,
            'assigned' => [],
        ]);
    }

    public function store(Request $request, SchoolContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $this->validateRole($request);

        $this->refuseBeyond($request, $data['permissions'] ?? []);

        $role = DB::transaction(function () use ($data, $context) {
            $role = Role::create([
                'school_id' => $context->schoolId(),
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name'], $context->schoolId()),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

            return $role;
        });

        $audit->log('created', 'Roles & permissions', "Role \"{$role->name}\" was created.", $role);

        return redirect()->route('roles.index')->with('status', "Role \"{$role->name}\" was created.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', [
            'role' => $role,
            'catalogue' => Permissions::CATALOGUE,
            'assigned' => $role->permissions->pluck('slug')->all(),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $this->validateRole($request, $role);

        // Neither widening a role past your own authority, nor editing one
        // that already out-ranks you (which is how you would demote it).
        abort_unless(Delegation::beyond($request->user(), $role->permissions->pluck('slug'))->isEmpty(), 403,
            'This role holds permissions you do not have, so you cannot change it.');

        $this->refuseBeyond($request, $data['permissions'] ?? []);

        $before = $role->permissions->pluck('slug')->sort()->values()->all();

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));
        });

        $after = $role->permissions()->pluck('slug')->sort()->values()->all();

        if ($before !== $after) {
            $audit->log(
                'permissions_changed',
                'Roles & permissions',
                "Permissions for role \"{$role->name}\" were changed.",
                $role,
                ['permissions' => $before],
                ['permissions' => $after],
            );
        }

        return redirect()->route('roles.index')->with('status', "Role \"{$role->name}\" was updated.");
    }

    public function destroy(Role $role, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name;

        $role->permissions()->detach();
        $role->delete();

        $audit->log('deleted', 'Roles & permissions', "Role \"{$name}\" was deleted.");

        return redirect()->route('roles.index')->with('status', "Role \"{$name}\" was deleted.");
    }

    /** @return array<string, mixed> */
    protected function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('roles', 'name')
                    ->where('school_id', app(SchoolContext::class)->schoolId())
                    ->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            // Only slugs the platform actually defines may be granted.
            'permissions.*' => ['string', Rule::in(Permissions::slugs())],
        ]);
    }

    /** @param  array<int, string>  $slugs */
    protected function refuseBeyond(Request $request, array $slugs): void
    {
        $beyond = Delegation::beyond($request->user(), $slugs);

        if ($beyond->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant permissions you do not have yourself: '.$beyond->join(', ').'.',
            ]);
        }
    }

    /** @param  array<int, string>  $slugs */
    protected function permissionIds(array $slugs): array
    {
        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }

    protected function uniqueSlug(string $name, ?int $schoolId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Role::where('school_id', $schoolId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
