<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;

test('deleting a project cascades and wipes all associated child records', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $chapter1 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'Bab I Pendahuluan',
    ]);

    $chapter2 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'Bab II Tinjauan Pustaka',
    ]);

    expect(Chapter::where('project_id', $project->id)->count())->toBe(2);

    $document = \App\Models\Document::factory()->create(['project_id' => $project->id]);
    $page = \App\Models\DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
    ]);

    expect(\App\Models\Document::where('project_id', $project->id)->count())->toBe(1);
    expect(\App\Models\DocumentPage::where('project_id', $project->id)->count())->toBe(1);

    $ref = \App\Models\Reference::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
    ]);
    expect(\App\Models\Reference::where('project_id', $project->id)->count())->toBe(1);

    $chunk = \App\Models\DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'document_page_id' => $page->id,
    ]);
    expect(\App\Models\DocumentChunk::where('project_id', $project->id)->count())->toBe(1);

    $response = $this->actingAs($user)->delete("/projects/{$project->id}");
    $response->assertRedirect('/projects');

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseMissing('chapters', ['id' => $chapter1->id]);
    $this->assertDatabaseMissing('chapters', ['id' => $chapter2->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('document_pages', ['id' => $page->id]);
    $this->assertDatabaseMissing('references', ['id' => $ref->id]);
    $this->assertDatabaseMissing('document_chunks', ['id' => $chunk->id]);
    expect(Chapter::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\Document::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\DocumentPage::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\Reference::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\DocumentChunk::where('project_id', $project->id)->count())->toBe(0);
});
