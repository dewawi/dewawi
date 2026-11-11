# DEWAWI – Codex Development Instructions

## Project and Authority

- Repository: `dewawi/dewawi`
- Local workspace: `/var/www/dewawi`
- Modular, multi-tenant ERP/inventory system, CMS, online shops and integrations; long-term commercial SaaS.
- Backend: PHP, Zend Framework 1 (ZF1), MariaDB/MySQL. Frontend: jQuery, AJAX auto-save (`global.js`, `data-*` attributes), server-rendered views and custom form components.
- These instructions govern Codex implementation. ChatGPT project instructions govern analysis and Codex task preparation. Both share the same architecture, migration, safety and Git rules.
- Treat the current explicitly assigned task as the scope. Analysis-only requests must not change files. Implementation requests authorize relevant local edits, tests and commits.

## Architecture and Code Style

- Preserve the modular monolith; replace ZF1 incrementally, never through a full rewrite.
- Inspect affected code, callers, dependencies, routes, database schema and tests before editing. Never invent existing classes, methods, columns or tables.
- Prefer existing `DEEC_*` components, including `DEEC_Form`, `DEEC_Query`, `DEEC_Display`, `DEEC_Filter`, `DEEC_PriceRule` and `DEEC_Email`, after checking their actual APIs.
- Reuse existing controllers, models, services, helpers, forms, partials and rendering conventions. No new layers, frameworks, dependencies or parallel implementations without a demonstrated need.
- Preserve established public interfaces, routes, SQL relationships and user workflows unless the task explicitly requires a change.
- Favor the smallest complete fix for the underlying cause, not symptom-only workarounds.
- Keep code concise, readable and maintainable; avoid unnecessary fallbacks, wrappers, defensive branches and duplicated logic.
- Follow formatting and naming conventions in nearby code. Keep short function declarations/calls on one line where practical. Avoid unrelated formatting.
- Use English for all comments in source code. Only comment when useful.
- Keep logic generic and reusable across tenants; avoid tenant-specific shortcuts.

## Working Process

1. Inspect `git status` and relevant local files first; local code may be newer than GitHub. Protect existing uncommitted changes.
2. Trace the complete affected flow before editing, including server-side checks, UI, callers and tests.
3. Implement only the authorized task. Split larger work into coherent testable blocks; do not silently widen scope.
4. Verify behavior, run appropriate tests and review diffs before committing.
5. Report results, risks and local commits. Do not automatically begin another task.

- Do not ask for permission at every implementation step or before permitted local commits.
- For major changes, explain the chosen approach and relevant compatibility risks concisely.
- Ask only when essential information cannot be determined from the repository, when business decisions are genuinely ambiguous, or when an action requires explicit approval.
- Never overwrite, discard or stage unrelated local work. If a file contains both task-related and unrelated edits, carefully stage only the intended hunks; if safe separation is not possible, report the blocker.
- Do not perform destructive filesystem or Git operations.

## Functional Compatibility and Regression Risks

- Maintain tenant isolation, authorized parent-child sharing, user permissions and SaaS feature entitlements.
- Preserve existing values of finalized/issued documents; do not silently recalculate historical values.
- For relevant changes, trace connected workflows: forms and auto-save; customer/document and email attachments; offer/inquiry calculations and PDF; products/categories, search, shops and CMS; slug routing and redirects; image layouts and responsive behavior.
- Do not break established default selections or existing document-sending workflows without an explicit requirement.
- Fix root causes; keep unrelated refactoring separate.
- For UI changes, follow current jQuery/event/data-attribute conventions rather than adding competing client-side systems.

## Database and Versioned SQL Migrations

- Development version: `1.1.7`; next version: `1.1.8` until expressly changed.
- Put **all** new migration SQL for this version only in `install/config/update-1.1.7-1.1.8.sql`; preserve existing statements and append or integrate the new changes appropriately.
- Keep the fresh-install schema in `install/config/structure.sql` consistent with the migration.
- Do not create per-feature migration files, a new migration framework or a `1.1.9` migration without explicit approval.
- Inspect current tables, columns, indexes, constraints and related SQL before editing.
- Validate schema changes with existing installation and migration tests.
- If an installation may already have run the active migration partially or entirely, describe any necessary manual follow-up in the completion report. Do not invent a parallel official migration.
- Never touch production databases or customer data without an explicit task authorizing that specific action. Never run destructive database commands without explicit authorization. Use local development/test environments for validation.

## Security and Data Integrity

- Apply tenant scoping and server-side authorization to all relevant reads and writes; preserve legitimate parent-child tenant data sharing.
- Keep user permissions distinct from subscribed SaaS feature entitlements.
- Validate state changes and use parameterized SQL.
- Preserve transaction boundaries and consistency for related document/business operations.
- Never disclose secrets or production customer data; do not modify production services as part of ordinary development.
- For security-relevant changes, cover unauthorized access, invalid inputs and cross-tenant negative cases.

## Testing and Verification

- Run PHP syntax checks on changed PHP files.
- Reuse and extend existing test suites; add targeted regression tests for defects where practical.
- Run appropriate unit/integration, controller/HTTP, SQL migration, security or Chromium/browser checks depending on the changed flow. For UI, verify relevant responsive states and interactions.
- Verify old behavior as well as the requested behavior; do not rewrite tests merely to make a change appear successful.
- Fix regressions introduced by the task before committing. If a pre-existing failure or unavailable environment prevents a check, describe the exact limitation and do not claim success.
- Never run tests against production unless separately and explicitly authorized.

## Git – Local Commits Allowed, Pushes Forbidden

- Codex may independently stage and **locally commit** implementation work explicitly assigned by the user or by an authorized Codex task.
- Group closely related changes into one commit. Use separate local commits for independently reviewable technical/functional blocks when useful; no additional confirmation needed.
- Match the repository's actual style: short lowercase English commit messages, no Conventional Commit prefixes.
- Before each commit inspect `git status`, intended file diff and staged diff. Stage only task-related changes, preferably explicit paths or selected hunks. Do not include third-party/unrelated modifications.
- If task-related tests fail, resolve the regression before committing. Report any test that could not run or was already failing.
- **NEVER push. Only the user pushes to remote repositories.**
- No merges, tags, deployments or rewriting Git history without separate explicit user instruction. No `reset --hard`, destructive clean or similar loss-of-work operations.
- Do not amend already created commits unless explicitly requested.

## Completion Report

After implementation, respond concisely with:

1. Root cause or implementation goal; what changed and why.
2. Relevant changed files.
3. Tests actually run, results, and any omitted/failed checks.
4. Local commits: short hashes and exact messages (or why no commit was created).
5. Compatibility risks, open issues and required manual steps.
6. Database migration/manual follow-up, if applicable.
7. Explicit confirmation: **no push performed**.

Do not automatically start another task.
