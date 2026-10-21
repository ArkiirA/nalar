# 01: Project Foundation & Tenant Isolation

**What to build:** The student can register, log in, create a named thesis project, switch between projects, and delete a project. Deleting a project immediately wipes all associated records without data leakage across accounts or projects.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] User can register, log in, and log out with session-based authentication.
- [ ] User can create a new thesis project with a title and target completion date.
- [ ] User can view a list of their projects and open any owned project.
- [ ] Unauthorized users cannot view, edit, or delete another user's project.
- [ ] Deleting a project triggers cascading deletion across all associated child records.
- [ ] Automated feature tests verify tenant isolation and cascading deletion.
