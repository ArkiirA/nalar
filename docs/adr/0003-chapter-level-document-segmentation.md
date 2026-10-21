# 3. Chapter-Level Document Segmentation

Date: 2026-10-21

## Context

Undergraduate theses in Indonesia typically run between 60 to 120 pages with dozens of figures, tables, and references. A single ProseMirror/Tiptap document instance holding the entire thesis introduces significant frontend DOM lag, heavy auto-save payloads, and complex diff merging.

## Decision

Segment the thesis into discrete Chapter Documents linked to a parent Project outline. Each chapter maintains its own Tiptap JSON content, auto-save stream, and revision history. Full thesis export stitches chapters together sequentially on the backend.

## Consequences

- Editor performance remains fast regardless of total thesis length.
- Granular chapter locking and isolated auto-saves prevent accidental corruption of entire thesis.
- Cross-chapter references and continuous page/figure numbering require project-level compilation logic during export.
