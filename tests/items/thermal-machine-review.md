# D4B2 fixed-inlet parallel thermal coupling

Baseline: D4B1 commit `ca28a800` (`add synthetic thermal circuit coupling`). D4B2 adds a synthetic two-circuit calculation with explicitly supplied secondary flow allocation, section-specific exchanger performance and a checked mixed outlet. It preserves the existing configuration, profiles, maps and read-only authorization/snapshot path. All results have `eligible=false`; no real machine rating, automatic selection, Inquiry/Sales/PDF integration, productive import or deployment is added.

## Supported topology and physical boundary

The implemented case has two independent refrigerant circuits, each with its actual selected compressor instances. Each secondary group serves either one independent exchanger section or two isolated sections of one physical evaporator/water condenser. Parallel sections have one fixed common inlet temperature, explicitly fixed positive branch flows, separately specified effectiveness/domain data, individual outlets and a common mixed outlet. The whole device appears once in the physical inventory and once in its secondary group.

The tests cover separate evaporators/condensers, a common evaporator, a common water condenser and both common devices. Independent groups must use different existing hydraulic loops. A serial connection cannot be relabeled as two independent supplies. The boundary is at the actual exchanger's liquid inlet/outlet ports, not a guessed machine connection temperature after unmodeled pump/tank effects. No pump curve, automatic flow distribution, fan power, expansion law or oil/parallel-operation release is inferred.

Because inlet temperatures and branch flows are fixed inputs, the four Te/Tc unknowns form two conditional D4B1 systems with a block-diagonal Jacobian. Mixing determines the outlets after those roots; it does not feed an unknown recirculated temperature back into the inlets. The unique complete solution is therefore obtained by the existing bounded circle solvers followed by the two checked linear mixing equations and global energy balance. Adding an arbitrary outer temperature iteration or a guessed hydraulic split would add unsupported physics. Nonfixed inlets/recirculation are explicitly outside this registered topology.

## Backward-compatible contracts

No SQL change or new dataset type is necessary. Existing `technicaldataset` records retain `schema_version=1`, normalization version one and immutable revision/hash behavior.

`thermal_balance_model.data.contract_version=2` registers `synthetic_fixed_inlet_parallel_sections_v1`. It contains:

- `usage=synthetic_test_only`;
- `circuits`: actual circuit keys and exact `circuit_model_ref` references to D4B1 version-one thermal balance/search/numerics records;
- `secondary_groups`: physical instance, side, existing hydraulic loop and exact inlet/outlet ports, permitted medium, explicit total volume flow, branch circuit/section identities and branch flows;
- `topology_kind=single_section_boundary_v1` for one branch or `parallel_sections_common_inlet_v1` for two;
- `mixing_model_id=synthetic_constant_properties_parallel_v1`;
- `inactive_section_model_id=synthetic_zero_heat_pass_through_v1`;
- fixed units: degC, m3/h, kW, kW/K; declared common inlet/mixed-outlet/total-flow ranges;
- global heat and mixed-temperature tolerances, and missing engineering evidence.

A branch example is `{circuit_key: r2, section_key: r2, volume_flow_m3_h: 8.82}`. Its map is resolved through the existing instance -> exact article/profile -> component_spec -> thermal_map_ref chain, not through a second map manager. Section-specific effectiveness and limits remain inside the original version-one `heat_exchanger_map.sections` contract. The referenced constant density/cp and fixed branch flows define each constant heat-capacity rate; no real water/glycol properties are substituted.

Positive branch flows must add exactly to the explicitly specified total. TechnicalJson adds nonnegative canonical decimal digit strings without passing them through binary64; even an infinitesimal contradictory decimal total is rejected. Null flow operands may remain draft data but cause `secondary_split_underdetermined`, never inferred allocations. Missing branch definitions, duplicate sections/circuits, wrong users, missing/extra section-map coverage, wrong loops/ports, unsupported serial/counterflow identifiers and wrong model revisions are rejected or blocked.

