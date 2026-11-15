# D6B1 – Gekoppelter thermisch-hydraulischer Betriebspunkt

## Ergebnis und genau definierte Modellgrenze

D6B1 ergänzt einen unabhängig prüfbaren synthetischen Kühl- und Wärmepumpenbetrieb mit einem Kältekreis und zwei getrennten Flüssigkeitsloops. Austrittstemperaturen, Flüssigkeitsmassenströme, lokale Volumenströme, Druckverluste, Pumpenbetrieb und Te/Tc werden innerhalb desselben Gleichungssystems konsistent bestimmt. Ein bisheriger D4- oder D5-Ergebnisstand wird nicht ungeprüft übernommen.

Der erste registrierte Modellvertrag ist bewusst eng: `synthetic_constant_compressor_two_liquid_loops_v1` verlangt im gesamten Suchbereich nachweislich konstante D3-Kälteleistung und Verdichteraufnahme. Nichtkonstante Q-/P-Polynome werden ausdrücklich gesperrt. Dieser mathematische Spezialfall erlaubt die analytische Elimination der thermischen Nebenbedingungen und einen vollständigen Eindeutigkeitsnachweis mit zwei skalaren Restgleichungen. Es wird damit keine allgemeine gekoppelte Maschine mit beliebigen Verdichterkennlinien behauptet.

`mathematical_converged=true` und `fully_coupled=true` entstehen ausschließlich nach bestandener Wärme-, Enthalpie-, Massenstrom-, Druck-, Betriebsgrenzen- und Eindeutigkeitsprüfung innerhalb dieser synthetischen Modellgrenze. `eligible=false` und `status=review_required` bleiben immer bestehen. Der Ergebnis-Scope nennt die synthetische, extern thermostatierte Grenze ausdrücklich. Fehler liefern `solution=null`, `mathematical_converged=false` und `fully_coupled=false`; es wird kein letzter Iterationsstand veröffentlicht.

Realer Betrieb, technische/kaufmännische Maschinenfreigabe, allgemeine Kältemittelzustandsberechnung und Maschinen-EER/COP/SCOP sind nicht Bestandteil des Ergebnisses. Insbesondere wird das neue Kopplungskennzeichen nicht auf andere Betriebsarten, alte Ergebnisse oder reale Anlagen übertragen.

## Versionierte Verträge und Integration

Die bestehende `machine_configuration` Version 2 aus D6A bleibt erhalten: feste physische Kältemittelleitungen, wirkliche Komponenten-/Profilbindungen, Stufen, Loops und explizite Ventilzustände. Eine reine Heizmaschine benötigt keine reversible Konstruktion. Eine ausdrücklich reversible Maschine benötigt weiterhin tatsächlich vertauschte Wärmeübertragerrollen, geeignete bidirektionale Anschlüsse, Expansionsrichtung und gültige Schalterstellungen; die neue Rechnung verwendet diese bestehenden Prüfungen.

`operating_mode_model` erhält Vertragsversion 2. Sie verwendet dieselben tatsächlichen Topologiezuordnungen wie D6A, ersetzt aber die festen v1-Punktzeilen durch einen geschlossenen `coupling`-Vertrag und begrenzte Numerik. Zugelassen sind ausschließlich `cooling` und `heat_pump`. Erforderlich sind der registrierte Modellname, die ausdrückliche sekundäre Wärmegrenze, ein Quellenhinweis sowie Wärme-, Druck- und Temperaturtoleranzen und maximale Iterationszahl. Freie Formeln oder vom Client behauptete Ströme/Ergebnisflags sind keine gültigen Felder.

