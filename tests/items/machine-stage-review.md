# D4A machine, circuit and operating-stage evaluation

Baseline: local D3 commit `8ad1c768360681ded38e56fa0790be5675d5ee01`. D4A adds a database-independent stage aggregator and a private read-only method on the existing technical data service. It has no HTTP route, UI, Inquiry/Sales/PDF integration, new schema, automatic stage selection or machine release. Fixtures and expected machine results are synthetic.

## Interfaces and reuse

```php
$result = $technicalData->evaluateMachineStage(
    $machineDatasetId,
    $exactMachineRevision,
    $expectedPayloadSha256,
    $request,
    'mathematical_reference' // default is 'technical'
);
```

The closed request contract is:

```json
{
  "stage_key": "full",
  "operating_mode": "cooling",
  "circuit_points": [
    {"circuit_key": "r1", "point": {"Te_degC": "5", "Tc_degC": "50", "temperature_unit": "degC"}},
    {"circuit_key": "r2", "point": {"Te_degC": "0", "Tc_degC": "40", "temperature_unit": "degC"}}
  ]
}
```

Every configured circuit, including an inactive circuit, requires exactly one explicit point. Duplicate, missing and unknown circuit keys are rejected. Points reuse D3 validation; no fluid/air temperature or approximation field is converted to Te/Tc. Only stored cooling topology is supported. Heating and all heat-recovery modes are rejected.

Optional `compressor_contexts` is an object keyed by active instance_key. Every supplied value uses D3's complete context contract (manufacturer_name, manufacturer_revision, model, refrigerant, conditions, temperature_basis, electrical). Unknown/inactive keys and incomplete context objects are rejected. Supplied contexts must match their own pinned curves; source conditions are never scaled or substituted. Mathematical reference may omit contexts and evaluates only the documented source conditions.

D2B `TechnicalSchema::dataset`, profile/machine binding checks, reference-type checks, readiness, authentication, permissions and the owner transaction/advisory lock are reused. D3's stored evaluation body is shared privately inside the same transaction, avoiding a nested transaction; its public `evaluateCompressor` signature and behavior remain intact. Source/payload approval checking is shared between datasets and profiles. `CompressorPolynomial` itself is unchanged.

`Items_Service_MachineStage::evaluate(record, request, compressorCallback)` is the database-free calculation interface. The callback is an internal seam: it must supply D3 results from the service's authorized, verified graph. It is not an alternative authorization API. Pure tests inject the existing D3 core; database tests exercise actual stored D3 evaluation.

## Revision, binding and security gates

The private service requires D2B's authenticated active account, legitimate selected tenant and Items owner, `items.item.view` and `items.technicaldataset.view`. Mathematical reference additionally requires `items.item.edit` and `items.technicaldataset.import`. Parent/child Items sharing stays available only through existing permitted owner relationships; site context is rejected. No new grant or SaaS entitlement is created.

Within the existing owner lock and transaction, D4A loads the root with owner, ID, revision and expected SHA-256, then recursively locks and verifies every stored dependency, including inactive compressor profiles, shared components and predecessor revisions. All reference IDs, keys, revisions, table names, owner, payload hashes, closed schemas and lifecycle headers must agree. Required references are compared with the persisted pinned reference set; omitted or extra references cannot be selected implicitly. No latest revision is inferred.

Profile SQL article/dataset/role headers must agree with their payload. Existing profile binding logic rechecks model, ordering code, electrical execution and variants; machine binding logic rechecks family/chassis, article/base/options (including selected equipment article aliases restored from pinned option identities), component profile/role/refrigerant/ports and isolated evaporator sections. Temporary import-resolution state is restored even on rejection. An indirect compressor `component_spec.specification.polynomial_ref` is supported, with exact model/refrigerant/electrical agreement checked throughout the graph. A null polynomial reference returns missing performance, not estimated values.

Current article/option identities and variant relations are checked through D2B. Changed identities block mathematical sums (some binding inconsistencies throw immediately). Any withdrawn dependency blocks the entire stage, even when its compressor is inactive. Approved datasets and profiles require matching payload/source evidence and scope where applicable; recorded reference releases must match pinned identities and the dependencies' approval actor/time/hash. Errors reveal no private package identity or coefficients. Every evaluation is read-only; repeat runs use current approval/withdrawal state, and previously returned inputs/results remain unchanged.

## Version-one result contract

