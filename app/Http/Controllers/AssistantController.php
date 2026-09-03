<?php

namespace App\Http\Controllers;

use App\Services\Assistant\SchoolAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The assistant endpoint (spec section 70).
 *
 * Answers run as the person asking, through SchoolAssistant, so the assistant
 * can never reach data its user could not open directly. It is a faster route
 * to what someone may already see, not a second route around the permissions.
 */
class AssistantController extends Controller
{
    public function ask(Request $request, SchoolAssistant $assistant): JsonResponse
    {
        abort_unless(config('assistant.enabled', true), 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $result = $assistant->ask($request->user(), $data['question']);

        return response()->json([
            'answer' => $result['answer'],
            'sources' => $result['sources'],
            'handled' => $result['handled'],
        ]);
    }

    /** Starting points, tailored to what this account may actually see. */
    public function suggestions(Request $request, SchoolAssistant $assistant): JsonResponse
    {
        abort_unless(config('assistant.enabled', true), 404);

        return response()->json([
            'suggestions' => $assistant->suggestionsFor($request->user()),
            'provider' => $assistant->providerName(),
        ]);
    }
}
