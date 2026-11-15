# D6B2 – Dynamische Verdichterkennlinien im thermisch-hydraulischen Solver

## Ergebnis und Kompatibilität

D6B2 erweitert den vorhandenen `ThermohydraulicCircuit` um temperaturabhängige D3-Kälteleistung Q(Te,Tc) und Verdichteraufnahme P(Te,Tc). Der gemeinsame Betriebspunkt berücksichtigt beide Verdichterwerte, beide Wärmeübertrager, Fluidenthalpien, örtliche Dichten/Volumenströme, temperaturabhängige Druckverluste und Pumpen gleichzeitig. Jeder numerisch geprüfte Te-/Tc-Punkt erhält eine neue D3-Stufenauswertung; Leistungen des Startpunkts werden nicht für spätere Temperaturen weiterverwendet.

Es bleibt bei der synthetischen D6B1-Grenze: ein Kältekreis, zwei getrennte Flüssigkeitsloops mit vollständigen Serienpfaden und festen Pumpen, einsektionale Flüssigkeitswärmeübertrager und ausdrücklich extern thermostatierte Pumpen-/Dissipationswärme. `cooling` und `heat_pump` werden unterstützt. Ein ausschließlich heizender Wärmepumpenbetrieb verlangt keine reversible Konstruktion. Tatsächlich reversible Konstruktionen benötigen weiterhin die vollständigen D6A-Topologie-/Ventilnachweise; ihre Prüfung wird nicht durch eine Rollenbezeichnung ersetzt.

Der bisherige D6B1-Vertrag bleibt vollständig erhalten. Vier vollständige Ergebnis-JSONs für Kühlung/Wärmepumpe mit konstanten bzw. variablen Fluidstoffwerten wurden zusätzlich mit der Implementierung aus Commit `93f1ca7e` verglichen: alle vier waren bytegleich. Der bestehende skalare D6B1-Lösungsweg und seine Ergebnisversion bleiben bestehen. D4A-/D4B2-Mehrkreisrechnungen, D5B und D6A-Rückgewinnung werden unverändert über ihre bisherigen Verträge aufgerufen.

`mathematical_converged=true` und `fully_coupled=true` bedeuten ausschließlich einen nachgewiesenen Betriebspunkt innerhalb der ausdrücklich synthetischen mathematischen Grenze. `eligible=false` und `status=review_required` bleiben ausnahmslos erhalten. Bei fehlender Konvergenz, Eindeutigkeit, Existenz oder Evidenz gibt es keine Lösung und kein vollständiges Kopplungskennzeichen.

## Datenverträge und vorhandene Services

| Vertrag | D6B2-Verwendung |
| --- | --- |
| `machine_configuration`, Version 2 | Unverändert: physische Leitungen, Komponenten, Stufen, hydraulische Loops, Betriebsrollen und Ventilzustände. |
| `operating_mode_model`, Version 1 | Unveränderte feste D6A-Betriebspunkte. |
| `operating_mode_model`, Version 2 | Unverändertes konstantes D6B1-Modell. |
| `operating_mode_model`, neue Version 3 | `synthetic_polynomial_compressor_two_liquid_loops_v1`, ausschließlich Kühlung/Wärmepumpe; gleiche begrenzte Numerik und sekundäre Wärmegrenze wie D6B1. |
| `compressor_polynomial` | Unveränderte D3-C0–C9-Kennlinien, Einheiten, Einsatzgrenzen, elektrische Ausführung und Referenzbedingungen. |
| `heat_exchanger_map`, Version 3 | Unveränderte D6B1-Wärmeübertragung mit D5-Enthalpiesekante und genau gebundener Fluidrevision/Zusammensetzung. |
| `thermal_balance_model`, Version 1 | Unveränderte ausdrücklich hermetische synthetische Energiebilanz ohne externe Kältemittel-/Verdichterverluste und ohne Speicherung. |
| D5-Fluid-, Druckverlust-, Pumpen- und Serienpfadverträge | Unverändert; tatsächliche lokale Temperaturen und massenkonsistente Ströme werden bei jedem Versuch neu bestimmt. |

