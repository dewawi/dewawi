# D5A – Fluid-, Glykol- und Hydraulikrechnung

## Ergebnis und Umfang

D5A ergänzt unabhängige Stoffwert-, Enthalpie-, Mischungs-, Druckverlust- und Pumpenrechner im Items-Modul. Alle Ergebnisse behalten `eligible=false`. Es gibt keine neue öffentliche Route, Selection-/Angebotsintegration, automatische Pumpenauswahl, Maschinenfreigabe oder produktive Datenänderung.

Die neuen Datentypen verwenden unverändert den versionierten D2B-JSON-Import, Quellen und SHA-256-Prüfsummen, Owner-Zuordnung, Rechte, unveränderliche Revisionen, Freigaben und Entzüge. `technicaldataset.dataset_type` ist bereits `varchar(40)`; neue Tabellen, Spalten und SQL-Migrationen sind nicht notwendig. Vorhandene Revisionen bleiben unverändert. Bestehende CSV-Spalten werden nicht umgedeutet; die neuen Tabellenverträge werden über den vorhandenen JSON-Import eingebunden.

## Verträge

Alle vier neuen Typen haben `schema_version=1`, `contract_version=1`, `usage=synthetic_test_only|source_documented` und eine explizite Liste `missing_evidence`. Quellen, Dateninhaber, Herstellerrevision, Importprovenienz und Freigaben gehören weiterhin zum bestehenden äußeren Datensatz. Unbekannte Felder, Einheiten, Modelle und unvollständige Tabellen werden zurückgewiesen. Es werden keine importierten Ausdrücke ausgeführt.

| Typ | Inhalt und Bindung |
| --- | --- |
| `fluid_properties` | Eindeutiges Fluidprodukt (`fluid_key`); Wasser, Ethylen- oder Propylenglykol/Wasser; Massen- oder Volumenanteil; bei Volumenanteilen zwingende Bezugstemperatur; vollständiges Temperatur-/Konzentrationsraster mit Dichte kg/m³, cp J/(kg K), dynamischer Viskosität Pa s und optional belegten Gefriergrenzen je Konzentrationsknoten. |
| `pressure_loss_map` | Artikel-/Modell-/Rollenbindung, exakte `fluid_ref`, vollständig tabellierte Druckverluste je Sektion über Konzentration, Temperatur und m³/h. Eingabeeinheit Pa, kPa oder bar; Ergebnis immer Pa. Komponentenkennfelder gelten zwischen den Flanschen, externe Kennfelder ausdrücklich außerhalb der Maschine. |
| `pump_curve` | Pumpenartikel und Modell, exakte Fluidrevision, Drehzahl, Frequenz und Betriebsmodus, belegter Durchflussbereich als Stützstellen sowie Temperatur-/Konzentrations-/Dichte-/Viskositätsgrenzen. Kennlinie in m oder Pa/kPa/bar. Hydraulischer Wirkungsgrad, Gesamtwirkungsgrad und elektrische Eingangsleistung sind getrennte, nullable Werte je Stützstelle. |
| `hydraulic_path` | Verlustnachweise für einen bereits vorhandenen hydraulischen Loop, vollständige Komponentenabdeckung und höchstens eine zugeordnete Pumpe. Kein zweites Rohrnetz: Rohrsegmente müssen auf vorhandenen Loop-Kanten liegen. Jede Kante braucht einen Quellenverweis und einen Nachweis durch explizite Rohrsegmente, Einbeziehung im Komponentenkennfeld oder belegten Nullverlust. |

`fluid_dataset`, `pressure_loss_dataset`, `pressure_loss_ref`, `curve_ref` und `fluid_ref` sind die vorhandenen D2B-Referenzstellen. Nur Tanks erhalten zusätzlich ein optionales `pressure_loss_ref`; Wärmeübertrager besitzen dieses Feld bereits. Komponentenkennfelder und Pumpenkurven speichern zusätzlich die tatsächliche Artikelidentität im bestehenden Payload. Ein identischer symbolischer Artikelname genügt nicht, um ein historisches Kennfeld auf einen anderen Artikel umzubinden.

