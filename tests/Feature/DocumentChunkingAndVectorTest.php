<?php

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentChunker;
use App\Services\GeminiEmbeddingService;
use Illuminate\Support\Facades\DB;

test('job segments document pages into passages and generates pgvector embeddings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $document = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'title' => 'Sample Research Paper',
    ]);

    // Page 1 with 2 paragraphs
    $p1 = 'Penelitian ini bertujuan untuk menganalisis efektivitas implementasi model pembelajaran mesin dalam penyusunan tugas akhir mahasiswa teknik informatika. Pendekatan ini memfokuskan pada ekstraksi pengetahuan dan penelusuran sitasi akademik.';
    $p2 = 'Metode yang digunakan mencakup evaluasi dokumen menggunakan closed-library RAG serta pemetaan sitasi secara otomatis sesuai format CSL.';
    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'content' => $p1 . "\n\n" . $p2,
    ]);

    // Page 2
    $p3 = 'Hasil pengujian menunjukkan peningkatan akurasi penulisan sitasi hingga 94 persen dibandingkan metode manual tanpa asisten cerdas.';
    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 2,
        'content' => $p3,
    ]);

    $embeddingService = new GeminiEmbeddingService();
    $embeddingService->setFakeRunner(function (string $text) {
        $dim = array_fill(0, 768, 0.0);
        $dim[0] = str_contains($text, 'Penelitian') ? 0.9 : 0.1;
        $dim[1] = str_contains($text, 'pengujian') ? 0.8 : 0.05;

        return $dim;
    });

    $job = new GenerateDocumentEmbeddingsJob($document);
    $job->handle(new DocumentChunker(), $embeddingService);

    // Verify chunks saved in database
    $chunks = DocumentChunk::where('document_id', $document->id)->orderBy('chunk_index')->get();
    expect($chunks)->not->toBeEmpty();

    // Verify chunk attributes: project_id, document_id, page_number, content, token_count, embedding
    foreach ($chunks as $chunk) {
        expect($chunk->project_id)->toBe($project->id);
        expect($chunk->document_id)->toBe($document->id);
        expect($chunk->content)->not->toBeEmpty();
        expect($chunk->token_count)->toBeGreaterThan(0);
        expect($chunk->token_count)->toBeLessThanOrEqual(550);
        expect($chunk->embedding)->toBeArray();
        expect(count($chunk->embedding))->toBe(768);
    }

    // Verify page number consistency
    $page1Chunks = $chunks->where('page_number', 1);
    $page2Chunks = $chunks->where('page_number', 2);
    expect($page1Chunks)->not->toBeEmpty();
    expect($page2Chunks)->not->toBeEmpty();

    foreach ($page1Chunks as $c) {
        expect($c->content)->not->toContain('Hasil pengujian');
    }
    foreach ($page2Chunks as $c) {
        expect($c->content)->toContain('Hasil pengujian');
    }
});

test('oversized page text is segmented into ~500 token chunks preserving page number', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->processed()->create(['project_id' => $project->id]);

    // Construct ~1200 tokens on a single page across 4 paragraphs
    $para = str_repeat('Kalimat evaluasi mendalam pada bab metodologi riset komputasi modern. ', 18);
    $pageContent = $para . "\n\n" . $para . "\n\n" . $para . "\n\n" . $para;

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 7,
        'content' => $pageContent,
    ]);

    $job = new GenerateDocumentEmbeddingsJob($document);
    $job->handle(new DocumentChunker(), new GeminiEmbeddingService());

    $chunks = DocumentChunk::where('document_id', $document->id)->get();
    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk->page_number)->toBe(7);
        expect($chunk->token_count)->toBeLessThanOrEqual(550);
    }
});

test('deleting a document cascades and wipes all associated chunks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->processed()->create(['project_id' => $project->id]);

    $chunk = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'content' => 'Teks passage kandidat untuk uji cascade.',
    ]);

    expect(DocumentChunk::where('document_id', $document->id)->count())->toBe(1);

    $document->delete();

    expect(DocumentChunk::where('document_id', $document->id)->count())->toBe(0);
});