`heat_exchanger_map` erhält Vertragsversion 3 mit `synthetic_fluid_enthalpy_effectiveness_v1`. Sie bindet dieselben tatsächlichen Artikel, Modelle, Sektionen, Kältemittel und Bauarten sowie eine genaue D5-Fluidrevision und Zusammensetzung. Einsektionale Platten- und Rohrbündelwärmeübertrager sind zulässig. Die Temperaturen sind in °C, Ströme in m³/h bzw. kg/s, Leistungen in kW; Grenzen tragen ihre expliziten Einheiten. Die D4-Gleichung für konstante Wirksamkeit `epsilon` bleibt erhalten. Ihre Wärmekapazitätsrate wird hier aus der tatsächlichen D5-Enthalpiesekante des jeweiligen Versuchs berechnet. Temperaturabhängiges cp wird nicht durch einen konstanten Ersatzwert ersetzt.

Der private Zugang lautet:

`TechnicalDataset::evaluateThermohydraulic(id, revision, hash, request, mode='technical')`

Ein synthetischer Aufruf erfordert ausdrücklich `mode='synthetic_reference'`. Eingaben sind Betriebsart, Stufe, tatsächliche Ventilzustände und die beiden Flüssigkeitseintrittstemperaturen; optional dieselben Verdichterkontexte wie bisher. Suchgrenzen und Numerik stammen aus den gebundenen Datenrevisionen. Unbekannte Felder, fehlende Randbedingungen, nichtendliche Zahlen und nicht unterstützte Verträge werden abgewiesen.

Der gespeicherte Zugang verwendet den mit D6A gemeinsamen privaten Snapshot-/Transaktionspfad; es gibt keinen parallelen Import-, Rechte- oder Simulationsunterbau. Alte `evaluateOperatingMode`-Aufrufe behalten ihre Ergebnisse. Ein neuer gekoppelter Vertrag wird im alten D6A-Rechner ausdrücklich gesperrt. Alte D4-/D5-Aufrufe und deren v1/v2-Verträge werden nicht umgedeutet. Es werden keine bestehenden Revisionen nachträglich ergänzt.

## Gleichungen, Rückkopplung und Wiederverwendung

Für die gewählte Stufe liefern D3 und `MachineStage` Q und P. Alle aktiven Verdichter benötigen passende Referenzbedingungen, Kältemittel und reine Sättigungstemperaturbasis. Der rechteckige gesamte Te-/Tc-Suchbereich muss innerhalb der Verdichtergrenzen liegen. Der Rechner prüft die konstanten Koeffizienten und benutzt die bestehende D4-Polynomintervallrechnung für deren Leistungsgrenzen. Die D3-Stufe wird zusätzlich am endgültigen Punkt ausgewertet.

Der bestehende D4-Vertrag `synthetic_hermetic_no_external_losses_v1` definiert ausdrücklich vollständige Motorwärme im Kältemittel, keine externen Verdichter-/Kältemittelleitungsverluste und keine transiente Speicherung. Damit gilt innerhalb dieser synthetischen Grenze:

- `Q_evaporator = Q_comp`.
- `Q_condenser = Q_comp + P_comp`.

Für jede Flüssigkeitsseite gelten gleichzeitig:

- `Tout = Tin + epsilon * (Tsat - Tin)` aus der D4-Wärmeübertragung.
- `Q = m * abs(h(Tout)-h(Tin))` mit der integrierten D5-Wärmekapazität.
- `V_i = 3600*m/rho(T_i)` an jeder tatsächlichen Pfadposition.
- `R = dp_pump(V_pump,T_pump) - sum(dp_i(V_i,T_i)) = 0`.

Die unabhängigen Unbekannten des ursprünglichen Modells sind Te, Tc und die beiden Flüssigkeitsmassenströme; die beiden Austrittstemperaturen sind durch die Wärmeübertragergleichungen bestimmt. Mit den ausdrücklich konstanten Verdichterleistungen lassen sich Massenstrom und Sättigungstemperatur aus je einer Austrittstemperatur eliminieren. Die beiden Druckrestgleichungen bestimmen die verbleibenden Unbekannten. Das System ist damit vollständig bestimmt; es erhält keinen zusätzlichen frei gewählten Volumenstrom oder unerklärten Startwert.

