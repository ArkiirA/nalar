<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        ]);

        $request->user()->projects()->create($validated);

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
