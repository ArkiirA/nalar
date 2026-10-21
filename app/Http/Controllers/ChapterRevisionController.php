<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ChapterRevision;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChapterRevisionController extends Controller
{
    /**
     * Display a listing of chronological snapshots for the specified chapter.
     */
    public function index(Request $request, Project $project, Chapter $chapter): JsonResponse
    {
        Gate::authorize('view', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $revisions = $chapter->revisions()
            ->get()
            ->map(fn (ChapterRevision $rev) => $rev->toSummaryArray());

        return response()->json([
            'revisions' => $revisions,
        ]);
    }

    /**
     * Store a named manual snapshot checkpoint.
     */
    public function store(Request $request, Project $project, Chapter $chapter): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $revision = $chapter->recordSnapshot(
            title: $validated['title'],
            isManual: true
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Snapshot versi berhasil disimpan.',
                'revision' => $revision->toFullArray(),
            ], 201);
        }

        return back();
    }

    /**
     * Display the specified snapshot with full structured content.
     */
    public function show(Request $request, Project $project, Chapter $chapter, ChapterRevision $revision): JsonResponse
    {
        Gate::authorize('view', $project);

        abort_if($chapter->project_id !== $project->id, 404);
        abort_if($revision->chapter_id !== $chapter->id, 404);

        return response()->json([
            'revision' => $revision->toFullArray(),
        ]);
    }

    /**
     * Restore the chapter to the specified snapshot.
     */
    public function restore(Request $request, Project $project, Chapter $chapter, ChapterRevision $revision): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);
        abort_if($revision->chapter_id !== $chapter->id, 404);

        if (is_array($revision->content)) {
            $citedRefIds = Chapter::extractCitationReferenceIds($revision->content);
            if (! empty($citedRefIds)) {
                $validRefIds = $project->references()->whereIn('id', $citedRefIds)->pluck('id')->all();
                $invalidRefIds = array_diff($citedRefIds, $validRefIds);
                if (! empty($invalidRefIds)) {
                    throw ValidationException::withMessages([
                        'revision' => 'Snapshot tidak dapat dipulihkan karena mengandung referensi yang sudah tidak terdaftar dalam proyek ini.',
                    ]);
                }
            }
        }

        $chapter->restoreRevision($revision);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Chapter restored to snapshot successfully.',
                'chapter' => $chapter->fresh(),
                'revision' => $revision->toSummaryArray(),
            ]);
        }

        return back();
    }
}
