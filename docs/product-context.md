# Skripsi Studio — Product Requirements Context

> **Purpose:** Context document for `/grill-me` and AI-assisted product development.
>
> **Status:** Brainstorming decisions consolidated. This is not yet the final PRD or implementation plan. The agent should challenge inconsistencies and identify only genuinely consequential gaps.

## 1. Product Vision

**Skripsi Studio** is an AI-powered research and thesis workspace for Indonesian undergraduate students.

It supports students throughout the thesis-writing process, from proposal development to final revision, with an initial focus on students actively writing their undergraduate thesis.

The product is not an autonomous thesis generator. It is a **research and writing copilot** that helps students organize sources, understand papers, develop arguments, improve writing, manage citations, and verify whether claims are supported by their uploaded documents.

### Core value proposition

Help students write more efficiently while maintaining traceability between academic claims and their supporting sources.

### Primary differentiator

**Citation Evidence Checker:** A system that assesses whether a selected claim is supported by evidence from the student's uploaded research documents and presents traceable source passages for human verification.

The checker must not claim to guarantee scientific truth, research validity, or the correctness of an entire thesis.

## 2. Target Users and Market

- Initial market: Indonesia.
- Primary users: Indonesian undergraduate students (S1).
- Supported thesis stages: proposal, active writing, and revision.
- Initial pilot users: students actively writing their thesis.
- Initial recruitment: personal networks, friends, and known university students.
- Initial launch strategy: limited pilot before wider public availability.
- Initial collaboration model: individual, private projects.

The product should accommodate different campus guidelines without claiming perfect compatibility with every Indonesian university.

## 3. Product Principles

1. **Human remains in control.** AI assists; students make final academic decisions.
2. **Evidence over plausible-sounding output.** Retrieval similarity is not proof of claim support.
3. **Traceability.** Evidence should link to the source document and page or location when available.
4. **Honest uncertainty.** The system must disclose insufficient evidence, extraction failures, and uncertain metadata.
5. **Private by default.** Project documents are isolated and accessible only to authorized users.
6. **Structured document first.** The internal document model is the source of truth while editing.
7. **No fabricated references.** Never invent DOI values, bibliographic metadata, quotations, page numbers, or supporting evidence.
8. **Incremental development.** Build a maintainable modular monolith before considering microservices.
9. **Useful free tier.** Free users should be able to accomplish real work, not merely preview the product.

## 4. MVP Scope

The MVP is a **Focused MVP**: a coherent end-to-end thesis workflow, not a broad collection of disconnected AI features.

### Included modules

1. Multi-project thesis workspace.
2. Campus guideline import and configurable guideline profile.
3. Thesis import from DOCX and PDF.
4. Structured thesis editor.
5. Research Library for uploaded research documents.
6. AI research and writing copilot using uploaded documents.
7. Citation and bibliography manager.
8. Citation Evidence Checker for user-selected claims.
9. DOCX and PDF export.
10. Basic thesis progress and supervision notes.
11. Contextual onboarding and project isolation.
12. Privacy controls and explicit consent for external AI processing.

### Excluded or deferred

- Live collaborative editing.
- Supervisor or lecturer accounts.
- Mandatory multi-role workflows.
- External academic-paper search integrated into the product.
- Autonomous thesis writing or submission.
- Advanced Literature Review Matrix and Research Gap Finder.
- Full Word-clone functionality.
- Advanced AI supervision-note analysis.
- Fully automated compliance with every campus guideline.
- Active payment gateway and payment collection at initial launch.
- Complex microservices infrastructure.

These exclusions can be reconsidered after validating the core product.

## 5. Thesis Workspace and Project Model

Users can create multiple separate projects.

Each project maintains its own:

- Campus guideline profile.
- Thesis document and structure.
- Uploaded papers and extracted content.
- Reference records and citation styles.
- AI conversation context.
- Evidence-check results.
- Progress and supervision notes.
- Export history or relevant document revisions.

**Strict isolation:** AI retrieval for one project must never include documents, references, or context from another project unless an explicitly authorized future feature changes that behavior.

### Onboarding

After registration, users create a project and land directly in an empty workspace/editor.

Show contextual hints for actions such as:

- Uploading campus guidelines.
- Importing an existing thesis.
- Uploading research papers.
- Creating chapters and subchapters.

Do not run guideline-compliance checks before the guideline profile is confirmed.

## 6. Guideline Management

Students can upload campus guidelines in PDF or DOCX format.

The system extracts configurable rules, such as:

- Required chapters and sections.
- Heading hierarchy.
- Citation and bibliography styles.
- Formatting requirements.
- Numbering conventions.
- Other explicitly detectable structural rules.

The extracted rules become a **Guideline Profile**.

Workflow:

1. Upload guideline.
2. Extract candidate rules.
3. Display extracted rules for review.
4. Let the student edit or correct them.
5. Require confirmation before using them for compliance checks.

The system generates a starting thesis structure from the confirmed profile, but students can add, delete, rename, and reorder chapters and subchapters.

If a change may violate a guideline, display a warning rather than hard-blocking the edit.

Do not claim support for every campus-specific rule. Deterministic rules should be validated automatically where feasible; ambiguous rules must be marked for manual review.

## 7. Document Model and Editor

### Source of truth

A **structured document** is the source of truth while editing.

Represent the thesis using structured elements such as:

- Chapters and sections.
- Headings and subheadings.
- Paragraphs.
- Lists and tables where supported.
- Citation references.
- Relevant document metadata.

DOCX and PDF are import/export formats, not the primary internal editing model.

### Editor behavior

The editor should provide:

- Structured headings and subheadings.
- Table of contents support.
- Chapter and section numbering.
- Integrated citation insertion.
- Inline AI assistance.
- Comparison between original and proposed text.
- Basic revision history or equivalent recovery mechanisms.

Do not build a full Microsoft Word clone for the MVP.

### AI editing policy

Default behavior: **proposal-first editing**.

AI-generated revisions should be presented as proposed changes that users can compare, edit, accept, or reject.

Direct editing is permitted only as an explicit, limited option for operations such as grammar correction or formatting on selected text.

Substantive changes to arguments, research interpretations, and evidence-based claims must be presented for review rather than silently overwriting existing text.

## 8. Document Import and Export

### Import

Support importing an existing thesis from DOCX or PDF.

- DOCX: preserve recognized structure where feasible.
- Text-based PDF: extract available text and structure where feasible.
- Scanned PDF: attempt OCR when supported.
- Display an import review step when structure or text extraction is uncertain.
- Do not promise perfect conversion of arbitrary documents.

### Export

MVP output formats: DOCX and PDF.

Preserve, as far as practical:

- Headings and hierarchy.
- Chapter numbering.
- Citations and bibliography.
- Table of contents.
- Guideline-specific formatting.
- Tables and other supported document elements.

Test generated DOCX files in Microsoft Word and LibreOffice.

### Export validation

Before export, run available deterministic validation checks and display a report covering issues such as:

- Citations without corresponding reference records.
- References that are not cited in the document.
- Unconfirmed bibliographic metadata.
- Detectable guideline violations.
- Other detectable structural inconsistencies.

Export remains available after the user reviews the report. Do not hard-block export for every warning or uncertain AI judgment.

Distinguish deterministic errors from heuristic or AI-generated warnings.

## 9. Research Library and Reference Management

### Accepted inputs

- PDF uploads.
- DOI input.
- Manual bibliographic metadata.

No integrated external academic-paper search is included in the MVP.

### Reference workflow

1. Add a source through the Research Library.
2. Extract text and available bibliographic metadata.
3. Extract metadata from DOI where possible.
4. Mark uncertain or missing fields.
5. Require confirmation of uncertain metadata before use.
6. Assign a stable, unique internal reference ID.
7. Make registered references available for citation insertion in the editor.

**All references enter through the Research Library first.** The editor must not create unrelated free-text citation records.

### Citation management requirements

- Multiple standard citation styles.
- Configurable campus-specific rules where feasible.
- Consistent inline citations and bibliography generation.
- Detection of duplicate references.
- Citation/reference consistency checks.
- Structured metadata rather than freeform model-generated citation strings.
- Reference updates reflected consistently throughout the document.

Potential initial citation styles include APA 7, IEEE, and Vancouver. The final supported list remains to be confirmed.

DOI resolution or metadata lookup may retrieve bibliographic metadata, but it does not establish that a paper's contents support a particular claim.

## 10. Document Processing

Uploaded files remain stored even if processing fails.

Use these processing states:

- `Processed`
- `Partial`
- `Failed`

### Processing rules

- Extract text and metadata where possible.
- Preserve page or location information when available.
- Support OCR for scanned PDFs in the MVP where feasible.
- Warn when OCR quality or extraction confidence is low.
- Allow retrying failed processing jobs.
- Never silently treat failed or partial extraction as complete.
- Use only successfully extracted and indexed passages for AI retrieval.
- Mark uncertain quotations, page numbers, and locations explicitly.

