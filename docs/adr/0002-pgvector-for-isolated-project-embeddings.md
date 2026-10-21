# 2. PostgreSQL and pgvector for Vector Storage and Project Isolation

Date: 2026-10-21

## Context

The system requires semantic search over project documents to support closed-library RAG and citation evidence checking. Cross-project data leakage must be strictly prevented, and project deletion must cleanly remove all associated embeddings and chunk data.

## Decision

Use PostgreSQL with the `pgvector` extension for storing document chunks and embeddings. Document chunks belong to specific project IDs with database-level cascading foreign keys.

## Consequences

- Project deletion automatically cascades to delete all document chunks and embeddings in a single atomic transaction.
- Eliminates external vector database management, synchronization bugs, and external service costs.
- Queries enforce tenant project scoping natively in SQL (`WHERE project_id = ?`).
