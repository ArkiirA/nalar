# 09: Hybrid Retrieval Engine (Vector + Full-Text RRF)

**What to build:** The system executes hybrid queries over project document passages, combining vector cosine similarity (`pgvector`) and PostgreSQL full-text search (`tsvector`), merged with Reciprocal Rank Fusion (RRF). All queries enforce strict tenant project scoping.

**Blocked by:** 08 (Page-Preserving Chunking & pgvector Storage)

**Status:** ready-for-human

- [x] PostgreSQL migration adds `tsvector` column and GIN index to `document_chunks` for Indonesian/English text search.
- [x] Hybrid search repository combines top vector results and top keyword results via Reciprocal Rank Fusion (k=60).
- [x] Every query hard-filters by `WHERE project_id = :projectId` preventing cross-project data leakage.
- [x] Retrieval service returns ranked candidate passages with source document title, page number, and score.
- [x] Automated integration tests verify hybrid ranking recall and verify that queries never return chunks from foreign projects.
