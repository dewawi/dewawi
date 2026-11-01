
# DEWAWI – Codex Development Instructions

## Project

- Repository: dewawi/dewawi
- Local development workspace: /var/www/dewawi
- DEWAWI is a modular, multi-tenant ERP and inventory management system.
- Backend: PHP, Zend Framework 1, MariaDB/MySQL.
- Frontend: jQuery, AJAX auto-save, server-rendered views.
- Long-term goal: commercial SaaS with ERP, website CMS, online shops and integrations.

## Architecture

- Preserve the existing modular monolith.
- Migrate away from Zend Framework 1 incrementally, never through a full rewrite.
- Prefer existing DEEC_* components and extend them when appropriate.
- Reuse existing controllers, models, forms, services, helpers and rendering patterns.
- Do not introduce new abstractions, layers or dependencies without a concrete need.
- Keep changes small, clear, efficient and maintainable.
- Preserve existing routes, database relationships and public interfaces unless a change is explicitly approved.
- Keep functionality generic and reusable across different tenants.

## Coding Style

- Follow the existing conventions in the affected files.
- Use English for all code comments.
- Prefer concise, readable code and short functions.
- Do not introduce unnecessary fallbacks or defensive complexity.
- Keep short function declarations and calls on one line where practical.
- Avoid unrelated formatting changes.
- Do not duplicate existing functionality.
- Use existing DEEC_Form, DEEC_Query, DEEC_Display, DEEC_Filter and other DEEC components where suitable.
- Never invent classes, methods, fields or tables without inspecting the repository.

## Working Process

- Always inspect the relevant existing code and dependencies first.
- For analysis or planning requests, do not modify any files.
- Modify files only when the user explicitly requests implementation.
- Work on one clearly defined task at a time.
- Before major changes, explain the approach, risks and compatibility implications.
- Request missing essential information instead of guessing.
- Do not silently expand the scope of a task.
- Never overwrite or discard unrelated local changes.

## Git

- Local commits are generally permitted for explicitly commissioned development tasks.
- Codex should independently implement, test and commit technically related changes in clear local commits.
- Keep the existing repository style: short lowercase English messages without Conventional Commit prefixes.
- Before each commit, inspect the Git status and staged diff. Commit only Codex's own related changes, and stop if tests fail or foreign changes are present.
- Pushes, merges, tags, deployments and rewriting existing Git history remain prohibited without separate explicit approval.
- Never perform destructive Git operations.
- After implementation, show the changed files and a short summary.

## Security and Data

- Enforce tenant isolation and server-side permissions.
- Preserve authorized parent-child tenant data sharing.
- Separate user permissions from SaaS feature entitlements.
- Validate and authorize all state-changing operations.
- Use parameterized SQL queries.
- Never expose secrets or production customer data.
- Never modify production databases.
- Never execute destructive database commands without explicit authorization.
- Preserve existing issued documents and their historical values.

## Testing

- Run PHP syntax checks for modified PHP files.
- Reuse existing tests and extend them when necessary.
- Add regression tests for bug fixes where practical.
- Include negative permission and cross-tenant tests for security changes.
- Test against the local development environment.
- Do not claim tests passed unless they were executed successfully.
- Report any tests that could not be run.

## Completion Report

After each implementation, provide:

1. Files changed
2. What changed and why
3. Tests performed and their results
4. Compatibility risks and remaining issues
5. Suggested Git commit message

Do not automatically start the next task.