Jeder Versuch bestimmt aus der Austrittstemperatur zuerst die Enthalpiedifferenz und den dazu gehörenden Massenstrom, danach sämtliche lokalen Volumenströme und den zugehörigen Te-/Tc-Wert. `HeatExchangerMap::atFluidPoint` verwendet den vorhandenen D4-Kern `atPoint`, mit der bei genau diesem Punkt aus D5 bestimmten Enthalpiesekante. Der ursprüngliche D5-Serienpfad wird an denselben lokalen Temperaturen und massenkonsistenten Strömen neu ausgewertet. Die hydraulische Restgleichung entscheidet, welcher dieser thermisch konsistenten Zustände zugleich zur Pumpe passt. Geänderte Widerstände oder Pumpenkennlinien verändern deshalb auch den thermischen Betriebspunkt.

`FluidProperties` bleibt für cp-Integration, Dichte, Viskosität, Konzentrationsbasis, Frostschutz und Umrechnung zuständig. `FluidHydraulics` prüft unverändert die vollständige Komponenten-/Druckkennfeld-/Pumpenbindung, Anschlussverluste, Kennfeldbereiche, Leistungsbelege und Massenkontinuität. Seine vorhandene einzelne Loop-Rechnung ist zur Wiederverwendung öffentlich verfügbar. Ein- und Austrittstemperaturen anderer Komponenten werden aus ihrer tatsächlichen Reihenfolge im Serienpfad bestimmt; die vorhandene `FluidThermalAudit::temperatures`-Prüfung kontrolliert die finale Übergabe zusätzlich. Eine konstante Dichte oder gleiche Volumenströme bei unterschiedlichen Temperaturen werden nicht vorausgesetzt.

Die Druckkennfelder enthalten ausdrücklich die Temperatur-/Konzentrationsabhängigkeit für die exakte Fluidrevision. Es wird keine zusätzliche unbelegte Viskositätskorrektur angewandt. Die variable Referenz erzeugt die belegten Drucktabellen proportional zu ihrer synthetischen Viskosität. Pumpen mit Druckordinate oder Förderhöhenordinate werden über den bestehenden D5-Kern und denselben Intervallnachweis ausgewertet; bei Förderhöhe geht die lokale Fluiddichte in den verfügbaren Druck ein.

## Sekundäre Wärmegrenze und Pumpenwärme

Der geschlossene Vertrag `synthetic_pump_and_dissipation_heat_removed_by_external_thermostats_v1` ist eine ausdrücklich idealisierte Referenzanlage: Pumpen-/Motorwärme und die spätere Dissipation hydraulischer Energie werden durch äußere Thermostate abgeführt. Zwischen diesen Elementen und den Wärmeübertragerflanschen entsteht dadurch kein zusätzlicher Enthalpieeintrag. Passive Komponenten sind stationär und adiabatisch ohne Speicherung; sonstige Hilfs-/Standbywärme liegt außerhalb des Kältemittel- und Sekundärstrommodells. Diese Annahmen werden im Ergebnis ausgegeben.

Die Pumpenaufnahme wird trotzdem vollständig mit den vorhandenen D5-Kennlinien und Leistungsbelegen berechnet. Ihre Summe wird als `pump_heat_removed_to_external_thermostats_W` quantitativ ausgegeben. Unbekannte Pumpenleistung, fehlende Verlustdaten oder eine fehlende ausdrückliche Wärmegrenze sperren die gekoppelte Prüfung. Null-Pumpenwärme wird nicht als unbelegte Annahme für eine reale Maschine verwendet. Eine reale nicht thermostatierte Pumpe benötigt später eine andere belegte Energiegrenze und zusätzliche Temperatur-/Enthalpierückkopplung.

## Numerik und Eindeutigkeit

Der Suchbereich stammt aus der genauen D4-Energiebilanzrevision. Der gesamte deklarierte Bereich muss für die verwendeten Modell-/Kennfeldfunktionen auswertbar sein; es gibt keine Extrapolation oder stillschweigende Bereichserweiterung. Unvollständige oder ungeeignete Bereiche werden konservativ gesperrt.

