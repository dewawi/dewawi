# Versioned SQL catalog

The development catalog now contains `1.1.8-1.1.9/001-shop-shipping.sql`.
Application release remains 1.1.8. This feature migration does not publish 1.1.9
or initialize migration history on a fresh development snapshot; see the operator
workflow below.

Use `FROM-TO/NNN-description.sql`, starting with
`1.1.8-1.1.9/001-description.sql`. Each transition starts at `001`; numbers are
consecutive, three digits, at most `999`. Descriptions contain lowercase ASCII
letters/digits separated by hyphens. The full relative path is the immutable ID.

The ordered `transitions` array in `registry.json` explicitly declares the release
chain. The first `from` is `1.1.8`; every subsequent `from` equals the previous
`to`; each `to` must increase numerically. Every transition has at least one
migration. Files are declared in numeric order, without gaps. No implicit
directory sorting, date sorting or filesystem order is used.

Example of the registry format (documentation only):

```json
{
    "transitions": [
        {
            "from": "1.1.8",
            "to": "1.1.9",
            "migrations": [
                {"file": "001-example.sql", "depends": []},
                {"file": "002-example.sql", "depends": ["1.1.8-1.1.9/001-example.sql"]}
            ]
        }
    ]
}
```

Every migration implicitly depends on its immediate predecessor, including across
transitions; the first depends on `baseline/1.1.8`. Additional `depends` entries
must name earlier full migration IDs. Forward, missing and cyclic dependencies
are rejected. Empty transitions, missing/extra SQL files and sequence gaps stop
the runner. Already executed files and registry entries remain permanently;
their bytes, identifiers and dependency definitions are immutable. Append a new
migration to correct an earlier one after its state has been resolved.

Use ordinary semicolon-separated CREATE TABLE/INDEX, ALTER TABLE, DROP TABLE/INDEX,
RENAME TABLE, INSERT INTO, UPDATE and DELETE FROM statements. The installer SQL
splitter is reused. Session changes, transaction control, stored programs,
DELIMITER, executable comments and writes to the history table are unsupported.
SQL files are reviewed repository code, not untrusted user input; this prefix
validation is not a general SQL sandbox. Keep statements scoped to the selected
installation and preserve tenant/data/document invariants.

See [operator and release workflow](../../../tools/migrations/README.md).
