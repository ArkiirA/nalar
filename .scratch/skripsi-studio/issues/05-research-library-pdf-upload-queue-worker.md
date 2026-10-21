# 05: Research Library PDF Upload & Queue Worker

**What to build:** The student uploads PDF research papers into the project Research Library. Uploaded files process in background queues using server CLI tools (`pdftotext` with layout preservation and `tesseract-ocr` fallback), extracting plain text while strictly tagging each extracted page with its physical page number.

**Blocked by:** 01 (Project Foundation & Tenant Isolation)

**Status:** ready-for-agent

- [ ] File uploader accepts PDF files up to 25MB, validating file type and scoping file storage to the active project.
- [ ] Background job extracts text page-by-page using native CLI utilities, preserving page boundaries.
- [ ] Documents transition through observable states (`Queued`, `Processing`, `Processed`, `Partial`, `Failed`).
- [ ] Research Library displays the processing state badge and lets students retry failed jobs.
- [ ] Automated tests verify background extraction with fixture PDFs, page number tagging, and failure handling.