One small existing configuration extension is necessary for a shared condenser: optional `circuit.condenser_section_key`. When present, the actual condenser ports are `<section>_in/out`; otherwise the established unsectioned `in/out` ports remain valid. Shared condensers, like evaporators, require isolated refrigerant sections and distinct circuit port groups. A sole legacy condenser map section can keep any explicit name, including a name other than `main`, without changing historical ports/profiles. Its group must match that sole actual section; no default section name is invented. Every physical map section must be allocated to a modeled branch, including explicitly inactive branches.

Whole-machine binding is checked on import and evaluation: circuit coverage, matching version-one child model, complete side assignments, one secondary group per device/loop, actual component roles/ports and profile/spec/map section agreement. Existing records with no new properties normalize identically. No historical draft or approved revision is rewritten; new data uses new identities/revisions.

## Interfaces and reuse

```php
$result = $technicalData->evaluateThermalMachine(
    $machineDatasetId,
    $exactRevision,
    $expectedPayloadSha256,
    $request,
    'synthetic_reference' // default technical mode does not emit synthetic solutions
);
```

For two circuits the closed request has `stage_key`, `operating_mode=cooling`, `secondary_boundaries` and optional active-instance `compressor_contexts`. Every group requires one boundary with exact medium, inlet temperature, total volume flow and fluid state. No branch flow is computed from compressor counts or guessed from the request's total.

Example for the fully shared fixture:

```json
{
  "stage_key": "full",
  "operating_mode": "cooling",
  "secondary_boundaries": [
    {"group_key": "load", "boundary": {"medium_key": "fixture:liquid", "inlet_degC": "10", "volume_flow_m3_h": "19.62", "fluid_state": "single_phase_liquid"}},
    {"group_key": "source", "boundary": {"medium_key": "fixture:liquid", "inlet_degC": "30", "volume_flow_m3_h": "11.064", "fluid_state": "single_phase_liquid"}}
  ]
}
```

A one-circuit call delegates to the original D4B1 contract (`evaporator`/`condenser` boundaries). The existing `evaluateThermalCircuit`, `evaluateCompressor` and complete D4A `MachineStage::evaluate` interfaces remain available with their original behavior. D4B1's old multicircuit blocker remains in that old entry point; D4B2 requires explicit new distribution data and the new machine entry point.

`MachineStage::evaluateCircuit` selects one actual circuit while validating the original complete machine record and exact stored stage. `ThermalCircuit::evaluateAssignedCircuit` uses the existing solver for its actual section pair and pinned child model. It never creates a replacement machine configuration, fills fictitious operating points for other circles or duplicates compressor math. Its result is scoped to a section pair and is not a complete shared-device result. `HeatExchangerMap::prepare/atPoint`, D3, D4A summation and the original D4B1 interval/overflow/uniqueness guards are reused.

`ThermalCircuit::mixParallel` reuses the existing outward binary64 arithmetic for mixing and balance checks; `sumThermalBalances` independently accumulates evaporator heat, condenser heat and compressor electrical input. Their array inputs are internal validated mathematical operands, not authorization APIs. The private TechnicalDataset methods share the same authenticated, owner-locked full graph snapshot and cached immutable compressor records.

## Numerical and inactive-state behavior

For a shared medium with explicitly constant properties:

`C_i = rho * V_i / 3600 * cp`, with cp in kJ/(kg K), hence C in kW/K.

`T_mix = T_in + sum(C_i * (T_out_i - T_in)) / sum(C_i)`.

The calculation uses this difference form to avoid unnecessary cancellation in absolute-temperature weighted sums. Each C is positive and comes from its actual validated map/branch flow. There is no unweighted averaging and no division of a device-wide rating by compressor count.

For each group, independently evaluated map heats are compared with both:

- secondary energy: `C_total * (T_in - T_mix)` for evaporation, or `C_total * (T_mix - T_in)` for condensation;
- the appropriate actual D3 compressor contribution (Q or Q+P under the pinned explicit synthetic hermetic boundary).

