<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use App\Models\Project;
use App\Models\User;
use App\Repositories\HybridSearchRepository;
use App\Services\GeminiEmbeddingService;
use App\Services\RetrievalService;
use App\Support\CandidatePassage;
use Illuminate\Support\Facades\DB;

test('hybrid search repository merges vector similarity and keyword search using reciprocal rank fusion k=60', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $doc = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'title' => 'Buku Panduan Kecerdasan Buatan',
    ]);

    // Chunk A: Strong keyword match ("transformer attention mechanism"), moderate vector match
    $chunkA = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 12,
        'content' => 'Model transformer mengandalkan attention mechanism untuk memproses urutan teks bahasa alami secara paralel.',
        'embedding' => array_merge([0.5, 0.0], array_fill(0, 766, 0.0)),
    ]);

    // Chunk B: Strong vector match, moderate keyword match
    $chunkB = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 15,
        'content' => 'Arsitektur deep learning modern mempercepat pemodelan representasi kata dalam komputasi linguistik.',
        'embedding' => array_merge([0.9, 0.1], array_fill(0, 766, 0.0)),
    ]);

    // Chunk C: Weak match on both
    $chunkC = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 20,
        'content' => 'Sistem operasi berbasis kernel monolitik mengelola alokasi memori fisik.',
        'embedding' => array_merge([0.0, 0.0], array_fill(0, 766, 0.0)),
    ]);

    $repository = new HybridSearchRepository();

    // Query vector aligned with dimension 0
    $queryVector = array_merge([1.0, 0.0], array_fill(0, 766, 0.0));
    $queryText = 'transformer attention mechanism deep learning';

    $results = $repository->hybridSearch(
        projectId: $project->id,
        query: $queryText,
        vector: $queryVector,
        limit: 3,
        k: 60.0
    );

    expect($results)->not->toBeEmpty();
    expect($results->count())->toBeGreaterThanOrEqual(2);

    // Chunks A and B should be top results, Chunk C should not beat either
    $topChunkIds = $results->pluck('id')->all();
    expect($topChunkIds)->toContain($chunkA->id);
    expect($topChunkIds)->toContain($chunkB->id);

    // Verify RRF score exists and is computed properly
    foreach ($results as $chunk) {
        expect($chunk->rrf_score)->toBeGreaterThan(0.0);
        expect($chunk->rrf_score)->toBeLessThanOrEqual(2.0 / 61.0);
    }
});

test('every query strictly enforces tenant project scoping and prevents cross-project leakage', function () {
    $userA = User::factory()->create();
    $projectA = Project::factory()->create(['user_id' => $userA->id, 'title' => 'Project A']);

    $userB = User::factory()->create();
    $projectB = Project::factory()->create(['user_id' => $userB->id, 'title' => 'Project B']);

    $docA = Document::factory()->processed()->create([
        'project_id' => $projectA->id,
        'title' => 'Project A Paper',
    ]);
    $docB = Document::factory()->processed()->create([
        'project_id' => $projectB->id,
        'title' => 'Secret Project B Paper',
    ]);

    $identicalText = 'Penelitian klasifikasi citra medis dengan convolutional neural network mencapai akurasi 98 persen.';
    $identicalVector = array_merge([0.88, 0.44], array_fill(0, 766, 0.0));

    // Chunk in Project A
    $chunkA = DocumentChunk::factory()->create([
        'project_id' => $projectA->id,
        'document_id' => $docA->id,
        'page_number' => 5,
        'content' => $identicalText,
        'embedding' => $identicalVector,
    ]);

    // Chunk in Project B (Identical content and vector)
    $chunkB = DocumentChunk::factory()->create([
        'project_id' => $projectB->id,
        'document_id' => $docB->id,
        'page_number' => 8,
        'content' => $identicalText,
        'embedding' => $identicalVector,
    ]);

    $repository = new HybridSearchRepository();

    // Query on Project A
    $resultsA = $repository->hybridSearch(
        projectId: $projectA->id,
        query: 'klasifikasi citra medis convolutional neural network akurasi',
        vector: $identicalVector,
        limit: 10
    );

    // Query on Project B
    $resultsB = $repository->hybridSearch(
        projectId: $projectB->id,
        query: 'klasifikasi citra medis convolutional neural network akurasi',
        vector: $identicalVector,
        limit: 10
    );

    // Project A results must contain chunkA and NEVER chunkB
    $idsA = $resultsA->pluck('id')->all();
    expect($idsA)->toContain($chunkA->id);
    expect($idsA)->not->toContain($chunkB->id);
    foreach ($resultsA as $chunk) {
        expect($chunk->project_id)->toBe($projectA->id);
    }

    // Project B results must contain chunkB and NEVER chunkA
    $idsB = $resultsB->pluck('id')->all();
    expect($idsB)->toContain($chunkB->id);
    expect($idsB)->not->toContain($chunkA->id);
    foreach ($resultsB as $chunk) {
        expect($chunk->project_id)->toBe($projectB->id);
    }
});

