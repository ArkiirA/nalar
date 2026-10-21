<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPdfDocument;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * List all documents in the project research library.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $documents = $project->documents()
            ->select(['id', 'project_id', 'title', 'original_filename', 'file_size', 'mime_type', 'page_count', 'status', 'error_message', 'metadata', 'created_at'])
            ->get();

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Upload a new PDF research paper into the library.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:25600'], // 25MB max
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $fallbackTitle = pathinfo($originalFilename, PATHINFO_FILENAME);
        $title = !empty($validated['title']) ? trim($validated['title']) : $fallbackTitle;

        // Store file scoped to the active project
        $storedPath = $uploadedFile->store("projects/{$project->id}/documents", 'local');

        $document = $project->documents()->create([
            'title' => $title,
            'original_filename' => $originalFilename,
            'file_path' => $storedPath,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType() ?: 'application/pdf',
            'status' => Document::STATUS_QUEUED,
        ]);

        // Dispatch background extraction job
        ProcessPdfDocument::dispatch($document);

        return response()->json([
            'message' => 'Dokumen berhasil diunggah dan masuk antrean ekstraksi.',
            'document' => $document->fresh(),
        ], 201);
    }

    /**
     * Show single document with extracted pages and text.
     */
    public function show(Project $project, Document $document): JsonResponse
    {
        Gate::authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);

        return response()->json([
            'document' => $document,
        ]);
    }

    /**
     * Retry extraction for a failed or partial document.
     */
    public function retry(Project $project, Document $document): JsonResponse
    {
        Gate::authorize('update', $project);
        abort_unless($document->project_id === $project->id, 404);

        $document->update([
            'status' => Document::STATUS_QUEUED,
            'error_message' => null,
        ]);

        ProcessPdfDocument::dispatch($document);

        return response()->json([
            'message' => 'Proses ekstraksi dijadwalkan ulang.',
            'document' => $document->fresh(),
        ]);
    }

    /**
     * Delete document and remove physical file from disk.
     */
    public function destroy(Project $project, Document $document): JsonResponse
    {
        Gate::authorize('update', $project);
        abort_unless($document->project_id === $project->id, 404);

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Dokumen berhasil dihapus dari Research Library.',
        ]);
    }
}