`source_documented` allein ist keine Freigabe: Technische Auswertungen verlangen die bestehenden vollständigen Freigabe-/Quellennachweise sämtlicher Abhängigkeiten. Synthetische Pakete sind nicht freigabefähig. Fehlende Frostnachweise sperren die Fluidrechnung; fehlende elektrische Pumpendaten lassen die elektrische Leistung ausdrücklich unbekannt.

## Rechenregeln und numerische Grenzen

- Stoffwerte: bilineare Interpolation auf streng aufsteigenden Temperatur-/Konzentrationsachsen, ohne Extrapolation oder automatische Konzentration. Auch unmittelbar außerhalb liegende exakte Dezimalwerte werden gesperrt. Numerisch ununterscheidbare Tabellenknoten sind unzulässig.
- Enthalpie: stückweise exakte Integration des linear interpolierten cp über die Temperatur. Gemeinsamer relativer Nullpunkt ist der erste Temperaturknoten derselben Fluidrevision und Zusammensetzung; daraus folgt keine Behauptung über absolute Enthalpien oder Mischungswärmen. Wärmedifferenzen werden direkt über das betreffende Temperaturintervall integriert, um die Subtraktion großer Bezugswerte zu vermeiden.
- Temperaturinversion: monotone Intervallhalbierung, maximal 80 Schritte, Zielbreite 1e-10 K und anschließende Enthalpieprüfung `max(1e-5 J/kg, |h|*1e-12)`. Nicht auflösbare Änderungen und nicht endliche Ergebnisse werden gesperrt.
- Volumen-/Massenstrom: Dichte immer am ausdrücklich angegebenen Messzustand; m³/h → kg/s durch `V*rho/3600`, kg/h → kg/s durch `/3600`. Lokale Volumenströme können sich trotz gleicher Masse unterscheiden.
- Mischung: nur dieselbe Fluidrevision und exakt dieselbe Konzentration/Basis/Volumen-Bezugstemperatur; Summe der Massen- und Enthalpieströme und Rückrechnung der Mischtemperatur. Unterschiedliche Konzentrationen bleiben gesperrt, weil zusätzliche belegte Mischungsenthalpien und gegebenenfalls Umrechnungsdaten erforderlich wären. Energieprüfung `max(1e-4 W, |Enthalpiestrom|*1e-12)`.
- Druckkennfelder: trilineare Interpolation. Die jeweilige Fluidrevision bindet Temperatur und Konzentration an ihre tatsächliche Dichte und Viskosität; diese werden ausgegeben. Es gibt keinen zusätzlichen pauschalen Viskositäts- oder Glykolkorrekturfaktor auf gemessene Kennfelder.
- Rohre: ausschließlich ausdrücklich deklarierte stationäre, Newtonsche, voll entwickelte laminare Kreisrohrströmung mit `Re<2300`; Poiseuille `128*mu*L*V/(pi*D^4)` plus der ausdrücklich belegte lokale Verlustbeiwert `K*rho*v²/2`. Keine Standardarmaturen oder geratenen Beiwerte. Die Beschränkung folgt dem [ASHRAE Handbook, Fluid Flow, Abschnitte Laminar Flow und Conduit Friction](https://handbook.ashrae.org/Handbooks/F17/SI/f17_ch03/f17_ch03_si.aspx).
- Pfade: ausschließlich geschlossene, belegte reine Verlustbilanzen (`closed_loop_dissipative_only_v1`), ohne unbekannte statische oder kinetische Druckkorrekturen. Offene Behälterkreise werden gesperrt. Verluste in Serie werden addiert. Parallele Sektionen müssen bei den vorgegebenen Teilströmen denselben Druckverlust innerhalb `max(1e-6 Pa, max(dp)*1e-8)` aufweisen; andernfalls bleibt die Pfadbilanz gesperrt. Der gemeinsame Verlust ist der Mittelwert der übereinstimmenden Zweigwerte, niemals deren Summe.
- Massendurchgang im bekannten Pfad: Abweichung höchstens `max(1e-10 kg/s, m*1e-9)`. Keine Ermittlung einer unbekannten Teilstromverteilung.
- Pumpe: lineare Kennlinieninterpolation bei vorgegebenem Durchfluss; Drehzahl-/Frequenz-/Modusvergleich ohne Affinitätsumrechnung; `dp=rho*g*H` mit `g=9.80665 m/s²`; hydraulische Leistung `dp*V`. Elektrische Leistung nur aus einem belegten Eingangsleistungskennfeld oder Gesamtwirkungsgrad. Ein hydraulischer Wirkungsgrad ersetzt keinen Gesamtwirkungsgrad. Widersprüchliche Leistungsnachweise werden gesperrt. Der bekannte Förderhöhenbedarf wird mit der Pumpenförderhöhe verglichen, kein selbsttätiger Schnittpunkt gesucht.

Die Rechnung verwendet deterministisches PHP-Binary64 mit den genannten Bereichs-, Endlichkeits- und Bilanzprüfungen; sie ist keine Intervallzertifizierung einer realen Maschine. Pumpen-NPSH, Saugdruck, Kavitationssicherheit, lokale Höhen-/Geschwindigkeitsänderungen, thermischer Auftrieb und Pumpenwärme sind damit nicht als Maschinenfunktion nachgewiesen. Für reale Anwendung müssen die Kennfeld- und Pfadquellen die verwendeten Messgrenzen und die zulässigen Betriebsbedingungen abdecken; anderenfalls gehören die fehlenden Nachweise in `missing_evidence` und sperren die technische Freigabe.

## Schnittstellen und D4B2

Private, lesende Service-APIs in `TechnicalDataset`:

```php
$service->evaluateFluid($id, $revision, $sha256, $request, $mode);
$service->evaluatePressureLoss($id, $revision, $sha256, $request, $mode);
$service->evaluatePump($id, $revision, $sha256, $request, $mode);
$service->evaluateHydraulics($machineId, $revision, $sha256, $request, $mode);
```

Modi: standardmäßig `technical`; für ausdrücklich synthetische Daten `synthetic_reference` mit zusätzlichem bestehendem Importrecht. Die reine Mathematik ist über `FluidProperties` und `FluidHydraulics` unabhängig von Zend/DB testbar. Eigentümer- und Rechteprüfungen gehören immer zum gespeicherten Servicezugang. Validierte Datensatz-/Komponentenrechnung, bedingte mathematische Rechnung und Maschinenfreigabe sind durch Modus, Scope, Freigabestatus und `eligible=false` getrennt.

`ThermalMachine` akzeptiert optional `fluid_hydraulics` mit einem vollständig vorgegebenen Hydraulikrequest. Ohne dieses Feld bleiben Rechenweg, Zahlen und Ergebnisstruktur unverändert. Mit diesem Feld bilanziert `FluidThermalAudit` die bekannten Sektionsein- und -austritte über die D5-Enthalpie. Es prüft hydraulische Teilströme, Massenerhaltung, gemeinsame Mischung, Quellenkennzeichnung und die tatsächliche Temperaturfolge entlang des vorhandenen Pfads einschließlich Pumpe, Rohren und externem Eintritt.

Die optionale Rechnung ist eine Prüfung des bestehenden synthetischen D4B2-Punkts. Sie ersetzt dessen konstanten Wärmekapazitätsstrom nicht durch einen neuen nichtlinearen Wärmeübertrager-Solver. Bei Widerspruch der Fluiddaten zum thermischen Punkt bleibt `fluid_hydraulics.calculation=null`; die ursprüngliche synthetische Lösung bleibt als solche sichtbar. Vergleichstoleranzen: 1e-3 W für Wärme und 1e-7 K für Mischung bzw. zugeordnete Pfadtemperaturen. Eine reale thermische Maschinenbewertung bleibt gesperrt.

Die vorhandene Graphprüfung wird wiederverwendet: Owner, aktuelle Artikelidentität, vollständige Referenzen einschließlich inaktiver Komponenten, Payload-/Quellen-/Freigabeprüfsummen, Rücknahme und unveränderte Datenbankzustände. Der D5-Nachfolgertest deckte zusätzlich eine bestehende Unstimmigkeit auf: `supersedes` wurde vom lesenden Maschinenprüfer als Leistungsabhängigkeit erwartet, vom Import aber nur als Historienbeziehung gespeichert. Die Prüfung trennt jetzt beide Beziehungen und kontrolliert den Historien-Fremdschlüssel ausdrücklich. Eine vollständige unabhängige Nachfolgerrevision ist keine physikalische Abhängigkeit ihrer Vorgängerrevision; ausdrücklich referenzierte Fluid-/Kennfeldrevisionen bleiben vollständig entziehbar.

## Synthetische Referenzen und vollständiges Beispiel

Alle automatisch verwendeten Tabellen sind erfunden und ausdrücklich synthetisch gekennzeichnet, einschließlich der Kategorien „Wasser“, Ethylen- und Propylenglykol. Sie sind keine realen Stoffwerte und keine Herstellerkennfelder. Die Glykoltests decken beide Konzentrationsbasen und Interpolation bei Anteil 0,3 zwischen den Knoten 0,2 und 0,4 ab. Der variable Wasserfall hat bei 0/20 °C cp=3000/4000 J/(kg K): bei 1 kg/s ergibt die Integration 70.000 W. Unterschiedliche Dichten im Mischtest machen das Ergebnis bewusst von einer volumenstromgewichteten Mitteltemperatur unterscheidbar.

Vollständig definierter 4/2-Fall: `fluidMachineBundle()` plus `fluidHydraulicRequest()` in `fixtures/fluid-hydraulics.php`, ausgeführt über `php tests/items/fluid-hydraulics.php` und den isolierten DB-Runner.

| Größe | Synthetische Referenz |
| --- | ---: |
| Lastseitige Teilströme | 10,8 und 8,82 m³/h |
| Gemeinsamer Eintritt | 10 °C, 19,62 m³/h, 5,45 kg/s |
| Fluidwerte dieser Referenz | ausdrücklich konstante 1000 kg/m³ und 4000 J/(kg K) |
| Wärmeabgabe des Lastfluids | 59,4 kW |
| Gemeinsame Austrittstemperatur | 7,275229357798166 °C |
| Quellseitige Teilströme | 6,687 und 4,377 m³/h |
| Kondensatorwärme zum Fluid | 73,625 kW |
| Quellseitiger gemeinsamer Austritt | 35,989018438177872 °C |
| Lastseitiger Tankverlust | 2000 Pa |
| Parallele Verdampfersektionen | jeweils 4000 Pa → gemeinsam 4000 Pa |
| Externer belegter Verlust | 1000 Pa |
| Bekannter Gesamtbedarf | 7000 Pa |
| Pumpenförderhöhe bei 19,62 m³/h | 7,553884711779449 m |
| Verfügbarer Pumpendruck | 74.078,30350877192 Pa |
| Reserve gegenüber bekanntem Bedarf | 67.078,30350877192 Pa |
| Hydraulische Leistung | 403,726754122807 W |
| Elektrische Aufnahme bei belegtem Gesamtwirkungsgrad 0,5 | 807,453508245614 W |

Das Beispiel enthält keine verborgenen Rohr- oder Einbauverluste: seine Verbindungskanten sind ausdrücklich als ideale synthetische Nullverluste belegt. Ein zusätzlicher Test ersetzt eine solche Kante durch ein belegtes Rohr und prüft dessen zusätzlichen Verlust. Die Quellseite hat keine zugeordnete Pumpe; dafür wird ausdrücklich `not_assigned` ausgegeben.

## Geänderte Dateien

- `application/modules/items/services/FluidHydraulicSchema.php`: neue geschlossene Verträge.
- `application/modules/items/services/FluidProperties.php`: Stoffwerte, Enthalpie, Umrechnung und Mischung.
- `application/modules/items/services/FluidHydraulics.php`: Kennfelder, Rohre, bekannte Pfade und Pumpen.
- `application/modules/items/services/FluidThermalAudit.php`: optionale Prüfung der D4B2-Lösung.
- `application/modules/items/services/TechnicalSchema.php`, `TechnicalDataset.php`, `ThermalMachine.php`: Registrierung, bestehende Referenz-/Sicherheitskette, Servicezugang und optionale Anbindung.
- `tests/items/fixtures/fluid-hydraulics.php`, `tests/items/fluid-hydraulics.php`, `tests/items/fluid-hydraulics-db.php`, `tests/items/technical-data.php`: ausschließlich synthetische Fixtures und Regressionen.
- `tests/items/fluid-hydraulics-review.md`: dieser Bericht.

## Verifikation

- `php tests/items/fluid-hydraulics.php`: 93 Assertions erfolgreich, einschließlich Stoffwerte, Konzentrationsbasis, Einheiten, Frost, Enthalpieinversion, Mischung, Druckkennfelder, Serien-/Parallelpfade, Rohrdomäne, Pumpenleistung/-defizit, numerische Grenzen, 4/2 und inaktive Sektionen.
- `python3 tests/items/technical-data.py`: erfolgreich: 41 initiale Assertions, 54 D2B-Matrixfälle mit 163 Assertions, gespeicherte D3-/D4A-/D4B1-/D4B2-Prüfungen mit 143/225/121/211 Assertions sowie 127 neue gespeicherte D5A-Assertions. Ausschließlich eine temporäre MariaDB mit synthetischen Daten, eigenem Socket und `--skip-networking`. Der vorhandene gekoppelte Runner enthält auch seine bisherigen Fresh-/Upgrade-Schemakontrollen; keine zusätzlichen D5-Migrationstests oder SQL-Änderungen.
- Reine Regressionen: D3 58, D4A 78, D4B1 124, D4B2 190 Assertions erfolgreich.
- Inquiry-Regressionen: `shop-inquiry-calculation.php` 16 sowie `inquiry-selection.php` 37 + 60 kumulative Checks erfolgreich.
- PHP-Syntax aller elf geänderten/neuen PHP-Dateien sowie `git diff --check` erfolgreich.
- Keine Browserprüfung: keine UI-/HTTP-Änderung. Keine Prüfung realer Herstellerdaten und keine produktive Datenbankprüfung; diese sind nicht Teil dieses synthetischen Auftrags.

## Grenzen und Voraussetzungen für D5B/reale Maschinen

Nicht unterstützt: allgemeine Netze, unbekannte Durchflussverteilungen, seriell thermisch gekoppelte Wärmeübertrager, offene Kreisläufe mit unbekannter statischer Höhe, turbulente/transitionale oder nicht voll entwickelte Rohrströmung, andere Rohrquerschnitte, freie Konzentrationsumrechnung oder Mischung unterschiedlicher Konzentrationen, Pumpenaffinitäten, Netto-Maschinenkennlinien als Brutto-Pumpenkennlinie, komplexe Armaturen-/Mehrpumpenregelungen, D6 und reversible Heizung.

Für D5B bzw. reale Maschinen sind echte identifizierte Fluidprodukte und Stoffwert-/Frosttabellen mit Rechte- und Engineeringnachweis, vollständig gebundene Komponenten-/Pumpenkennfelder und Messgrenzen, verifizierte Verbindungslasten und statische/kinetische Randbedingungen, tatsächliche feste Teilströme oder eine separat freizugebende Verteilungsregelung und gegebenenfalls belegte elektrische/NPSH-Daten erforderlich. Variable Stoffwerte dürfen erst mit einem entsprechend belegten Wärmeübertragervertrag in die eigentliche thermische Lösung eingehen. D5B wird nicht begonnen.

Keine Datenmigration und keine manuellen Datenbanknacharbeiten erforderlich. Neue Daten müssen als neue Revisionen über den vorhandenen technischen Import aufgenommen werden. Kein Push, Merge oder Deployment.
