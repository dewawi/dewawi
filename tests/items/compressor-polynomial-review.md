# D3 compressor polynomial evaluation

D3 adds a database-independent mathematical core and one controlled read-only method to the existing technical data service. It adds no schema, import behavior, public endpoint, UI, Inquiry/Sales/PDF integration, machine solver or automatic approval. Baseline: local D2B HEAD `1c05423ba89f481eafe972c95edbfb0abbe78cfc`.

## Core contract

`Items_Service_CompressorPolynomial::evaluate(stdClass $record, stdClass $point): array` validates the existing version-one compressor_polynomial record through TechnicalSchema. Its inputs are the same exact decimal strings/integers used by D2B. Load external JSON through TechnicalJson/D2B rather than decoding coefficient numbers into floats first.

```php
$result = (new Items_Service_CompressorPolynomial())->evaluate(
    $validatedRecord,
    (object)['Te_degC' => '5', 'Tc_degC' => '50', 'temperature_unit' => 'degC']
);
```

Only `te_tc_cubic_c0_c9_v1` is supported. The ordered basis is:

`[1, Te, Tc, Te², Te*Tc, Tc², Te³, Te²*Tc, Te*Tc², Tc³]`.

Each quantity has exactly ten coefficients and its own source unit. No expression is executed or dynamically registered. Powers/products use PHP binary64, and terms are accumulated in fixed C0…C9 order using Neumaier compensation. Calculation version: `compressor_polynomial_binary64_neumaier_v1`. Original coefficient strings and package bytes remain unchanged; conversion to binary64 occurs only at evaluation. No intermediate result is rounded for presentation. Both canonical input strings and the numeric input values are returned. Reproducibility means the same data, algorithm version and floating-point/runtime output configuration; this is not arbitrary-precision decimal arithmetic.

Results expose all five supported quantities: cooling_capacity, power_input, current, refrigerant_mass_flow and discharge_temperature. Missing optional vectors return status `not_available` with null value/unit; a missing mass-flow vector is never a numeric zero. Q/P are also explicitly normalized to kW; COP uses those normalized values, is dimensionless and scoped to the compressor. No fan, pump, heat-exchanger or total-machine quantity is calculated.

Nonpositive Q/P/current/mass flow, temperatures at/below absolute zero, numerical overflow/underflow and an invalid COP produce invalid/unavailable results and reasons. Finite diagnostic polynomial outputs may still be present with `invalid_result`; they must not be consumed as technically usable values. Nonfinite results are replaced by null before serialization. Wrong units/equations, malformed coefficients and invalid input temperatures are rejected. Te/Tc require explicit degC, values above absolute zero and Te<Tc.

The core never assesses an approval, sets `technical_status=not_assessed`, and always returns `eligible=false`. A mathematically calculated value is not engineering approval.

## Stored revision evaluation

The existing service adds:

```php
$result = $data->evaluateCompressor(
    $datasetId,
    $exactRevision,
    $expectedPayloadSha256,
    $request,
    'technical' // default; alternatively 'mathematical_reference'
);
```

The request has a required point object (shown above) and, for technical use, a required context object with exactly these fields:

- manufacturer_name and manufacturer_revision;
- model and refrigerant;
- conditions: the existing D2B speed_rpm, frequency_Hz, superheat_K, return_gas_temperature_degC, subcooling_K and suction_condition_mode;
- temperature_basis: Te/Tc definitions from the package;
- electrical: voltage_V, phases and execution_code from the package.

Context is normalized through the existing compressor schema and compared with the selected record. Equivalent exact decimals such as 50 and 50.000 match. A different refrigerant, model, electrical execution, frequency, speed, SH/SC, temperature basis or manufacturer revision blocks calculation; no value is adapted or substituted. ID, revision and expected hash are all mandatory. There is no implicit latest, SKU matching or merging of condition sets.