Jede Austrittstemperaturdomäne wird für den Nachweis vollständig in 64 geschlossene Teilintervalle aufgeteilt. Intervall-Autodifferentiation bestimmt die Ableitung der Druckrestgleichung über jedem ganzen Teilintervall. Hierfür werden die vorhandenen nach außen gerundeten Binary64-Intervallprimitive aus `ThermalCircuit` wiederverwendet. Eingangsdezimalzahlen, cp-Integrale, Dichte, Konzentrationsinterpolation, temperatur-/volumenstromabhängige Drucktabellen und Pumpenordinaten gehen in die eingeschlossenen Werte und Ableitungen ein. Tabellenknoten werden mit beiden angrenzenden Zweigen berücksichtigt.

Der Nachweis verlangt durchgängig dasselbe strikt von null getrennte Ableitungsvorzeichen. Zusammen mit einem durch Restwertintervalle an beiden Suchgrenzen streng eingeschlossenen Vorzeichenwechsel beweist dies genau eine Lösung innerhalb der erklärten Modellgrenze. Eine feste Anzahl von Stützproben allein gilt ausdrücklich nicht als Eindeutigkeitsbeweis. Zusätzlich gefundene mehrere Vorzeichenwechsel werden als `multiple_solutions_detected` ausgewiesen. Auch Tangential-/nicht zertifizierbare oder unbestimmte Fälle werden ohne Lösung gesperrt. Nur innerhalb der Rundungsunsicherheit liegende Randtreffer erhalten keinen Existenznachweis und bleiben gesperrt.

Die anschließende Intervallhalbierung verwendet maximal die versionierte Anzahl von Iterationen, höchstens 80 je Seite. Eine Intervallhälfte wird nur bei eingeschlossenem Restvorzeichen verworfen. Der numerische D5-Rest muss innerhalb der unabhängig gebildeten Restwert-Einschließung liegen. Konvergenz verlangt gleichzeitig passende thermische/sekundäre Enthalpiereste samt Rundungszulage, einen vollständig eingeschlossenen Druckrest innerhalb der Toleranz und einen Temperaturfehlernachweis. Letzterer folgt entweder aus der verbleibenden Intervallbreite oder aus Restwertgrenze geteilt durch die global bewiesene minimale Ableitung und epsilon. Ein exakter Mittelpunkt kann deshalb schon früh vollständig nachgewiesen sein.

Referenztoleranzen sind `1e-7 kW`, `1e-6 Pa` und `1e-8 K`, bei höchstens 60 Lösungsiterationen je Seite. Der Vertrag erlaubt nur positive Toleranzen zwischen `1e-9` und `1e-4` in der jeweiligen Einheit. Ergebnisse außerhalb der numerischen Auflösung werden nicht freigegeben. Das ist ein Nachweis für das registrierte synthetische mathematische Modell, keine Zertifizierung einer realen Anlage.

## Vollständige Kühl- und Wärmepumpenreferenz

Alle Daten sind frei erfundene synthetische Prüfwerte. Die zwei unterschiedlichen Verdichter liefern zusammen Q=30 kW und P=7,15 kW; damit sind am Verflüssiger 37,15 kW zu übertragen. Die Flüssigkeit hat im ersten Fall rho=1000 kg/m³ und cp=4000 J/(kg K). Beide Wärmeübertrager haben epsilon=0,5. Inlet-/Outletgrenzen, Quellen, Fluidrevisionen und alle Anschlüsse sind ausdrücklich angegeben.

Die kalte Seite hat vor dem Verdampfer eine Pumpe und einen Tank. Verdampfer und Tank verlieren jeweils `1000*V Pa`, der äußere vollständige Pfad `500*V Pa`. Die Pumpe liefert `12600-1000*V Pa`. Die heiße Seite hat eine separate Pumpe, einen Verflüssiger mit `1000*V Pa` und einen äußeren Pfad mit `500*V Pa`; ihre Pumpe liefert `8358,75-1000*V Pa`. V steht hier in m³/h. Sämtliche Verbindungsverluste sind ausdrücklich als synthetische ideale Nullverluste belegt, nicht unbekannt. Jede Pumpe hat einen belegten Gesamtwirkungsgrad von 0,5.

