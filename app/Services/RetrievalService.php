<?php

namespace App\Services;

use App\Repositories\HybridSearchRepository;
use App\Support\CandidatePassage;
use Illuminate\Support\Collection;

class RetrievalService
{
    public function __construct(
        protected HybridSearchRepository $repository,
        protected GeminiEmbeddingService $embeddingService,
    ) {
    }

    /**
     * Set explicit connection for repository tenant queries.
     */
    public function onConnection(?string $connection): self
    {
        $clone = clone $this;
        $clone->repository = $this->repository->onConnection($connection);

        return $clone;
    }

    /**
     * Retrieve ranked Candidate Passages for a Claim within a Project.
     *
     * @param int $projectId
     * @param string $query
     * @param int $limit
     * @param array<int, float>|null $vector Optional precomputed query embedding
     * @param int $candidatePool
     * @param float $k
     * @return Collection<int, CandidatePassage>
     */
    public function retrieve(
        int $projectId,
        string $query,
        int $limit = 5,
        ?array $vector = null,
        int $candidatePool = HybridSearchRepository::DEFAULT_CANDIDATE_POOL,
        float $k = HybridSearchRepository::DEFAULT_RRF_K
    ): Collection {
        $trimmedQuery = trim($query);
        if ($trimmedQuery === '' && empty($vector)) {
            return collect();
        }

        // Generate embedding when not explicitly provided
        if ($vector === null && $trimmedQuery !== '') {
            $vector = $this->embeddingService->generateEmbedding($trimmedQuery);
        }

        $chunks = $this->repository->hybridSearch(
            projectId: $projectId,
            query: $trimmedQuery,
            vector: $vector,
            limit: $limit,
            candidatePool: $candidatePool,
            k: $k
        );

        return $chunks->map(
            fn ($chunk) => CandidatePassage::fromChunk($chunk, (float) ($chunk->rrf_score ?? 0.0))
        );
    }
}
