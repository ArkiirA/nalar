<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\SupervisionNote;
use App\Models\SupervisionTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SupervisionNoteController extends Controller
{
    /**
     * Display a listing of supervision notes and checklist tasks for the project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $notes = $project->supervisionNotes()
            ->with(['tasks.chapter'])
            ->get();

        return response()->json([
            'supervision_notes' => $notes,
        ]);
    }

    /**
     * Store a newly created supervision note.
     */
    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'meeting_date' => ['required', 'date'],
            'advisor_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'discussion_notes' => ['nullable', 'string'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['nullable', 'string'],
            'tasks.*.chapter_id' => ['nullable'],
        ]);

        $note = $project->supervisionNotes()->create([
            'meeting_date' => $validated['meeting_date'],
            'advisor_name' => $validated['advisor_name'],
            'notes' => $validated['notes'] ?? $validated['discussion_notes'] ?? null,
        ]);

        if (! empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $taskData) {
                $taskTitle = trim($taskData['title'] ?? '');
                if (empty($taskTitle)) {
                    continue;
                }

                $chapterId = $this->validateChapterBelongsToProject($taskData['chapter_id'] ?? null, $project);

                $note->tasks()->create([
                    'project_id' => $project->id,
                    'chapter_id' => $chapterId,
                    'title' => $taskTitle,
                    'is_completed' => false,
                ]);
            }
        }

        $note->load('tasks.chapter');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Catatan bimbingan berhasil disimpan.',
                'supervision_note' => $note,
            ], 201);
        }

        return back();
    }

    /**
     * Update an existing supervision note.
     */
    public function update(Request $request, Project $project, SupervisionNote $supervision_note): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_if($supervision_note->project_id !== $project->id, 404);

        $validated = $request->validate([
            'meeting_date' => ['sometimes', 'required', 'date'],
            'advisor_name' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'discussion_notes' => ['nullable', 'string'],
        ]);

        $updateData = [];
        if (isset($validated['meeting_date'])) {
            $updateData['meeting_date'] = $validated['meeting_date'];
        }
        if (isset($validated['advisor_name'])) {
            $updateData['advisor_name'] = $validated['advisor_name'];
        }
        if (array_key_exists('notes', $validated) || array_key_exists('discussion_notes', $validated)) {
            $updateData['notes'] = $validated['notes'] ?? $validated['discussion_notes'] ?? null;
        }

        $supervision_note->update($updateData);
        $supervision_note->load('tasks.chapter');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Catatan bimbingan berhasil diperbarui.',
                'supervision_note' => $supervision_note,
            ]);
        }

        return back();
    }

    /**
     * Delete a supervision note and cascade its checklist tasks.
     */
    public function destroy(Request $request, Project $project, SupervisionNote $supervision_note): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_if($supervision_note->project_id !== $project->id, 404);

        $supervision_note->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'deleted',
            ]);
        }

        return back();
    }

    /**
     * Store a new checklist task under a supervision note.
     */
    public function storeTask(Request $request, Project $project, SupervisionNote $supervision_note): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_if($supervision_note->project_id !== $project->id, 404);

        $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'chapter_id' => ['nullable'],
        ]);

        $chapterId = $this->validateChapterBelongsToProject($request->input('chapter_id'), $project);

        $task = $supervision_note->tasks()->create([
            'project_id' => $project->id,
            'chapter_id' => $chapterId,
            'title' => trim($request->input('title')),
            'is_completed' => false,
        ]);

        $task->load('chapter');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Tugas checklist berhasil ditambahkan.',
                'task' => $task,
            ], 201);
        }

        return back();
    }

    /**
     * Update a supervision task's title, linked chapter, or completion status.
     */
    public function updateTask(Request $request, Project $project, SupervisionTask $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'chapter_id' => ['nullable'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('chapter_id')) {
            $task->chapter_id = $this->validateChapterBelongsToProject($request->input('chapter_id'), $project);
        }

        if ($request->has('title')) {
            $task->title = trim($request->input('title'));
        }

        if ($request->has('is_completed')) {
            $task->is_completed = (bool) $request->input('is_completed');
            $task->completed_at = $task->is_completed ? now() : null;
        }

        $task->save();
        $task->load('chapter');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Tugas berhasil diperbarui.',
                'task' => $task,
            ]);
        }

        return back();
    }

    /**
     * Toggle a task's completion status in real time.
     */
    public function toggleTask(Request $request, Project $project, SupervisionTask $task): JsonResponse
    {
        Gate::authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $isCompleted = $task->toggle();
        $task->load('chapter');

        return response()->json([
            'success' => true,
            'is_completed' => $isCompleted,
            'task' => $task,
        ]);
    }

    /**
     * Delete a supervision task.
     */
    public function destroyTask(Request $request, Project $project, SupervisionTask $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $task->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'deleted',
            ]);
        }

        return back();
    }

    /**
     * Validate that a given chapter ID belongs to the active project.
     *
     * @throws ValidationException
     */
    private function validateChapterBelongsToProject(mixed $chapterId, Project $project): ?int
    {
        if ($chapterId === null || $chapterId === '') {
            return null;
        }

        if (! is_numeric($chapterId) || Chapter::where('id', (int) $chapterId)->where('project_id', $project->id)->doesntExist()) {
            throw ValidationException::withMessages([
                'chapter_id' => 'Bab tidak valid dalam proyek ini.',
            ]);
        }

        return (int) $chapterId;
    }
}
