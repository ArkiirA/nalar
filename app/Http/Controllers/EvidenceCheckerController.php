<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\EvidenceAssessment;
use App\Models\Project;
use App\Services\EvidenceCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvidenceCheckerController extends Controller
{
    /**
     * Check evidence for a claim against the project's Research Library.
     */
    public function check(
        Request $request,
        Project $project,
        EvidenceCheckerService $checkerService
    ): JsonResponse {
        Gate::authorize('view', $project);

        if (! $project->hasAiConsent()) {
            return response()->json([
                'message' => 'External AI processing consent has not been granted for this project.',
                'error'   => 'ai_consent_required',
            ], 403);
        }

        $validated = $request->validate([
            'claim_text' => ['required', 'string', 'max:5000'],
        ]);

        $assessmentData = $checkerService->assess(
            project: $project,
            claimText: $validated['claim_text'],
        );

        $claim = Claim::create([
            'project_id' => $project->id,
            'claim_text' => $validated['claim_text'],
        ]);

        $assessment = EvidenceAssessment::create([
            'claim_id'           => $claim->id,
            'project_id'         => $project->id,
            'status'             => $assessmentData['status'],
            'explanation'        => $assessmentData['explanation'],
            'cited_page_numbers' => $assessmentData['cited_page_numbers'] ?? [],
            'candidate_passages' => $assessmentData['candidate_passages'],
        ]);

        return response()->json([
            'claim_id'           => $claim->id,
            'status'             => $assessment->status,
            'explanation'        => $assessment->explanation,
            'cited_page_numbers' => $assessment->cited_page_numbers,
            'candidate_passages' => $assessment->candidate_passages,
            'assessed_at'        => $assessment->created_at->toIso8601String(),
        ]);
    }

    /**
     * Retrieve the historical evidence assessment for a checked claim.
     */
    public function show(Project $project, Claim $claim): JsonResponse
    {
        Gate::authorize('view', $project);

        if ($claim->project_id !== $project->id) {
            abort(404, 'Claim does not belong to this project.');
        }

        $assessment = $claim->assessment;

        if (! $assessment) {
            abort(404, 'Assessment not found for this claim.');
        }

        return response()->json([
            'claim_id'           => $claim->id,
            'claim_text'         => $claim->claim_text,
            'status'             => $assessment->status,
            'explanation'        => $assessment->explanation,
            'cited_page_numbers' => $assessment->cited_page_numbers,
            'candidate_passages' => $assessment->candidate_passages,
            'assessed_at'        => $assessment->created_at->toIso8601String(),
        ]);
    }
}
