<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\BackupStatus;
use App\Services\SchoolSettings;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SchoolSettingsController extends Controller
{
    /**
     * The settings hub (spec section 57).
     *
     * Several things a school configures were built before this screen and
     * already live sensibly elsewhere - the grading scale sits with
     * examinations, portal access with students, what the public site shows
     * with the website editor. Rather than move them and break the links people
     * already use, the hub points at them, so "where do I change X" has one
     * answer even when X is edited somewhere else.
     */
    public function index(SchoolContext $context, SchoolSettings $settings): View
    {
        $school = $context->school();

        abort_unless($school !== null, 403, 'Select a school before opening its settings.');

        $this->authorize('update', $school);

        return view('settings.index', [
            'school' => $school,
            'values' => $settings->all(),
            'groups' => SchoolSettings::GROUPS,
            'currentYear' => AcademicYear::active(),
            'currentTerm' => Term::active(),
        ]);
    }

    /** One tab of the settings the school itself decides. */
    public function editGroup(string $group, SchoolContext $context, SchoolSettings $settings): View
    {
        $school = $context->school();

        abort_unless($school !== null, 403);
        abort_unless(array_key_exists($group, SchoolSettings::GROUPS), 404);

        $this->authorize('update', $school);

        return view('settings.group', [
            'school' => $school,
            'group' => $group,
            'definition' => SchoolSettings::group($group),
            'values' => $settings->all(),
        ]);
    }

    public function updateGroup(
        Request $request,
        string $group,
        SchoolContext $context,
        SchoolSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $school = $context->school();

        abort_unless($school !== null, 403);
        abort_unless(array_key_exists($group, SchoolSettings::GROUPS), 404);

        $this->authorize('update', $school);

        $request->validate($settings->rulesFor($group));

        $changed = $settings->updateGroup($school->id, $group, $request->input('settings', []));

        if ($changed !== []) {
            $audit->log(
                'updated',
                'Settings',
                SchoolSettings::group($group)['label'].' settings were updated.',
                $school,
                null,
                $changed,
            );
        }

        return redirect()
            ->route('settings.group.edit', $group)
            ->with('status', SchoolSettings::group($group)['label'].' settings saved.');
    }

    public function edit(SchoolContext $context, BackupStatus $backups): View
    {
        $school = $context->school();

        abort_unless($school !== null, 403, 'Select a school before editing its profile.');

        $this->authorize('update', $school);

        return view('settings.school', [
            'school' => $school,
            // Only platform-level administrators are shown backup health.
            'backup' => auth()->user()->isSuperAdministrator() ? $backups->current() : null,
        ]);
    }

    public function update(Request $request, SchoolContext $context, AuditLogger $audit): RedirectResponse
    {
        $school = $context->school();

        abort_unless($school !== null, 403);

        $this->authorize('update', $school);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'motto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:512'],
        ]);

        $original = $school->only(array_keys(collect($data)->except(['logo', 'favicon'])->all()));

        // The slug is not editable here: it identifies the tenant and changing
        // it would break existing links.
        $attributes = collect($data)->except(['logo', 'favicon'])->all();

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $previous = $school->{$column};

                $attributes[$column] = $request->file($input)->store("schools/{$school->id}/branding", 'public');

                if ($previous) {
                    Storage::disk('public')->delete($previous);
                }
            }
        }

        $school->update($attributes);

        $audit->log('updated', 'Settings', 'School profile and branding were updated.', $school, $original, $attributes);

        return back()->with('status', 'School profile updated successfully.');
    }
}
