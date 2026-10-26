<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\GuidelinePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of user projects.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Projects/Index', [
            'projects' => $request->user()->projects()->latest()->get(),
            'guidelinePresets' => GuidelinePresets::all(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_completion_date' => ['nullable', 'date'],
            'guideline_preset' => ['nullable', 'string', Rule::in(array_keys(GuidelinePresets::all()))],
        ]);

        $project = $request->user()->projects()->create([
            'title' => $validated['title'],
            'target_completion_date' => $validated['target_completion_date'] ?? null,
        ]);

        if (! empty($validated['guideline_preset'])) {
            GuidelinePresets::applyToProject($project, $validated['guideline_preset']);
        }

        return redirect()->route('projects.index');
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'chapters' => $project->rootChapters()->with('sections')->get(),
            'guidelinePresets' => GuidelinePresets::all(),
            'documents' => $project->documents()->withCount('pages')->get(),
            'references' => $project->references()->with(['document', 'project'])->get()->map(function ($ref) {
                return array_merge($ref->toArray(), [
                    'formatted_apa' => $ref->formatted_apa,
                    'formatted_harvard' => $ref->formatted_harvard,
                    'in_text_citation' => $ref->in_text_citation,
                    'missing_field_warnings' => $ref->getMissingFieldWarnings(),
                ]);
            }),
            'supervisionNotes' => $project->supervisionNotes()->with(['tasks.chapter'])->get(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_completion_date' => ['nullable', 'date'],
        ]);

        $project->update($validated);

        return redirect()->route('projects.index');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index');
    }
}
