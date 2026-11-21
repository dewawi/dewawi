# M2A verification

Historical M2A results from `090c0128`. M2C supersedes the per-user allowlist policy described here; see [current administrator-access verification](update-access.md).

Verified locally on 2026-11-21 with PHP 8.3.6, MariaDB 10.11.14 and headless
Chromium. All database and HTTP tests below use temporary instances and synthetic
fixtures. No application database configuration, customer data or production
service is used.

## Results

| Command | Result |
| --- | --- |
| `python3 -B tests/admin/updates.py` | 306 checks passed |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `python3 -B tests/install/import-db.py` | Fresh/historical upgrade and error cases passed; 66 UOM checks passed |
| `python3 -B tests/security/shop-shipping-db.py` | Fresh/upgrade schema equality, repeated apply, 70 DB checks and 147 HTTP/browser checks passed |
| `php tests/security/admin-access.php` | 68 ACL cases and forbidden response passed |
| `php tests/security/bootstrap-auth.php` | 29 authentication, tenant and sharing cases passed |
| `php tests/security/permissions.php` | 450 permission/ACL compatibility cases passed |
| `php tests/security/configuration.php` | 22 entry-point/configuration cases passed |
| `python3 -B tests/security/configuration-http.py` | 9,208 Apache HTTP cases passed, including all new PHP/include files, private paths and manager browser checks |
| `python3 -B tests/security/error-handling-http.py` | 152 cases passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| PHP syntax checks | All 21 changed PHP/view files passed |

The initial sandbox runs could not bind Unix sockets. The database/HTTP suites
were then run successfully with the required execution permission, still using
their isolated temporary servers. Initial fixture-copy failures were corrected
by including the extracted SQL splitter and central version dependencies; no
assertion was weakened.

An additional `php tests/translations/cleanup.php` run fails in the unchanged
`application/modules/shops/views/scripts/cart/index.phtml:37`: the existing
cleanup fixture provides no `SiteContext` for `hasFeature()`. Both the test and
cart template are unchanged from HEAD. This separate fixture issue remains open;
the cleanup suite is not claimed as passed.

## M2A coverage and limits

The new suite imports the frozen released schema and obtains authentic baseline
and shipping history through the real M1 CLI. It creates separate mutated-history
fixtures and exercises the common inspector and actual controller, ACL, view,
menu and ERP layout. Its web database account has SELECT privileges only. A full
before/after dump confirms that overview requests change neither schema nor data.

Cases cover absent/empty history, an existing development shipping schema without
history, registered baseline with empty/pending catalog, successful shipping
execution, started/failed/invalid states, wrong statement counts, zero dates,
file/definition/baseline checksum mismatches, unknown IDs, dependency gaps and
invalid history-table structure. Missing, malformed and output-producing version
files remain unknown and never leak their contents. Invalid catalogs return a
sanitized page. Raw SQL, SQLSTATE diagnostics, unknown IDs, secrets and private
paths are absent from responses.

Authorization checks include ordinary tenant admins, non-admins, guests,
unconfigured/invalid operator grants, inactive/deleted users and mismatched
identities. Each is checked through the ACL and directly at controller
initialization in three selected tenant contexts, including parent admin sharing.
Only an explicitly granted active admin can see the menu and global history.
Inherited actions and migration/SQL/status endpoints return 404; mutation methods
on index return 405. GET and HEAD succeed.

DE/EN browser checks run at 1280, 390 and 320 pixels: ERP shell, translated labels,
no horizontal overflow, baseline/shipping cards, focusable errors, native anchor
navigation and absence of execution controls. Focus/navigation checks do not
replace a screen-reader audit. No dedicated MySQL server or live installation
was tested. Full schema verification and authoritative execution planning remain
separate, locked CLI operations as stated on the page.

See [the operator guide and M2B boundary](../../tools/migrations/README.md).
M2A needs no SQL update. Operator access remains denied until the installation
owner configures reviewed numeric account IDs in the protected installation INI.
No private configuration was changed during implementation.