The global check is `sum(Qcond_maps) - sum(Qevap_maps) - sum(Pcomp)`. Flow residuals are exact decimal zero. Heat residuals and their outward enclosures must fit the version-two heat tolerance. The mixed-temperature error bound includes rounding and propagation of each certified circle temperature error through its effectiveness and positive capacity weight. It must fit the declared mixture tolerance. Tests also use stricter global tolerances than the per-circle solver can establish; such cases remain blocked, not partially published.

Version one of the new machine result is `synthetic_fixed_inlet_parallel_machine_v1`, scoped to synthetic parallel thermal balances. It retains the full original request, exact machine identity, model/child-model/map/profile revisions and hashes, physical inventory, compressor activation/results, circle Te/Tc and both map outputs, section references, branch/total flows, weights/outlets/mixed temperature, all residuals, numerical certificates, per-circle iteration traces and total evaluation budget.

The fixture uses D4B1 tolerances 1e-7 kW / 1e-8 K with at most 60 inner and 60 outer iterations per active circle. The maximum is `(outer_limit+2)*inner_limit` per circle, 7,440 stage evaluations for the two fixture circles; the existing contract limits each level to at most 80. There is no unbounded additional coupled search. Each circle must establish its original uniqueness certificate, envelope/map validity and root/error bounds; mixing then has a unique solution because its positive capacity sum is known. Unsupported/ambiguous/underdefined/nonconvergent states never create a global solution. Failed circle or junction diagnostics may contain bounded trial traces, but `solution` remains null until every branch/group/global check succeeds.

A wholly inactive circle has no Te/Tc point and no D3 evaluation. The required registered synthetic inactive-section model explicitly declares zero refrigerant heat exchange and unchanged secondary outlet=inlet at its prescribed flow. This is not a real standby-loss model or an assumed valve closure. Its section still participates in the common total flow and mixture, and inlet/outlet/property limits are still enforced. A partially active circle evaluates every remaining actual compressor separately. No source/profile/revision/withdrawal check is skipped for inactive hardware.

## Fully documented synthetic 4/2 reference

`thermalMachineBundle('both_shared')` generates the complete fixture using D4A's existing machine/instance/stage contract. The database test uses `fixture:parallel:db:both_shared:machine:2@1`, `parallel-model@1`, `circuit-model:r1@1`, `circuit-model:r2@1`, exact compressor A/B profiles and `evaporator-map@1` / `condenser-map@1`. The runtime `dependencies` dictionary records actual IDs/hashes, source evidence and lifecycle state; no production article/SKU mapping is assumed. In the disposable database the variant article is 1104 with base 1103.

There are four compressors, one evaporator with isolated r1/r2 refrigerant sections, one water condenser with isolated r1/r2 sections, one pump and one tank: **eight declared physical component instances**. Pump/auxiliary input is unknown and not estimated. The version-two groups are the existing `load` and `source` loops at their actual HX liquid ports; neither refrigerant circuit is connected to the other.

Each circle has actual curve A and B, in kW:

- A: `Q=10+0.1Te-0.01Tc`, `P=2+0.01Te`;
- B: `Q=20+0.2Te-0.02Tc`, `P=5+0.02Te`.

These come from the existing synthetic D3/D4A fixture, with documented 2900 rpm, 50 Hz, SH10/SC0, pure-saturation temperature definitions and 400 V/3-phase execution. The confirmed synthetic compressor rectangle is -10..10 degC Te / 30..60 degC Tc. Both child searches use Te0..9 / Tc31..60 within their exact section maps. They retain the explicitly idealized zero-external-loss hermetic boundary; no real loss model is claimed.

Every secondary medium is explicitly synthetic with rho=1000 kg/m3 and cp=4 kJ/(kg K), not an automatic water property.

| Side / circle | Inlet degC | Flow m3/h | C kW/K | Effectiveness | K=epsilon*C kW/K | Analytical outlet degC |
|---|---:|---:|---:|---:|---:|---:|
| Evaporator r1 | 10 | 10.8 | 12 | 0.5 | 6 | 7.5 |
| Evaporator r2 | 10 | 8.82 | 9.8 | 0.4 | 3.92 | 7 |
| Condenser r1 | 30 | 6.687 | 7.43 | 0.25 | 1.8575 | 35 |
| Condenser r2 | 30 | 4.377 | 4.863333333333333 | 0.5 | 2.431666666666667 | 37.5 |

