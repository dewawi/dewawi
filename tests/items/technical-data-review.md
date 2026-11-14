# D2B technical data storage and private import

Implemented against local baseline `36a223890a4273c199032040f4b9f25383060dc0`, following the accepted D2A contract. D2B stores and validates technical data; it contains no polynomial evaluator, machine solver, public selection method, quote automation or new UI.

## Storage and entry points

- `technicaldataset`: exact immutable package revision, owner, source hashes/locators, schema version, canonical payload/hash, predecessor and controlled approval/withdrawal metadata.
- `itemtechnicalprofile`: exact existing item/variant identity and options, exact package revision, immutable binding/hash, predecessor and the same lifecycle.
- DDL is appended only to `install/config/update-1.1.7-1.1.8.sql` and identically to `structure.sql`. It seeds no technical or manufacturer data.
- `Items_Service_TechnicalDataset`: `validateJson`, `importJson`, `getById`, `technicalStatus`, `approve`, `withdraw`.
- `Items_Service_TechnicalImport`: explicit CSV adapters and `referencePoints` for the separate `dewawi-reference-points` format.
- `Items_Service_TechnicalSchema`: five closed version-one package types: compressor_polynomial, machine_configuration, machine_family, component_spec, reference_points.
- `Items_Service_TechnicalJson`: duplicate-aware JSON reader and exact decimal normalization. No new dependency.
- `tools/technical-import.php`: CLI only; it loads no application DB configuration and defaults to validation/dry-run. No upload/controller route is added.

All inserts and lifecycle writes go through the service. The shared Technicalrevision DbTable base rejects raw `insert`, `update`, `delete`; inherited create/copy/status/default/lock operations and Row save/delete consequently cannot mutate technical revisions. All raw fetches/finds add the authorized owner constraint. Service reads verify the stored payload hash. `getById` is a historical read; technical consumers must additionally use `technicalStatus`, which checks the current dependency lifecycle and live item/option identity under an owner lock and transaction.

The service's deliberate adapter-level writes are restricted to validated technical tables. Arbitrary privileged SQL or arbitrary application PHP can bypass application rules; this is not a claim of database-administrator tamper resistance. Existing generic Entity behavior is unchanged.

## Owner and permissions

The actor must match the authenticated identity and a persisted active user. Switchable clients are checked through the existing Client model, anchored to the persisted account. Internal item sharing uses the selected client's authorized Items owner; an owner override must be the selected owner or its persisted parent. There is no client 0/global fallback. Mapped items, manufacturers, options, option groups and all dependencies must belong to this one owner and be undeleted.

The service uses the existing permission JSON and `DEEC_Permission::hasPermission`; it creates no permission table, seed grant or admin bypass. An operator needs existing `items.item.view`; mutations also require `items.item.edit`. Explicit entries in the existing `permission.items` JSON select the new private operations:

```json
{
  "item": ["view", "edit"],
  "technicaldataset": ["view", "import", "approve", "withdraw"]
}
```

Each technical operation checks its corresponding permission. These rights are not a Selection SaaS entitlement. No Selection feature or public access is activated. Existing permissions are not modified by installation or import. Grant only the required operation to a named authorized actor through the existing permission administration process.

The optional site read context uses SiteContext's owner, never an internal Parent Items fallback. It still requires an authenticated authorized private operator. D2B does not expose these raw records on a site. Any D7 public adapter must use existing catalog visibility/features and return an explicitly redacted result.

## Import and precision

Common envelope:

```json
{
  "format": "dewawi-technical-import",
  "format_version": 1,
  "datasets": [],
  "profiles": []
}
```

An actual import requires at least one package or profile. Each dataset carries dataset_key, positive revision, dataset_type, schema_version=1, requested_status=draft, nullable manufacturer_name/manufacturer_revision/data_owner, source and a type-specific data object. Optional blockers, predecessor, additional sources and an explicit manufacturer_ref are validated. Sources require filename, SHA-256 and locator or sheet. Manufacturer reference text is limited to the matching SQL column length. Root provenance notes/template markers are retained in each immutable package. An explicit production_import_allowed=false remains an approval blocker. Source bytes are never downloaded or executed; hash provenance does not prove rights or engineering correctness.

Profiles require profile_key, revision, item_ref, dataset_ref, role and binding. Binding contains model, manufacturer ordering code, electrical execution, exact included/extra option symbols, source locator and scope key; variants require their exact base item. Optional manufacturer/source references are checked. Item mapping is explicit, not a first SKU lookup:

```json
{
  "items": {"fixture:component": 1000},
  "options": {},
  "groups": {},
  "manufacturers": {}
}
```

The number above illustrates the mapping format with a synthetic test identity; obtain actual IDs from an authorized nonproduction context. Mapping IDs are positive integers, owner/deletion checked. No import creates an item, variant, option, price or commercial BOM.