Both modes reuse D2B's active-user authentication, switchable-client/Items-owner checks and explicit `items.item.view` plus `items.technicaldataset.view`. Mathematical reference additionally requires the existing private import rights (`items.item.edit`, `items.technicaldataset.import`), which cannot replace the read right. No new grants or feature entitlements are created. Private access remains unavailable in site context. Failure messages for missing/foreign/stale identities reveal no package identity, coefficients or source paths.

The service uses D2B's same owner advisory lock and transaction, reads the exact owner/id/revision row with FOR UPDATE, verifies the payload bytes/hash, closed schema and consistency of stored key/revision/type/schema/lifecycle headers, then reuses D2B readiness checks for dependencies, lifecycle, current article/option identity and engineering gaps. For approved data, approval evidence is also checked against the payload and source hashes, with its recorded actor/time. This yields a consistent snapshot with respect to D2B approval/withdrawal operations. It never inserts, updates, imports, approves or creates a revision during calculation.

## Evaluation modes and status

| Mode / evidence | Status and behavior |
|---|---|
| Authorized mathematical_reference, valid draft coefficients, engineering facts missing | `review_required`; calculation allowed with actual missing-data blockers plus `mathematical_reference_only`. Stored draft is never relabeled approved. |
| Mathematical reference at a point conclusively outside a known valid operating polygon | `review_required`; no stored-service extrapolation and no calculation. |
| Technical use with a draft, missing/contradictory context, missing facts or missing release evidence | `review_required`; calculation is null. |
| Technical use, complete approved revision, matching context and confirmed point inside a valid region | `valid_compressor_point`; available quantity results and compressor COP are returned. |
| Complete approved technical context, point conclusively outside the confirmed polygon | `unsuitable`; calculation is null. |
| Withdrawn revision | No calculation in either mode; `revision_withdrawn` and D2B blockers remain visible. |
| Numerical/nonphysical output from an otherwise approved point | `review_required`; diagnostic calculation is marked invalid and no usable COP is invented. |

Every result has `eligible=false`, including valid compressor points. `valid_compressor_point` is restricted to the available quantities at one exact component operating point and does not release an entire machine, electrical installation, operating stage or offer. Missing optional quantities remain explicitly unavailable even for an otherwise valid point; D4 must require all quantities its own model needs.

The result preserves package ID/key/revision, stored hash and normalization version, source hashes, actual lifecycle/approval/withdrawal evidence, equation/calculation versions, source model/condition set, documented conditions, electrical execution, temperature basis, original inputs, operating-region status, violations, blockers, available quantities/units and COP. No coefficient vector, private source path or historical business document is changed or emitted through a public response.

## Operating region and precision

Only the existing `te_tc_polygon` in degC is supported. D3 checks simple-polygon validity, self-intersection, orientation-independent containment and boundary membership; concave regions work. It rejects corrupt/degenerate or numerically ambiguous regions instead of treating a rectangular bounding box as the full domain. No manufacturer range is inferred from example points or the old FORM_CALC raster.

Known axis bounds are compared using exact canonical decimal strings. A value infinitesimally beyond 10 is not rounded onto the 10-degree boundary. Polygon arithmetic uses power-of-two scaling; a vertex or in-range point that loses its supplied decimal value on binary64 round-trip produces a precision blocker. Near-collinear floating-point ambiguity also produces review_required. No epsilon expands an approved region. Exact representable boundaries are inclusive. Numeric rendering follows the current PHP serialization configuration; stricter configurations may conservatively cause additional precision blockers.

The pure mathematical core can inspect coefficients independently of engineering limits, which enables isolated term/reference tests. The stored service applies the known-domain restriction in both modes and otherwise allows unknown-domain drafts only in the explicit mathematical reference mode. No technical extrapolation is enabled.

## Verification

Commands actually run:

```sh
php tests/items/compressor-polynomial.php
python3 tests/items/technical-data.py
php tests/items/compressor-polynomial-private.php /tmp/dewawi-selection-d2
php tests/security/shop-inquiry-calculation.php
php tests/security/inquiry-selection.php
```

