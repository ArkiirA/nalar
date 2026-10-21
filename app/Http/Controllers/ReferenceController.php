<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Reference;
use App\Services\DoiResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceController extends Controller
{
    /**
     * Resolve bibliographic metadata for a DOI via content negotiation.
     */
    public function resolveDoi(Request $request, Project $project, DoiResolver $doiResolver): JsonResponse
    {
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'doi' => ['required', 'string'],
        ]);

        $result = $doiResolver->resolve($validated['doi']);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Gagal memuat metadata dari DOI.',
            ], 422);
        }

        $normalizedDoi = $doiResolver->normalizeDoi($validated['doi']);
        $isDuplicate = $project->references()->where('doi', $normalizedDoi)->exists();

        return response()->json([
            'success' => true,
            'metadata' => $result['data'],
            'is_duplicate' => $isDuplicate,
        ]);
    }

    /**
     * Store a newly created reference in the Research Library.
     */
    public function store(Request $request, Project $project, DoiResolver $doiResolver): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $this->validateReference($request);

        $title = trim($validated['title']);
        $doi = ! empty($validated['doi']) ? $doiResolver->normalizeDoi($validated['doi']) : null;

        // Duplicate checks within active project
        if ($doi && $project->references()->where('doi', $doi)->exists()) {
            throw ValidationException::withMessages([
                'doi' => 'Referensi dengan DOI ini sudah terdaftar dalam Research Library proyek Anda.',
            ]);
        }

        if ($project->references()->whereRaw('LOWER(title) = ?', [strtolower($title)])->exists()) {
            throw ValidationException::withMessages([
                'title' => 'Referensi dengan judul serupa sudah terdaftar dalam Research Library proyek Anda.',
            ]);
        }

        $authors = Reference::normalizeAuthors($validated['authors'] ?? []);

        $reference = $project->references()->create([
            'document_id' => $validated['document_id'] ?? null,
            'type' => $validated['type'] ?? 'article-journal',
            'title' => $title,
            'authors' => $authors,
            'year' => ! empty($validated['year']) ? (int) $validated['year'] : null,
            'publication_title' => $validated['publication_title'] ?? null,
            'volume' => $validated['volume'] ?? null,
            'issue' => $validated['issue'] ?? null,
            'pages' => $validated['pages'] ?? null,
            'publisher' => $validated['publisher'] ?? null,
            'publisher_place' => $validated['publisher_place'] ?? null,
            'doi' => $doi,
            'url' => $validated['url'] ?? null,
            'csl_data' => $validated['csl_data'] ?? null,
            'is_confirmed' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json($reference, 201);
        }

        return back();
    }

    /**
     * Update an existing reference.
     */
    public function update(Request $request, Project $project, Reference $reference, DoiResolver $doiResolver): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);
        abort_if($reference->project_id !== $project->id, 404);

        $validated = $this->validateReference($request);

        $title = trim($validated['title']);
        $doi = ! empty($validated['doi']) ? $doiResolver->normalizeDoi($validated['doi']) : null;

        if ($doi && $project->references()->where('id', '!=', $reference->id)->where('doi', $doi)->exists()) {
            throw ValidationException::withMessages([
                'doi' => 'Referensi dengan DOI ini sudah terdaftar dalam Research Library proyek Anda.',
            ]);
        }

        if ($project->references()->where('id', '!=', $reference->id)->whereRaw('LOWER(title) = ?', [strtolower($title)])->exists()) {
            throw ValidationException::withMessages([
                'title' => 'Referensi dengan judul serupa sudah terdaftar dalam Research Library proyek Anda.',
            ]);
        }

        $authors = Reference::normalizeAuthors($validated['authors'] ?? ($reference->authors ?? []));

        $reference->update([
            'document_id' => $validated['document_id'] ?? $reference->document_id,
            'type' => $validated['type'] ?? $reference->type,
            'title' => $title,
            'authors' => $authors,
            'year' => ! empty($validated['year']) ? (int) $validated['year'] : null,
            'publication_title' => $validated['publication_title'] ?? null,
            'volume' => $validated['volume'] ?? null,
            'issue' => $validated['issue'] ?? null,
            'pages' => $validated['pages'] ?? null,
            'publisher' => $validated['publisher'] ?? null,
            'publisher_place' => $validated['publisher_place'] ?? null,
            'doi' => $doi,
            'url' => $validated['url'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json($reference);
        }

        return back();
    }

    /**
     * Delete a reference.
     */
    public function destroy(Request $request, Project $project, Reference $reference): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);
        abort_if($reference->project_id !== $project->id, 404);

        if ($reference->isCited()) {
            throw ValidationException::withMessages([
                'reference' => 'Referensi ini tidak dapat dihapus karena sedang disitasi dalam bab skripsi Anda.',
            ]);
        }

        $reference->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }

    /**
     * Update project preferred citation style (APA or Harvard).
     */
    public function updateCitationStyle(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'citation_style' => ['required', 'string', Rule::in(['apa', 'harvard', 'APA 7th', 'Harvard', 'apa_7th'])],
        ]);

        $normalized = str_contains(strtolower($validated['citation_style']), 'harvard') ? 'harvard' : 'apa';

        $project->update([
            'citation_style' => $normalized,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['citation_style' => $normalized]);
        }

        return back();
    }

    /**
     * Validate incoming reference input.
     *
     * @return array<string, mixed>
     */
    protected function validateReference(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:50'],
            'authors' => ['nullable'],
            'year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'publication_title' => ['nullable', 'string', 'max:255'],
            'volume' => ['nullable', 'string', 'max:50'],
            'issue' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'string', 'max:50'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publisher_place' => ['nullable', 'string', 'max:255'],
            'doi' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'csl_data' => ['nullable', 'array'],
        ]);
    }
}
