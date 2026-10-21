# 02: Guideline Profiles & 3-Column Workspace Shell

**What to build:** When opening a project, the student selects or views a campus guideline profile (with presets for UI, UGM, ITB, Gunadarma, and Universal S1). The workspace presents a 3-column shell: collapsible chapter outline on the left, empty editor stage in the center, and expandable tools drawer on the right.

**Blocked by:** 01 (Project Foundation & Tenant Isolation)

**Status:** ready-for-human

- [x] Campus guideline presets (UI, UGM, ITB, Gunadarma, Universal S1) populate default chapter outline structures (Bab I through Bab V).
- [x] Student can customize chapter and section titles and reorder them in the outline.
- [x] 3-column workspace shell renders with collapsible sidebar and tool panels.
- [x] Contextual onboarding prompts show empty states guiding student to start writing or upload papers.
- [x] Automated tests verify guideline preset attachment and outline persistence per project.

## Comments

- Added `guideline_preset` and `guideline_profile` to `projects` table and `parent_id` foreign key with cascading delete to `chapters` table.
- Implemented `App\Support\GuidelinePresets` defining presets for Universal S1, UI, UGM, ITB, and Gunadarma with Bab I-V outlines, citation styles, and formatting rules.
- Built 3-column workspace shell in `resources/js/Pages/Projects/Show.vue` with collapsible chapter tree outline, academic-styled empty editor stage with contextual onboarding prompts, and expandable tools drawer (Pedoman, Library, Evidence).
- Added chapter/section CRUD and sequential reorder endpoints in `ChapterController` and `routes/web.php` with `scopeBindings()`.
- Verified with 49 passing automated Pest feature tests (including 5 preset datasets) and clean Vite production build.
- Status set to `ready-for-human` for final human review.