Neue Funktionalität erfordert ausdrücklich eine neue Modellrevision mit Vertragsversion 3. Vorhandene gespeicherte Konfigurationen werden nicht nachträglich verändert. Ein alter Version-2-Vertrag akzeptiert weiterhin keine nichtkonstanten Q-/P-Kennlinien. Die Zuordnung zwischen Vertragsversion und geschlossenem Modellnamen wird beim Import validiert; freie Formeln sind nicht zugelassen.

Der gespeicherte Zugang bleibt `TechnicalDataset::evaluateThermohydraulic(id, revision, hash, request, mode)`. Requests tragen weiterhin Betriebsart, Stufe, tatsächliche Ventilzustände, die beiden Eintrittstemperaturen und gegebenenfalls explizite Verdichterkontexte. Die vorhandene technische Standardauswertung gibt keine reale Berechnung frei. Mathematische Referenzauswertung erfordert ausdrücklich `synthetic_reference` und die bestehenden privaten Rechte.

Es gibt keinen neuen Polynomkern, keine zweite Maschinen-/Strömungsarchitektur und keine neue Abhängigkeit. Die Erweiterung verwendet:

- D3 über den bisherigen `MachineStage`-Callback für sämtliche numerischen Verdichterwerte und den normierten Verdichter-COP.
- `ThermalCircuit::polynomialBounds` für bestehende C0–C9-Werte- und Ableitungsintervalle einschließlich C7/C8; außerdem seine nach außen gerundeten Intervallprimitive.
- D6B1-`prepare`, `sample` und die vorhandene Intervall-Autodifferentiation für die beiden Flüssigkeitsseiten.
- D4-`HeatExchangerMap::atPoint` über den bereits vorhandenen Enthalpiesekanten-Zugang.
- D5-`FluidProperties`, `FluidHydraulics` und `FluidThermalAudit` für Stoffwerte, Frostschutz, Enthalpie, Strömung, Druck, Pumpenleistung und finale Temperaturkontrolle.

## Gemeinsames Gleichungssystem und neue Auswertung

Für jeden geprüften Punkt werden die genauen aktiven Verdichterinstanzen mit ihren eigenen revisionsgebundenen Kennliniensätzen ausgewertet. Unterschiedliche Mengen aus fremden Datensätzen werden nicht zusammengebaut. Aggregation setzt die vorhandenen kompatiblen Referenzbedingungen und Temperaturbasen voraus; exakte elektrische Ausführung, Modell, Kältemittel und Kontext werden im bisherigen privaten D3-/Profilpfad geprüft. Alle numerischen Q-/P-Werte werden vor Summen und COP in kW normiert.

Mit der unveränderten synthetischen Kältemittelgrenze gilt:

`Q_e = Q(Te,Tc)` und `Q_c = Q(Te,Tc) + P(Te,Tc)`.

D4 liefert für jede Seite `Tout = Tin + epsilon*(Tsat-Tin)`. Der Massenstrom folgt aus dem jeweiligen aktuellen Q_e bzw. Q_c und der integrierten D5-Enthalpiedifferenz. An jeder tatsächlichen Pfadposition gilt `V_i = 3600*m/rho(T_i)`. Daraus entstehen die beiden gemeinsamen Restgleichungen:

`R_e(Te,Tc) = dp_pump,e - sum(dp_loss,e) = 0`

`R_c(Te,Tc) = dp_pump,c - sum(dp_loss,c) = 0`.

Beide Gleichungen hängen jetzt über Q und P von beiden Temperaturen ab. Der vorhandene D6B1-Skalaransatz wird deshalb für diesen neuen Vertrag um eine gemeinsame zweidimensionale Suche erweitert. Bei jedem numerischen Versuch werden Verdichterstufe, beide Ströme und beide hydraulischen Pfade vollständig neu ausgewertet. Die globale Ableitungsrechnung enthält sowohl die D3-Temperaturableitungen als auch die Rückwirkungen über cp-Integral, Dichte, lokale Volumenströme, Drucktabellen und Pumpenordinaten.

