# D5B – Hydraulische Parallelverteilung und gemeinsamer Pumpenbetrieb

## Ergebnis

D5B berechnet den eindeutigen gemeinsamen Betriebspunkt einer vorhandenen Pumpe und zweier paralleler Flüssigkeitssektionen bei einer ausdrücklich vorgegebenen einheitlichen Fluidtemperatur. Gemeinsame Verluste und Zweigverluste werden getrennt bilanziert. Der neue unabhängige `HydraulicDistribution`-Rechner verwendet die D5A-Kerne für Stoffwerte, Frostschutz, Konzentrationsbasis, Durchflussumrechnung, Druckkennfelder, laminare Rohre und Pumpenkennlinien.

Alle Maschinenbewertungen bleiben `review_required`, `eligible=false` und `fully_coupled=false`. Es gibt keine automatische Pumpen- oder Maschinenfreigabe, keine Gesamtsystemleistung/EER, keine Angebots-/Shopanbindung und keine produktiven Eingriffe. Die thermische Rückkopplung wird nicht als gelöst behauptet.

## Bestehende Struktur und versionierter Vertrag

Die bestehende Maschinenkonfiguration modelliert die äußeren hydraulischen Loops als Serienpfade. D4B2 beschreibt die parallelen Flüssigkeitszweige als Sektionen eines gemeinsamen Wärmeübertragers mit gemeinsamen Flüssigkeitsanschlüssen. D5B bindet genau diese vorhandenen Komponenten-/Profil-/Sektionseinträge. Es wird kein zweites Maschinen- oder allgemeines Knotenmodell aufgebaut.

`hydraulic_path` erhält einen geschlossenen `contract_version=2`:

- `topology_kind=fixed_speed_two_parallel_sections_v1`.
- Vorhandene `loop_key`, `component_instances`, `pump_instance`, `external_loss_ref`, `pipe_segments`, `connections` und Druckbasis aus D5A.
- `parallel_instance`: eine tatsächlich vorhandene Verdampfer- oder Wasserkondensatorinstanz dieses Loops.
- Genau zwei `parallel_sections` mit tatsächlichem `section_key` und belegten `flow_min_m3_h`/`flow_max_m3_h`.
- `temperature_model=prescribed_uniform_temperature_v1`.
- `numerics`: Druck-, Volumenstrom- und Massenstromtoleranz sowie maximale Iterationszahl.

Komponentenabdeckung, zugeordnete Pumpe, gemeinsame Anschlüsse, alle Verbindungslasten und Sektionen müssen vollständig zu den vorhandenen Maschinen-/Komponentenreferenzen passen. Weitere gemeinsame Komponenten dürfen im unterstützten Modell nur einsektionale Tanks oder Flüssigkeitswärmeübertrager mit validierten Kennfeldern sein. Fehlende Teile, unbekannte Ventile, zusätzliche Parallelgruppen oder allgemeine Netze werden gesperrt. Jede Verbindung benötigt weiterhin einen expliziten Verlustnachweis. Nichtleere `missing_evidence` der verwendeten hydraulischen Betriebsdaten sperren D5B; optionale unbekannte elektrische Daten werden dagegen durch nullable Kennfeldwerte dargestellt.

D5A-Verträge der Version 1 und ihre bisherigen Ergebnisse bleiben erhalten. Die alte Auswertung vorgegebener Ströme nimmt keine v2-Pfade an: `distribution_path_requires_operating_point_solver` verhindert, dass neue v2-Betriebsrestriktionen durch einen alten Aufruf ignoriert werden. Die D4B2-Schnittstelle mit expliziten Teilströmen bleibt unverändert verfügbar.

Die bestehenden `fluid_properties`, `pressure_loss_map` und `pump_curve` werden unverändert wiederverwendet. Auch Rohrsegmente verwenden ausschließlich das bestehende registrierte laminare D5A-Modell. Es werden keine zusätzlichen Tabellen, Spalten, SQL-Migrationen, Frameworks oder Abhängigkeiten benötigt.

## Verfahren und Eindeutigkeit

