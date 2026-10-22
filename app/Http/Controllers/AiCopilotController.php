<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiCopilotController extends Controller
{
    /**
     * Update AI external processing consent for the project.
     */
    public function updateConsent(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'consent' => ['required', 'boolean'],
        ]);

        if ($validated['consent']) {
            $project->grantAiConsent();
        } else {
            $project->revokeAiConsent();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'ai_consent' => $project->hasAiConsent(),
                'ai_consent_at' => $project->ai_consent_at?->toIso8601String(),
            ]);
        }

        return back();
    }

    /**
     * Send a prompt to the AI copilot in Exploratory or Evidence-Based mode.
     */
    public function chat(Request $request, Project $project, AiCopilotService $copilotService): JsonResponse
    {
        Gate::authorize('view', $project);

        if (! $project->hasAiConsent()) {
            return response()->json([
                'message' => 'External AI processing consent has not been granted for this project.',
                'error' => 'ai_consent_required',
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'mode' => ['required', 'string', 'in:exploratory,evidence_based'],
            'history' => ['nullable', 'array'],
            'context_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $response = $copilotService->chat(
            project: $project,
            message: $validated['message'],
            mode: $validated['mode'],
            history: $validated['history'] ?? [],
            contextText: $validated['context_text'] ?? null,
        );

        return response()->json($response);
    }

    /**
     * Generate a side-by-side proposal diff for selected chapter text.
     */
    public function proposal(Request $request, Project $project, AiCopilotService $copilotService): JsonResponse
    {
        Gate::authorize('update', $project);

        if (! $project->hasAiConsent()) {
            return response()->json([
                'message' => 'External AI processing consent has not been granted for this project.',
                'error' => 'ai_consent_required',
            ], 403);
        }

        $validated = $request->validate([
            'selected_text' => ['required', 'string', 'max:10000'],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'mode' => ['nullable', 'string', 'in:exploratory,evidence_based'],
        ]);

        $mode = $validated['mode'] ?? AiCopilotService::MODE_EVIDENCE_BASED;
        $instruction = $validated['instruction'] ?? 'Tingkatkan kualitas dan gaya bahasa akademis';

        $result = $copilotService->generateProposal(
            project: $project,
            selectedText: $validated['selected_text'],
            instruction: $instruction,
            mode: $mode,
        );

        return response()->json($result);
    }
}
