<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Support\GuidelinePresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChapterController extends Controller
{
    /**
     * Store a newly created chapter or section.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $this->validateChapter($request, $project);

        $order = $validated['order'] ?? (
            Chapter::where('project_id', $project->id)
                ->where('parent_id', $validated['parent_id'] ?? null)
                ->max('order') + 1
        );

        $project->outlineItems()->create([
            'parent_id' => $validated['parent_id'] ?? null,
            'title' => $validated['title'],
            'order' => $order,
        ]);

        return back();
    }

    /**
     * Update the specified chapter or section.
     */
    public function update(Request $request, Project $project, Chapter $chapter): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $validated = $this->validateChapter($request, $project, true);

        if (isset($validated['parent_id']) && $validated['parent_id'] === $chapter->id) {
            throw ValidationException::withMessages(['parent_id' => 'Item cannot be parented to itself.']);
        }

        if (array_key_exists('content', $validated) && is_array($validated['content'])) {
            $citedRefIds = Chapter::extractCitationReferenceIds($validated['content']);
            if (! empty($citedRefIds)) {
                $validRefIds = $project->references()->whereIn('id', $citedRefIds)->pluck('id')->all();
                $invalidRefIds = array_diff($citedRefIds, $validRefIds);
                if (! empty($invalidRefIds)) {
                    throw ValidationException::withMessages([
                        'content' => 'Terdapat sitasi yang merujuk pada referensi yang tidak terdaftar dalam proyek ini.',
                    ]);
                }
            }
        }

        $chapter->update($validated);

        if (array_key_exists('content', $validated) && $validated['content'] !== null) {
            $chapter->maybeRecordAutoSnapshot($validated['content']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Chapter saved successfully.',
                'chapter' => $chapter->fresh(),
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return back();
    }

    /**
     * Remove the specified chapter or section.
     */
    public function destroy(Request $request, Project $project, Chapter $chapter): RedirectResponse
    {
        Gate::authorize('update', $project);

        abort_if($chapter->project_id !== $project->id, 404);

        $chapter->delete();

        return back();
    }

    /**
     * Batch reorder chapters and sections.
     */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => [
                'required',
                Rule::exists('chapters', 'id')->where('project_id', $project->id),
            ],
            'items.*.order' => ['required', 'integer'],
            'items.*.parent_id' => [
                'nullable',
                Rule::exists('chapters', 'id')->where('project_id', $project->id),
            ],
        ]);

        DB::transaction(function () use ($validated, $project) {
            foreach ($validated['items'] as $item) {
                if (isset($item['parent_id']) && $item['parent_id'] === $item['id']) {
                    continue;
                }

                Chapter::where('id', $item['id'])
                    ->where('project_id', $project->id)
                    ->update([
                        'order' => $item['order'],
                        'parent_id' => $item['parent_id'] ?? null,
                    ]);
            }
        });

        return back();
    }

    /**
     * Apply or switch the guideline preset for a project.
     */
    public function applyPreset(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'preset' => ['required', 'string', Rule::in(array_keys(GuidelinePresets::all()))],
        ]);

        $project->applyGuidelinePreset($validated['preset']);

        return back();
    }

    /**
     * Validate common chapter/section attributes.
     *
     * @return array<string, mixed>
     */
    private function validateChapter(Request $request, Project $project, bool $isUpdate = false): array
    {
        return $request->validate([
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'parent_id' => [
                'nullable',
                Rule::exists('chapters', 'id')->where('project_id', $project->id),
            ],
            'order' => ['nullable', 'integer'],
        ]);
    }
}