The full-stage sum per circle is `Q=30+0.3Te-0.03Tc`, `P=7+0.03Te`. Independent equations are:

- r1: `6.3Te-0.03Tc=30`; `-0.33Te+1.8875Tc=92.725`;
- r2: `4.22Te-0.03Tc=9.2`; `-0.33Te+(2.431666666666667+0.03)Tc=109.95`.

| Circle | Analytical Te/Tc degC | Compressor Q kW | Compressor P kW | Evaporator heat kW | Condenser heat kW |
|---|---|---:|---:|---:|---:|
| r1 | 5 / 50 | 30 | 7.15 | 30 | 37.15 |
| r2 | 2.5 / 45 | 29.4 | 7.075 | 29.4 | 36.475 |
| Total | Separate points | 59.4 | 14.225 | 59.4 | 73.625 |

Shared evaporator flow: `10.8+8.82=19.62 m3/h`, Ctotal=21.8 kW/K.

`T_mix_load=(12*7.5+9.8*7)/21.8=7.275229357798166 degC`.

`21.8*(10-T_mix_load)=59.4 kW`.

Shared condenser flow: `6.687+4.377=11.064 m3/h`, Ctotal=12.293333333333333 kW/K.

`T_mix_source=(7.43*35+(4.377/0.9)*37.5)/(7.43+4.377/0.9)=35.989018438177872 degC`.

`Ctotal*(T_mix_source-30)=73.625 kW`; `73.625-59.4-14.225=0`.

Observed binary64 output on the tested PHP runtime:

- r1 Te/Tc = 4.999999999534339 / 50.000000000116415 degC;
- r2 Te/Tc = 2.4999999976716936 / 44.9999999984866 degC;
- mixed load/source outlets = 7.275229357251334 / 35.98901843789611 degC;
- propagated mixed-temperature bounds approximately 5.474e-10 / 3.417e-10 K;
- HX heat sums = 59.40000001192092 / 73.62499999653616 kW;
- secondary-versus-section heat residuals = -7.11e-15 / -2.84e-14 kW;
- compressor-versus-section heat residuals = -1.272e-8 / 2.584e-9 kW;
- global energy residual = -1.5300944511409398e-8 kW, with its complete enclosure inside 1e-7 kW;
- 2,170 stage evaluations against the fixed 7,440 budget, followed by one checked mixing pass.

These are synthetic HX/compressor balances. Useful-machine capacity and machine efficiency stay unavailable. The shared-evaporator-only and shared-condenser-only fixtures have nine devices; separate devices have ten. Equal compressor counts do not imply equal circle duty or flow.

The stored synthetic stage sequence is r1.A; r1.A+r2.A; r1.A+r2.A+r1.B; all four. Independent 2x2 reference equations are used for every active circle in every stage. Switching a compressor or changing a curve changes the common secondary heat/mixed outlet while all prescribed branch flows remain unchanged. A parked circle contributes its declared pass-through outlet, not a fictitious Te/Tc or a copied active-circle load.

## Tests, authorization and history

Commands actually run:

```sh
php tests/items/thermal-machine.php
php tests/items/thermal-circuit.php
php tests/items/machine-stage.php
php tests/items/compressor-polynomial.php
python3 tests/items/technical-data.py
php tests/security/shop-inquiry-calculation.php
php tests/security/inquiry-selection.php
```

