<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    /** Statuses a reviewer may move an application to by hand. */
    protected const REVIEW_STATUSES = [
        'submitted', 'pending', 'under_review', 'documents_required',
        'interview_required', 'approved', 'rejected', 'cancelled',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Admission::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
        ];

        $admissions = Admission::query()
            ->withCount('documents')
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admissions.index', [
            'admissions' => $admissions,
            'filters' => $filters,
            'statuses' => Admission::STATUSES,
        ]);
    }

    public function show(Admission $admission): View
    {
        $this->authorize('view', $admission);

        $admission->load('documents.reviewer');

        return view('admissions.show', [
            'admission' => $admission,
            'reviewStatuses' => self::REVIEW_STATUSES,
            'documentStatuses' => AdmissionDocument::STATUSES,
        ]);
    }

    public function update(Request $request, Admission $admission, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('review', $admission);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'review_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        // Approving and rejecting are separately permissioned decisions.
        if ($data['status'] === 'approved') {
            $this->authorize('approve', $admission);
        }

        if ($data['status'] === 'rejected') {
            $this->authorize('reject', $admission);
        }

        $previousStatus = $admission->status;

        $admission->update($data);

        if ($previousStatus !== $admission->status) {
            $audit->log(
                'status_changed',
                'Admissions',
                "Application {$admission->application_number} moved from {$previousStatus} to {$admission->status}.",
                $admission,
                ['status' => $previousStatus],
                ['status' => $admission->status],
            );
        }

        return back()->with('status', 'Admission review updated.');
    }

    public function reviewDocument(Request $request, AdmissionDocument $document, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('verify', $document);

        $data = $request->validate([
            'status' => ['required', Rule::in(AdmissionDocument::STATUSES)],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $document->status;

        $document->update($data + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $audit->log(
            'document_reviewed',
            'Admission documents',
            "Document \"{$document->document_type}\" was marked {$document->status}.",
            $document,
            ['status' => $previousStatus],
            ['status' => $document->status],
        );

        return back()->with('status', 'Document review saved.');
    }

    /**
     * Uploaded documents are held on the private disk and only ever streamed
     * through this authorized route, never linked to directly.
     */
    public function downloadDocument(AdmissionDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}
