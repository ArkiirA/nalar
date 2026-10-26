# 13: Pre-Export Integrity Validator & DOCX Export

**What to build:** Before exporting, the student runs an integrity validation check that detects uncited references, citations without reference records, and empty chapters. Upon review, the system compiles all chapters sequentially into a downloadable `.docx` file formatted with campus margins, headings, and bibliography using PHPWord.

**Blocked by:** 07 (Inline Citations in Tiptap & Reference Drawer)

**Status:** closed

- [x] Pre-export check scans all chapters and reports warnings (uncited references, invalid citations, empty chapters).
- [x] Export button remains accessible after student reviews validation report.
- [x] PHPWord compilation stitches all chapter documents in outline order with chapter page breaks.
- [x] Formats document fonts (Times New Roman 12pt), line spacing (1.5), and Indonesian margins (4cm top/left, 3cm bottom/right).
- [x] Generates bibliography section formatted in the selected style (APA 7th or Harvard).
- [x] Automated tests verify DOCX generation, citation-to-bibliography consistency, and structure stitching.
