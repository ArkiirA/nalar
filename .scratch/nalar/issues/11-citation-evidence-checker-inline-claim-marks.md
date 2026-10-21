# 11: Citation Evidence Checker & Inline Claim Marks

**What to build:** The student highlights a factual claim in the chapter editor and clicks "Check Evidence". The system retrieves candidate passages, runs Gemini 1.5 Pro assessment, classifies the claim into 4 categories (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`), displays traceable source excerpts with page numbers, and marks the claim inline.

**Blocked by:** 07 (Inline Citations in Tiptap & Reference Drawer), 10 (AI Copilot Consent & Side-by-Side Proposal Diff)

**Status:** closed

- [x] Selecting text in Tiptap enables the "Check Evidence" action (Cek Bukti).
- [x] System evaluates claim against context using AI service / heuristic fallback.
- [x] Returns deterministic assessment (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`) with explanation and traceable excerpts.
- [x] Interactive drawer displays assessment, confidence meter, literature passages, and one-click rewrite replacement.
- [x] Automated tests verify assessment status categorization, overclaim detection, and API authorization.
