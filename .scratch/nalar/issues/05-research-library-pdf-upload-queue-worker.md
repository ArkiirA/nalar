# 05: Research Library PDF Upload & Queue Worker

**What to build:** The student uploads PDF research papers into the project Research Library. Uploaded files process in background queues using server CLI tools (`pdftotext` with layout preservation and `tesseract-ocr` fallback), extracting plain text while strictly tagging each extracted page with its physical page number.

**Blocked by:** 01 (Project Foundation & Tenant Isolation)

**Status:** ready-for-human

- [x] File uploader accepts PDF files up to 25MB, validating file type and scoping file storage to the active project.
- [x] Background job extracts text page-by-page using native CLI utilities, preserving page boundaries.
- [x] Documents transition through observable states (`Queued`, `Processing`, `Processed`, `Partial`, `Failed`).
- [x] Research Library displays the processing state badge and lets students retry failed jobs.
- [x] Automated tests verify background extraction with fixture PDFs, page number tagging, and failure handling.

## Comments

- Added `documents` and `document_pages` migrations with project cascading deletion and indexed lookups.
- Implemented `Document` and `DocumentPage` models with state helpers, automatic physical file cleanup on model deletion, and relationships on `Project`.
- Implemented `PdfExtractor` service utilizing native server CLI tools (`pdftotext -layout` page-by-page extraction, `pdfinfo` page counting, and fallback OCR via `pdftoppm` + `tesseract`).
- Implemented `ProcessDocumentJob` background queue worker updating observable states (`Queued` -> `Processing` -> `Processed`, `Partial`, or `Failed`), saving page records tagged with exact physical page numbers.
- Built `DocumentController` with upload (validating PDF and 25MB limit), retry failed job, delete, and view pages endpoints, strictly scoped to the active project.
- Built Research Library UI in `resources/js/Pages/Projects/Show.vue` with file uploader modal, observable status badges (`Queued`, `Processing`, `Processed`, `Partial`, `Failed`), retry button for failed/partial extractions, extracted pages preview modal, and background polling for pending jobs.
- Added comprehensive Pest feature tests in `tests/Feature/DocumentUploadTest.php` and `tests/Feature/DocumentProcessingJobTest.php` with real PDF fixtures, verifying file upload, size/type validation, tenant isolation, retry logic, page-by-page extraction, OCR fallback, and cascading deletion.
- Full test suite passes (84 tests, 465 assertions) and frontend bundle builds cleanly (`npm run build`).