test('deleting a project cascades and wipes all associated chunks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->processed()->create(['project_id' => $project->id]);

    $chunk = DocumentChunk::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'content' => 'Teks kandidat dalam proyek yang akan dihapus.',
    ]);

    expect(DocumentChunk::where('project_id', $project->id)->count())->toBe(1);

    $project->delete();

    expect(DocumentChunk::where('project_id', $project->id)->count())->toBe(0);
});

test('vector embeddings are persisted and retrieved with cosine similarity on pgvector', function () {
    if (config('database.connections.pgsql.url') === '') {
        config(['database.connections.pgsql.url' => null]);
    }
    // PHPUnit env overrides DB_DATABASE to :memory: which breaks pgsql connection
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
        $pdo = DB::connection('pgsql')->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('PostgreSQL connection not available: ' . $e->getMessage());
    }

    $uniqueEmail = 'pgtest_' . uniqid() . '@example.com';
    $user = User::on('pgsql')->create([
        'name' => 'Pg Tester',
        'email' => $uniqueEmail,
        'password' => bcrypt('password'),
    ]);

    $project = Project::on('pgsql')->create([
        'user_id' => $user->id,
        'title' => 'Pg Vector Research Project',
    ]);

    $document = Document::on('pgsql')->create([
        'project_id' => $project->id,
        'title' => 'Machine Learning Paper',
        'original_filename' => 'paper.pdf',
        'file_path' => 'projects/paper.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'status' => Document::STATUS_PROCESSED,
    ]);

    // Vector A: primarily focused on dimension 0
    $vectorA = array_fill(0, 768, 0.0);
    $vectorA[0] = 1.0;

    // Vector B: orthogonal, primarily focused on dimension 1
    $vectorB = array_fill(0, 768, 0.0);
    $vectorB[1] = 1.0;

    $chunkA = DocumentChunk::on('pgsql')->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'chunk_index' => 0,
        'content' => 'Passage A tentang Deep Learning dan Transformer.',
        'token_count' => 50,
        'embedding' => $vectorA,
    ]);

    $chunkB = DocumentChunk::on('pgsql')->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 2,
        'chunk_index' => 1,
        'content' => 'Passage B tentang Pengujian Perangkat Keras dan CPU.',
        'token_count' => 50,
        'embedding' => $vectorB,
    ]);

    // Query with Vector A
    $results = DocumentChunk::on('pgsql')
        ->where('document_id', $document->id)
        ->nearestTo($project->id, $vectorA, 2)
        ->get();

    expect($results)->toHaveCount(2);
    expect($results[0]->id)->toBe($chunkA->id);
    expect((float) $results[0]->similarity)->toBeGreaterThan(0.99);
    expect((float) $results[1]->similarity)->toBeLessThan(0.01);

    // Verify HNSW index is active on pgvector table
    $indexCheck = DB::connection('pgsql')->select("
        SELECT indexname, indexdef 
        FROM pg_indexes 
        WHERE tablename = 'document_chunks' AND indexname = 'document_chunks_embedding_hnsw_idx'
    ");
    expect($indexCheck)->not->toBeEmpty();
    expect($indexCheck[0]->indexdef)->toContain('hnsw');

    // Clean up pgsql test records
    $project->delete();
    $user->delete();
});

test('job transitions document to failed when embedding service throws exception', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->processed()->create(['project_id' => $project->id]);

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'content' => 'Teks dokumen yang akan memicu kegagalan embedding API.',
    ]);

    $mockService = Mockery::mock(GeminiEmbeddingService::class);
    $mockService->shouldReceive('generateBatchEmbeddings')
        ->once()
        ->andThrow(new \RuntimeException('Google Gemini Quota Exceeded'));

    $job = new GenerateDocumentEmbeddingsJob($document);

    try {
        $job->handle(new DocumentChunker(), $mockService);
        test()->fail('Expected RuntimeException was not thrown');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Google Gemini Quota Exceeded');
    }

    $document->refresh();
    expect($document->status)->toBe(Document::STATUS_FAILED);
    expect($document->error_message)->toContain('Quota Exceeded');
});
