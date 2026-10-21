# 08: Page-Preserving Chunking & pgvector Storage

**What to build:** The system splits extracted PDF text into ~500-token Candidate Passages strictly bounded by physical page numbers. Embeddings are generated via Google Gemini embedding API and stored in PostgreSQL using `pgvector`, tied directly to the parent `project_id` and source document.

**Blocked by:** 05 (Research Library PDF Upload & Queue Worker)

**Status:** ready-for-agent

- [ ] Chunking algorithm segments extracted text into ~500-token passages respecting paragraph boundaries where possible.
- [ ] Every chunk records `project_id`, `document_id`, `page_number`, and plain text content.
- [ ] Background job generates vector embeddings using Gemini API and saves to `document_chunks` table with `vector` type.
- [ ] Deleting a document or project cascades to delete all associated chunks and vector records.
- [ ] Automated tests verify chunk token sizes, page number consistency, and vector index persistence.
