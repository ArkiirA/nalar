<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\Ai\EvidenceCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvidenceCheckerController extends Controller
{
    public function __construct(
        protected EvidenceCheckerService $evidenceChecker
    ) {}

    /**
     * Check factual claim against research literature and evidence.
     */
    public function check(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        set_time_limit(180);

        $validated = $request->validate([
            'claim' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:5000'],
            'chapter_id' => ['nullable', 'integer'],
        ]);

        $chapterTitle = null;
        if (!empty($validated['chapter_id'])) {
            $chapter = Chapter::where('project_id', $project->id)->find($validated['chapter_id']);
            $chapterTitle = $chapter?->title;
        }

        $result = $this->evidenceChecker->assess(
            claim: $validated['claim'],
            context: $validated['context'] ?? null,
            chapterTitle: $chapterTitle
        );

        return response()->json([
            'claim' => $validated['claim'],
            'assessment' => $result,
        ]);
    }
}