If a source is `Failed`, it must not be treated as a fully indexed source.

Advanced layout understanding and sophisticated scientific document parsing can be deferred.

Use background jobs for long-running document processing so uploads do not block the main application.

## 11. AI Copilot and Knowledge Policy

### AI infrastructure

Use a cloud AI API as the primary inference approach for the MVP.

Implement a provider abstraction so the application can switch AI providers or models without rewriting core product logic.

### Closed-library RAG

For thesis-specific research and evidence retrieval, the system uses **only documents uploaded by the student to the current project**.

No integrated external academic search or general web retrieval is included in this scope.

Important distinction: the underlying language model may have general pretrained knowledge. The application must not represent that knowledge as evidence from uploaded documents.

When uploaded sources are insufficient, the system must say so instead of fabricating citations or evidence.

### Supported AI tasks

- Discuss research questions and thesis structure.
- Summarize uploaded papers.
- Compare methodologies, findings, and limitations across uploaded papers.
- Draft or improve text with source-grounded assistance.
- Explain difficult passages.
- Suggest revisions and identify unsupported claims.
- Help with citations based on registered references.
- Provide evidence-checking assistance.

### Language support

Support Indonesian and English for discussion, summarization, and writing assistance.

Preserve meaning, qualifications, and strength of evidence when translating or summarizing academic material.

Do not turn tentative source findings into definitive conclusions.

### Consent for external processing

Before documents or their contents are processed by an external AI provider, obtain explicit user consent after explaining:

- What data may be sent.
- Which processing purpose requires it.
- That processing is handled by an external provider.
- Relevant privacy implications.

AI-dependent external processing must not begin before the required consent is obtained.

Users should still be able to manage their projects and documents without invoking external AI processing.

Support clear consent status and withdrawal behavior. Do not imply that withdrawing consent can undo data already sent to a provider.

## 12. AI Modes

### Exploratory Mode

For brainstorming, outlines, possible research directions, and exploratory discussion.

- May produce ideas not established by uploaded sources.
- Clearly label unverified ideas or claims.
- Encourage the student to verify important claims before academic use.
- Do not fabricate citations.

### Evidence-Based Mode

For source-grounded academic drafting and claim checking.

- Restrict new factual claims that cannot be connected to evidence in the uploaded project documents.
- Use traceable evidence from indexed passages.
- Flag unsupported or uncertain claims.
- State when the available sources are insufficient.
- Never present a generated reference or quotation as verified without a corresponding source record and source passage.

A missing passage is not proof that a claim is false. The system must distinguish between insufficient evidence, contradictory evidence, and evidence that supports a claim.

## 13. Citation Evidence Checker

### Primary purpose

Help students assess whether selected claims are supported by their uploaded academic sources.

### Processing approach

Use a hybrid retrieval and assessment pipeline:

1. **Retrieve:** Find candidate passages relevant to the selected claim.
2. **Assess:** Use AI to assess the logical relationship between the claim and candidate evidence.
3. **Trace:** Present original source passages and source locations where available.
4. **Review:** Let the student evaluate the result and decide whether to keep, revise, or supplement the claim.

Semantic similarity is candidate retrieval, not proof of support.

### Minimum assessment categories

- `Supported`
- `Contradicted`
- `Insufficient Evidence`
- `Unable to Assess`

Use appropriate uncertainty indicators and a human-review state where necessary. Any numeric confidence score must be calibrated or clearly labeled as an estimate; do not imply statistical reliability without validation.

### Evidence presentation

Where available, show:

- Selected claim.
- Source document.
- Original supporting or contradicting passage.
- Page number or location.
- Explanation of the assessed relationship.
- Limitations or uncertainty.

If the page number cannot be verified, say so rather than inventing one.

### MVP scope

Initially check **user-selected claims or text**, not the entire thesis automatically.

The checker is decision support, not a guarantee of scientific correctness or a substitute for academic supervision.

## 14. Progress and Supervision Notes

Include a simple mechanism for:

- Recording supervision meeting notes.
- Creating action items.
- Linking action items to thesis sections.
- Tracking task status.

No supervisor accounts, live collaboration, or AI processing of supervision notes is required in the MVP.

## 15. Authentication and Authorization

Support:

- Email/password registration and login.
- Google Sign-In.
- Email verification.
- Password reset and recovery.
- Strict project-level data isolation.

Do not implement lecturer roles or complex multi-role permissions for the MVP.

Every project, uploaded file, reference, document, and evidence result must be authorized against the correct user and project.

## 16. Privacy and Data Lifecycle

