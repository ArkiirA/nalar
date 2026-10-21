<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiEmbeddingService
{
    public const DEFAULT_MODEL = 'text-embedding-004';
    public const DIMENSIONS = 768;

    protected ?string $apiKey;
    protected string $model;

    /**
     * @var (callable(string): array<int, float>)|null
     */
    protected $fakeRunner = null;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.gemini.api_key');
        $this->model = $model ?? config('services.gemini.embedding_model', self::DEFAULT_MODEL);
    }

    /**
     * Set a custom fake runner callback for deterministic testing.
     * Signature: function(string $text): array<int, float>
     */
    public function setFakeRunner(?callable $runner): self
    {
        $this->fakeRunner = $runner;

        return $this;
    }

    /**
     * Generate 768-dimensional vector embedding for a single text.
     *
     * @return array<int, float>
     */
    public function generateEmbedding(string $text): array
    {
        if ($this->fakeRunner !== null) {
            return ($this->fakeRunner)($text);
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return array_fill(0, self::DIMENSIONS, 0.0);
        }

        if (empty($this->apiKey)) {
            if (app()->environment('testing')) {
                return $this->generateDeterministicFakeVector($trimmed);
            }
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:embedContent?key={$this->apiKey}";

        $response = Http::timeout(30)->post($url, [
            'model' => "models/{$this->model}",
            'content' => [
                'parts' => [
                    ['text' => $trimmed],
                ],
            ],
        ]);

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?? "Gemini API error (HTTP {$response->status()})";
            throw new RuntimeException($errorMsg);
        }

        $values = $response->json('embedding.values', []);

        return array_map('floatval', $values);
    }

    /**
     * Generate embeddings for a batch of texts in a single API call.
     *
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        if ($this->fakeRunner !== null) {
            return array_map(fn ($text) => ($this->fakeRunner)($text), $texts);
        }

        if (empty($this->apiKey)) {
            if (app()->environment('testing')) {
                return array_map(fn ($t) => $this->generateDeterministicFakeVector(trim($t)), $texts);
            }
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $allEmbeddings = [];
        $batches = array_chunk($texts, 100);

        foreach ($batches as $batch) {
            $requests = array_map(function ($text) {
                return [
                    'model' => "models/{$this->model}",
                    'content' => [
                        'parts' => [
                            ['text' => trim($text)],
                        ],
                    ],
                ];
            }, $batch);

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:batchEmbedContents?key={$this->apiKey}";

            $response = Http::timeout(60)->post($url, [
                'requests' => array_values($requests),
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message') ?? "Gemini API error (HTTP {$response->status()})";
                throw new RuntimeException($errorMsg);
            }

            $embeddings = $response->json('embeddings', []);
            foreach ($embeddings as $item) {
                $allEmbeddings[] = array_map('floatval', $item['values'] ?? []);
            }
        }

        return $allEmbeddings;
    }

    /**
     * Produce a normalized deterministic vector for testing without external network calls.
     *
     * @return array<int, float>
     */
    protected function generateDeterministicFakeVector(string $text): array
    {
        $hash = md5($text);
        $vector = [];
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $byte = hexdec(substr($hash, ($i % 30), 2));
            $vector[] = round(($byte / 255.0) * 0.1, 6);
        }

        return $vector;
    }
}
