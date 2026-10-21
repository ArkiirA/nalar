# 5. Hybrid Retrieval with Reciprocal Rank Fusion

Date: 2026-10-21

## Context

The Citation Evidence Checker must locate exact candidate passages across research papers. Dense semantic retrieval (`pgvector`) captures conceptual relevance but frequently misses exact acronyms, statistical figures, proper nouns, and formula references. Pure lexical search (`tsvector`) misses paraphrased findings.

## Decision

Implement hybrid search within PostgreSQL combining full-text search (`tsvector` with Indonesian/English dictionaries) and vector search (`pgvector` cosine similarity), merged via Reciprocal Rank Fusion (RRF).

## Consequences

- Improves retrieval recall for academic texts containing specialized domain jargon.
- Retains all indexing and retrieval inside PostgreSQL without introducing Elasticsearch or external search clusters.
- Requires maintaining both vector embeddings and search vectors during document chunking.