The existing D2B runner starts its own temporary MariaDB with `--no-defaults --skip-networking` and a dedicated /tmp socket/datadir. It still covers all 54 D2B cases, including full fresh installation and real tagged 1.1.7 upgrade, before including the additional D3 database checks. Repository fixtures remain synthetic. Direct database corruption in negative tests is confined to that disposable server and restored afterwards.

- Pure tests isolate C0…C9 (including distinct C7/C8 values), cancellation compensation, W/kW/COP, all quantities, missing mass flow, zero/negative output, NaN/Infinity, unknown equations, coefficient damage, overflow/underflow, unit/temperature rejection, convex/concave/clockwise polygons, exact boundaries and precision blockers.
- Stored tests cover draft/reference/approved/withdrawn states, full synthetic engineering releases, expected ID/revision/hash, immutable reads and unchanged business state, approval-proof tampering, context/set mismatches, standard/selected units, missing optional mass flow, nonphysical output/overflow, malformed regions, read-only users, forbidden reference access, foreign owners, authorized parent sharing and exact old/new revisions.
- D2-064…D2-067 are covered. D2-068's gate is tested with explicit synthetic engineering evidence; independent real OEM operating-point acceptance remains unavailable and was not claimed.
- Private comparison reads four D2A packages and 18 quantity references outside Git, checks exact set/revision/source binding and applies `max(absolute_tolerance, relative_tolerance*abs(expected))` from the point files. Q/P/COP additionally use the documented D2A 1e-8 kW / 5e-6 COP checks. These are derived mathematical references, not independent manufacturer acceptance.

Observed private results at Te5/Tc50:

| Model / set | Q kW | P kW | Compressor COP |
|---|---:|---:|---:|
| DSH090-4 standard | 20.047507269375 | 6.5743637096050005 | 3.0493456332642555 |
| DSH090-4 selected | 20.564562097622623 | 6.5743637096050005 | 3.12799276188177 |
| DSH105-4 standard | 22.939509932875 | 7.25921554845 | 3.1600535594749055 |
| DSH105-4 selected | 23.48517606990532 | 7.259215548450001 | 3.23522230648185 |

All four comparisons and all 18 available quantity references passed. On the tested runtime, the maximum absolute deviation from the supplied derived quantity values was zero for Q, P, current, mass flow and discharge temperature. Against the task’s printed 12-decimal table, the maximum display differences are 3.77e-13 kW for Q, 1e-15 kW for P and 2.555e-13 for COP, all below the D2A thresholds. The extra displayed digits reflect binary64 representation; they do not increase manufacturer measurement accuracy. Standard sets correctly have no mass-flow result. Private coefficients/original files were neither committed nor imported into a database. The optional private script requires explicitly supplied sources outside the repository and fails if they are unavailable; it never silently turns a missing reference into PASS.

PHP syntax and git diff checks are also run. Existing Inquiry calculation and form/approval tests pass; no shared Inquiry/Variant/Calculate code is changed. No new HTTP, browser, PDF, real-mail, independent OEM acceptance, production or D4-D6 physics tests were run for D3.

## Remaining data and handover

Danfoss operating envelopes, confirmed saturation-temperature definitions, exact electrical ordering-code/application mapping, independent OEM cases and final rights/engineering evidence are still missing. RGT remains unknown where the source is empty; known 2900 rpm / 50 Hz, standard SH10/SC0 and selected SH8/SC2 are preserved. No DSH090→ZCE114 assignment is made.

D4 has a tested per-compressor mathematical core, exact revision/context contract and read-only technical gate to build on. It still needs released real component/machine topologies, heat-exchanger/control data and independent machine reference cases. D3 does not implement or approve multi-compressor summation, machine COP/EER, hydraulics, heat recovery, heating or automatic selection. D4 was not started.

There is no D3 migration or manual SQL step. Existing D2B permission assignments apply; no production database or user grant was modified, and no deployment/push was performed.