Die Druckverlustkennfelder sind bei fester Temperatur und Konzentration stückweise linear über dem Volumenstrom. Der Rechner schneidet ihre gültigen Bereiche mit Pumpen-, Komponenten-, Loop- und Zweiggrenzen. Für Rohre wird zusätzlich die vorhandene D5A-Grenze `Re<2300` berücksichtigt; der obere Suchwert bleibt mit einem relativen Abstand von `1e-12` innerhalb dieser Grenze. Es gibt keine Extrapolation.

Vor der Suche werden sämtliche im betrachteten Bereich liegenden Stützstellen geprüft:

1. Beide Zweigverluste müssen streng steigen. Flache oder fallende Zweigkennlinien werden gesperrt.
2. Gemeinsame Kennfeldverluste müssen monoton nicht fallend sein. Die registrierten laminaren Rohrverluste sind bei festen Stoffwerten ebenfalls monoton steigend.
3. Der verfügbare Pumpendruck muss monoton nicht steigend sein. Es erfolgt keine Drehzahl-, Frequenz-, Fluid- oder Viskositätskorrektur außerhalb des belegten Pumpenvertrags.

Für den gemeinsamen Zweigdruck `z` lassen sich die beiden streng steigenden Tabellenkennlinien eindeutig invertieren. Damit gilt `Q_total(z)=Q_1(z)+Q_2(z)`. Die Pumpenbilanz

`R(z)=dp_pump(Q_total(z))-dp_shared(Q_total(z))-z`

ist streng fallend; ihre Steigung ist im registrierten Modell höchstens `-1`. Die zulässigen Druckbereiche beider Zweige und die zulässigen Gesamtstrombereiche müssen sich überlappen. Ohne eingeschlossenen Vorzeichenwechsel bleibt der Betriebspunkt gesperrt. Die Suche erfolgt deterministisch durch Intervallhalbierung; ein fehlgeschlagener oder mehrdeutiger Fall liefert weder `solution` noch `thermal_inputs`.

Nach der Kennlinienprüfung werden höchstens `max_iterations+2` Betriebspunktproben ausgewertet. Die zulässige maximale Iterationszahl ist 100. Jede Probe verwendet erneut die ursprünglichen D5A-Druckkennfelder und den D5A-Pumpenrechner. Druck-, Inversions-, Volumen- und Massenbilanz werden geprüft. Zusätzlich muss das verbliebene Intervall aller Teilströme und des Gesamtstroms die Volumenstromtoleranz einhalten; ein zufällig kleiner nominaler Druckrest genügt nicht.

Die synthetische Referenz verwendet 80 maximale Iterationen und folgende Toleranzen:

| Größe | Toleranz |
| --- | ---: |
| Pumpen-, Zweig- und Inversionsdruckrest | 1e-6 Pa, einschließlich numerischer Rundungszulage |
| Breite des verbleibenden Durchflussintervalls | 1e-9 m³/h |
| Volumenbilanz | 1e-9 m³/h |
| Massenbilanz | 1e-10 kg/s |

Der Vertrag begrenzt frei gewählte Toleranzen: mindestens `1e-12`, höchstens `1e-3 Pa`, `1e-6 m³/h` bzw. `1e-6 kg/s`. Die Rechnung verwendet Binary64; sie ist eine Prüfung des registrierten interpolierten mathematischen Modells und keine formale Intervallzertifizierung einer realen Anlage. Ein nicht erreichbarer numerischer Genauigkeitsanspruch führt zu einer Sperre.

## Analytischer Referenzfall

Alle Daten sind ausdrücklich erfunden, synthetisch gekennzeichnet und frei commitbar. `Q` wird in m³/h, Druck in Pa eingesetzt:

- Zweig 1: `dp_1=(8000/6)*Q_1`.
- Zweig 2: `dp_2=2000*Q_2`.
- Gemeinsamer Tankpfad: `dp_tank=150*Q_total`.
- Belegter externer Pfad: `dp_external=50*Q_total`.
- Pumpe: `dp_pump=20000-1000*Q_total`, feste 2900 min⁻¹/50 Hz, Modus `fixed`.

Aus gleichem Zweigdruck folgt `Q_1=1,5*Q_2`, damit `dp_parallel=800*Q_total`. Gemeinsam mit `dp_shared=200*Q_total` ergibt die Pumpenbilanz analytisch `Q_total=10`, `Q_1=6`, `Q_2=4`, `dp_parallel=8000`, `dp_shared=2000` und `dp_pump=10000`.

