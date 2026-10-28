# Safe error handling review

## Behavior

- `index.php` applies the environment's display settings before loading configuration. Initialization, bootstrap and dispatch failures are caught as `Throwable` and logged with their exception chain and trace through PHP's existing error log.
- Startup output is buffered until bootstrap succeeds. A failed startup discards that output. Normal dispatch and file streaming continue to use the existing Zend response handling.
- Production failures return a generic HTML page or an `ok: false, message` JSON response with HTTP 500. Development adds escaped HTML diagnostics or a separate JSON `exception` field.
- Successful cron initialization still skips web bootstrap and dispatch. Failed or missing configuration stops execution with exit code 1; a failing HTTP cron initialization returns HTTP 500.
- The ErrorController works without menu, user or database initialization. It clears partial response bodies and download/redirect headers, preserves HTTP 404 classification and logs full exceptions even when an optional application logger fails.
- The error view never dumps request parameters. Technical diagnostics require both Development and `displayExceptions`; Production cannot enable them through that flag alone.
- Variant responses retain the existing `ok`, `id`, `data` and `message` structure. The five fixed business validation keys remain public; technical failures are logged and return `save_failed`, which `global.js` already handles. Invalid request methods return the existing `MESSAGES_INVALID_REQUEST` key without executing the service.
- Four OAuth catch blocks now forward technical failures into the existing central error flow instead of printing exception messages and upstream response bodies.
- Existing DEEC save, delete and PDF fallback responses remain unchanged; their caught technical exceptions now retain a full internal trace.
- Installer database validation messages, retry controls and SQL import stop behavior are preserved. Production SQL details move to logs; Development can display them through the existing escaped import template.
- Installer steps are restricted to the existing seven step names. Apache permits the installer entry point and assets while denying direct requests to internal PHP step/helper files. Server-side includes remain available.
- FileManager and MediaManager apply safe settings before session startup. Caught upload, FTP and image errors are logged; public messages use the existing `File_Save_Error` translation. Both existing upload error shapes (`files` and `error`) and the two fixed URL validation messages remain available. Thumbnail failures return `false` instead of a truthy exception message. Uncaught manager failures produce a controlled HTTP 500 response.

## Changed files

Entry point and central errors:

- `.htaccess`
- `index.php`
- `application/controllers/ErrorController.php`
- `application/views/scripts/error/error.phtml`

AJAX and integration controllers:

- `application/modules/items/controllers/VariantController.php`
- `application/modules/items/controllers/ItemController.php`
- `application/modules/ebay/controllers/AccountController.php`
- `application/modules/ebay/controllers/LogController.php`
- `application/modules/ebay/controllers/OrderController.php`

Existing DEEC error paths:

- `library/DEEC/Controller/Action.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/Controller/MultiEntityAction.php`
- `library/DEEC/Service/FormSaveService.php`

Installer:

- `install/index.php`
- `install/helper.php`
- `install/database.php`
- `install/importSQL.php`
- `install/filePermissions.php`

Integrated managers:

- `library/FileManager/config/config.php`
- `library/FileManager/upload.php`
- `library/FileManager/dialog.php`
- `library/FileManager/include/utils.php`
- `library/FileManager/UploadHandler.php`
- `library/MediaManager/config/config.php`
- `library/MediaManager/upload.php`
- `library/MediaManager/dialog.php`
- `library/MediaManager/include/utils.php`
- `library/MediaManager/UploadHandler.php`

Regression tests and documentation:

- `tests/security/error-handling-http.py` (new)
- `tests/security/configuration-http.py`
- `tests/install/import-db.php`
- `tests/security/error-handling-review.md` (new)

## Executed verification

| Check | Result |
| --- | --- |
| `php -l` for all 28 changed PHP/PHTML files | Passed |
| Python syntax compilation for both changed/new HTTP regression scripts | Passed |
| All `tests/security/*.php` scripts | 24/24 passed, including 16 configuration cases |
| `python3 tests/security/error-handling-http.py` | 136 cases passed |
| `python3 tests/security/configuration-http.py` | 345 Apache HTTP cases passed |
| `python3 tests/security/attachment-http.py` | Passed: anonymous bypass prevented; authorized legacy/ID downloads, HEAD and public media preserved |
| `python3 tests/security/session-http.py` | 25 HTTP cases passed |
| `python3 tests/install/import-db.py` | Full installation, migrations and failure handling passed on isolated MariaDB 10.11.14; the runner's additional 66 UOM checks also passed |
| `git diff --check` | Passed |

The new HTTP test executes the real entry point, Zend dispatch, ErrorController/view, variant action methods, manager error paths and UploadHandler image fallback. Technical exceptions, logger failures, invalid configuration and invalid installer steps are injected through isolated fixtures. Production responses are checked for SQL diagnostics, internal paths, exception types, traces and request secrets; internal logs are checked for full technical context. Both unset/default Production and explicit Development are exercised.

## Compatibility and limits

- No production database was accessed or changed. The installation test uses its own disposable MariaDB instance under `/tmp` and disables network access to that database server.
- No live OAuth or FTP services were called; those failures were simulated. No manual browser session was performed. Existing JSON field names and validation responses were preserved and exercised over HTTP; `global.js` was inspected and left unchanged.
- Apache must continue to honor the existing `.htaccess` configuration. Direct internal installer step URLs now return HTTP 403; the supported flow remains `install/index.php` with POSTed step names.
- Full technical diagnostics intentionally remain in the configured PHP/server error log, with the optional application logger receiving the same exception text. Production responses do not include them.
- These changes cover catchable PHP `Throwable` failures and normal PHP warning logging. They do not introduce shutdown recovery for process termination or exhausted memory.
- No unresolved failures remain in the executed tests. No commits or pushes were made.

Suggested commit message: `unify safe error handling and preserve validation responses`