Die Prüfung verlangt positive, vollständig nachgewiesene Q-/P-Bereiche jedes einzelnen Verdichters. Ein nichtphysikalischer Einzelverdichter kann nicht durch eine positive Gesamtsumme verdeckt werden. Am endgültigen Punkt wird D3 erneut aufgerufen; die ausgegebenen Leistungen stammen aus dieser Endauswertung. Zusätzlich werden Wärmeübertrager-/Sekundärenthalpie-, Kältekreis- und Pumpendruckresiduen gleichzeitig kontrolliert.

Der zusätzliche ausgegebene `compressor_cop` ist ausdrücklich `Q_cold/P_compressor` mit Scope `compressor_cooling_capacity_over_compressor_input_only`. Er ist weder Heiz-COP noch Maschinen-EER/SEER/SCOP. Andere D3-Ausgaben bleiben je Verdichter in der vorhandenen Stufenausgabe erhalten. Kennlinienrevisionen werden zusätzlich je Instanz ausgegeben; vollständige Hash-/Quellen-/Profilprovenienz bleibt im gespeicherten Ergebnis enthalten.

## Konvergenz, Eindeutigkeit und Existenz

Der gesamte erklärte rechteckige Te-/Tc-Suchbereich muss innerhalb der nachgewiesenen Verdichtereinsatzgrenzen liegen. Die vorhandenen Fluid-, Wärmeübertrager-, Komponenten-/Loop-Durchfluss-, Druck- und Pumpengrenzen bleiben verbindlich; es gibt keine Extrapolation oder stillschweigende Erweiterung. Jede vollständige Zertifizierungszelle wird zusätzlich auf gültige flüssige Zustände, Sekundärtemperaturen, Komponentenströme und Pumpen-Anwendungsbereiche geprüft. Ein ungültiger schmaler Rand zwischen den numerischen Zentren wird dadurch nicht übersehen. Die Zertifizierung teilt das Rechteck in 8×8 geschlossene Zellen. Jeder Zellenmittelpunkt erhält eine tatsächliche vollständige D3-/D5-Auswertung. Intervall-Autodifferentiation schließt zusätzlich die Druckreste und die vollständige 2×2-Jacobimatrix über jede ganze Zelle ein; Stützpunkte allein gelten nicht als Nachweis.

Für den globalen Eindeutigkeitsnachweis müssen die beiden diagonalen Ableitungen im gesamten Rechteck jeweils ein festes, von null getrenntes Vorzeichen haben. Sei `a=min(abs(J11))`, `d=min(abs(J22))`, `b=max(abs(J12))`, `c=max(abs(J21))`. Das nach außen gerundete Intervall für `a*d-b*c` muss strikt positiv sein. Gäbe es zwei verschiedene Nullstellen, ergäbe die Integration beider Zeilen entlang ihrer Verbindungsstrecke gleichzeitig `a*abs(deltaTe)<=b*abs(deltaTc)` und `d*abs(deltaTc)<=c*abs(deltaTe)`; dies widerspricht der positiven Produktmarge. Die Intervallrechnung schließt auch die angrenzenden Zweige an Tabellenknoten ein.

Dieser hinreichende Nachweis ist bewusst konservativ. Mehrere Lösungen, unterbestimmte Systeme oder eine nicht nachweisbare Eindeutigkeit liefern `ambiguous_or_uniqueness_not_certified` und keine Lösung. Dieser Status behauptet keine numerisch ermittelte Anzahl von Nullstellen. Ein Testmodell hat die kalte Bilanzdifferenz `0.2*(Te-4)*(Te-6)` und damit unabhängig analytisch nachweisbar zwei gültige Punkte bei Te=4 und Te=6; es wird entsprechend gesperrt.