- `result_version=1`, `calculation_version=machine_stage_compressor_sums_v1`, plus D3 calculation version.
- `evaluation_mode`, `scope=selected_stage_compressor_contributions_only`, status, `eligible=false`, reasons and violations.
- `machine`: exact dataset identity/hash and machine article/base identity evidence.
- Selected operating mode, stage key and declared stage release status; a copied `original_request` and normalized per-circuit points.
- `components`: one entry per physical instance, `physical_component_count` and shared-component declarations. The count covers the declared component inventory; unconfirmed expansion placeholders do not count as confirmed devices. Reusing a profile does not merge distinct compressor instances; referencing a shared evaporator from several circuits does not duplicate that physical device.
- `compressors`: each configured compressor's instance/circuit/profile identity, active flag and original D3 evaluation; inactive evaluation is null. D3 contains each available Q, P, current, mass flow, discharge temperature and compressor COP, with source conditions and region checks.
- `circuits`: explicit member/active keys, point, evaporator-section and condenser references, missing evidence and compressor sums. Individual results are linked through the compressor dictionary, avoiding duplicate physical records.
- `stage_compressor_sums`: mathematical contributions over all active instances; no partial numeric total if a required contribution is missing or incompatible.
- `dependencies`: complete dataset/profile IDs, revisions, hashes, schema/normalization versions, current lifecycle/approval/withdrawal metadata, source records, pinned references and article/option snapshots. Profiles include their effective dataset sources and any binding source.
- Explicit unavailable useful-machine cooling and auxiliary power, plus assumptions and missing engineering evidence. No machine EER/COP is produced.

Q and P are normalized to kW by D3. Positive finite results are summed in configured order with binary64 Neumaier compensation; aggregate compressor COP is sum(Q)/sum(P), never an average of individual COPs. Complete source refrigerant, saturation-temperature definitions and matching documented operating conditions are required for compatible sums; unknown or differing conditions conservatively block aggregation. Each model is evaluated separately, so sums do not assume equal per-compressor output. Circuit Te/Tc may differ when adding independent compressor energy contributions across circuits.

Current and discharge temperature are always individual results, with null `not_aggregated` aggregate fields. Mass flow is normalized to kg/s and summed only when every value is valid and the complete refrigerant/temperature/condition/point basis is identical. Different circuit points leave the stage mass-flow sum unavailable while compatible per-circuit sums remain available. Missing optional quantities are not zero. Nonphysical D3 results, unavailable required results, incompatible basis or aggregate numeric overflow cannot become usable totals. An inactive circuit has unavailable contribution fields with `no_active_compressors`; it does not imply measured zero standby consumption.

Every machine result remains `review_required`, or `unsuitable` for definite operating-region/context violations, and always `eligible=false`. Missing technical context is a review blocker. Technical mode suppresses mathematical contributions because D4A has no released thermal machine model and caller-supplied example temperatures are reference inputs only. Only explicit authorized mathematical reference can calculate draft contributions. Marking a stage/mode approved in configuration does not bypass D2B's machine release gate.

Missing parallel-operation release, oil management/stage limits, common-pressure evidence, stage/circuit controls, heat-exchanger performance, expansion-system evidence, thermal coupling and auxiliary power are reported separately. Mathematical parallel-compressor sums do not approve physical parallel operation. No Q+P condenser rating, estimated auxiliaries, useful cooling, machine efficiency, fluid/hydraulic model or stage optimization is inferred.

## Synthetic acceptance results and traceable example

Fixture curve A in W: Q = 10000 + 100 Te - 10 Tc; P = 2000 + 10 Te.
Fixture curve B in W: Q = 20000 + 200 Te - 20 Tc; P = 5000 + 20 Te.
The remaining cubic coefficients are zero. Source conditions are 2900 rpm, 50 Hz, SH10/SC0, pure-saturation Te/Tc, fixture-fluid, 400 V/3 phases. The source operating polygon is -10 <= Te <= 10 and 30 <= Tc <= 60. This is test data, not OEM engineering evidence.

At Te5/Tc50 in every circuit, with A and B per circuit:

| Compressors / circuits | Sum Q kW | Sum P kW | Aggregate compressor COP |
|---|---:|---:|---:|
| 1 / 1 (A only) | 10 | 2.05 | 4.878048780488 |
| 2 / 1 | 30 | 7.15 | 4.195804195804 |
| 4 / 2 | 60 | 14.3 | 4.195804195804 |
| 8 / 4 | 120 | 28.6 | 4.195804195804 |

Expectations are independently specified in the tests and compared without presentation rounding.

The stored two-circuit example is `fixture:d4:2:2:machine:2@1`, machine article 1007 with base 1006. Its source/profiles are pinned to revision 1. A instances use `fixture:d4:2:2:profile:compressor@1` and its exact `fixture:d4:2:2:compressor@1`; B instances use the equivalent `compressor-b` identities. Test database IDs/hashes are obtained from D2B import, checked in the result and retained in `dependencies`; no fixed production identity is assumed.

