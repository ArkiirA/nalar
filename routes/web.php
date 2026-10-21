<?php

use App\Http\Controllers\ChapterController;
use App\Http\Controllers\ChapterRevisionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('projects.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('/projects/{project}/guideline-preset', [ChapterController::class, 'applyPreset'])->name('projects.guideline-preset');
    Route::post('/projects/{project}/chapters/reorder', [ChapterController::class, 'reorder'])->name('projects.chapters.reorder');
    Route::post('/projects/{project}/chapters', [ChapterController::class, 'store'])->name('projects.chapters.store');
    Route::patch('/projects/{project}/chapters/{chapter}', [ChapterController::class, 'update'])->name('projects.chapters.update')->scopeBindings();
    Route::delete('/projects/{project}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('projects.chapters.destroy')->scopeBindings();

    Route::get('/projects/{project}/chapters/{chapter}/revisions', [ChapterRevisionController::class, 'index'])->name('projects.chapters.revisions.index')->scopeBindings();
    Route::post('/projects/{project}/chapters/{chapter}/revisions', [ChapterRevisionController::class, 'store'])->name('projects.chapters.revisions.store')->scopeBindings();
    Route::get('/projects/{project}/chapters/{chapter}/revisions/{revision}', [ChapterRevisionController::class, 'show'])->name('projects.chapters.revisions.show')->scopeBindings();
    Route::post('/projects/{project}/chapters/{chapter}/revisions/{revision}/restore', [ChapterRevisionController::class, 'restore'])->name('projects.chapters.revisions.restore')->scopeBindings();

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
