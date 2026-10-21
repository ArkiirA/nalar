# 15: Existing Thesis Importer (DOCX/PDF)

**What to build:** The student uploads an existing thesis draft (DOCX or text-based PDF). The system scans for chapter heading markers (`BAB [I-V]` and `Heading 1`), splits the draft into candidate chapters, and presents an interactive preview modal where the student can verify, rename, or merge chapters before populating the workspace.

**Blocked by:** 03 (Chapter Document Tiptap Editor & Continuous Auto-save)

**Status:** ready-for-agent

- [ ] File upload endpoint accepts DOCX and PDF thesis drafts up to 50MB.
- [ ] Parser splits content at recognized chapter headers into candidate chapter records.
- [ ] Interactive modal displays candidate chapter titles and preview text, allowing manual corrections.
- [ ] On confirmation, creates Chapter Documents and updates the project outline without overwriting unselected items.
- [ ] Automated tests verify heading detection patterns, candidate split integrity, and document creation.