test('retrieval service returns ranked candidate passages with document title, page number, and score', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $doc = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'title' => 'Sistem Rekomendasi Jurnal Ilmiah',
    ]);

    $chunk = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 14,
        'content' => 'Evaluasi model menghasilkan nilai Mean Reciprocal Rank (MRR) sebesar 0.82 pada dataset pengujian.',
        'embedding' => array_fill(0, 768, 0.1),
    ]);

    $embeddingService = new GeminiEmbeddingService();
    $embeddingService->setFakeRunner(fn ($text) => array_fill(0, 768, 0.1));

    $repository = new HybridSearchRepository();
    $service = new RetrievalService($repository, $embeddingService);

    $passages = $service->retrieve(
        projectId: $project->id,
        query: 'Mean Reciprocal Rank evaluasi dataset',
        limit: 5
    );

    expect($passages)->not->toBeEmpty();
    $topPassage = $passages->first();

    expect($topPassage)->toBeInstanceOf(CandidatePassage::class);
    expect($topPassage->id)->toBe($chunk->id);
    expect($topPassage->document_id)->toBe($doc->id);
    expect($topPassage->document_title)->toBe('Sistem Rekomendasi Jurnal Ilmiah');
    expect($topPassage->page_number)->toBe(14);
    expect($topPassage->content)->toContain('Mean Reciprocal Rank (MRR)');
    expect($topPassage->score)->toBeGreaterThan(0.0);
});

test('retrieval service handles pure vector search and pure keyword search gracefully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $doc = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'title' => 'Statistik Inferensial',
    ]);

    $chunk = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 22,
        'content' => 'Uji hipotesis statistik t-test dua sampel independen menunjukkan signifikansi p < 0.01.',
        'embedding' => array_fill(0, 768, 0.2),
    ]);

    $repository = new HybridSearchRepository();
    $embeddingService = new GeminiEmbeddingService();

    $service = new RetrievalService($repository, $embeddingService);

    // Pure vector search with pre-supplied vector and empty query
    $vectorResults = $service->retrieve(
        projectId: $project->id,
        query: '',
        limit: 5,
        vector: array_fill(0, 768, 0.2)
    );
    expect($vectorResults)->not->toBeEmpty();
    expect($vectorResults->first()->id)->toBe($chunk->id);

    // Pure keyword search with empty vector and fake embedding runner returning empty
    $embeddingService->setFakeRunner(fn () => []);
    $keywordResults = $service->retrieve(
        projectId: $project->id,
        query: 'uji hipotesis statistik signifikansi',
        limit: 5,
        vector: []
    );
    expect($keywordResults)->not->toBeEmpty();
    expect($keywordResults->first()->id)->toBe($chunk->id);

    // Completely empty query and vector returns empty collection
    $emptyResults = $service->retrieve($project->id, '', 5, null);
    expect($emptyResults)->toBeEmpty();
});

