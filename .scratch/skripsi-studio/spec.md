# Skripsi Studio: AI-Powered Research & Thesis Workspace

**Status:** ready-for-agent

## Problem Statement

Indonesian undergraduate students (S1) face immense friction and anxiety when writing their undergraduate thesis (*skripsi*). They struggle with:
1. Fragmented workflows: switching between PDF readers, Word processors, reference managers (Mendeley/Zotero), and notes.
2. Citation anxiety and academic integrity risks: losing track of which paper supported which specific claim, risking accidental fabrication or supervisor reprimands during defense.
3. Complex campus guideline formatting: navigating rigid university structural, margin, and citation rules without deterministic validation.
4. AI hallucinations: generic AI tools produce fabricated citations, hallucinated page numbers, and unsupported claims that damage academic credibility.
5. Technical fatigue: heavy desktop Word processors become slow and crash on low-to-mid-tier student laptops when handling large documents.

## Solution

Skripsi Studio provides an integrated, lightweight, and trustworthy research and thesis workspace designed specifically for Indonesian undergraduates. It features:
- A structured, chapter-segmented editor using authentic academic formatting previews (Times New Roman, 1.5 line spacing, standard margins) within a fast, lightweight web interface.
- A project-scoped Research Library for managing uploaded PDFs, resolving DOIs, and maintaining CSL-JSON bibliographic records.
- A Citation Evidence Checker that allows students to highlight any factual claim in their thesis and evaluates whether their uploaded research documents genuinely support or contradict it, presenting traceable source passages and verified page numbers.
- Proposal-first AI assistance in two distinct modes (Exploratory vs. Evidence-Based) with side-by-side diff review before any text changes are applied.
- Strict project isolation where AI retrieval and vector search are hard-scoped to the active project, backed by PostgreSQL and pgvector.
- Pre-export validation and export to DOCX and print-ready PDF formatted to university guidelines.

## User Stories

### Project & Workspace Setup
1. As an undergraduate student, I want to create a named thesis project, so that all my writing, papers, and citations remain strictly isolated in one place.
2. As an undergraduate student, I want to select a campus guideline preset upon creating a project, so that my thesis outline is pre-populated with standard Indonesian chapter structures (Bab I - Bab V).
3. As an undergraduate student, I want to customize and reorder chapters, sections, and subheadings in the project outline, so that the document matches my faculty's specific handbook.
4. As an undergraduate student, I want to see contextual onboarding tips in an empty workspace, so that I know how to upload papers, insert citations, and structure chapters without getting overwhelmed.
5. As an undergraduate student, I want to delete a project and have all associated uploaded files, chunks, embeddings, and notes permanently removed, so that my data is completely wiped on demand.

### Research Library & Reference Management
6. As an undergraduate student, I want to upload PDF research papers into my project's Research Library, so that they can serve as verified evidence for my writing.
7. As an undergraduate student, I want to add references by inputting a DOI, so that bibliographic metadata is automatically fetched without uploading a full file.
8. As an undergraduate student, I want to manually enter or edit bibliographic metadata for book chapters, web articles, or university guidelines, so that all my sources have complete records.
9. As an undergraduate student, I want to be warned when reference metadata has uncertain or missing fields, so that I can correct them before generating citations.
10. As an undergraduate student, I want the system to assign a stable internal Reference ID to every source, so that in-text citations remain bound even if author names or titles are edited.
11. As an undergraduate student, I want to select my project's citation style (APA 7th edition or Harvard), so that citations and bibliographies format automatically according to campus requirements.
12. As an undergraduate student, I want the system to detect duplicate references during upload, so that my bibliography does not contain redundant entries.

### Document Processing & Knowledge Extraction
13. As an undergraduate student, I want uploaded PDF papers to process in the background, so that I can continue writing without waiting for uploads to finish.
14. As an undergraduate student, I want to see clear processing states (`Processed`, `Partial`, `Failed`) for each uploaded paper, so that I know which papers are ready for evidence checking.
15. As an undergraduate student, I want the system to run OCR automatically on scanned PDFs, so that scanned or older Indonesian journal articles can still be indexed.
16. As an undergraduate student, I want the system to extract text preserving page numbers, so that all retrieved evidence points back to a verified physical page.
17. As an undergraduate student, I want to retry processing for a failed document, so that temporary processing or OCR glitches can be resolved.

