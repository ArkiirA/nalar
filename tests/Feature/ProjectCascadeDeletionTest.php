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

    $claim = \App\Models\Claim::create([
        'project_id' => $project->id,
        'claim_text' => 'Klaim uji cascade',
    ]);
    $assessment = \App\Models\EvidenceAssessment::create([
        'claim_id' => $claim->id,
        'project_id' => $project->id,
        'status' => 'Supported',
        'explanation' => 'Uji cascade',
        'candidate_passages' => [],
    ]);
    expect(\App\Models\Claim::where('project_id', $project->id)->count())->toBe(1);
    expect(\App\Models\EvidenceAssessment::where('project_id', $project->id)->count())->toBe(1);

    $supervisionNote = \App\Models\SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-25',
        'advisor_name' => 'Prof. Dr. Bambang',
        'notes' => 'Catatan bimbingan cascade test',
    ]);
    $supervisionTask = \App\Models\SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $supervisionNote->id,
        'chapter_id' => $chapter1->id,
        'title' => 'Revisi rumusan masalah',
        'is_completed' => false,
    ]);
    expect(\App\Models\SupervisionNote::where('project_id', $project->id)->count())->toBe(1);
    expect(\App\Models\SupervisionTask::where('project_id', $project->id)->count())->toBe(1);

    $response = $this->actingAs($user)->delete("/projects/{$project->id}");
    $response->assertRedirect('/projects');

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseMissing('chapters', ['id' => $chapter1->id]);
    $this->assertDatabaseMissing('chapters', ['id' => $chapter2->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('document_pages', ['id' => $page->id]);
    $this->assertDatabaseMissing('references', ['id' => $ref->id]);
    $this->assertDatabaseMissing('document_chunks', ['id' => $chunk->id]);
    $this->assertDatabaseMissing('claims', ['id' => $claim->id]);
    $this->assertDatabaseMissing('evidence_assessments', ['id' => $assessment->id]);
    $this->assertDatabaseMissing('supervision_notes', ['id' => $supervisionNote->id]);
    $this->assertDatabaseMissing('supervision_tasks', ['id' => $supervisionTask->id]);
    expect(Chapter::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\Document::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\DocumentPage::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\Reference::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\DocumentChunk::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\Claim::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\EvidenceAssessment::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\SupervisionNote::where('project_id', $project->id)->count())->toBe(0);
    expect(\App\Models\SupervisionTask::where('project_id', $project->id)->count())->toBe(0);
});