### Defaults

- Projects are private.
- Documents are not shared with other users by default.
- AI retrieval is scoped to the current project.
- External AI processing requires explicit consent.
- User data is not used for unrelated purposes without appropriate authorization.

### Deletion

Deleting a project should trigger deletion of associated:

- Document records and uploaded files.
- Extracted text and chunks.
- Embeddings and vector indexes.
- Project references and evidence-check records.
- Other project-specific derived data.

Define and disclose realistic deletion and retention schedules for backups, logs, and any external provider copies. Do not claim instantaneous total deletion if backups or provider retention policies prevent that.

### Security priorities

Treat cross-project data leakage, unauthorized file access, and unintended external data transfer as critical incidents.

Use access controls, secure file handling, appropriate secret management, and auditability for sensitive operations.

## 17. Business Model

### Initial model

Freemium, with a planned **Thesis Pass at Rp50,000 per project**.

The initial public launch is free-only. Payment collection and the payment gateway are deferred until AI and document-processing costs are better understood.

The application architecture may prepare for future entitlements, quotas, and project-level passes, but must not imply that paid purchasing is available at initial launch.

### Free tier

- A real, usable workspace.
- Limited document uploads and file sizes.
- Periodically renewed AI usage quota.
- Useful core functionality rather than a demo-only experience.

### Future Thesis Pass

- Higher capacity than the free tier.
- Explicit limits on AI usage and document processing.
- No promise of unlimited AI usage at Rp50,000.
- Capacity and duration rules to be finalized after cost modeling.

Do not finalize numerical quotas until model usage, OCR, embedding, storage, and other variable costs have been estimated.

## 18. Pilot and Validation

### Launch approach

Conduct a limited pilot with students recruited through personal networks.

- Target participants: students actively writing their thesis.
- Duration: 2–4 weeks.
- All MVP features are available from the beginning.
- Participants use the product on real thesis work.
- Feedback mechanism: periodic direct interviews.

### Pilot success dimensions

1. **AI quality**
   - Extraction reliability.
   - Evidence relevance and traceability.
   - Quality of claim–evidence assessments.
   - Frequency and severity of fabricated or misleading output.

2. **Repeated usage**
   - Return usage.
   - Repeat use of core writing and evidence features.
   - Continued use during real thesis work.

3. **Productivity**
   - Time spent on selected tasks.
   - Perceived usefulness and ease of completing thesis work.
   - Examples of work made easier or faster.

Define measurable thresholds and test procedures before pilot evaluation. Do not invent numerical success criteria without considering pilot size and test design.

### Error handling

Use risk-based triage.

- Record all reported and detected errors.
- Classify by severity and risk.
- Immediately address critical issues, especially privacy/security failures and fabricated evidence presented as verified.
- Fix minor issues through periodic releases.
- Record software versions and material model/prompt changes so evaluation results remain interpretable.

### Go/no-go decision

Do not expand to a public launch solely because participants express satisfaction.

Set measurable minimum criteria for AI quality, repeated usage, and productivity. If important criteria are not met, improve the MVP and conduct another pilot before broader launch.

## 19. Recommended Technical Architecture

The architecture is a starting recommendation, not a requirement to adopt every technology immediately.

### Application architecture

Use a **Laravel modular monolith** as the core application, built for a solo developer using AI coding assistants.

Potential stack:

- Backend: Laravel.
- Frontend: Laravel-compatible frontend approach; evaluate Inertia or another suitable integration.
- Structured document editor: Tiptap/ProseMirror or an equivalent structured editor.
- Database: PostgreSQL.
- Semantic retrieval: pgvector if PostgreSQL is selected for vector storage.
- Background jobs: Laravel Queue, with Redis if needed.
- File storage: private object storage or another secure storage backend.
- AI: cloud LLM API behind a provider abstraction.
- Optional Python/FastAPI service: only if complex PDF/OCR/document-processing needs justify it.

Avoid introducing microservices at the beginning without a demonstrated need.

### Core processing pipeline

1. Upload and validate the file.
2. Store it privately.
3. Queue document processing.
4. Extract text, metadata, and available page locations.
5. Run OCR if needed and supported.
6. Store extraction state and quality warnings.
7. Chunk and index successfully extracted text.
8. Retrieve project-scoped passages for AI requests.
9. Return source-linked responses and evidence assessments.

### Architectural constraints

