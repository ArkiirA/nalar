# 11: Citation Evidence Checker & Inline Claim Marks

**What to build:** The student highlights a factual claim in the chapter editor and clicks "Check Evidence". The system retrieves candidate passages, runs Gemini 1.5 Pro assessment, classifies the claim into 4 categories (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`), displays traceable source excerpts with page numbers, and marks the claim inline.

**Blocked by:** 07 (Inline Citations in Tiptap & Reference Drawer), 10 (AI Copilot Consent & Side-by-Side Proposal Diff)

**Status:** ready-for-human

- [x] Selecting text in Tiptap enables the "Check Evidence" action.
- [x] System retrieves candidate passages via hybrid search and submits claim + evidence to Gemini 1.5 Pro.
- [x] Returns deterministic assessment (`Supported`, `Contradicted`, `Insufficient Evidence`, `Unable to Assess`) with explanation and cited page numbers.
- [x] Claim is marked inline in Tiptap with an evidence status badge; clicking the badge opens the assessment details drawer.
- [x] Automated tests verify assessment status categorization, excerpt page attribution, and inline claim node persistence.