Nach dem globalen Nachweis verwendet die gemeinsame Suche begrenzte Newton-Schritte mit der 2×2-Jacobimatrix und höchstens zwölf Halbierungen je Schrittsuche. Kandidaten außerhalb des erklärten Rechtecks werden nicht ausgewertet. Eine Schrittsuche muss den tatsächlichen maximalen Druckrest verkleinern. Singularität, Stagnation, unzureichende Genauigkeit oder das Iterationslimit führen zu einem gesperrten Ergebnis; kein letzter Iterationsstand wird übernommen.

Auch kleine Residuen genügen allein nicht. Ein lokaler Krawczyk-Nachweis muss mit nach außen gerundeter Rechnung eine strikte innere Einschließung liefern:

`K = x - C*R(x) + (I-C*J(X))*(X-x)`, mit `K` strikt innerhalb `X` und `X` innerhalb des gültigen Suchbereichs.

`C` ist ein numerischer Vorbehandler aus der Punkt-Jacobimatrix. Die Einschließung beweist Existenz; der globale Nachweis liefert Eindeutigkeit. Die Abstände des eingeschlossenen Nullstellenbilds zum ausgegebenen Punkt liefern die Temperaturfehlergrenzen. Randtreffer ohne solchen inneren Existenznachweis bleiben gesperrt. Die numerischen Druckreste müssen zudem innerhalb ihrer unabhängig bestimmten Punktintervalle liegen.

Die Fixture verwendet `1e-7 kW`, `1e-6 Pa`, `1e-8 K` und höchstens 60 gemeinsame Lösungsiterationen. Wie zuvor erlaubt der Vertrag höchstens 80 Iterationen und Toleranzen von `1e-9` bis `1e-4` in der jeweiligen Einheit. Zertifizierungszellen, numerische Versuche, Iterationen, Q/P/COP pro Versuch, Jacobieinschließungen, Produktmarge, Restintervalle und Existenz-/Temperaturnachweis werden ausgegeben. Der Nachweis gilt für das registrierte synthetische mathematische Modell und ist keine reale Gerätefreigabe.

## Unabhängige synthetische Kennlinien und Soll-/Ist-Vergleich

Sämtliche Koeffizienten sind frei erfunden. Mit Temperaturen in °C und Leistungen in kW lauten die beiden unterschiedlichen Verdichtersätze:

| Verdichter | Q(Te,Tc) | P(Te,Tc) |
| --- | --- | --- |
| A | `10.5 + 0.1*Te - 0.02*Tc` | `1.475 + 0.005*Te + 0.01*Tc` |
| B | `22.5 + 0.3*Te - 0.08*Tc` | `4.075 + 0.015*Te + 0.02*Tc` |
| Summe | `33 + 0.4*Te - 0.1*Tc` | `5.55 + 0.02*Te + 0.03*Tc` |

Die primären Fixtures speichern W-Koeffizienten; ein weiterer Fall mischt ausdrücklich W- und kW-Kennlinien mit demselben normierten Ergebnis. Weitere Tests verwenden steilere positive Kennlinien sowie kubische C7-/C8-Terme. Kein neuer Produktionscode wertet diese Gleichungen separat aus; alle Zahlen kommen aus D3.

Die konstante Flüssigkeitsreferenz und die vollständigen Pfade entsprechen D6B1: rho=1000 kg/m³, cp=4000 J/(kg K), epsilon=0,5; Pumpen `12600-1000*V` und `8358.75-1000*V` Pa, kalter Gesamtwiderstand `2500*V`, heißer Gesamtwiderstand `1500*V` Pa mit V in m³/h. Daraus folgen unabhängig hydraulisch V_e=3,6 und V_c=3,3435 m³/h, m_e=1 und m_c=0,92875 kg/s. Die thermischen Leitwerte sind 2 und 1,8575 kW/K.

