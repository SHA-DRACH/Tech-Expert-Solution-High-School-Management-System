<?php

namespace App\Http\Controllers;

use App\Actions\GrantPortalAccess;
use App\Http\Requests\StoreGuardianRequest;
use App\Models\Guardian;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Guardian::class);

        $search = $request->string('search')->trim()->toString();

        $guardians = Guardian::query()
            ->withCount('students')
            ->search($search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('guardians.index', compact('guardians', 'search'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Guardian::class);

        $roles = $request->user()->hasPermission('users.create')
            ? Role::inCurrentSchool()->orderBy('name')->get()
            : collect();

        return view('guardians.create', [
            'roles' => $roles,
            'defaultRole' => $roles->firstWhere('slug', 'parent-guardian'),
        ]);
    }

    public function store(StoreGuardianRequest $request, GrantPortalAccess $access): RedirectResponse
    {
        // Creating an account is a different authority from adding a guardian
        // record, so the form only offers it to someone who holds both.
        abort_if(
            GrantPortalAccess::wanted($request) && ! $request->user()->hasPermission('users.create'),
            403,
        );

        $guardian = DB::transaction(function () use ($request, $access) {
            $guardian = Guardian::create(
                collect($request->validated())
                    ->except(['account_email', 'account_password', 'account_role_id'])
                    ->all()
            );

            $access->handle($request, $guardian->full_name, ['guardian_id' => $guardian->id]);

            return $guardian;
        });

        return redirect()
            ->route('guardians.index')
            ->with('status', $guardian->fresh()->user_id
                ? "{$guardian->full_name} was added and can sign in as {$request->input('account_email')}."
                : "{$guardian->full_name} was added.");
    }

    public function show(Guardian $guardian): View
    {
        $this->authorize('view', $guardian);

        $guardian->load('students');

        return view('guardians.show', compact('guardian'));
    }

    public function edit(Guardian $guardian): View
    {
        $this->authorize('update', $guardian);

        return view('guardians.edit', compact('guardian'));
    }

    public function update(Request $request, Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $guardian);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $original = $guardian->only(array_keys($data));

        $guardian->update($data);

        $audit->log('updated', 'Parents & guardians', "{$guardian->full_name}'s record was updated.", $guardian, $original, $data);

        return redirect()
            ->route('guardians.show', $guardian)
            ->with('status', "{$guardian->full_name}'s record was updated.");
    }

    /**
     * Archive, never delete.
     *
     * A guardian is who the school calls about an absence and who an invoice is
     * addressed to. Deleting the record would strip a child of the person
     * responsible for them, so it is archived and can be restored. A guardian
     * who is still the only contact for a child is refused outright.
     */
    public function destroy(Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('archive', $guardian);

        $stranded = $guardian->students()->get()
            ->filter(fn ($student) => $student->guardians()->count() <= 1);

        if ($stranded->isNotEmpty()) {
            return back()->withErrors([
                'guardian' => $guardian->full_name.' is the only contact for '
                    .$stranded->map->full_name->join(', ')
                    .'. Add another guardian for '
                    .($stranded->count() === 1 ? 'that child' : 'those children').' first.',
            ]);
        }

        $name = $guardian->full_name;

        $guardian->delete();

        $audit->log('archived', 'Parents & guardians', "{$name} was archived.", $guardian);

        return redirect()
            ->route('guardians.index')
            ->with('status', "{$name} was archived. Their record is kept.");
    }

    public function restore(int $guardian, AuditLogger $audit): RedirectResponse
    {
        $record = Guardian::onlyTrashed()->findOrFail($guardian);

        $this->authorize('archive', $record);

        $record->restore();

        $audit->log('restored', 'Parents & guardians', "{$record->full_name} was restored.", $record);

        return back()->with('status', "{$record->full_name} was restored.");
    }
}