The JSON reader keeps numeric tokens as decimal text before any float conversion. Normalization v1 sorts object keys, retains array order and uses exact decimal strings in scientific form (e.g. 123.4500 becomes `1.2345e2`, negative zero becomes `0`). Integer identity/revision fields remain integers. Decimal digit/exponent manipulation is string based; a separate finite-range check rejects overflow/underflow. Long coefficient digits survive unchanged in numeric value. Hashes cover the exact stored canonical UTF-8 bytes. No coefficient is rounded to a PHP float for storage or hashing. Binary floating-point evaluation is outside D2B.

Rejected: duplicate JSON keys including escaped aliases, malformed JSON/UTF-8, NUL/unsupported utf8mb3 characters, unknown fields/types/schema/equation IDs/units, nonfinite numbers, malformed mappings, missing/dangling/cyclic dependencies, mismatched profile model/role/refrigerant/ports and invalid option combinations. Missing engineering facts may remain explicit nulls in drafts; malformed identities cannot.

Limits: 4 MiB input, 100 datasets, JSON depth 32, 1 MiB resolved payload, 5,000 aggregate package/list entries. These resource budgets are unrelated to machine output. Draft corrections always create new revisions. An owner advisory lock serializes concurrent imports/lifecycle operations; a transaction covers mapping/dependency validation, conflict preflight and all inserts. Identical revisions are idempotent. Conflicting bytes fail, including in dry-run. A database error after the first insert rolls back the entire bundle.

## Machine scope

The registered v1 circuit topology is `parallel_compressors_single_evaporator_single_condenser_v1`. It validates each compressor assignment and directional port, suction/discharge/liquid/expanded groups, expansion device, evaporator section and condenser. Independent refrigerant circuits cannot share a port. Shared evaporators require explicitly isolated sections and exact circuit users. Fan/coils, pump/tank and other physical instance identities cannot be duplicated or silently reused in incompatible paths.

2/1, 4/2 and 8/4 use the same schema and share the same exact compressor profile revision. Machine family/chassis, existing machine/base/variant, component profiles and complete option signatures are resolved before persistence. Source-controlled stage declarations do not release a machine.

D2B supports explicit serial hydraulic passages with one inlet/outlet passage per component and named machine boundaries; disconnected subcycles, duplicate ports and direction errors are rejected. Branching/bypass/internal multistream hydraulic solvers are not implemented. They require an explicit later topology contract. Water condensers use water ports and a separate source-water path; an air-temperature approximation is invalid for them. The offset model is stored, never evaluated. Unsupported heat-recovery/heating modes stay unsupported.

Exchanger/pump/fan/fluid/control map schemas belong to later phases. Non-null references masquerading as these not-yet-supported map types are rejected; keep missing data null plus blockers. `component_spec` is a closed union for the supported roles, not an arbitrary property bag. Expansion component/profile identity and ports are checked when supplied; unknown expansion facts remain draft-only.

## CSV and reference adapters

`application/modules/items/configs/technical-import-columns.json` contains the D2A header and required-field contract, with no manufacturer data. CSV uses UTF-8, semicolon, double quote escaping and dot decimal syntax. Header-only files, duplicate row identities, malformed quoting, invalid booleans and coerced revisions are rejected.

CSV context is explicit:

```json
{
  "format": "dewawi-technical-csv-context",
  "format_version": 1,
  "bundle": {"format": "dewawi-technical-import", "format_version": 1, "datasets": []},
  "sources": {},
  "component_datasets": {}
}
```

`sources` maps source_ref to the closed source object. `component_datasets` maps component symbols to exact dataset_key/revision objects. Optional context data_owner is preserved across packages; a conflicting existing owner is rejected. Supply the relevant full base packages/profiles/machine topology in bundle when a flat CSV cannot express them. Flat views cannot silently replace topology or contradict exact bindings.

| D2A template | Behavior |
|---|---|
| 01 components | Explicit component→package mapping produces a profile; manufacturer/electrical/operating conditions are checked. |
| 02 polynomials | Quantity rows aggregate to one condition-specific draft; ten exact coefficients/unit per quantity; contradictory conditions rejected. |
| 03 family/chassis | Rows aggregate to a family revision with unique chassis. |
| 04 circuit/compressor | Checks the flat assignment view against the complete machine context; no invented ports or expansion topology. |
| 05 heat exchangers | Requires exact component/profile context; preserves all isolated sections and validates section identity; map data remains absent until supported. |
| 06 pump/hydraulics | Requires complete loop context; validates gross/net, fluid/loss references, external requirements and usable tank volume when given. |
| 07 options/accessories | Checks the exact machine/equipment view, base/variant/group/accessory symbols and included flag. |
| 08 reference points | Produces explicitly typed reference packages with units/tolerances; mathematical points confer no approval. |