Die erste Referenz ergibt die unabhängigen linearen Gleichungen `2.4*Te-0.1*Tc=7` und `0.42*Te-1.9275*Tc=-94.275`. Die Tests lösen die allgemein ausgeschriebenen Referenzgleichungen zusätzlich mit Cramers Regel; sie verwenden nicht den gekoppelten Solver als Sollwertgeber.

| Referenz | Soll | Tatsächliches Ergebnis |
| --- | --- | --- |
| Erster Punkt, Eintritt 20/30 °C | Te=5, Tc=50 °C | exakt 5/50 °C |
| Q/P/Q_hot am ersten Punkt | 30 / 7,15 / 37,15 kW | exakt 30 / 7,15 / 37,15 kW |
| Verdichter-COP erster Punkt | 30/7,15 | 4,195804195804196 |
| Zweiter Punkt, Eintritt 21,1 / `52-37.43/1.8575` °C | Te=6, Tc=52 °C | 6,000000000000003 / 51,99999999999999 °C |
| Q/P/Q_hot am zweiten Punkt | 30,2 / 7,23 / 37,43 kW | 30,200000000000003 / 7,23 / 37,43000000000001 kW |
| Verdichter-COP zweiter Punkt | 30,2/7,23 | 4,177040110650069 |
| Sekundäraustritte erster Punkt | 12,5 / 40 °C | 12,5 / 40 °C |
| Sekundäraustritte zweiter Punkt | 13,55 / 41,92462987886945 °C | 13,550000000000002 / 41,924629878869446 °C |

Der erste Punkt benötigt eine gemeinsame Iteration und 65 numerische Versuche einschließlich der 64 Zertifizierungszentren; der zweite fünf Iterationen und 69 Versuche. Beide Betriebsarten verwenden dieselbe gemeinsame Rechengrundlage. Im Wärmepumpenmodus wird die heiße Wärmeübertragerleistung als Heizleistung an der erklärten Flanschgrenze ausgegeben; im Kühlmodus bleibt die Kälteleistung separat ausgewiesen.

Geänderte Q- oder P-Koeffizienten verschieben beide Temperaturen entsprechend den unabhängigen linearen Gleichungen. Eine um 500 Pa angehobene kalte Pumpenkennlinie ändert den Strömungs-/Wärmeübertragerbetrieb und darüber auch die tatsächlichen Verdichterwerte. Die alte konstante Kennlinie ergibt auch im ausdrücklich neuen dynamischen Vertrag den alten Sollpunkt; bestehende Version-2-Ergebnisse bleiben zusätzlich bytegleich.

## Variable Fluidstoffwerte und Residuen

Der variable Fall behält die vorhandenen erfundenen D6B1-Stoffwerte rho(T)=1040-2*T, cp(T)=3800+10*T und mu(T)=0,08-0,0005*T sowie die zur Viskosität proportional definierten Drucktabellen. Ein unabhängiger Test löst mit ausgeschriebenen Q-/P-, Enthalpie- und Druckgleichungen durch verschachtelte skalare Intervallhalbierung. Er benutzt weder D3/D4/D5 noch die Produktions-Solversuche zur Bestimmung der Solltemperaturen.

| Variable Referenz | Ergebnis |
| --- | ---: |
| Te | 4,753310257106061 °C |
| Tc | 48,64227598647105 °C |
| Q / P / Q_hot | 30,03709650419532 / 7,104334484736253 / 37,141430988931575 kW |
| Verdichter-COP | 4,227995819837928 |
| Kalter/heißer Sekundäraustritt | 12,37665512855303 / 39,321137993235524 °C |
| Kalter/heißer Massenstrom | 0,9945135539601127 / 0,9609415131270459 kg/s |
| Kalter/heißer Eintrittsvolumenstrom | 3,5802487942564056 / 3,5299892318952706 m³/h |
| Gemeinsame Iterationen / numerische Versuche | 5 / 69 |

