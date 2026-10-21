# 05: Research Library PDF Upload & Queue Worker

**What to build:** The student uploads PDF research papers into the project Research Library. Uploaded files process in background queues using server CLI tools (`pdftotext` with layout preservation and `tesseract-ocr` fallback), extracting plain text while strictly tagging each extracted page with its physical page number.

**Blocked by:** 01 (Project Foundation & Tenant Isolation)

**Status:** closed

- [x] File uploader accepts PDF files up to 25MB, validating file type and scoping file storage to the active project.
- [x] Background job extracts text page-by-page using native CLI utilities, preserving page boundaries.
- [x] Documents transition through observable states (`Queued`, `Processing`, `Processed`, `Partial`, `Failed`).
- [x] Research Library displays the processing state badge and lets students retry failed jobs.
- [x] Automated tests verify background extraction with fixture PDFs, page number tagging, and failure handling.