With the request shown above:

| Instance | Circuit | Te/Tc °C | Q kW | P kW |
|---|---|---|---:|---:|
| r1.compressor1 (A) | r1 | 5/50 | 10 | 2.05 |
| r1.compressor2 (B) | r1 | 5/50 | 20 | 5.1 |
| r2.compressor1 (A) | r2 | 0/40 | 9.6 | 2 |
| r2.compressor2 (B) | r2 | 0/40 | 19.2 | 5 |

r1 totals 30/7.15 kW; r2 totals 28.8/7 kW. Full stage totals **58.8 kW compressor Q, 14.15 kW compressor P, aggregate compressor COP 4.155477031802**. These are compressor contributions, not useful-machine cooling or machine efficiency. Stage mass flow stays null because circuit points differ. One shared evaporator has isolated r1/r2 sections; all four compressors, two condensers, evaporator, pump, tank and fan yield ten physical components.

The explicitly stored synthetic sequence is stage1 = r1.A; stage2 = r1.A + r2.A; stage3 adds r1.B; stage4 adds r2.B. The corresponding Q/P contributions at the distinct points are 10/2.05, 19.6/4.05, 39.6/9.15 and 58.8/14.15 kW. This is not a released real control sequence. Current, discharge temperatures and per-compressor quantities remain traceable in each original D3 result; all missing engineering evidence remains visible.

## Verification

Commands run:

```sh
php tests/items/machine-stage.php
php tests/items/compressor-polynomial.php
python3 tests/items/technical-data.py
php tests/items/compressor-polynomial-private.php /tmp/dewawi-selection-d2
php tests/security/shop-inquiry-calculation.php
php tests/security/inquiry-selection.php
```

The database runner creates a fresh temporary MariaDB with `--no-defaults --skip-networking` and dedicated /tmp socket/datadir. It tests fresh install, actual tagged 1.1.7 upgrade and repeated/partial targeted migration before D2B, D3 and D4A. All corruption/withdrawal cases operate only on synthetic records in that disposable server. Calculations are compared against snapshots of technical and existing business tables to confirm no writes.

- D4A pure checks: 78 assertions, including 1/1, 2/1, 4/2, 8/4, different curves/points/stages, independent Q/P/COP expectations, shared physical counts, deterministic/copied inputs, optional quantities, incompatible/unknown basis, kg/h and kg/s, overflow, topology damage and rejected modes/inputs.
- D4A stored checks: 225 assertions, including exact graph/profile/article binding, indirect/missing/mismatched polynomials, missing/wrong profile revisions, incompatible refrigerants/conditions, explicit context and outside-limit rejection, no implicit latest, unauthorized access, allowed parent sharing, forged approvals, corrupt hashes/headers, omitted pinned refs, foreign dependency references, changed articles/variants, selected equipment bindings, declared stage/mode flags, forged approval-reference chains and active/inactive withdrawals.
- D3 pure checks: 58 assertions; stored D3 checks: 143 assertions. D2B: 54 matrix cases with 163 assertions, plus 41 initial validation checks.
- Existing Inquiry regressions: 16 calculation checks and 37 dependency/configuration checks with 60 cumulative approval checks.
- Optional private D3 comparison: four condition sets, 18 supplied mathematical quantity references matched. Private coefficients were neither committed nor imported; this is not independent OEM acceptance.

Changed PHP files pass syntax checks; git whitespace checks pass. No browser/HTTP, PDF, production-database or independent real-machine/OEM tests were run; D4A adds no corresponding integration. The acceptance boundary is mathematical contributions plus blocking engineering gates.

## Remaining data, risks and D4B handover

D4B requires authorized exact real machine/component/profile mappings, OEM operating envelopes and saturation definitions, independent manufacturer reference cases, released parallel/oil/control/stage limits, actual evaporator/condenser/expansion performance and pressure-loss maps, fluid/air boundary definitions and measured auxiliary input. Those models and schemas must be explicitly specified before implementing thermally coupled Te/Tc calculation. No fixed temperature offset can replace them. Conservative condition compatibility may leave sums unavailable until their physical basis is documented.

There is no D4A migration or manual SQL follow-up, no new permission assignment and no historical-run table. Results can be retained by an authorized caller as a versioned evidence snapshot; D4A itself stores nothing. Earlier D2B deployment/migration follow-up remains separate. No push, merge or deployment is performed. D4B is not started.
