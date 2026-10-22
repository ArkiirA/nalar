# 10: AI Copilot Consent & Side-by-Side Proposal Diff

**What to build:** The student must review and accept explicit external AI processing consent before AI features activate. Once enabled, the student can chat with a Gemini-powered copilot in Exploratory or Evidence-Based mode. Writing suggestions appear in a side-by-side diff drawer with Accept/Reject controls.

**Blocked by:** 03 (Chapter Document Tiptap Editor & Continuous Auto-save), 09 (Hybrid Retrieval Engine (Vector + Full-Text RRF))

**Status:** ready-for-human

- [x] Explicit consent dialog must be accepted before any data is dispatched to external AI endpoints; student can revoke consent at any time.
- [x] Copilot interface toggles between Exploratory Mode (creative ideation) and Evidence-Based Mode (closed-library grounded).
- [x] Evidence-Based Mode strictly refuses to state facts not supported by retrieved candidate passages.
- [x] AI-suggested text revisions render in a side-by-side comparison drawer highlighting additions/deletions.
- [x] Clicking "Accept" replaces the selected chapter text in Tiptap; clicking "Reject" closes the drawer without modifying text.
- [x] Automated tests verify consent gate enforcement, mode prompt restrictions, and diff payload formatting.
