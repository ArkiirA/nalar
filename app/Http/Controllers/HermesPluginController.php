<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\Ai\HermesBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class HermesPluginController extends Controller
{
    public function __construct(
        protected HermesBridge $bridge
    ) {}

    /**
     * Send student prompt and chapter context to Hermes Agent.
     */
    public function chat(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        set_time_limit(180);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $chapterTitle = null;
        if (!empty($validated['chapter_id'])) {
            $chapter = Chapter::where('project_id', $project->id)->find($validated['chapter_id']);
            $chapterTitle = $chapter?->title;
        }

        try {
            $reply = $this->bridge->query(
                prompt: $validated['message'],
                chapterTitle: $chapterTitle,
                chapterText: $validated['chapter_text'] ?? null,
            );

            return response()->json([
                'reply' => $reply,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Gagal menghubungi Hermes Agent: ' . $e->getMessage(),
            ], 500);
        }
    }
}
