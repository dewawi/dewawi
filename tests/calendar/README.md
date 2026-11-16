# K1 team calendar

Run `python3 tests/calendar/run.py` from the repository root. The suite creates a disposable MariaDB with networking disabled and a local PHP HTTP fixture, then exercises the real calendar model, controller, forms, views, shared layout, jQuery and global scripts. Chromium checks desktop (1280 px) and mobile (390 px) interactions. Existing configuration files and databases are never used. The services and temporary databases are removed when the runner exits.

Also run `python3 tests/install/import-db.py`, the existing permission/authentication/client-switch/profile/admin security tests, `python3 tests/ui/notifications.py`, and PHP syntax checks on the changed PHP/templates.

## Implementation

- `/calendar` is a standard ZF1 module, with `calendar.index` permissions in the existing user permission editor. The header link requires view permission. Creating users and fresh installation use the existing default-permission convention. Upgrades preserve existing permissions; an administrator explicitly enables calendar access.
- Every calendar model operation uses the authenticated actor and selected tenant. The existing parent/child sharing of contacts and products does not share calendars. Users who legitimately switch tenants may own appointments in the selected tenant. All mutations additionally require ownership, including for administrators.
- Private foreign events expose only their busy interval, owner and calendar metadata. SQL masks title, description and location before presentation. The same projection serves HTML and JSON. Deleted appointments are excluded. There is no separate search/export path with access to raw events.
- Calendar forms reuse `DEEC_Form` and the existing session-token/`hash_equals` CSRF pattern. Start and end are saved in one explicit POST with `data-autosave="false"`; the general edit-lock keepalive does not apply to the calendar. GET never creates or deletes an appointment. Owner and tenant cannot be submitted or reassigned.
- New events retain local `start`/`end` and the IANA timezone, with authoritative `startutc`/`endutc` instants. Nonexistent local times are rejected. Ambiguous times require the earlier/later choice, which survives editing. Seconds are preserved. All-day events use local calendar dates and an exclusive end date, so a day remains one date across DST or display-zone differences.
- The existing `calendarevent` table and active `update-1.1.7-1.1.8.sql` migration are extended. `structure.sql` has matching columns/indexes; `data.sql` grants the initial user the same defaults as new users. The installer test executes the complete active migration against its pre-upgrade fixture.
- K2 reminders and K3 participants/invitations are not implemented. Stable appointment/owner IDs, timezone and UTC instants provide their future basis. This change introduces no new subscription model or entitlement bypass.

## Upgrade and operational notes

Apply the added K1 block in `install/config/update-1.1.7-1.1.8.sql` using the normal controlled update process before using this module. No database update is performed by the implementation task. If 1.1.8 was already applied, apply only the previously unapplied statements after inspecting the schema; do not rerun the complete migration blindly. The added ALTER is not designed for repeated execution.

Legacy events keep their original wall times, are private by default and receive `createdby` as owner. Their timezone was never stored, so they are interpreted using the configured tenant timezone; UTC columns remain NULL until the owner explicitly saves the event. During a legacy ambiguous hour the busy interval conservatively covers the earlier start through the later end. Verify historically uncertain timezones or DST values manually before relying on them. Events with no recorded creator remain ownerless and require a deliberate administrative data correction to assign ownership. No automated reassignment or historical time conversion is attempted.

Regular users need at least `view` plus the relevant `add`, `edit` or `delete` permissions. Enable them under Settings → Users → Permissions → Calendar. Existing calendar permissions remain unchanged. All-day end dates are exclusive. Mobile month/week views become a readable daily list. The fixture covers MariaDB; a separate MySQL server was not available for runtime verification.

## Verification of this change

- `python3 tests/calendar/run.py`: passed 87 database/validation/security/schema checks, 43 HTTP checks, and browser navigation, CRUD, filtering, privacy and responsive interaction at 1280 and 390 px. Includes DST gaps/folds, midnight transitions, leap dates, UTC range limits and historical seconds.
- `python3 tests/install/import-db.py`: passed full installation, the complete active migration, schema/index checks and installer failure handling on MariaDB 10.11.14; its existing UOM regression suite passed 66 checks.
- `php tests/security/permissions.php`, `bootstrap-auth.php`, `client-switch.php`, `profile-save.php`, `user-profile.php`, `admin-access.php` (all under `tests/security/`): passed 415, 29, 53, 100, 126 and 68 checks respectively.
- `python3 tests/ui/notifications.py`: passed 1,985 existing UI/global-script regression checks at each viewport (1280 and 390 px).
- All 18 changed PHP files/templates passed `php -l`; new Python sources passed syntax parsing; `git diff --check` passed.
- Standalone `node --check` was unavailable because Node is not installed. The JavaScript was exercised in Chromium instead. Initial sandbox restrictions on local sockets were resolved by running the disposable services with the required sandbox allowance. No production database or data was used.