### Structured Chapter Editor
18. As an undergraduate student, I want my thesis divided into discrete Chapter Documents in the editor, so that editing remains fast, responsive, and lightweight regardless of total thesis length.
19. As an undergraduate student, I want auto-saves to run continuously per chapter, so that I never lose my work due to browser refresh or network drop.
20. As an undergraduate student, I want to view and restore from past Chapter Revisions saved every 5 minutes or created manually, so that I can revert unwanted edits safely.
21. As an undergraduate student, I want to insert citations directly into the text from my Research Library using an autocomplete search, so that citations are linked as tamper-proof atomic nodes.
22. As an undergraduate student, I want to click any inline citation to view its source reference details and open the referenced PDF, so that I can verify context immediately while writing.
23. As an undergraduate student, I want to insert tables and figures with academic captions (e.g. "Tabel 3.1", "Gambar 4.1"), so that my methodology and results sections comply with academic standards.
24. As an undergraduate student, I want to switch the editor background between clean paper-white and soft cream sepia, so that I can reduce eye strain during long writing sessions.

### AI Copilot & Modes
25. As an undergraduate student, I want to give explicit consent before any text or documents are sent to external AI providers, so that my academic confidentiality is protected.
26. As an undergraduate student, I want to withdraw AI consent at any time, so that external AI processing immediately halts without deleting my local thesis draft.
27. As an undergraduate student, I want an Exploratory Mode for brainstorming research questions, outlines, and theoretical perspectives, so that I can explore new ideas with clear warnings that ideas require verification.
28. As an undergraduate student, I want an Evidence-Based Mode for drafting and argumentation, so that the AI strictly bases its assistance on documents in my Research Library and refuses to invent facts.
29. As an undergraduate student, I want AI writing assistance to provide proposals in a side-by-side diff drawer, so that I can review, edit, accept, or reject suggested changes before they touch my chapter text.
30. As an undergraduate student, I want the AI to converse and assist in both Indonesian and English, so that I can write in Indonesian while summarizing international English journal papers.

### Citation Evidence Checker
31. As an undergraduate student, I want to select any sentence or claim in my thesis and run the Citation Evidence Checker, so that I can verify whether my library sources back it up.
32. As an undergraduate student, I want the checker to evaluate the logical relationship and classify it as `Supported`, `Contradicted`, `Insufficient Evidence`, or `Unable to Assess`, so that I have clear guidance on my claim's credibility.
33. As an undergraduate student, I want the checker to show the original candidate passages alongside the source title and page number, so that I can independently verify the claim.
34. As an undergraduate student, I want checked claims to be marked inline in the editor and linkable to their historical assessment, so that I can review evidence trails before submitting my thesis to my advisor.
35. As an undergraduate student, I want the checker to admit when sources are insufficient rather than fabricating a connection, so that I do not submit unfounded claims.

### Thesis Import & Export
36. As an undergraduate student, I want to import an existing thesis draft from DOCX or PDF, so that I can migrate work already started in Microsoft Word into Skripsi Studio.
37. As an undergraduate student, I want to review candidate chapter splits on an import confirmation screen before importing, so that I can adjust chapter boundaries if the importer misidentifies a heading.
38. As an undergraduate student, I want to run a pre-export validation check, so that I am notified of uncited references, citations without bibliography entries, or missing chapters before generating a final file.
39. As an undergraduate student, I want to export my entire thesis as a clean DOCX file, so that I can send it to advisors who require Word files.
40. As an undergraduate student, I want to export my entire thesis as a formatted PDF with compiled table of contents, figures list, and bibliography, so that I can submit it directly for campus review.

### Supervision Notes & Quota Tracking
41. As an undergraduate student, I want to record notes from supervision meetings with my advisor and link action items to specific chapters, so that I never miss revision requests.
42. As an undergraduate student, I want to mark supervision action items as completed as I edit the corresponding chapter, so that I can track revision progress.
43. As an undergraduate student, I want to see my daily AI query and document upload quota usage, so that I can manage my research activities within free-tier limits.

## Implementation Decisions

### Architectural Structure & Tech Stack
- Built as a Laravel 11 modular monolith with Inertia.js (Vue 3) frontend and Tiptap editor engine.
- Single deployment model: all backend services, queues, and database interactions reside in one deployable application without microservices.
- UI built with Tailwind CSS and unstyled headless primitives (shadcn-vue/Radix Vue) keeping total frontend bundles under ~150KB gzipped.
- Three-column layout: collapsible chapter outline/guidelines on the left, centered distraction-free editor in the center, expandable Research Library and Evidence Checker drawer on the right.

### Data Model & Tenant Isolation
- Strict project isolation enforced at the database layer: all tables (`chapters`, `documents`, `document_chunks`, `references`, `claims`, `evidence_assessments`, `supervision_notes`) belong to a `project_id`.
- Cascading foreign keys on `project_id` ensure immediate, atomic deletion of all associated text, chunks, embeddings, and notes when a project is deleted.
- Vector storage and search implemented directly in PostgreSQL using `pgvector`.
- Thesis structure split into separate Chapter Documents, each storing its own Tiptap JSON document, metadata, and sequential order.

