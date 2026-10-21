# 14: Campus-Styled PDF Compilation

**What to build:** The student clicks "Export PDF" to compile the entire thesis into a print-ready PDF formatted strictly to campus guidelines. The system renders styled server-side HTML through headless Chromium, producing a table of contents, figures list, continuous page numbers, and exact Indonesian thesis layout.

**Blocked by:** 13 (Pre-Export Integrity Validator & DOCX Export)

**Status:** ready-for-agent

- [ ] Backend renders compiled HTML view representing the entire thesis with campus typography, spacing, and CSS paged media.
- [ ] Headless Chromium generates PDF output with correct paper size (A4), margins (4-4-3-3cm), and page numbering.
- [ ] Generates Table of Contents (Daftar Isi), List of Tables (Daftar Tabel), and List of Figures (Daftar Gambar).
- [ ] Download endpoint streams final PDF to student with project-specific filename.
- [ ] Automated tests verify PDF generation job execution, file existence, and response streaming.