Alle thermischen, sekundären Enthalpie- und Kältekreisreste dieser Referenzen betragen im Betrag höchstens ungefähr 7,11e-15 kW. Die numerischen Druckreste betragen maximal ungefähr 5,46e-12 Pa. Die konservativen eingeschlossenen Druckreste bleiben im Betrag unter 3,44e-10 Pa, die nachgewiesenen Temperaturfehler unter 3,91e-13 K. Die globale Produktmarge ist in allen drei Fällen strikt positiv, ungefähr 148594 / 162854 / 139919 Pa²/K².

Die ausdrücklich abgeführte Pumpenwärme beträgt für die beiden konstanten Fluidreferenzen ungefähr 27,315826875 W, im variablen Fall 27,41023716314683 W. Die vorhandene synthetische Thermostatgrenze und die außerhalb des Modells liegende Hilfs-/Standbywärme werden unverändert ausgegeben. Unbekannte reale Verluste oder Pumpenwärme werden nicht mit null ersetzt. Die örtlichen Volumenströme werden bei veränderter Dichte massenkonsistent neu berechnet.

## Sicherheit, Revisionen und ausgeführte Tests

Der vorhandene transaktionale `TechnicalDataset`-Zugang bleibt unverändert. Er prüft den vollständigen physischen Graph einschließlich relevanter inaktiver Abhängigkeiten, Owner/Mandanten, berechtigter Eltern-/Kindfreigabe, Benutzer-/Artikel-/Featureberechtigungen, Quellen-/Payload-/Freigabehashes, Profile und Entzüge. Der bestehende D3-Callback bindet die genaue Kennlinien-ID, Revision und Hash und hält seinen Snapshot fest. Während der Iteration werden keine anderen Kennlinienrevisionen ausgewählt. Elektrische Ausführung, Kältemittel, Saug-/Unterkühlungsbedingungen, Frequenz und Drehzahl bleiben an denselben Quellen-/Profil-/Kontextvertrag gebunden.

Ein Kennlinienwechsel wird als neue Kennlinien-, Profil- und Maschinenrevision geprüft. Die Tests zeigen ein verändertes Ergebnis des neuen Kennfelds bei unverändertem historischen Ergebnis. Entzug der tatsächlich benutzten alten Kennlinie sperrt die alte Maschine; ein vollständig unabhängiger Nachfolger bleibt trotz seiner `supersedes`-Historie berechenbar. Manipulierte Historien-Fremdschlüssel, Hashes, falsche Referenzbedingungen, fehlende Rechte, fremde Artikel und entzogene inaktive Profile werden abgewehrt. Auswertungen verändern keine technischen Daten, Artikel, Angebote oder Inquiry-Daten.

Tatsächlich ausgeführte Prüfungen:

- `php tests/items/thermohydraulic-polynomial.php`: 456 D6B2-Assertions und 2760 unabhängige vollständige D3-Vergleiche sämtlicher tatsächlich ausgewerteter Verdichterpunkte in den numerischen Varianten erfolgreich. Enthalten sind die beiden Betriebsarten, analytische Sollpunkte, variable Stoffwerte, Kennlinien-/Pumpenänderungen, W/kW, steile und kubische Kennlinien, Mehrdeutigkeit, Unterbestimmung, Nichtkonvergenz, ungültige Einzelverdichterbereiche und Randtreffer ohne Existenznachweis, vollständige Fluid-/Pumpendomänen einschließlich Frostgrenzen sowie explizite reversible Ventilwege.
- `python3 tests/items/technical-data.py`: 125 neue gespeicherte D6B2-Assertions erfolgreich; außerdem 41 Initialprüfungen, 54 D2B-Matrixfälle mit 163 Assertions und gespeicherte D3/D4A/D4B1/D4B2/D5A/D5B/D6A/D6B1-Regressionen mit 143/225/121/211/127/103/235/127 Assertions erfolgreich. Ausschließlich temporäre synthetische MariaDB mit eigenem Socket und `--skip-networking`, einschließlich der bestehenden echten Fresh-/1.1.7-Upgrade-Kontrollen.
- Reine D3/D4A/D4B1/D4B2/D5A/D5B/D6A/D6B1-Rechenregressionen: 58/78/124/190/93/75/91/69 Assertions erfolgreich.
- Vier vollständige D6B1-Ergebnis-JSONs gegen den ursprünglichen Rechner aus Commit `93f1ca7e`: bytegleich.
- Inquiry: `php tests/security/shop-inquiry-calculation.php` mit 16 Prüfungen und `php tests/security/inquiry-selection.php` mit 37 sowie 60 kumulativen Prüfungen erfolgreich.
- PHP-Syntax der sechs geänderten/neuen PHP-Dateien und `git diff --check` erfolgreich.