The separate `dewawi-reference-points` JSON is accepted only by `TechnicalImport::referencePoints` or CLI `--references KEY --revision N`. The common importer does not guess its format. It keeps the referenced dataset revision, original source, units and tolerances. Polynomial reference points must target a polynomial and contain exact Te/Tc inputs, with an existing quantity vector.

Examples (paths/IDs are supplied by the private operator):

```sh
php tools/technical-import.php --file /path/to/bundle.json --validate
php tools/technical-import.php --file /path/to/reference-points.json --references local:points --revision 1 --validate
php tools/technical-import.php --file /path/to/rows.csv --csv 02-polynomdatensaetze --context /path/to/context.json --validate
```

For an explicitly authorized isolated test database, supply `--socket`, `--database`, `--db-user`, `--user`, `--client` and optionally `--mapping`. The password comes from DEWAWI_TECHNICAL_DB_PASSWORD. The default DB operation is a no-write dry-run; only `--write` persists drafts. `--validate` opens no database at all. No production connection or real-data DB import was used during this task.

## Lifecycle and remaining gates

Only draft→approved→withdrawn is supported. Payload/binding bytes, original source and prior approval evidence remain immutable. Withdrawal is terminal; another release requires a new revision. Expected-hash and state checks prevent stale/concurrent transitions. Approval proof freezes the actor/time, exact payload hash, all source hashes, scope/rights/engineering references and direct dependencies' approval identities. Profile evidence must match its exact binding scope. Overlapping approved profiles in the same item/role/condition context are rejected.

A valid import never approves data. Approval requires explicit server-side permission, a non-null data owner, no unresolved blockers, complete type-specific facts, source-hash proof and approved dependencies. In D2B, complete synthetic families, compressor definitions and tank specifications can exercise positive lifecycle tests. Machine physics, performance maps, unknown options and heat/heating modes cannot obtain a machine release. Mathematical reference datasets are not independent engineering evidence.

The four real Danfoss drafts were offline validated through the CLI, including the explicit reference adapter. Their corrected 2900 rpm / 50 Hz, standard SH10/SC0 and selected SH8/SC2 conditions are accepted; RGT remains null. No coefficient/source file was committed, no private data was imported into any DB and no DSH090→ZCE114 link was created. Missing operating envelopes, temperature basis, exact electrical ordering-code mapping, rights/approval evidence and machine/HX/pump data continue to block real technical use.

D3's software foundation is ready for a separately assigned implementation. Real operating-point approval is not ready until the missing manufacturer facts and independent reference cases are supplied. D3 was not started.

## Executed verification

- `python3 tests/items/technical-data.py`: complete fresh schema and actual tagged 1.1.7 schema upgraded with the entire active migration; exact new CREATE TABLE parity, preservation of prices, and targeted repeated/partial DDL. The runner requires local Git tag 1.1.7 and creates its own disposable MariaDB, socket and datadir with `--no-defaults --skip-networking`.
- 54 D2B matrix cases (D2-010…D2-063), synthetic fixtures only. Coverage includes all five types, all eight CSV adapters, source-condition conflicts, exact long decimals, duplicate escaped JSON keys, budgets, owner/Parent/site scopes, FK failures, generic DbTable/Row writes, account/rights denial, explicit approvals/terminal withdrawal, shared 2/1–4/2–8/4 machines, current option identities and preserved historical payloads.
- Two independent PHP workers are held at the owner advisory lock before a concurrent import and approval test; identical imports converge to one row and exactly one approval succeeds. An injected MariaDB insert failure after a preceding insert proves transaction rollback.
- Existing installer suite: full installation/migrations/failure handling and 66 UOM cases. Its synthetic upgrade fixture now includes the existing client/item parent tables required by the new FKs.
- `php tests/security/shop-inquiry-calculation.php`: 16 cases; `php tests/security/inquiry-selection.php`: 37 form / 60 cumulative checks.
- `python3 tests/security/shop-inquiry-quote.py`: 57 catalog, 95 variant and 764 cumulative quote/transaction/privacy/PDF/replay cases; HTTP/AJAX/back navigation and Chromium for both product groups at 390/1280 px passed.
- PHP syntax on all new/changed PHP files and `git diff --check`.

D2-028 is covered by synthetic equivalents of the documented condition conflict; the real local files received offline schema validation only. This is not a claim of a real Danfoss DB import or independent OEM verification. No D3–D7 physics, manufacturer machine acceptance, real mail delivery or production tests were run.

For a previously applied/partially applied 1.1.7→1.1.8 installation, inspect the actual schema and apply only the two missing CREATE statements from the existing active migration, in dataset-then-profile order, under the target environment's explicit authorization. Do not rerun unrelated ALTER statements or invent another official migration. An existing incompatible partial technical table requires explicit inspection/correction rather than trusting CREATE IF NOT EXISTS. No such manual action was performed here.