| Ergebnis | Verdampferseite | Verflüssigerseite |
| --- | ---: | ---: |
| Eintrittstemperatur | 20 °C | 30 °C |
| Austrittstemperatur | 12,5 °C | 40 °C |
| Ermittelte Sättigungstemperatur | Te=5 °C | Tc=50 °C |
| Massenstrom | 1 kg/s | 0,92875 kg/s |
| Volumenstrom | 3,6 m³/h | 3,3435 m³/h |
| Wärmefluss | 30 kW | 37,15 kW |
| Erforderlicher/verfügbarer Pumpendruck | 9000 Pa | 5015,25 Pa |
| Numerischer Druckrest | 0 Pa | 9,09e-13 Pa |
| Wärme-/Sekundärenthalpierest | 0 kW | 0 kW |
| Eingeschlossener Druckrest | ungefähr [-2,44e-10; 2,41e-10] Pa | ungefähr [-9,19e-11; 9,19e-11] Pa |
| Globales Ableitungsintervall | ungefähr [-2653,10; -1158,30] Pa/K | ungefähr [493,41; 1711,94] Pa/K |
| Nachgewiesener Sättigungstemperaturfehler | <4,45e-13 K | <4,20e-13 K |
| Lösungsiterationen / Betriebspunktproben | 1 / 66 | 1 / 66 |

Die gesamte abgeführte Pumpenwärme beträgt 27,315826875 W. Die Kältekreis-Energiebilanz ist `30+7,15-37,15=0 kW`. Im Kühlmodus wird die Verdampferleistung als Kälteleistung ausgegeben; es wird keine nutzbare Heizleistung behauptet. Eine getrennte reine Wärmepumpenkonfiguration ohne Umschalter erreicht dieselben physikalischen Referenzwerte und weist 37,15 kW Heizleistung am definierten heißen Wärmeübertragerflansch aus. Die getrennte reversible Referenz verwendet zusätzlich die tatsächlichen D6A-Umschaltwege und kann deshalb aufgrund ihrer anderen physischen Loop-/Pumpenzuordnung einen anderen Te-/Tc-Punkt besitzen.

Ein um 50 % erhöhter Tankwiderstand ergibt unabhängig V=12600/4000=3,15 m³/h und Te≈2,857142857 °C. Eine um 500 Pa angehobene kalte Pumpenkennlinie ergibt V=13100/3500≈3,742857143 m³/h und den dazu passenden veränderten Te-Wert. Bei gleichen Kennlinien und auf 21/31 °C erhöhten Eintrittstemperaturen ergibt sich Te/Tc=6/51 °C. Diese Prüfungen belegen die wirkliche Rückwirkung auf die thermische Rechnung.

## Variable Stoffwerte und unabhängige Referenz

Der zweite Fall definiert ausdrücklich rho(T)=1040-2*T kg/m³, cp(T)=3800+10*T J/(kg K) und mu(T)=0,08-0,0005*T Pa s. Die Drucktabellen skalieren mit mu(T)/0,07. Die unabhängige Referenz benutzt direkt das analytische Integral `delta_h=3800*(Tout-Tin)+5*(Tout²-Tin²)` und die vollständig ausgeschriebenen Druckgleichungen; sie verwendet keine D3-/D4-/D5-Rechenfunktion und keine importierten Tabellen zum Lösen.

| Variable Referenz | Verdampferseite | Verflüssigerseite |
| --- | ---: | ---: |
| Te bzw. Tc | 4,7724271435290575 °C | 48,64648450911045 °C |
| Austrittstemperatur | 12,386213571764529 °C | 39,323242254555225 °C |
| Massenstrom | 0,9945202928351583 kg/s | 0,9609438414884112 kg/s |
| Eintrittsvolumenstrom | 3,5802730542065695 m³/h | 3,5299977850594693 m³/h |
| Numerischer Druckrest | -8,74734e-7 Pa | 5,47590e-7 Pa |
| Wärme-/Sekundärenthalpierest | 0 kW | 0 kW |
| Nachgewiesener Temperaturfehler | <1,513e-9 K | <2,331e-9 K |
| Lösungsiterationen / Proben | 30 / 95 | 28 / 93 |

