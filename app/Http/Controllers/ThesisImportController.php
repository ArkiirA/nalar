<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ThesisImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ThesisImportController extends Controller
{
    public function __construct(
        protected ThesisImporterService $thesisImporterService
    ) {
    }

    /**
     * Parse an uploaded thesis draft file (DOCX or PDF) into candidate chapters.
     */
    public function parse(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $request->validate([
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:51200'],
        ], [
            'file.required' => 'Pilih file draf skripsi untuk diunggah.',
            'file.mimes' => 'Format file harus berupa DOCX atau PDF.',
            'file.max' => 'Ukuran file draf skripsi tidak boleh melebihi 50MB.',
        ]);

        $uploadedFile = $request->file('file');

        try {
            $candidateChapters = $this->thesisImporterService->parseFile($uploadedFile);

            return response()->json([
                'message' => 'Draf skripsi berhasil dipindai.',
                'candidate_chapters' => $candidateChapters,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memindai draf skripsi: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm candidate chapters and import them as Chapter Documents in the project.
     */
    public function confirm(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'chapters' => ['required', 'array', 'min:1'],
            'chapters.*.title' => ['required', 'string', 'max:255'],
            'chapters.*.content' => ['nullable', 'array'],
        ], [
            'chapters.required' => 'Pilih setidaknya satu bab untuk diimpor.',
            'chapters.min' => 'Pilih setidaknya satu bab untuk diimpor.',
            'chapters.*.title.required' => 'Judul bab tidak boleh kosong.',
        ]);

        $created = $this->thesisImporterService->import($project, $validated['chapters']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $created->count().' bab berhasil diimpor ke dalam outline proyek.',
                'chapters' => $created,
            ], 201);
        }

        return back()->with('success', $created->count().' bab berhasil diimpor.');
    }
}