- D4B2 pure: **190 assertions** covering independent/shared evaporator/shared condenser/both-shared cases, independent analytic 4/2 values, exact flow sums, capacity-weighted mixtures, both energy balances and global conservation, stage/curve changes, zero-load parked sections, physical counts, reproducibility, legacy non-main condenser sections, incomplete/contradictory splits, unallocated/missing maps, wrong assignments/ports/units/topology, limits/extrapolation, ambiguity/nonconvergence, stricter junction heat/temperature requirements and no partial/eligible result.
- D4B2 stored: **211 assertions** covering the same four physical arrangements, pinned packet/profile/section/circuit bindings, non-main section compatibility, missing/excess/incorrect assignments, graph/hash/header/revision manipulation, source/artifact history and no implicit latest, altered articles, foreign dependencies, authorized parent sharing, forbidden tenant/site/anonymous access, view/import rights separation, synthetic approval denial, inactive-profile/map revocation, incomplete/real/nonconvergent data and read-only technical/business snapshots.
- D4B1: **124 pure / 121 stored assertions**. D4A: **78 pure / 225 stored assertions**. D3: **58 pure / 143 stored assertions**. D2B: **54 matrix cases / 163 assertions**, plus **41 initial validation checks** and existing CSV/history/lifecycle/concurrency coverage.
- Inquiry: **16 calculation checks**, **37 dependency/default/configuration checks** and **60 cumulative form/approval checks**.
- PHP syntax checks are run for every changed/new PHP file; git whitespace checks pass.

All database work uses the existing disposable MariaDB runner with its dedicated /tmp datadir/socket, `--no-defaults --skip-networking` and exclusively synthetic data. Fresh installation, actual tagged 1.1.7 upgrade and partial/repeated targeted migration tests run unchanged. Synthetic packets are unreleasable; direct corruption/revocation setup is confined to that disposable negative fixture, then restored. Existing production data, prices and finalized documents are not accessed or changed by tests.

The new service method uses the unchanged authenticated active-account checks, persisted selected tenant/Items owner, legitimate parent sharing, item/technical view rights and additional item edit/import rights for synthetic mode. It shares D4A/D4B1's same full graph verification, advisory owner lock and transaction. All profiles/maps/child models, including entirely inactive dependencies, are checked before thermal evaluation. Withdrawal or changed identity blocks every branch. Every evaluation obtains today's lifecycle evidence; previously returned inputs/results and stored historical revisions remain unchanged. No new permissions or SaaS entitlements are granted.

No browser/HTTP/PDF, production database, independent real OEM/machine acceptance or optional private manufacturer-coefficient tests are claimed. No corresponding public integration is introduced.

## Changed files and remaining boundaries

- `TechnicalSchema.php`: version-two distribution contract, whole-machine/map/section binding and optional sectioned condenser ports/isolation.
- `TechnicalJson.php`: exact nonnegative decimal flow addition.
- `TechnicalDataset.php`: private `evaluateThermalMachine`, shared authorization/snapshot wrapper and full reference/binding checks.
- `MachineStage.php`: select actual circuit contributions without constructing another configuration.
- `ThermalCircuit.php`: reuse the existing per-circle solver and numerical guards; add checked mixing/global balances and root-error propagation.
- `HeatExchangerMap.php`: explicitly registered parked-section pass-through behavior.
- New `ThermalMachine.php`: fixed-inlet two-circle orchestration and all-or-nothing result assembly.
- New `fixtures/thermal-machine.php`, `thermal-machine.php`, `thermal-machine-db.php`, included by the existing `technical-data.php` runner; this report documents the contract and reference.

D4B2 supports two circuits, liquid-side parallel section distributions and genuinely separate liquid loops. Common air/fan distributions, serial/mixed/counterflow arrangements, variable or unknown splits, recirculated unknown inlets and unallocated whole-device maps remain blocked. Three/four-circle thermal solves are explicitly unsupported; the existing 8/4 structural/D4A compressor calculation continues to pass. The one-circle 2/1 thermal regression uses the original D4B1 solver.

Real OEM operation needs actual authorized component/profile mappings and sectional exchanger maps, proven split/flow/property domains, compressor loss/balance evidence, pressure losses, expansion/control/oil/parallel-stage approvals, auxiliaries and independent machine reference points. Constant synthetic cp/rho/effectiveness or ideal zero standby heat cannot be transferred to real water/glycol/machines. Pump/hydraulic calculation, heat recovery/heating, automatic selection and public/business integration remain separate work.

No SQL change, migration or manual database follow-up is necessary. No push, merge, productive import or deployment is performed. No following phase is started.
