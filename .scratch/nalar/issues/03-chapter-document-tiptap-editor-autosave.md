# 03: Chapter Document Tiptap Editor & Continuous Auto-save

**What to build:** The student selects a chapter from the outline and edits the text in a rich structured editor styled to authentic academic standards (Times New Roman, 1.5 line spacing, standard margins). Changes save continuously in the background without freezing the editor.

**Blocked by:** 02 (Guideline Profiles & 3-Column Workspace Shell)

**Status:** ready-for-human

- [x] Selecting a chapter in the outline loads that chapter's structured document into Tiptap.
- [x] Editor supports headings (H1, H2, H3), paragraphs, bullet/numbered lists, tables, and figure captions.
- [x] Background debounced auto-save persists chapter content with visual save status indicator ("Saved", "Saving...").
- [x] Switching between chapters flushes any pending edits before loading the next chapter.
- [x] Automated tests verify chapter payload saving and isolation across chapters.

## Comments

- Installed `@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/extension-table`, `@tiptap/extension-table-row`, `@tiptap/extension-table-cell`, and `@tiptap/extension-table-header`.
- Implemented `FigureCaption` extension in `resources/js/Components/extensions/FigureCaption.js` for academic figure and table captions (`<figcaption>`).
- Built `resources/js/Components/ChapterEditor.vue` with academic typography (Times New Roman, 1.5 line spacing, A4 page layout preview, paper-white and sepia theme modes), comprehensive toolbar (H1/H2/H3, bullet/numbered lists, table manipulation, captions), live word/character counters, and debounced auto-save with visual status indicators ("Tersimpan", "Menyimpan...", "Gagal simpan").
- Implemented immediate auto-save flushing on chapter switching and window unmount.
- Enhanced `ChapterController@update` to accept and validate JSON content payloads, returning 200 JSON for API/AJAX callers and maintaining project/tenant isolation.
- Added `content` attribute casting in `Chapter` model supporting Tiptap AST structures and JSON serialization.
- Verified with 55 passing automated Pest feature tests (including 6 new dedicated tests in `ChapterEditorAndAutoSaveTest.php`) and clean Vite production build.
- Status set to `ready-for-human` for review.
