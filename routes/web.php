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

    Route::post('/projects/{project}/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('projects.documents.store');
    Route::post('/projects/{project}/documents/{document}/retry', [\App\Http\Controllers\DocumentController::class, 'retry'])->name('projects.documents.retry')->scopeBindings();
    Route::delete('/projects/{project}/documents/{document}', [\App\Http\Controllers\DocumentController::class, 'destroy'])->name('projects.documents.destroy')->scopeBindings();
    Route::get('/projects/{project}/documents/{document}/pages', [\App\Http\Controllers\DocumentController::class, 'pages'])->name('projects.documents.pages')->scopeBindings();
    Route::get('/projects/{project}/documents/{document}/file', [\App\Http\Controllers\DocumentController::class, 'file'])->name('projects.documents.file')->scopeBindings();

    Route::post('/projects/{project}/references/resolve-doi', [\App\Http\Controllers\ReferenceController::class, 'resolveDoi'])->name('projects.references.resolve-doi');
    Route::post('/projects/{project}/references', [\App\Http\Controllers\ReferenceController::class, 'store'])->name('projects.references.store');
    Route::patch('/projects/{project}/references/{reference}', [\App\Http\Controllers\ReferenceController::class, 'update'])->name('projects.references.update')->scopeBindings();
    Route::delete('/projects/{project}/references/{reference}', [\App\Http\Controllers\ReferenceController::class, 'destroy'])->name('projects.references.destroy')->scopeBindings();
    Route::patch('/projects/{project}/citation-style', [\App\Http\Controllers\ReferenceController::class, 'updateCitationStyle'])->name('projects.citation-style');

    Route::post('/projects/{project}/ai/consent', [\App\Http\Controllers\AiCopilotController::class, 'updateConsent'])->name('projects.ai.consent');
    Route::post('/projects/{project}/ai/chat', [\App\Http\Controllers\AiCopilotController::class, 'chat'])->name('projects.ai.chat');
    Route::post('/projects/{project}/ai/proposal', [\App\Http\Controllers\AiCopilotController::class, 'proposal'])->name('projects.ai.proposal');

    Route::post('/projects/{project}/evidence/check', [\App\Http\Controllers\EvidenceCheckerController::class, 'check'])->name('projects.evidence.check');
    Route::get('/projects/{project}/evidence/claims/{claim}', [\App\Http\Controllers\EvidenceCheckerController::class, 'show'])->name('projects.evidence.show')->scopeBindings();

    Route::get('/projects/{project}/supervision-notes', [\App\Http\Controllers\SupervisionNoteController::class, 'index'])->name('projects.supervision-notes.index');
    Route::post('/projects/{project}/supervision-notes', [\App\Http\Controllers\SupervisionNoteController::class, 'store'])->name('projects.supervision-notes.store');
    Route::patch('/projects/{project}/supervision-notes/{supervision_note}', [\App\Http\Controllers\SupervisionNoteController::class, 'update'])->name('projects.supervision-notes.update')->scopeBindings();
    Route::delete('/projects/{project}/supervision-notes/{supervision_note}', [\App\Http\Controllers\SupervisionNoteController::class, 'destroy'])->name('projects.supervision-notes.destroy')->scopeBindings();

    Route::post('/projects/{project}/supervision-notes/{supervision_note}/tasks', [\App\Http\Controllers\SupervisionNoteController::class, 'storeTask'])->name('projects.supervision-notes.tasks.store')->scopeBindings();
    Route::patch('/projects/{project}/supervision-tasks/{task}', [\App\Http\Controllers\SupervisionNoteController::class, 'updateTask'])->name('projects.supervision-tasks.update')->scopeBindings();
    Route::post('/projects/{project}/supervision-tasks/{task}/toggle', [\App\Http\Controllers\SupervisionNoteController::class, 'toggleTask'])->name('projects.supervision-tasks.toggle')->scopeBindings();
    Route::delete('/projects/{project}/supervision-tasks/{task}', [\App\Http\Controllers\SupervisionNoteController::class, 'destroyTask'])->name('projects.supervision-tasks.destroy')->scopeBindings();

    Route::get('/projects/{project}/export/validate', [\App\Http\Controllers\ExportController::class, 'validateExport'])->name('projects.export.validate');
    Route::get('/projects/{project}/export/docx', [\App\Http\Controllers\ExportController::class, 'exportDocx'])->name('projects.export.docx');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
