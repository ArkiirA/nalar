# 4. Google Gemini Tiering via Provider Abstraction

Date: 2026-10-21

## Context

The application performs multiple AI tasks with differing requirements for throughput, context length, cost, and analytical reasoning. Solo development requires high API cost-efficiency and reliable rate limits.

## Decision

Standardize on Google Gemini via an internal Laravel provider abstraction:
- `gemini-1.5-flash`: Fast tasks (PDF text cleanup, metadata extraction, candidate passage pre-filtering).
- `gemini-1.5-pro`: High-reasoning tasks (Citation Evidence Checker assessment, substantive writing critique).

## Consequences

- Significantly reduces inference costs compared to OpenAI GPT-4o while preserving high context capacity (1M+ tokens).
- Provider interface allows falling back or switching models without modifying domain logic.
- Requires user consent handling prior to sending data to Google Cloud endpoints.
