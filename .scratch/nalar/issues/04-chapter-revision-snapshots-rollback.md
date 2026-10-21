# 04: Chapter Revision Snapshots & Rollback

**What to build:** The system automatically captures snapshots of the chapter document every 5 minutes during active typing, and the student can manually trigger a named "Save Version" checkpoint. The student can open a revision history drawer, browse previous states, and revert the chapter to any snapshot.

**Blocked by:** 03 (Chapter Document Tiptap Editor & Continuous Auto-save)

**Status:** closed

- [x] Automatic background snapshots recorded every 5 minutes during active edits (capped at 50 snapshots per chapter).
- [x] Student can create a named manual snapshot checkpoint at any time.
- [x] Revision drawer lists chronological snapshots with relative timestamps.
- [x] Student can preview a previous snapshot and click "Restore this version" to roll back the editor.
- [x] Automated tests verify snapshot frequency rules, cap limits, and restoration integrity.
