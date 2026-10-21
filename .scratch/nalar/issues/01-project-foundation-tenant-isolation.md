# 01: Project Foundation & Tenant Isolation

**What to build:** The student can register, log in, create a named thesis project, switch between projects, and delete a project. Deleting a project immediately wipes all associated records without data leakage across accounts or projects.

**Blocked by:** None (can start immediately)

**Status:** ready-for-human

- [x] User can register, log in, and log out with session-based authentication.
- [x] User can create a new thesis project with a title and target completion date.
- [x] User can view a list of their projects and open any owned project.
- [x] Unauthorized users cannot view, edit, or delete another user's project.
- [x] Deleting a project triggers cascading deletion across all associated child records.
- [x] Automated feature tests verify tenant isolation and cascading deletion.

## Comments

- Laravel 11/Inertia.js (Vue 3) modular application scaffolded with PostgreSQL and pgvector support.
- Implemented `Project` model, migrations, `ProjectPolicy`, and `ProjectController` with full tenant isolation (`view`, `update`, `delete`).
- Configured cascading deletion (`ON DELETE CASCADE`) for project child records (e.g. `chapters`).
- Automated feature tests added and passing in `ProjectManagementTest`, `TenantIsolationTest`, and `ProjectCascadeDeletionTest`.
- Status set to `ready-for-human` for final review.