Die abgeführte Pumpenwärme beträgt hier 27,410316646481235 W. Lokale Volumenströme unterscheiden sich wegen der Dichteänderung, während die Masse erhalten bleibt. Weitere Prüfungen verwenden Förderhöhen statt Druckordinaten und eine interpolierte synthetische Propylenglykol-Massenkonzentration von 0,2 mit ebenfalls unabhängig analytisch bestimmtem Sollpunkt.

## Sicherheit, Revisionen und Tests

`TechnicalDataset` verwendet die vollständige vorhandene D2B–D6A-Kette: authentifizierter privater Kontext, View-/Import-/Artikelrechte und bestehende Featuregrenzen, Mandantenisolierung und zulässige Parent-Child-Freigabe, genaue Artikel-/Profilidentitäten, Paketrevisionen, Payload-/Quellen-/Freigabehashes sowie Entzüge. Alle relevanten indirekten, anderen Betriebsarten- und inaktiven Komponentenabhängigkeiten werden im selben transaktionalen Snapshot geprüft. Neue v3-Wärmeübertrager erhalten dieselbe tatsächliche Artikelbindung wie die bereits eingeführten v2-Punktkennfelder. Synthetische Quellen können keine reale technische Freigabe erhalten.

`supersedes` bleibt Historie und keine zusätzliche physikalische Leistungsabhängigkeit. Ein eigenständig vollständiger Nachfolger verändert keine historische Rechnung. Der Entzug eines wirklich referenzierten Modells sperrt die betreffende alte Maschinenrevision, ohne einen unabhängig vollständigen Nachfolger allein wegen seiner Historienbeziehung ungültig zu machen. Manipulierte Historien-Fremdschlüssel bleiben gesperrt. Wiederholte Auswertungen verändern weder technische Daten noch Artikel oder Geschäftsdokumente.

Ausgeführte Prüfungen:

- `php tests/items/thermohydraulic-circuit.php`: 69 direkte Modell-, Bilanz-, Pumpen-, Fluid-, Numerik-, Mehrdeutigkeits-, Fehler- und Kopplungsgate-Prüfungen erfolgreich.
- `python3 tests/items/technical-data.py`: 127 gespeicherte D6B1-Prüfungen erfolgreich; außerdem 41 Initialprüfungen, 54 D2B-Matrixfälle mit 163 Assertions und gespeicherte D3/D4A/D4B1/D4B2/D5A/D5B/D6A-Regressionen mit 143/225/121/211/127/103/235 Assertions erfolgreich.
- Reine D3/D4A/D4B1/D4B2/D5A/D5B/D6A-Regressionen: 58/78/124/190/93/75/91 Assertions erfolgreich.
- Inquiry: `php tests/security/shop-inquiry-calculation.php` mit 16 Prüfungen sowie `php tests/security/inquiry-selection.php` mit 37 und 60 kumulativen Prüfungen erfolgreich.
- PHP-Syntax aller 12 geänderten/neuen PHP-Dateien und `git diff --check` erfolgreich.

Der gekoppelte Datenbankrunner verwendet ausschließlich eine temporäre synthetische MariaDB mit eigenem Socket und `--skip-networking`; seine bestehenden echten Fresh-/1.1.7-Upgrade-Prüfungen bleiben enthalten. Anfängliche Fixturefehler bei Provenienz und dem vollständigen gültigen Suchbereich der kleinen Stufe wurden korrigiert; die Schutzprüfungen wurden beibehalten. Keine Browser-/UI-, reale OEM- oder Produktionsdatenbankprüfung, da der Auftrag ausschließlich den unabhängigen Backend-Rechner betrifft. Keine SQL-Änderung oder zusätzliche Schema-Migration.

