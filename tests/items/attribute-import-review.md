# Attribute CSV import regression checks

Run `python3 tests/items/attribute-import-db.py`. The runner initializes its own MariaDB data directory and Unix socket under `/tmp/dewawi-attribute-import-db-*`, disables networking, and removes that test instance afterwards. It never reads application database credentials or connects to an existing database. Socket creation must be permitted by the execution environment.

The PHP suite loads the actual `item`, `itematr`, `itematrset` and `category` definitions from `install/config/structure.sql`, and exercises the real importer and public Inquiry calculation/model code. It covers:

- Initial FORM_CALC import, identical reimport and changed replacement.
- Invalid/missing/duplicate headers, missing fields, malformed quoting, duplicate calculation entries, invalid numbers, oversized fields and unsupported characters. Invalid input must cause zero database write attempts.
- Existing parentless attributes, attached FORM_CALC attributes and another tenant's records staying unchanged.
- Ordinary attributes, groups, placeholders, discounts and mixed CSV files, including normal-only legacy files without a value column.
- Exact preservation of calculation titles, description/device mappings and comma/point decimal text; BOM, both field separators, both quote styles and multiline descriptions.
- Database-triggered failures during FORM_CALC, ordinary attribute and group insertion, with full restoration of both tables.
- Rejection of nontransactional tables before replacement and preservation of the general Position parent-ID check.
- Public device suggestions using imported data and rejection of unavailable public categories.
- Two independent import processes for existing data and for a first import. The database/tenant advisory lock is acquired before the transaction starts, including when there are no existing attribute rows.
- Public reads while replacement is uncommitted: no dirty deletion or partial insertion becomes visible. A repeatable-read reader transaction retains its original generation after replacement commits.
- The import form's single-CSV validation, rejection without database changes and escaped error/success rendering.

## Compatibility and operating limits

`FORM_CALC` replacement is limited to the importing client's rows with `sku=FORM_CALC` and `parentid=0`. Ordinary attributes are replaced only for items resolved in that same client context. Ownership, hierarchy, IDs and audit fields supplied by CSV are ignored. The importer does not manufacture calculation keys or reference capacities, require a fixed dataset size, or change Inquiry calculation logic.

The schema already declares the affected tables as InnoDB. No migration is required. An installation with nontransactional `itematr` or `itematrset` must have its storage engines corrected through a separately authorized database operation before this import can run. The importer checks the live engines and refuses replacement otherwise.

Imports of the same database/client serialize; a lock wait exceeding ten seconds fails without replacement and can be retried. Ordinary InnoDB lock waits/deadlocks, including between clients, also cause the complete import transaction to roll back. Other editing paths do not participate in this import-specific advisory lock.

Autocommit readers get a snapshot per SELECT. The unchanged Inquiry controller uses separate target and reference queries, so a commit between those queries can expose two different committed generations to one calculation. The suite demonstrates this boundary as well as consistent multi-query reads within a repeatable-read transaction. Adding an Inquiry reader transaction would be a separate behavior change and is not included here.

The previously mentioned 2,421-row export was not present in the workspace, so it was not used. The upload presentation is rendered in PHP tests; a browser/multipart HTTP upload was not exercised. Existing Inquiry and permission regression suites are run separately.
