# Skripsi Studio Domain Glossary

Domain terminology for Skripsi Studio, an AI-assisted research and thesis writing workspace for Indonesian undergraduate students.

## Language

### Workspace & Documents

**Project**:
An isolated thesis workspace owned by a student containing thesis structure, documents, references, and AI context.
_Avoid_: Workspace, notebook, folder

**Guideline Profile**:
A configurable set of campus-specific structural rules and formatting presets used to validate thesis layout.
_Avoid_: Formatting template, style guide

**Structured Document**:
The internal hierarchical thesis model represented by structured nodes (chapters, sections, citations) as the source of truth.
_Avoid_: Draft, file, docx

**Chapter Document**:
An individually stored, editable segment of the thesis representing one chapter within the project outline.
_Avoid_: Sub-document, page, chapter file

**Chapter Revision**:
A point-in-time snapshot of a Chapter Document's structured content for rollback and history.
_Avoid_: Commit, backup, save point

**Supervision Note**:
An advisor meeting record containing feedback and action items linked to specific Chapter Documents.
_Avoid_: Consultation note, advisor log, task list

### Research & Citations

**Research Library**:
A project-scoped collection of uploaded academic documents, extracted passages, and verified bibliographic records.
_Avoid_: Document store, file manager

**Reference**:
A registered bibliographic record within the Research Library with a stable ID and confirmed metadata.
_Avoid_: Citation record, source entry, bibliography item

**Citation**:
An inline marker in the thesis text pointing directly to a registered Reference.
_Avoid_: In-text reference, quote anchor

### Evidence & Verification

**Claim**:
A student-selected factual or analytical sentence in the thesis evaluated for evidence support.
_Avoid_: Statement, argument, query

**Candidate Passage**:
An extracted text snippet from an uploaded document retrieved as potential evidence for a claim, tagged with page location.
_Avoid_: Chunk, match, context excerpt

**Evidence Assessment**:
The evaluated relationship between a claim and candidate passages (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`).
_Avoid_: Fact check, verification score, truth rating

**Citation Evidence Checker**:
The subsystem that retrieves candidate passages and determines evidence support for student claims.
_Avoid_: Plagiarism checker, truth engine, auto-grader

**Checked Claim**:
An inline marked text span in the editor bound to a historical Evidence Assessment record.
_Avoid_: Verified text, highlighted claim
