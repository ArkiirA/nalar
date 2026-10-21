# 06: Reference Management & CSL Bibliographic Formatting

**What to build:** The student adds and manages references in the Research Library via extracted PDF metadata, DOI resolution, or manual entry forms. References are stored as structured CSL-JSON records with stable IDs, and display formatted according to APA 7th edition or Harvard styles.

**Blocked by:** 05 (Research Library PDF Upload & Queue Worker)

**Status:** ready-for-human

- [x] Resolving a DOI imports standard bibliographic metadata (authors, title, year, journal, volume, pages).
- [x] Manual entry form allows adding and editing book, conference, and journal references with missing-field warnings.
- [x] System detects duplicate reference titles or DOIs within the same project.
- [x] Formats bibliographic reference entries deterministically according to selected project style (APA 7th or Harvard).
- [x] Automated tests verify CSL-JSON serialization, duplicate detection, and citation style output.
