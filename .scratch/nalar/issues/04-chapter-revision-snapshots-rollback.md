# 04: Chapter Revision Snapshots & Rollback

**What to build:** The system automatically captures snapshots of the chapter document every 5 minutes during active typing, and the student can manually trigger a named "Save Version" checkpoint. The student can open a revision history drawer, browse previous states, and revert the chapter to any snapshot.

**Blocked by:** 03 (Chapter Document Tiptap Editor & Continuous Auto-save)

**Status:** ready-for-human

- [x] Automatic background snapshots recorded every 5 minutes during active edits (capped at 50 snapshots per chapter).
- [x] Student can create a named manual snapshot checkpoint at any time.
- [x] Revision drawer lists chronological snapshots with relative timestamps.
- [x] Student can preview a previous snapshot and click "Restore this version" to roll back the editor.
- [x] Automated tests verify snapshot frequency rules, cap limits, and restoration integrity.

## Comments

- Implemented `ChapterRevision` model and database migration `2026_10_21_000005_create_chapter_revisions_table.php` with cascade deletion, indexed on `(chapter_id, created_at)`.
- Added snapshot management to `Chapter` model: `recordSnapshot()`, `maybeRecordAutoSnapshot()` (5-minute frequency rule during active edits), `pruneExcessRevisions()` (50 revision cap per chapter, prioritizing auto snapshots to protect manual checkpoints), and `restoreRevision()`.
- Added duplicate snapshot prevention when manual snapshot is saved immediately after an autosave.
- Built `ChapterRevisionController` with REST endpoints: `index` (shallow chronological listing with relative timestamps), `store` (named manual checkpoint creation), `show` (full structured content loading for preview), and `restore` (rolling back chapter content to snapshot).
- Added `setContent` to `ChapterEditor.vue` to allow programmatic document rollback without component unmounting or lost state.
- Built Revision Drawer in `resources/js/Pages/Projects/Show.vue` with tab switcher, Save Version checkpoint modal, chronological snapshot cards with relative timestamps, structured snapshot preview (headings, lists, tables, captions), and prominent verbatim "Restore this version" action.
- Added comprehensive Pest test suite in `tests/Feature/ChapterRevisionTest.php` (11 tests, 66 assertions) covering 5-minute frequency rule, unchanged content deduplication, 50-snapshot cap with manual checkpoint protection, named checkpoints, chronological listing, restoration integrity, and tenant isolation.
- Full test suite passes (66 tests, 371 assertions) and frontend bundle builds cleanly (`npm run build`).
- Status set to `ready-for-human`.