Der Rechenkern enthält keine dieser Sollzahlen. Die Fixture erzeugt vollständige Tabellen, aus denen der Solver den Punkt bestimmt.

| Tatsächliches numerisches Ergebnis | Wert |
| --- | ---: |
| Gesamtstrom | 10,000000000076275 m³/h |
| Zweig 1 | 6,000000000045765 m³/h |
| Zweig 2 | 4,00000000003051 m³/h |
| Gemeinsamer Zweigdruck | 8000,00000006102 Pa |
| Gemeinsame Serienverluste | 2000,0000000152559 Pa |
| Pumpendruck | 9999,999999923724 Pa |
| Pumpenbilanzrest | −1,525513653177768e-7 Pa |
| Zweigdruckrest | 9,094947017729282e-13 Pa |
| Inversionsrest | 9,094947017729282e-13 Pa |
| Volumenbilanzrest | 0 m³/h |
| Massenbilanzrest | 4,440892098500626e-16 kg/s |
| Verbleibende Durchflussintervallbreite | 5,456968210637569e-10 m³/h |
| Iterationen / Betriebspunktproben | 36 / 38, bei maximal 82 Proben |
| Hydraulische Leistung | 27,77777777777778 W |
| Elektrische Leistung bei ausdrücklich belegtem Gesamtwirkungsgrad 0,5 | 55,55555555555556 W |

Zusätzliche unabhängige analytische Fälle prüfen gleiche Zweige, höheren Zweigwiderstand, eine stärkere Pumpe, temperaturabhängige Viskosität, veränderte Dichte bei einer Förderhöhenkennlinie, einen gemeinsamen laminaren Rohrabschnitt sowie korrekte Pa-/kPa-/bar-Umrechnung. Nullverluste der synthetischen Verbindungskanten sind ausdrücklich dokumentierte Testdaten und keine automatisch eingeführten Standardwerte. Wirkungsgrade werden nicht ergänzt, wenn sie fehlen.

## Service und Ergebnisvertrag

Der private gespeicherte Zugang lautet:

```php
$service->evaluateHydraulicDistribution($machineId, $revision, $sha256, $request, $mode);
```

Der Request enthält ausschließlich `loop_key`, explizite Fluidzusammensetzung, `temperature_degC`, Drehzahl, Frequenz und Betriebsmodus. Unbekannte Temperaturen, automatische Konzentrationen und vom Client gelieferte Ergebnisströme werden nicht angenommen.

`technical` ist der Standard und verlangt die vorhandene vollständige technische Freigabekette. Die beauftragten synthetischen Fälle verwenden `synthetic_reference` mit bestehendem Importrecht zusätzlich zum privaten Leserecht. Maschinenfreigabe bleibt in beiden Fällen ausgeschlossen.

Der Ergebnisvertrag (`result_version=1`, `fixed_speed_two_parallel_sections_bisection_v1`) enthält Maschinen-/Konfigurationsrevision, Loop und Pfadrevision, Pumpeninstanz/-profil/-kennfeld, Fluidrevision, Komponenten-/Kennfeldreferenzen, Bedingungen, Annahmen, zulässige Suchbereiche, Teil-/Gesamtvolumen- und Massenströme, gemeinsame und einzelne Drücke, Pumpenleistung soweit belegt, Bilanzreste, Konvergenzverlauf und fehlende Nachweise. Der gespeicherte Service ergänzt die bestehende vollständige Provenienz mit exakten IDs, Revisionen, Payload-/Quellen-/Freigabeprüfsummen und technischen Sperrgründen.

## Kontrollierte D4B2-Übergabe

`ThermalMachine` akzeptiert optional `hydraulic_conditions` als Liste solcher Bedingungen. Die Betriebspunkte werden innerhalb derselben vollständig gebundenen Maschinenrevision neu berechnet. Es gibt keinen Request, der einen vom Client behaupteten Betriebspunkt als gültig übernimmt.