### Document Processing Pipeline
- PDF uploads handled asynchronously via Laravel Queue workers.
- Extraction uses native server CLI tools (`pdftotext` with layout preservation, `tesseract-ocr` for scanned pages).
- Text parsed and split into ~500-token Candidate Passages strictly tagged with their source `document_id` and physical `page_number`.
- Embeddings generated via cloud embedding API and stored in PostgreSQL `document_chunks` table alongside full-text search vectors (`tsvector`).

### AI & Retrieval Engine
- Standardized on Google Gemini API behind an abstract Laravel AI service provider interface:
  - `gemini-1.5-flash`: Document text cleanup, metadata parsing, fast retrieval filtering.
  - `gemini-1.5-pro`: High-reasoning claim assessment and writing critique.
- Hybrid Retrieval: PostgreSQL queries combine vector similarity (`pgvector` cosine distance) with keyword matching (`tsvector` full-text search) merged using Reciprocal Rank Fusion (RRF).
- Hard-scoped RAG: All AI retrieval queries strictly include `WHERE project_id = :projectId`.
- AI editing operates on a proposal-first model: AI output is displayed in a side-by-side comparison drawer with granular Accept/Reject controls.

### Citation & Evidence Engine
- Bibliographic records stored using the CSL-JSON schema, supporting APA 7th edition and Harvard citation styles.
- In-text citations and checked claims represented as custom atomic inline nodes within Tiptap, referencing database primary keys (`reference_id`, `claim_id`).
- Citation Evidence Checker evaluates claims against retrieved candidate passages and returns one of four deterministic assessment states (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`) with explanation and page-cited source excerpts.

### Import & Export
- Importer splits DOCX/PDF files by major heading patterns (`BAB [I-V]`, `Heading 1`) and presents an interactive verification modal allowing students to confirm or remap chapter assignments before creating Chapter Documents.
- DOCX export compiled via PHPWord applying guideline presets (margins, font, spacing, heading styles, bibliography).
- PDF export generated by rendering server-side styled HTML through headless Chromium, guaranteeing exact typography and page layouts.

## Testing Decisions

### Guiding Principles
- Tests must verify external behavior and system contracts, never internal implementation details.
- Avoid mocking database transactions or vector operations where integration tests can verify real PostgreSQL behavior.
- AI provider APIs and OCR CLI tools must be isolated via test drivers/fixtures to ensure test suite speed and reliability.

### Primary Testing Seams
1. **HTTP / Inertia Request Seam (Laravel Feature Tests)**:
   - Primary seam for user-facing features.
   - Tests authentication, project isolation, authorization barriers (preventing User B from viewing Project A), editor auto-saves, and reference creation.
   - Asserts against HTTP status codes, Inertia prop payloads, and database state transitions.
2. **Background Document Processing Seam (Queue Integration Tests)**:
   - Dispatches document processing jobs with test PDF fixtures.
   - Verifies text extraction, page-number preservation, chunk creation in `document_chunks`, and status transition (`Processed` / `Failed`).
3. **Retrieval & Assessment Seam (AI Service Integration Tests)**:
   - Tests hybrid retrieval queries against real PostgreSQL `pgvector` and `tsvector` tables to verify cross-project isolation and ranking.
   - Tests claim assessment logic using recorded Gemini API response fixtures to verify deterministic categorization (`Supported`, `Contradicted`, etc.).
4. **Export Validation Seam**:
   - Tests compilation of multi-chapter documents, citation formatting, and bibliography generation against known CSL datasets.

## Out of Scope

- Real-time multi-user collaborative editing (Google Docs style).
- Supervisor/advisor accounts, logins, or direct grading interfaces.
- Integrated external web search or live academic database crawling (e.g., live Google Scholar / Scopus scraper).
- Autonomous thesis generation (full automatic thesis writing from a prompt).
- Payment gateway integration and paid billing tier at initial pilot launch.
- Microservices infrastructure or independent Python microservice deployment.
- Mobile native application (application is web-first for desktop/laptop screen sizes).

## Further Notes

- Pilot quota constraints: 3 active projects per student, maximum 15 research papers per project (max 25MB per PDF), 50 AI queries/checks per day.
- Presets will initially include standard templates for major Indonesian universities (Universitas Indonesia, Universitas Gadjah Mada, Institut Teknologi Bandung, Universitas Gunadarma, and a Universal Indonesian S1 Preset).
