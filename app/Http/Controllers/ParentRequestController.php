<?php

namespace App\Http\Controllers;

use App\Models\ParentRequest;
use App\Notifications\ParentRequestAnswered;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParentRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('requests.manage'), 403);

        $status = $request->string('status')->trim()->toString();

        return view('requests.index', [
            'requests' => ParentRequest::with(['guardian', 'student', 'responder:id,name'])
                ->when($status, fn ($query, $value) => $query->where('status', $value))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'status' => $status,
            'statuses' => ParentRequest::STATUSES,
            'types' => ParentRequest::TYPES,
            'openCount' => ParentRequest::whereIn('status', ['open', 'in_progress'])->count(),
        ]);
    }

    public function respond(Request $request, ParentRequest $parentRequest, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('requests.manage'), 403);
        abort_unless($parentRequest->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(ParentRequest::STATUSES)],
            'response' => ['nullable', 'string', 'max:4000'],
        ]);

        $parentRequest->update($data + [
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
        ]);

        if (filled($data['response'] ?? null)) {
            $notifier->notifyUser($parentRequest->guardian?->user, new ParentRequestAnswered($parentRequest));
        }

        return back()->with('status', 'Response saved.');
    }
}
