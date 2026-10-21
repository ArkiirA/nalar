<?php

namespace App\Repositories;

use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HybridSearchRepository
{
    public const DEFAULT_RRF_K = 60.0;
    public const DEFAULT_CANDIDATE_POOL = 20;

    protected ?string $connection = null;

    /**
     * Specify an explicit database connection for tenant search.
     */
    public function onConnection(?string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    /**
     * Perform vector cosine similarity search strictly scoped to a project.
     *
     * @param int $projectId
     * @param array<int, float> $vector
     * @param int $limit
     * @return Collection<int, DocumentChunk>
     */
    public function searchByVector(int $projectId, array $vector, int $limit = self::DEFAULT_CANDIDATE_POOL): Collection
    {
        if (empty($vector)) {
            return new Collection();
        }

        $builder = $this->newChunkQuery()
            ->with(['document' => fn ($q) => $q->where('project_id', $projectId)])
            ->where('document_chunks.project_id', $projectId);

        $driver = $builder->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $vectorLiteral = json_encode(array_values(array_map('floatval', $vector)));

            return $builder
                ->selectRaw('document_chunks.*, 1 - (embedding <=> ?) AS similarity', [$vectorLiteral])
                ->whereNotNull('document_chunks.embedding')
                ->orderByRaw('embedding <=> ? ASC', [$vectorLiteral])
                ->limit($limit)
                ->get();
        }

        // Driver fallback for non-pgsql (e.g., SQLite in-memory test runner)
        $chunks = $builder->whereNotNull('embedding')->get();
        if ($chunks->isEmpty()) {
            return new Collection();
        }

        $scored = $chunks->map(function (DocumentChunk $chunk) use ($vector) {
            $chunkVector = $chunk->embedding;
            if (! is_array($chunkVector) || empty($chunkVector)) {
                $chunk->similarity = 0.0;

                return $chunk;
            }

            $chunk->similarity = $this->calculateCosineSimilarity($vector, $chunkVector);

            return $chunk;
        })->sortByDesc('similarity')->values()->take($limit);

        return new Collection($scored->all());
    }

    /**
     * Perform full-text search using PostgreSQL tsvector dictionaries (Indonesian/English)
     * strictly scoped to a project.
     *
     * @param int $projectId
     * @param string $query
     * @param int $limit
     * @return Collection<int, DocumentChunk>
     */
    public function searchByKeyword(int $projectId, string $query, int $limit = self::DEFAULT_CANDIDATE_POOL): Collection
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        $cleanWords = array_values(array_filter(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}]+/u', ' ', $trimmed) ?? '')));
        if (empty($cleanWords)) {
            return new Collection();
        }

        $builder = $this->newChunkQuery()
            ->with(['document' => fn ($q) => $q->where('project_id', $projectId)])
            ->where('document_chunks.project_id', $projectId);

        $driver = $builder->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $orQuery = implode(' or ', $cleanWords);

            return $builder
                ->selectRaw(
                    'document_chunks.*, (ts_rank(search_vector, (websearch_to_tsquery(\'indonesian\', ?) || websearch_to_tsquery(\'english\', ?))) * 2.0 + ts_rank(search_vector, (websearch_to_tsquery(\'indonesian\', ?) || websearch_to_tsquery(\'english\', ?)))) AS text_rank',
                    [$trimmed, $trimmed, $orQuery, $orQuery]
                )
                ->whereRaw(
                    'search_vector @@ (websearch_to_tsquery(\'indonesian\', ?) || websearch_to_tsquery(\'english\', ?))',
                    [$orQuery, $orQuery]
                )
                ->orderByDesc('text_rank')
                ->limit($limit)
                ->get();
        }

        // Driver fallback for non-pgsql (e.g., SQLite in-memory test runner)
        $chunks = $builder->get();
        if ($chunks->isEmpty()) {
            return new Collection();
        }

        $scored = $chunks->map(function (DocumentChunk $chunk) use ($cleanWords, $trimmed) {
            $content = $chunk->content;
            $rank = 0.0;
            if (stripos($content, $trimmed) !== false) {
                $rank += 2.0;
            }
            foreach ($cleanWords as $word) {
                if (stripos($content, $word) !== false) {
                    $rank += 1.0;
                }
            }
            $chunk->text_rank = $rank;

            return $chunk;
        })->filter(fn (DocumentChunk $c) => ($c->text_rank ?? 0.0) > 0)
          ->sortByDesc('text_rank')
          ->values()
          ->take($limit);

        return new Collection($scored->all());
    }

    /**
     * Perform hybrid retrieval combining top vector results and top keyword results
     * merged via Reciprocal Rank Fusion (k=60).
     *
     * @param int $projectId
     * @param string $query
     * @param array<int, float>|null $vector
     * @param int $limit
     * @param int $candidatePool
     * @param float $k
     * @return Collection<int, DocumentChunk>
     */
    public function hybridSearch(
        int $projectId,
        string $query,
        ?array $vector = null,
        int $limit = 5,
        int $candidatePool = self::DEFAULT_CANDIDATE_POOL,
        float $k = self::DEFAULT_RRF_K
    ): Collection {
        $poolSize = max($candidatePool, $limit * 2);

        $vectorResults = ! empty($vector)
            ? $this->searchByVector($projectId, $vector, $poolSize)
            : new Collection();

        $keywordResults = trim($query) !== ''
            ? $this->searchByKeyword($projectId, $query, $poolSize)
            : new Collection();

        $rrfScores = [];
        $chunksById = [];

        foreach ([$vectorResults, $keywordResults] as $rankedList) {
            foreach ($rankedList as $index => $chunk) {
                $rank = $index + 1; // 1-based rank
                $rrfScores[$chunk->id] = ($rrfScores[$chunk->id] ?? 0.0) + (1.0 / ($k + $rank));
                $chunksById[$chunk->id] = $chunk;
            }
        }

        if (empty($rrfScores)) {
            return new Collection();
        }

        arsort($rrfScores);

        $selectedScores = array_slice($rrfScores, 0, $limit, true);

        $orderedChunks = [];
        foreach ($selectedScores as $chunkId => $score) {
            $chunk = $chunksById[$chunkId];
            $chunk->rrf_score = $score;
            $orderedChunks[] = $chunk;
        }

        return new Collection($orderedChunks);
    }

    /**
     * Create fresh DocumentChunk query on active connection.
     */
    protected function newChunkQuery(): Builder
    {
        return $this->connection !== null
            ? DocumentChunk::on($this->connection)
            : DocumentChunk::query();
    }

    /**
     * Calculate cosine similarity between two float vectors.
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    protected function calculateCosineSimilarity(array $vecA, array $vecB): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($vecA), count($vecB));

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
