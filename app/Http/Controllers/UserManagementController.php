<?php

namespace App\Http\Controllers;

use App\Actions\CreateSchoolUser;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CsvExporter;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->filters($request);

        $users = $this->query($filters)
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'search' => $filters['search'],
            'roles' => Role::inCurrentSchool()->orderBy('name')->get(),
            // Shown as a summary strip so "who has access to what" is one
            // glance rather than a page-by-page count.
            'roleCounts' => Role::inCurrentSchool()
                ->withCount(['users' => fn ($query) => $query->where('users.status', 'active')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['roles.permissions:id,slug,name', 'guardianProfile', 'studentProfile', 'teacherProfile']);

        return view('users.show', [
            'account' => $user,
            // The full picture of what this account can actually do: the union
            // of every permission across every role it holds.
            'permissions' => $user->roles
                ->flatMap->permissions
                ->unique('slug')
                ->sortBy('name')
                ->values(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'account' => $user->load('roles:id'),
            'roles' => Role::inCurrentSchool()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();

        $original = $user->only(['name', 'email']) + ['roles' => $user->roles->pluck('name')->sort()->values()->all()];

        DB::transaction(function () use ($user, $data) {
            $attributes = ['name' => $data['name'], 'email' => $data['email']];

            // An empty password box means "leave it alone", not "blank it".
            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);

            $user->roles()->sync(
                Role::inCurrentSchool()->whereIn('id', $data['roles'])->pluck('id')->all()
            );
        });

        $user->load('roles:id,name');

        $audit->log(
            'updated',
            'Users',
            "Account for {$user->name} was updated.",
            $user,
            $original,
            $user->only(['name', 'email']) + ['roles' => $user->roles->pluck('name')->sort()->values()->all()],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('status', "{$user->name}'s account was updated.");
    }

    /** @return array<string, string> */
    protected function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'role' => $request->string('role')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
        ];
    }

    /** The one query the listing and its export share, so they never disagree. */
    protected function query(array $filters)
    {
        return User::query()
            ->inCurrentSchool()
            /*
             | Two letters is enough. Search matches the start of any word, so
             | "ko" finds Grace Kollie; several words all have to match
             | somewhere, so "gr ko" finds her too. Identifiers are matched by
             | fragment because people read the last digits of a number off a
             | form rather than typing it from the beginning - and the account
             | id, the staff number and the student number are all reachable,
             | since "the id" means whichever of those the person is holding.
             */
            ->when($filters['search'], fn ($query, $search) => Search::apply($query, $search, [
                'words' => ['name', 'email'],
                'identifiers' => ['id'],
                'relations' => [
                    'teacherProfile' => ['staff_number', 'first_name', 'last_name'],
                    'studentProfile' => ['student_number', 'first_name', 'last_name'],
                    'guardianProfile' => ['first_name', 'last_name'],
                ],
            ]))
            ->when($filters['role'], fn ($query, $slug) => $query->whereHas('roles', fn ($q) => $q->where('slug', $slug)))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status));
    }

    /** Spec section 57: the account list, with the roles each one holds. */
    public function export(Request $request, CsvExporter $csv, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->query($this->filters($request))
            // The linked person is named on every row, so load it with the rows.
            ->with(['roles:id,name', 'school:id,name', 'teacherProfile', 'guardianProfile', 'studentProfile'])
            ->orderBy('name')
            ->get();

        $audit->log('exported', 'Users', $users->count().' user accounts were exported.');

        return $csv->stream(
            'users-'.now()->format('Y-m-d'),
            ['Name', 'Email', 'Roles', 'Status', 'Linked to', 'Created'],
            $users->map(fn (User $account) => [
                $account->name,
                $account->email,
                $account->roles->pluck('name')->join('; '),
                $account->status,
                $this->linkedPersonLabel($account),
                $account->created_at?->toDateString(),
            ]),
        );
    }

    /** What person, if any, this login represents. Never a password or a token. */
    protected function linkedPersonLabel(User $account): string
    {
        return match (true) {
            $account->teacherProfile !== null => 'Teacher: '.$account->teacherProfile->full_name,
            $account->guardianProfile !== null => 'Guardian: '.$account->guardianProfile->full_name,
            $account->studentProfile !== null => 'Student: '.$account->studentProfile->full_name,
            default => '',
        };
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => Role::inCurrentSchool()->orderBy('name')->get(),
            'guardians' => Guardian::whereNull('user_id')->orderBy('last_name')->get(),
            'students' => Student::whereNull('user_id')->orderBy('last_name')->get(),
            // Only staff who have no login yet, so an account can never be
            // attached to a person who already has one.
            'teachers' => Teacher::whereNull('user_id')
                ->where('status', 'active')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateSchoolUser $action): RedirectResponse
    {
        $user = $action->handle($request->validated());

        return redirect()
            ->route('users.index')
            ->with('status', "Account created for {$user->name}.");
    }

    public function toggleStatus(User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('suspend', $user);

        $user->update(['status' => $user->isActive() ? 'suspended' : 'active']);

        $audit->log(
            $user->isActive() ? 'reactivated' : 'suspended',
            'Users',
            "Account for {$user->name} was ".($user->isActive() ? 'reactivated' : 'suspended').'.',
            $user,
        );

        return back()->with('status', 'Account status updated.');
    }
}