Die ausgewählte Gruppe muss denselben Loop, dieselbe Instanz, dieselben Sektionen und dieselbe Eintrittstemperatur besitzen. Für die neue Übergabe tragen Wärmeübertragermedien zusätzlich die optionalen, versionierten Felder `fluid_ref` und `fluid_composition`. Sie müssen exakt zur Fluidrevision und zur Massen-/Volumenbasis, Konzentration und gegebenenfalls Volumen-Bezugstemperatur des hydraulischen Punkts passen. Fehlende oder widersprüchliche Identität wird gesperrt, auch bei zufällig gleichen numerischen Stoffwerten. Die Referenzen benutzen dieselbe D2B-Owner-/Hash-/Freigabekette.

Der aktuelle D4B2-Rechner bleibt ein synthetischer Rechner mit konstantem Wärmekapazitätsstrom. Deshalb verlangt die Übergabe belegte kompatible konstante Dichte/cp auf der verwendbaren Fluidtabelle. Variable oder widersprüchliche Daten werden nicht in diesen thermischen Ansatz gedrückt. Die berechneten Teilströme ersetzen nur die lokalen Recheneingaben; gespeicherte Maschinen-, Wärmeübertrager- und Modellrevisionen werden nicht verändert. Ein optional angegebener Gesamtstrom darf dem hydraulischen Punkt nicht widersprechen; bei `null` wird der berechnete Gesamtstrom verwendet. Die Summe der übergebenen Dezimalströme wird exakt mit dem vorhandenen D2B-Dezimaladdierer gebildet.

Die Rechnung kontrolliert anschließend die D5-Fluid-/Frostgrenzen für die thermischen Sektionenaustritte und Mischung. Sie trägt ausdrücklich `coupling_status=one_way_hydraulic_inputs_thermal_feedback_unverified`, `fully_coupled=false` und den Hinweis, dass die hydraulische Berechnung bei diesen thermisch ermittelten Austrittstemperaturen nicht neu gekoppelt gelöst wurde. Es werden keine fehlenden Pfadtemperaturen angenommen. Eine gleichzeitige Verwendung des alten D5A-Prüfmodus `fluid_hydraulics` und der neuen berechneten Eingaben wird gesperrt.

Tests zeigen sowohl die tatsächliche Verwendung eines neu berechneten 6/4-Splits im thermischen Stufenfall als auch die vollständige 4/2-Referenz mit unabhängig hydraulisch wiedergewonnenen 10,8/8,82 m³/h und 59,4 kW Verdampferwärme. Ohne neue Option bleiben die bisherigen expliziten D4B2-Ergebnisse reproduzierbar.

## Sicherheit, Revisionen und Tests

Die vorhandene D2B-Snapshot-/Transaktionsprüfung wird vollständig wiederverwendet: authentifizierter Benutzer, private Items-Rechte, berechtigte Eltern-/Kindfreigabe, tatsächliche Artikel-/Profilidentitäten, ownergebundene vollständige Referenzen und unveränderte Datenbankzustände. Indirekte und inaktive relevante Abhängigkeiten werden auch für den ausgewählten hydraulischen Teilloop geprüft. Synthetische Quellen können nicht versehentlich technisch freigegeben werden. Unvollständige oder manipulierte Freigabebelege sperren die Auswertung.

Die D5A-`supersedes`-Korrektur bleibt unverändert und ist erneut abgedeckt: Ein unabhängiger vollständiger Nachfolger verändert weder seinen Vorgänger noch dessen bereits berechenbaren historischen Punkt. Historie ist keine zusätzliche Leistungsabhängigkeit. Ein tatsächlich referenziertes entzogenes Kennfeld sperrt dagegen die alte Maschinenrevision; ein unabhängig vollständiges neues Kennfeld bleibt eigenständig nutzbar. Manipulierte Historien-Fremdschlüssel werden zurückgewiesen. Entzugszustände der nicht freigabefähigen synthetischen Fixtures werden gezielt als Fehlerzustände injiziert; der reguläre berechtigte Freigabe-/Entzugszyklus wird durch die bestehenden D2B-Regressionen geprüft.

Ausgeführte Prüfungen:

- `php tests/items/hydraulic-distribution.php`: 75 mathematische, Domänen-, Einheiten-, Monotonie-, Fehler-, Grenzwert- und Übergabe-Assertions erfolgreich.
- `python3 tests/items/technical-data.py`: 103 neue gespeicherte D5B-Assertions erfolgreich; außerdem 41 Initialprüfungen, 54 D2B-Matrixfälle mit 163 Assertions sowie gespeicherte D3/D4A/D4B1/D4B2/D5A-Regressionen mit 143/225/121/211/127 Assertions erfolgreich. Ausschließlich temporäre synthetische MariaDB mit eigenem Socket und `--skip-networking`.
- Reine D3/D4A/D4B1/D4B2/D5A-Regressionen: 58/78/124/190/93 Assertions erfolgreich.
- Inquiry: `shop-inquiry-calculation.php` 16 Prüfungen und `inquiry-selection.php` 37 + 60 kumulative Prüfungen erfolgreich.
- PHP-Syntax aller zehn geänderten/neuen PHP-Dateien und `git diff --check` erfolgreich.

Keine neuen SQL-Änderungen oder zusätzlichen Migrationstests. Der bestehende gekoppelte D2B-Runner enthält seine bisherigen Fresh-/Upgrade-Kontrollen. Keine Browserprüfung, da keine UI-/HTTP-Änderung vorliegt. Keine OEM-Datenprüfung, produktive Datenbankprüfung oder Behauptung realer Freigabefähigkeit.

## Geänderte Dateien und Grenzen

- `application/modules/items/services/HydraulicDistribution.php`: neuer unabhängiger Solver und Ergebnisvertrag.
- `application/modules/items/services/FluidHydraulicSchema.php`: v2-Pfadvertrag und begrenzte Numerik.
- `application/modules/items/services/FluidHydraulics.php`: Bindung an bestehende Sektionen und klare v1/v2-Aufrufgrenze.
- `application/modules/items/services/TechnicalSchema.php`: optionale exakte Fluidbindung der Wärmeübertragermedien.
- `application/modules/items/services/TechnicalDataset.php`: privater gespeicherter Zugang, gemeinsame Quellengates und Revisionsbindung.
- `application/modules/items/services/ThermalMachine.php`: kontrollierte einseitige Übergabe berechneter Teilströme.
- `tests/items/fixtures/hydraulic-distribution.php`, `hydraulic-distribution.php`, `hydraulic-distribution-db.php`, `technical-data.php`: synthetische Referenzen und Regressionen.
- `tests/items/hydraulic-distribution-review.md`: dieser Bericht.

Unterstützt sind genau eine feste Pumpe, zwei vorhandene Sektionen eines gemeinsamen Flüssigkeitswärmeübertragers und vollständig belegte gemeinsame Serienverluste bei einer einheitlichen vorgegebenen Temperatur. Beliebige äußere Zweignetze, mehrfache parallele Wärmeübertragergruppen, unbekannte oder unterschiedliche Pfadtemperaturen, Ventilnetze/-regelung, Mehrpumpenbetrieb, Drehzahloptimierung, nichtmonotone Kennlinien und nicht belegte turbulente Rohrströmung bleiben gesperrt. Es wird keine vollständige thermisch-hydraulische Maschinenoptimierung implementiert.

Reale Anwendung benötigt belegte Fluidprodukte und Zusammensetzungen, gültige Druckkennfelder zwischen den tatsächlichen gemeinsamen Anschlussknoten, vollständig berücksichtigte Sammler-/Verbindungsverluste, passende Druck-/Energiegrenzen und freigegebene Pumpenbedingungen. Kavitations-/NPSH-, Saugdruck-, absolute Festigkeits- und weitere Maschinenbetriebsnachweise sind durch diesen relativen Druckverlustrechner nicht erbracht. Nicht separat belegbare Sektionen- oder Sammlerwirkungen dürfen nicht als unabhängige Zweigkennlinie eingesetzt werden. Variable thermische Stoffwerte und die Rückwirkung der Austrittstemperaturen benötigen einen späteren ausdrücklich beauftragten gekoppelten Ansatz.

Keine Datenmigration und keine manuellen Datenbanknacharbeiten erforderlich. Neue v2-Pfade oder ergänzte Fluidbindungen müssen als neue Revisionen importiert werden; bestehende Revisionen werden nicht nachträglich verändert. Kein Push, Merge oder Deployment. Keine Folgephase begonnen.
