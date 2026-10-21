<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    /**
     * Store a newly uploaded PDF research document.
     */
    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:25600'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $storedPath = $uploadedFile->store("projects/{$project->id}/documents", 'local');

        $title = ! empty($validated['title'])
            ? $validated['title']
            : pathinfo($originalFilename, PATHINFO_FILENAME);

        $document = $project->documents()->create([
            'title' => $title,
            'original_filename' => $originalFilename,
            'file_path' => $storedPath,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType() ?: 'application/pdf',
            'status' => Document::STATUS_QUEUED,
        ]);

        ProcessDocumentJob::dispatch($document);

        if ($request->wantsJson()) {
            return response()->json($document, 201);
        }

        return back();
    }

    /**
     * Retry processing for a failed or partial document.
     */
    public function retry(Request $request, Project $project, Document $document): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);
        abort_if($document->project_id !== $project->id, 404);

        $document->update([
            'status' => Document::STATUS_QUEUED,
            'error_message' => null,
        ]);

        ProcessDocumentJob::dispatch($document);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'queued', 'document' => $document]);
        }

        return back();
    }

    /**
     * Delete a document and its associated pages.
     */
    public function destroy(Request $request, Project $project, Document $document): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);
        abort_if($document->project_id !== $project->id, 404);

        $document->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }

    /**
     * Get extracted pages of a document.
     */
    public function pages(Request $request, Project $project, Document $document): JsonResponse
    {
        Gate::authorize('view', $project);
        abort_if($document->project_id !== $project->id, 404);

        return response()->json([
            'document' => $document,
            'pages' => $document->pages()->get(),
        ]);
    }
}
