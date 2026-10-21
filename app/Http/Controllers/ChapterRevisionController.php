<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ChapterRevision;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChapterRevisionController extends Controller
{
    /**
     * Display a listing of chapter revisions without heavy content.
     */
    public function index(Request $request, Project $project, Chapter $chapter): JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $revisions = $chapter->revisions()
            ->select(['id', 'chapter_id', 'title', 'type', 'word_count', 'created_at'])
            ->get();

        return response()->json([
            'revisions' => $revisions,
        ]);
    }

    /**
     * Display the specified revision with full content for preview.
     */
    public function show(Request $request, Project $project, Chapter $chapter, ChapterRevision $revision): JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id || $revision->chapter_id !== $chapter->id, 404);

        return response()->json([
            'revision' => $revision,
        ]);
    }

    /**
     * Store an automatic background snapshot every 5 minutes during active edits.
     */
    public function storeAuto(Request $request, Project $project, Chapter $chapter): JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $validated = $request->validate([
            'content' => ['required', 'array'],
        ]);

        $lastAuto = $chapter->revisions()
            ->where('type', 'auto')
            ->first();

        // 5-minute (300 seconds) rate limit check
        if ($lastAuto && $lastAuto->created_at->greaterThan(now()->subMinutes(5))) {
            return response()->json([
                'message' => 'Auto-snapshot interval has not elapsed yet (min 5 minutes).',
                'skipped' => true,
            ], 200);
        }

        // Avoid duplicate identical snapshot
        if ($lastAuto && $lastAuto->content === $validated['content']) {
            return response()->json([
                'message' => 'Content has not changed since last snapshot.',
                'skipped' => true,
            ], 200);
        }

        $wordCount = ChapterRevision::extractWordCount($validated['content']);

        $revision = DB::transaction(function () use ($chapter, $validated, $wordCount) {
            $created = $chapter->revisions()->create([
                'title' => null,
                'type' => 'auto',
                'content' => $validated['content'],
                'word_count' => $wordCount,
            ]);

            // Enforce max 50 auto-snapshots cap per chapter (FIFO rotation)
            $autoRevisions = $chapter->revisions()
                ->where('type', 'auto')
                ->orderByDesc('created_at')
                ->get();

            if ($autoRevisions->count() > 50) {
                $idsToDelete = $autoRevisions->slice(50)->pluck('id');
                ChapterRevision::whereIn('id', $idsToDelete)->delete();
            }

            return $created;
        });

        return response()->json([
            'message' => 'Auto-snapshot captured successfully.',
            'revision' => $revision,
        ], 201);
    }

    /**
     * Store a manual named checkpoint.
     */
    public function storeCheckpoint(Request $request, Project $project, Chapter $chapter): JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
        ]);

        $content = $validated['content'] ?? $chapter->content ?? [];
        $title = !empty(trim($validated['title'] ?? ''))
            ? trim($validated['title'])
            : 'Versi Manual - ' . now()->format('d M Y H:i');

        $wordCount = ChapterRevision::extractWordCount($content);

        $revision = $chapter->revisions()->create([
            'title' => $title,
            'type' => 'manual',
            'content' => $content,
            'word_count' => $wordCount,
        ]);

        return response()->json([
            'message' => 'Manual checkpoint created successfully.',
            'revision' => $revision,
        ], 201);
    }

    /**
     * Restore chapter content to the specified snapshot.
     */
    public function restore(Request $request, Project $project, Chapter $chapter, ChapterRevision $revision): JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id || $revision->chapter_id !== $chapter->id, 404);

        DB::transaction(function () use ($chapter, $revision) {
            // Safety snapshot: capture current draft before rolling back
            if (!empty($chapter->content)) {
                $targetLabel = $revision->title ?: 'Otomatis (' . $revision->created_at->format('H:i') . ')';
                $chapter->revisions()->create([
                    'title' => 'Sebelum pemulihan ke: ' . $targetLabel,
                    'type' => 'manual',
                    'content' => $chapter->content,
                    'word_count' => ChapterRevision::extractWordCount($chapter->content),
                ]);
            }

            // Restore chapter content
            $chapter->update([
                'content' => $revision->content,
            ]);
        });

        return response()->json([
            'message' => 'Chapter content restored successfully.',
            'chapter' => $chapter->fresh(),
            'restored_revision' => $revision,
        ]);
    }
}