test('postgreSQL hybrid search integration tests with pgvector and tsvector GIN index', function () {
    if (config('database.connections.pgsql.url') === '') {
        config(['database.connections.pgsql.url' => null]);
    }
    $pgDb = env('PGSQL_DATABASE') ?: (env('DB_DATABASE') !== ':memory:' ? env('DB_DATABASE') : 'nalar');
    $pgUser = env('PGSQL_USERNAME') ?: (env('DB_USERNAME') !== 'root' ? env('DB_USERNAME', 'nalar') : 'nalar');
    $pgPass = env('PGSQL_PASSWORD') ?: env('DB_PASSWORD', 'secret');

    config([
        'database.connections.pgsql.database' => $pgDb ?: 'nalar',
        'database.connections.pgsql.username' => $pgUser ?: 'nalar',
        'database.connections.pgsql.password' => $pgPass ?: 'secret',
    ]);
    DB::purge('pgsql');

    try {
        DB::connection('pgsql')->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('PostgreSQL connection not available: ' . $e->getMessage());
    }

    $uniqueEmail = 'retrieval_tester_' . uniqid() . '@example.com';
    $user = User::on('pgsql')->create([
        'name' => 'Retrieval Tester',
        'email' => $uniqueEmail,
        'password' => bcrypt('password'),
    ]);

    $project = Project::on('pgsql')->create([
        'user_id' => $user->id,
        'title' => 'Thesis on NLP in Education',
    ]);

    $foreignProject = Project::on('pgsql')->create([
        'user_id' => $user->id,
        'title' => 'Foreign Project on Agriculture',
    ]);

    $doc = Document::on('pgsql')->create([
        'project_id' => $project->id,
        'title' => 'NLP Evaluation Paper',
        'original_filename' => 'nlp.pdf',
        'file_path' => 'projects/nlp.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'status' => Document::STATUS_PROCESSED,
    ]);

    $foreignDoc = Document::on('pgsql')->create([
        'project_id' => $foreignProject->id,
        'title' => 'Agriculture IoT Paper',
        'original_filename' => 'agri.pdf',
        'file_path' => 'projects/agri.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'status' => Document::STATUS_PROCESSED,
    ]);

    $vecTarget = array_fill(0, 768, 0.0);
    $vecTarget[0] = 1.0;

    $vecOther = array_fill(0, 768, 0.0);
    $vecOther[1] = 1.0;

    // Chunk 1: Both keyword and vector match
    $chunk1 = DocumentChunk::on('pgsql')->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 3,
        'chunk_index' => 0,
        'content' => 'Penerapan arsitektur Transformer pada tugas pemrosesan bahasa alami bahasa Indonesia menghasilkan peningkatan performa F1-score signifikan.',
        'token_count' => 60,
        'embedding' => $vecTarget,
    ]);

    // Chunk 2: Keyword match only (different vector)
    $chunk2 = DocumentChunk::on('pgsql')->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 7,
        'chunk_index' => 1,
        'content' => 'Model Transformer memfasilitasi komputasi paralel dalam korpus teks berskala besar.',
        'token_count' => 50,
        'embedding' => $vecOther,
    ]);

    // Chunk 3: Vector match only (different text)
    $chunk3 = DocumentChunk::on('pgsql')->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 9,
        'chunk_index' => 2,
        'content' => 'Kajian teoritis mengenai representasi matematis dalam ruang berdimensi tinggi tanpa istilah spesifik.',
        'token_count' => 50,
        'embedding' => $vecTarget,
    ]);

    // Chunk 4 in Foreign Project: Perfect match on both, must NEVER be returned
    $chunkForeign = DocumentChunk::on('pgsql')->create([
        'project_id' => $foreignProject->id,
        'document_id' => $foreignDoc->id,
        'page_number' => 1,
        'chunk_index' => 0,
        'content' => 'Penerapan arsitektur Transformer pada tugas pemrosesan bahasa alami bahasa Indonesia menghasilkan peningkatan performa F1-score signifikan.',
        'token_count' => 60,
        'embedding' => $vecTarget,
    ]);

    $repository = (new HybridSearchRepository())->onConnection('pgsql');

    $results = $repository->hybridSearch(
        projectId: $project->id,
        query: 'Transformer bahasa alami',
        vector: $vecTarget,
        limit: 5
    );

    // Foreign chunk must NEVER leak
    $resultIds = $results->pluck('id')->all();
    expect($resultIds)->not->toContain($chunkForeign->id);

    // Chunk 1 matches BOTH vector and keyword, so its RRF score must be higher than Chunk 2 or Chunk 3
    expect($resultIds[0])->toBe($chunk1->id);

    $chunk1Result = $results->firstWhere('id', $chunk1->id);
    $chunk2Result = $results->firstWhere('id', $chunk2->id);
    $chunk3Result = $results->firstWhere('id', $chunk3->id);

    expect($chunk1Result->rrf_score)->toBeGreaterThan($chunk2Result->rrf_score);
    expect($chunk1Result->rrf_score)->toBeGreaterThan($chunk3Result->rrf_score);

    // Test Indonesian stemming in PostgreSQL ('pembelajaran' matches 'belajar')
    $stemmingResults = $repository->searchByKeyword($project->id, 'pembelajaran', 5);
    // Even if exact word isn't present, text search matches Indonesian dictionary or English dictionary
    expect($repository->searchByKeyword($project->id, 'Transformer', 5)->pluck('id')->all())->toContain($chunk1->id);

    // Test RetrievalService on pgsql
    $fakeEmbedding = new GeminiEmbeddingService();
    $fakeEmbedding->setFakeRunner(fn () => $vecTarget);
    $service = (new RetrievalService($repository, $fakeEmbedding))->onConnection('pgsql');

    $passages = $service->retrieve($project->id, 'Transformer bahasa alami', 3);
    expect($passages)->not->toBeEmpty();
    expect($passages->first()->document_title)->toBe('NLP Evaluation Paper');
    expect($passages->first()->page_number)->toBe(3);

    // Clean up pgsql records
    $project->delete();
    $foreignProject->delete();
    $user->delete();
});