Keine Browser-/UI-, Produktionsdatenbank- oder reale OEM-Validierung. Private Danfoss-Daten wurden nicht ausgewertet oder in Git übernommen. Alle neuen Koeffizienten stammen ausschließlich aus frei erfundenen synthetischen Fixtures. Ein anfänglicher Testvergleich benutzte Objektidentität statt serialisierten Wertevergleich; der Vergleich wurde auf vollständige D3-Werte korrigiert. Produktionsprüfungen wurden dafür nicht gelockert.

## Geänderte Dateien und offene reale Nachweise

- `application/modules/items/services/ThermohydraulicCircuit.php`: gemeinsame dynamische Te-/Tc-Suche, D3 je Versuch, vollständige Ableitungsrückkopplung, Existenz-/Eindeutigkeitsnachweis und zusätzliche dynamische Ergebnisdaten; vorhandene Seiten-/Intervallkerne werden weiterverwendet.
- `application/modules/items/services/OperatingModeSchema.php`: ausdrücklich neuer Betriebsmodellvertrag Version 3 und unveränderte physische Topologie-/Stufenbindung.
- `tests/items/fixtures/thermohydraulic-polynomial.php`: freie synthetische Kennlinien und bestehende vollständig beschriebene D6B1-Anlage.
- `tests/items/thermohydraulic-polynomial.php`, `thermohydraulic-polynomial-db.php`, `technical-data.php`: unabhängige Referenzen, D3-Audit und gespeicherte Revision-/Sicherheitsregressionen.
- `tests/items/thermohydraulic-polynomial-review.md`: dieser Bericht.

Nicht unterstützt bleiben allgemeine Mehrkreis-/gemeinsame Fluidnetze, luftseitige vollständige Rückkopplung, gemeinsame mehrsektionale Wärmeübertrager, D5B-Parallelverteilung innerhalb dieses neuen vollständigen Kopplungsmodells, Rohrsegmente und offene Kreisläufe, automatische Pumpen-/Ventilauswahl, gekoppelte Wärmerückgewinnung, Integrated Free Cooling und automatische Maschinen-/Angebotsauswahl. Ein eindeutig erscheinender Punkt mit nicht zertifizierbarer globaler Jacobimatrix wird konservativ gesperrt. D6A-Rückgewinnung und vorhandene Mehrkreis-/D5B-Funktionen bleiben innerhalb ihrer bisherigen Grenzen verfügbar.

Reale Anwendung benötigt weiterhin vollständige OEM-Verdichter-/Wärmeübertrager- und Einsatzgrenznachweise, reale Fluid-/Druckverlust-/Pumpendaten und elektrische Bedingungen, belastbare Kältemittelzustands- und Energiegrenzen sowie tatsächliche Pumpen-/Motor-/Hilfswärmebeiträge. Je nach Konstruktion fehlen außerdem Steuerung, Schutzfunktionen, Ölmanagement, Umschaltung und Abtauung. Quellenrechte und technische Engineering-Freigaben bleiben erforderlich. Synthetische Konvergenz ist keine Hersteller- oder Produktfreigabe.

Keine SQL-Änderung, Migration oder manuelle Datenbanknacharbeit erforderlich. Neue Betriebsmodell-/Kennlinienverträge sind als neue unveränderliche Revisionen zu importieren. Kein Push, Merge, Tag oder Deployment. Keine Folgephase begonnen.