## Dateien, Grenzen und Voraussetzungen für D6B2

Geänderte Dateien:

- `application/modules/items/services/ThermohydraulicCircuit.php`: begrenzte Rückkopplungsrechnung, Intervallnachweis, gemeinsame Schlussprüfung und Ergebnisvertrag.
- `application/modules/items/services/OperatingModeSchema.php`, `TechnicalSchema.php`: neue versionierte Modell-/Flüssigkeitswärmeübertragerverträge unter Beibehaltung der bisherigen Normalisierung.
- `application/modules/items/services/HeatExchangerMap.php`: Wiederverwendung der D4-Wärmeübertragung mit der D5-Enthalpiesekante.
- `application/modules/items/services/ThermalCircuit.php`: vorhandene Polynom-/Intervallprimitive für den Nachweis wiederverwendbar.
- `application/modules/items/services/FluidHydraulics.php`: vorhandene Loop-Rechnung zur direkten Wiederverwendung öffentlich verfügbar.
- `application/modules/items/services/TechnicalDataset.php`: gemeinsamer privater Snapshot-Zugang einschließlich `evaluateThermohydraulic` und tatsächlicher v3-Artikelbindung.
- `application/modules/items/services/MachineOperatingMode.php`: alte Punktrechnung weist neue gekoppelte Verträge ausdrücklich zurück.
- `tests/items/fixtures/thermohydraulic-circuit.php`, `thermohydraulic-circuit.php`, `thermohydraulic-circuit-db.php`, `technical-data.php` und dieser Bericht: synthetische Referenzen und Regressionen.

Nicht unterstützt sind nichtkonstante Q-/P-Verdichterkennlinien im neuen Kopplungsmodell, Mehrkreis-/gemeinsame mehrsektionale Wärmeübertrager, D5B-Verteilnetze innerhalb der neuen Kopplung, Luftseiten, offene hydraulische Systeme, zusätzliche thermisch gekoppelte Serienwärmeübertrager, Rohrsegmente im neuen Nachweis, nicht thermostatierte Pumpenwärmeeinträge und allgemeine Netze. Druckverluste der unterstützten vollständig beschriebenen Serienpfade kommen aus den genauen D5-Drucktabellen und explizit belegten Anschlussverlusten. D5B und vorhandene Luft-/Mehrkreis-Kühlung bleiben unverändert über die bisherigen Zugänge verfügbar. D6A-Teil-/Vollwärmerückgewinnung bleibt in ihren bisherigen Grenzen; der neue Rechner akzeptiert dafür kein vollständiges Kopplungskennzeichen. Integrated Free Cooling wird nicht implementiert.

D6B2 benötigt für eine weitergehende Modellgrenze zunächst ausdrücklich beauftragte und nachweisbare Erweiterungen: nichtkonstante Verdichter-/Wärmeübertragerkennlinien mit gültigem gemeinsamen Eindeutigkeitsnachweis, reale Kältemittelzustands-/Druckverlust- und Energiedaten, vollständige reale Pumpen-/Motor-/Hilfswärmegrenzen und gegebenenfalls zusätzliche thermische Unbekannte. Für neue Topologien sind genaue Strömungswege, Verteil-/Mischbedingungen, Rohr-/Ventilmodelle und gültige OEM-Betriebsgrenzen erforderlich. Für reale Wärmepumpen fehlen außerdem konstruktionsabhängig Abtauung, Ölmanagement, Umschalt-/Sicherheitsfunktionen sowie Engineering-, Quellenrechts- und technische Freigaben. Ein synthetischer Konvergenznachweis ersetzt diese Nachweise nicht.

Keine Migration oder manuelle Datenbanknacharbeit erforderlich. Neue Verträge sind als neue unveränderliche technische Revisionen zu importieren. Kein Push, Merge, Tag oder Deployment. D6B2 wurde nicht begonnen.
