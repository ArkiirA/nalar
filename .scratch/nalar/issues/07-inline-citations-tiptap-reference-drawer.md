# 07: Inline Citations in Tiptap & Reference Drawer

**What to build:** While writing in the chapter editor, the student triggers a citation insertion popup (via `@` or toolbar), searches registered references, and inserts an inline citation. Citations render as tamper-proof atomic nodes. Clicking a citation opens a drawer displaying reference metadata and source details.

**Blocked by:** 03 (Chapter Document Tiptap Editor & Continuous Auto-save), 06 (Reference Management & CSL Bibliographic Formatting)

**Status:** ready-for-agent

- [ ] Custom inline Tiptap node represents citations bound to an internal `reference_id`.
- [ ] Autocomplete modal lets the student filter registered references by author or title and insert into editor.
- [ ] In-text citation displays formatted tag (e.g. `(Wijaya, 2023)`).
- [ ] Clicking a citation node opens a drawer with source metadata and a link to view the uploaded PDF.
- [ ] Automated tests verify citation node serialization in chapter JSON and prevention of orphaned citation IDs.
