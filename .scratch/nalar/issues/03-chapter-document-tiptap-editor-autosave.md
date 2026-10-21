# 03: Chapter Document Tiptap Editor & Continuous Auto-save

**What to build:** The student selects a chapter from the outline and edits the text in a rich structured editor styled to authentic academic standards (Times New Roman, 1.5 line spacing, standard margins). Changes save continuously in the background without freezing the editor.

**Blocked by:** 02 (Guideline Profiles & 3-Column Workspace Shell)

**Status:** ready-for-agent

- [ ] Selecting a chapter in the outline loads that chapter's structured document into Tiptap.
- [ ] Editor supports headings (H1, H2, H3), paragraphs, bullet/numbered lists, tables, and figure captions.
- [ ] Background debounced auto-save persists chapter content with visual save status indicator ("Saved", "Saving...").
- [ ] Switching between chapters flushes any pending edits before loading the next chapter.
- [ ] Automated tests verify chapter payload saving and isolation across chapters.