- Every retrieval operation must be scoped to the authorized project.
- Use stable IDs for sources, references, documents, and structured document elements.
- Separate reference metadata validation from claim–evidence assessment.
- Keep long-running tasks asynchronous.
- Keep secrets and provider credentials out of client-side code.
- Use automated tests for authorization, project isolation, citation integrity, document processing states, and export behavior.

## 20. Development Strategy

The product is built and maintained by a solo developer with AI coding assistants.

Favor:

- Small, testable implementation milestones.
- Clear module boundaries inside the monolith.
- Automated tests for high-risk logic.
- Simple deployment and monitoring.
- Explicit acceptance criteria for each feature.
- Documentation that helps future changes.
- Human review of AI-generated code, database migrations, authorization rules, and security-sensitive changes.

Do not implement every MVP module in one giant build step.

## 21. Recommended Implementation Order

This is a proposed order to validate with the engineering plan, not a finalized schedule.

1. **Foundation:** repository, environments, authentication, authorization, project model, database and testing setup.
2. **Project workspace:** multiple projects, isolated data, basic thesis structure and contextual onboarding.
3. **Document import and processing:** private upload, asynchronous extraction, processing states, OCR path, retries and error visibility.
4. **Research Library:** source records, metadata extraction, DOI/manual metadata, confirmation workflow, reference IDs and duplicate detection.
5. **Structured editor:** document schema, chapter hierarchy, import review, editing and basic revision recovery.
6. **AI foundation:** consent flow, provider abstraction, project-scoped retrieval, Exploratory/Evidence-Based modes and traceable responses.
7. **Citation management:** inline citation insertion, bibliography generation, supported styles and consistency validation.
8. **Citation Evidence Checker:** candidate retrieval, claim–evidence assessment, source passages, uncertainty states and user review.
9. **Export and guideline checks:** DOCX/PDF export, export validation report, configurable formatting and compliance warnings.
10. **Progress notes and usability polish:** supervision notes, action items, contextual help and recovery states.
11. **Private pilot:** evaluation instrumentation, interviews, risk-based fixes and go/no-go assessment.

Reorder these milestones if dependencies or implementation experiments justify it.

## 22. Open Decisions and Assumptions

The brainstorming established the product direction, but the following details are not yet finalized.

### Product and UX

- Final supported citation styles.
- Exact fields and rules supported by the Guideline Profile.
- Which structured document elements are supported in the first editor release.
- Import fidelity expectations and manual correction UI.
- Document revision history depth.
- Exact behavior for withdrawing external AI consent.
- Whether students can disable external AI processing for individual projects.

### Technical

- Exact Laravel version and frontend integration.
- Exact LLM provider and models.
- Embedding model and vector storage strategy.
- OCR and PDF extraction libraries/services.
- Object storage provider.
- Hosting, deployment, backups, monitoring and logging.
- DOCX/PDF generation implementation.
- Data retention periods and external provider retention behavior.

### Business and operations

- Free-tier numerical quotas.
- Thesis Pass duration and entitlement details.
- Maximum upload size and document count.
- Expected project lifetime and storage usage.
- Cost per active project and per AI-heavy user.
- Pilot size and quantitative go/no-go thresholds.
- Privacy notice and applicable legal compliance requirements.

Do not silently convert these open decisions into fixed requirements. Make a recommendation when appropriate, state its rationale, and record assumptions.

## 23. Instructions for the `/grill-me` Agent

Use this document as established product context.

1. Do not repeat questions already answered by these requirements.
2. Identify contradictions, material ambiguities, hidden costs, technical risks, and missing acceptance criteria.
3. Ask **one question at a time**, only when the answer materially changes product scope, architecture, safety, or implementation order.
4. Prioritize questions that could cause major rework or compromise evidence integrity, privacy, or project isolation.
5. When an uncertainty is low-impact, recommend a sensible default and record it as an assumption rather than asking another question.
6. Challenge scope that is unrealistic for a solo developer; propose phased delivery without silently dropping a selected MVP feature.
7. Do not produce a final PRD or implementation plan until consequential uncertainties have been resolved or explicitly recorded as assumptions.
8. When requirements are sufficiently clear, produce:
   - A structured PRD.
   - User stories and acceptance criteria.
   - Non-functional requirements.
   - Data model and module boundaries.
   - AI/RAG and document-processing design.
   - Security, privacy, and failure-state requirements.
   - Milestone-based implementation plan.
   - Test strategy and pilot evaluation plan.
   - Explicit assumptions, risks, and deferred scope.

**Product north star:** Build a trustworthy thesis-writing workspace where students can trace AI-assisted academic claims back to their own research sources, retain control over their writing, and understand the limitations of every automated assessment.

