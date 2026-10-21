<?php

use App\Services\GeminiEmbeddingService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('generateEmbedding returns 768-dimensional vector via HTTP API', function () {
    $expectedVector = array_fill(0, 768, 0.05);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => [
                'values' => $expectedVector,
            ],
        ], 200),
    ]);

    $service = new GeminiEmbeddingService(apiKey: 'test-api-key');
    $embedding = $service->generateEmbedding('Kajian pustaka mengenai model machine learning.');

    expect($embedding)->toHaveCount(768);
    expect($embedding[0])->toBe(0.05);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'text-embedding-004:embedContent')
            && str_contains($request->url(), 'key=test-api-key')
            && $request['content']['parts'][0]['text'] === 'Kajian pustaka mengenai model machine learning.';
    });
});

test('generateBatchEmbeddings returns multiple vectors in order via HTTP batch API', function () {
    $vec1 = array_fill(0, 768, 0.1);
    $vec2 = array_fill(0, 768, 0.2);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'embeddings' => [
                ['values' => $vec1],
                ['values' => $vec2],
            ],
        ], 200),
    ]);

    $service = new GeminiEmbeddingService(apiKey: 'test-api-key');
    $embeddings = $service->generateBatchEmbeddings(['Passage 1', 'Passage 2']);

    expect($embeddings)->toHaveCount(2);
    expect($embeddings[0][0])->toBe(0.1);
    expect($embeddings[1][0])->toBe(0.2);
});

test('service allows injecting custom runner for deterministic unit testing', function () {
    $service = new GeminiEmbeddingService();
    $service->setFakeRunner(function (string $text) {
        return array_fill(0, 768, mb_strlen($text) * 0.001);
    });

    $res = $service->generateEmbedding('Hello world');
    expect($res)->toHaveCount(768);
    expect($res[0])->toBe(0.011);
});

test('service throws exception on upstream HTTP failure', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'API quota exceeded'],
        ], 429),
    ]);

    $service = new GeminiEmbeddingService(apiKey: 'test-key');

    expect(fn () => $service->generateEmbedding('Some text'))
        ->toThrow(\RuntimeException::class, 'API quota exceeded');
});
