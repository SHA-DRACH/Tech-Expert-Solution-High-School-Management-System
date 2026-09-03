<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Notifications\AdmissionSubmitted;
use App\Services\Notifier;
use App\Services\ReferenceNumberGenerator;
use App\Services\SchoolSettings;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicAdmissionController extends Controller
{
    public function create(SchoolContext $context, SchoolSettings $settings): View
    {
        $school = $this->school($context);

        // A school that has closed admissions gets a plain explanation rather
        // than a form that would accept an application it will not read.
        if (! $settings->get('admissions_open')) {
            return view('public.apply-closed', [
                'school' => $school,
                'message' => $settings->get('admissions_closed_message'),
                'socialLinks' => collect(),
            ]);
        }

        return view('public.apply', [
            'school' => $school,
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function store(Request $request, SchoolContext $context, ReferenceNumberGenerator $numbers, Notifier $notifier, SchoolSettings $settings): RedirectResponse
    {
        $school = $this->school($context);

        /*
         | Checked again on submit, not only when the form is drawn. Hiding a
         | form is a frontend courtesy; a closed intake has to be enforced on
         | the backend or a stale tab, or a copied request, still gets in.
         */
        abort_unless($settings->get('admissions_open'), 403, 'Applications are closed.');

        $data = $request->validate([
            'student_first_name' => ['required', 'string', 'max:100'],
            'student_middle_name' => ['nullable', 'string', 'max:100'],
            'student_last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_class' => ['nullable', 'string', 'max:100'],
            'intended_class' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['required', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_address' => ['nullable', 'string', 'max:2000'],
            'guardian_occupation' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'documents' => ['array', 'max:10'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'documents.*.type' => ['nullable', 'string', 'max:120'],
        ]);

        $admission = DB::transaction(function () use ($school, $data, $request, $numbers) {
            $admission = Admission::create(
                collect($data)->except('documents')->all()
                + [
                    'school_id' => $school->id,
                    'application_number' => $numbers->applicationNumber($school),
                    'status' => 'submitted',
                ]
            );

            foreach ($request->input('documents', []) as $index => $entry) {
                $file = $request->file("documents.{$index}.file");

                if ($file === null) {
                    continue;
                }

                $admission->documents()->create([
                    'school_id' => $school->id,
                    'document_type' => $entry['type'] ?? 'Supporting document',
                    'original_name' => $file->getClientOriginalName(),
                    // Private disk: these are only served through an authorized route.
                    'path' => $file->store("admissions/{$school->id}/{$admission->id}", 'local'),
                    'status' => 'pending',
                ]);
            }

            return $admission;
        });

        // Whoever handles admissions hears about it straight away.
        $notifier->notifyPermission('admissions.view', new AdmissionSubmitted($admission));

        // The application number is the parent's only handle on the submission,
        // so it is put in the session rather than the URL.
        return redirect()
            ->route('apply.complete')
            ->with('application_number', $admission->application_number);
    }

    public function complete(Request $request, SchoolContext $context): View
    {
        $applicationNumber = $request->session()->get('application_number');

        abort_unless($applicationNumber !== null, 404);

        return view('public.application-complete', [
            'school' => $this->school($context),
            'applicationNumber' => $applicationNumber,
        ]);
    }

    protected function school(SchoolContext $context)
    {
        abort_unless($context->hasSchool(), 404, 'No school is available at this address.');

        return $context->school();
    }

    /**
     * What an applicant is asked to upload, from the school's own settings.
     * The default lives in the settings catalogue so this list and the one on
     * the public admissions page cannot drift apart.
     *
     * @return array<int, string>
     */
    protected function documentTypes(): array
    {
        return app(SchoolSettings::class)->get('admission_document_types');
    }
}
