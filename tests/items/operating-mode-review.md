# D6A – Betriebsarten, Wärmerückgewinnung und Heat Pump

## Ergebnis und Freigabegrenze

D6A ergänzt den privaten Items-Datenpfad um unabhängig prüfbare synthetische Betriebspunkte für `cooling`, `partial_heat_recovery`, `full_heat_recovery` und `heat_pump`. Wärmepumpenbetrieb benötigt eine belegte Wärmequelle, Wärmesenke und tatsächliche Topologie. Eine ausschließlich heizende Konfiguration benötigt weder einen Kühlmodus noch ein Umschaltventil. Die zusätzliche Eigenschaft `refrigerant_reversible=true` verlangt dagegen Kühlung und Wärmepumpenbetrieb mit tatsächlich vertauschten Wärmeübertragerrollen und unterschiedlichen, physisch gültigen Ventilwegen.

Alle Ergebnisse bleiben `eligible=false`, `status=review_required` und `fully_coupled=false`. Eine berechenbare synthetische Bilanz ist keine reale technische oder kaufmännische Freigabe. Es gibt keine UI-, Inquiry-, Sales- oder PDF-Anbindung, automatische Auswahl, Produktfreigabe oder allgemeine Simulationsengine. Der standardmäßige gespeicherte Aufruf im Modus `technical` liefert keine reale Heizberechnung. Synthetische Auswertung erfordert ausdrücklich `synthetic_reference` und die bestehenden privaten View-/Importrechte.

## Versionierte Datenverträge und Kompatibilität

- `machine_configuration.data.configuration_version=2` ergänzt genau einen Kältekreis um feste physische `refrigerant_connections`, eine ausdrückliche Reversibilitätseigenschaft und je Betriebsart einen revisionsgebundenen `operating_model_ref`. Feste Leitungen werden nicht beim Betriebsartenwechsel umgeschrieben. Die bisherigen Komponenten, Profile, Artikel, hydraulischen Loops und Stufen werden weiterverwendet. `cooling_kind` bleibt eine nominale Bauartangabe des bisherigen Maschinenkopfs; eine verfügbare Betriebsart ergibt sich ausschließlich aus ihrem tatsächlichen Modell.
- `operating_mode_model`, Vertragsversion 1, beschreibt aktive/isolierte Komponenten, unterstützte Stufen, Verdampfer-/Verflüssiger-/Rückgewinnungsrollen, deren tatsächliche Anschlüsse und Loops, Expansionsrichtung, erforderliche Ventilstellungen, Energiebilanzreferenz und ausdrücklich tabellierte Punkte. Der einzige zugelassene Verwendungszweck ist `synthetic_test_only`.
- `heat_exchanger_map`, Vertragsversion 2, beschreibt konkrete synthetische Punkte mit Kältemittel-Massenstrom, gemeinsamen Enthalpiebezug, Ein-/Austrittszuständen aus Temperatur, Druck, Enthalpie und Phase sowie unabhängigem sekundärseitigem Wärmestrom, Temperaturen, Fluidrevision, Zusammensetzung und Volumenstrom. Einheiten und Betriebsgrenzen sind geschlossen vorgegeben. Es gibt weder Interpolation noch Extrapolation, Zustandsfunktion oder ausführbare Importformel.
- `component_spec` kann für eine technische Option einen registrierten synthetischen Kältemittelventilvertrag tragen. Jede Stellung muss jeden physischen bidirektionalen Anschluss genau einmal verbinden oder schließen. Neue bidirektionale Kältemittelports werden ausdrücklich deklariert; bestehende gerichtete Anschlüsse werden nicht stillschweigend umgedeutet.
- Die bestehende D4-Energiebilanz `thermal_balance_model` Version 1 und D3-Polynome werden unverändert verwendet. Das synthetische Modell verlangt hermetische Verdichter ohne externe Energieleckage, isenthalpe Expansion sowie belegte Nullverluste der Kältemittelleitungen. Diese Annahmen sind ausdrücklich registrierte Modellgrenzen, keine Annahmen über reale Geräte.

Ein fehlendes `configuration_version` bleibt der unveränderte bisherige Vertrag. Bestehendes `reversible_heating` bleibt unverändert als nicht unterstützte Kennung erhalten; es wird weder gelöscht noch zu `heat_pump` umbenannt. Der neue Rechner akzeptiert diese alte Kennung nicht als Alias. Bestehende Datensätze erhalten keine zusätzlichen normalisierten Standardfelder und keine geänderten Hashes. Neue Verträge werden als neue Revisionen importiert; historische Daten werden nicht umgeschrieben.

Der neue Zugang `TechnicalDataset::evaluateOperatingMode(id, revision, hash, request, mode)` delegiert bisherige Kühlkonfigurationen vollständig an `ThermalMachine`/`ThermalCircuit`. Nur der neue API-Ergebnisumschlag ergänzt dabei `fully_coupled=false`. Die bisherigen D4-APIs und ihre numerischen Ergebnisse bleiben unverändert. Version-2-Konfigurationen und Punktkennfelder können nicht durch einen alten D4-Aufruf unter Umgehung der neuen Prüfungen ausgewertet werden. Neue Version-2-Kühlpunkte werden wie die anderen neuen Betriebsarten anhand ihrer expliziten Zustandsdaten geprüft; sie ersetzen keine D4-Betriebspunktsuche.

## Unterstützte und gesperrte Topologien

Unterstützt sind im neuen Vertrag genau ein Kältekreis, parallele Verdichter auf eindeutigen Saug-/Heißgasheadern, eine Expansionsstrecke und einsektionale Platten-/Rohrbündelwärmeübertrager an voneinander getrennten Flüssigkeitsloops. Jeder Betriebspunkt hat eine explizite Stufe und eine eindeutige tabellierte Te-/Tc-Kombination. Die Leitungsprüfung erkennt getrennte Wege, falsche Anschlüsse, Kurzschlüsse, zyklische Header, unbenutzte Ventilwege und weiterhin angebundene angeblich isolierte Wärmeübertrager. Zusätzliche Verdichtereinspritz-/Kühlports werden gesperrt. Inaktive Verdichter verwenden ausdrücklich das synthetische Modell ohne Kältemittelstrom; ihre elektrische Standbyaufnahme und Datenabhängigkeiten bleiben berücksichtigt.

Teilwärmerückgewinnung verlangt einen separaten `recovery_exchanger` im Heißgasweg vor dem Verflüssiger. Seine Austrittszustände müssen mit dem Eintritt des nachfolgenden Verflüssigers übereinstimmen. Die Rückgewinnungsleistung folgt aus den tatsächlichen expliziten Zustands- und Sekundärdaten; ein Prozentfaktor ist kein zugelassenes Eingabefeld. Heißgasenthitzung kann sekundärseitig oberhalb Tc arbeiten, sofern die beidseitigen Temperaturabstände und überhitzten Zustände belegt sind.

Vollwärmerückgewinnung verlangt einen dafür ausgewiesenen Rückgewinnungswärmeübertrager als tatsächlichen Verflüssiger. Die nutzbare Wärme wird aus seinem überprüften Sekundärstrom bestimmt. Die getrennte Energiebilanz muss erst anschließend bestätigen, dass innerhalb dieser synthetischen Grenze keine anderweitige Wärmeabgabe fehlt.

Die reversible Referenz verwendet dieselben festen Leitungen, einen Vierwege-Kältemittelschalter, bidirektional geeignete Wärmeübertrager und eine tatsächlich umgekehrte Expansionsrichtung. Bloße Rollenbezeichnungen oder eine falsch angeforderte Schalterstellung reichen nicht aus.

Neue mehrkreisige Wärmebetriebsarten, gemeinsam thermisch gekoppelte Sekundärloops, mehrsektionale neue Betriebsarten, luftseitige Wärme-/Abtaubetriebsarten und allgemeine Leitungs-/Ventilnetze werden nicht berechnet. Bestehende ein- und mehrkreisige Wasser-/Luftkühlung einschließlich der D5B-Übergabe bleibt über den bisherigen Vertrag verfügbar. Reale Betriebspunkte bleiben ohne OEM-Nachweise gesperrt.

## Unabhängige Energiebilanzen

Alle Referenzen sind erfunden, ausdrücklich synthetisch gekennzeichnet und ohne Herstellerdaten. Bei Te=5 °C und Tc=50 °C liefern zwei unterschiedliche Verdichter zusammen 30 kW Kälteleistung, 7,15 kW elektrische Verdichteraufnahme und 300 kg/h = 1/12 kg/s Kältemittelstrom. Die expliziten spezifischen Enthalpien sind:

| Zustand | Enthalpie kJ/kg | Temperatur °C | Druck kPa |
| --- | ---: | ---: | ---: |
| Verdampfereintritt nach Expansion, zweiphasig | 200 | 5 | 300 |
| Verdampferaustritt / Sauggas, überhitzt | 560 | 15 | 300 |
| Verdichteraustritt, überhitzt | 645,8 | 80 | 1200 |
| Austritt Teilwärmerückgewinnung, überhitzt | 597,8 | 60 | 1200 |
| Verflüssigeraustritt, flüssig | 200 | 50 | 1200 |

Damit ergeben sich unabhängig `m*(560-200)=30 kW`, `m*(645,8-560)=7,15 kW` und 37,15 kW Gesamtwärmeabgabe. Die Expansion bewahrt die Enthalpie exakt im kanonischen Dezimalvergleich. Der Kältemittelstrom muss zu allen Wärmeübertragerpunkten passen. Die Verdichterreferenzen müssen dieselbe Saugbedingung, Unterkühlung und reine Sättigungstemperaturbasis haben. Vorhandene Verdichteraustrittstemperaturkennfelder dürfen den expliziten Headerzustand nicht widersprechen; die Referenz benutzt stattdessen ausdrücklich vorgegebene synthetische Zustandsdaten ohne solches optionales Kennfeld. Es werden keine Enthalpien aus Temperaturen geschätzt.

Die synthetische Flüssigkeit hat konstant 1000 kg/m³ und 4000 J/(kg K). Der kalte Sekundärstrom beträgt 10,8 m³/h, 20 → 17,5 °C, also unabhängig 30 kW Wärmeaufnahme. Der heiße Strom beträgt 3,6 m³/h. Die integrierte D5A-Fluidbilanz prüft jeden Ein-/Austritt einschließlich Zusammensetzung, Fluiddomäne und Frostschutz. Temperaturabstände verhindern Wärmeübertragung gegen die vorgegebene Richtung; der Zweiphasen-Verdampfereintritt muss zu Te passen. Kennfeld-, Komponenten- und Loop-Durchflussgrenzen sowie geforderte Heißwasseraustrittstemperaturen werden geprüft.

| Vollstufenfall | Rückgewinnung kW | Verbleibende Wärmeabgabe kW | Nutzbare Wärme an der deklarierten Grenze kW |
| --- | ---: | ---: | ---: |
| Reine Kühlung | 0 | 37,15 | 0 |
| Teilwärmerückgewinnung, 55 → 56 °C am Rückgewinner | 4 | 33,15 | 4 |
| Geänderter Rückgewinner, 55 → 55,5 °C | 2 | 35,15 | 2 |
| Vollwärmerückgewinnung, 30 → 39,2875 °C | 37,15 | 0 | 37,15 |
| Wärmepumpe, 30 → 39,2875 °C | 0 als separate Rückgewinnung | 0 | 37,15 |

Die geänderte Teilwärmerückgewinnung enthält ausdrücklich einen veränderten Zwischenzustand mit h=621,8 kJ/kg und 70 °C. Die kleinere Verdichterstufe liefert unabhängig Q=10 kW, P=2,05 kW, 100 kg/h und insgesamt 12,05 kW Wärme. Teilwärmerückgewinnung beträgt dort 1 kW, die restliche Verflüssigerwärme 11,05 kW. Der frühere mehrkreisige D4B2-Kühlreferenzfall bleibt bei 59,4 kW Verdampferwärme und 73,625 kW Verflüssigerwärme.

Die Prüfung vergleicht Kältemittel- und Sekundärwärme, Verdichterarbeit, Gesamtenergie und Massenströme getrennt. Die Fixture verwendet 1e-7 kW und 1e-9 kg/s Toleranz. Die geschlossenen Verträge erlauben nur begrenzte Toleranzen; Binary64-Rundungszulagen müssen innerhalb der gewählten Wärmetoleranz bleiben. Nicht auflösbare Enthalpiedifferenzen oder nichtendliche Ergebnisse sperren die Rechnung.

## Elektrische Grenze und Hydraulik

Jeder Betriebspunkt benötigt einen vollständigen elektrischen Eingabekatalog: aktive Verdichter kommen aus D3, alle übrigen physischen Komponenten einschließlich inaktiver Verdichter, Pumpen, Ventile, passiver Komponenten und Expansion tragen einen ausdrücklich belegten Wert oder `null`. Externe Hilfsaufnahme ist separat enthalten. Die registrierte Modellgrenze setzt diese zusätzlichen Wärmeeinträge außerhalb der Kältemittel- und nutzbaren Wärmegrenze voraus. Eine unbekannte Aufnahme wird nicht als Null eingesetzt: der betreffende COP bleibt `null`.

Die Referenz setzt passive/ideale Komponenten ausdrücklich auf 0 kW und externe Hilfsaufnahme auf 0,1 kW. Damit beträgt die gesamte elektrische Aufnahme 7,25 kW; Kühl-COP=30/7,25≈4,137931 und Heiz-COP=37,15/7,25≈5,124138. Der Heiz-COP wird ausschließlich für Vollwärmerückgewinnung und Wärmepumpe ausgewiesen. Bei der kleinen Stufe werden zusätzliche bekannte 0,2 kW Standby korrekt zu 2,35 kW Gesamtaufnahme addiert. Diese Zahlen gelten nur als synthetische Wärme am deklarierten Wärmeübertragerflansch je vollständig deklarierter elektrischer Aufnahme, nicht als freigegebener Maschinen-EER, SEER, SCOP oder kundenseitige Nutzwärmegarantie.

Optional übergebene hydraulische Bedingungen werden mit `FluidHydraulics` neu berechnet. Es werden keine vom Client behaupteten alten Pumpenergebnisse übernommen. Fluidrevision, Zusammensetzung, Betriebsarten-Instanz/Sektion, Eintrittstemperatur und Volumenstrom müssen zum jeweiligen Wärmeübertragerpunkt passen. `FluidThermalAudit::temperatures` prüft auch die tatsächlichen übrigen Pfad-, Pumpen- und Austrittstemperaturen. Eine belegte elektrische Pumpenaufnahme muss mit dem elektrischen Betriebspunktrand übereinstimmen. Fehlende/ungültige angeforderte Hydraulik sperrt die kombinierte Punktprüfung; ganz fehlende hydraulische Evidenz bleibt ausdrücklich als fehlend ausgewiesen.

Die positiven neuen Hydraulikreferenzen verwenden ausdrücklich ideale verlustfreie D5A-Pfade, eine Nullförderdruck-Pumpenkennlinie und damit 0 W Pumpenaufnahme. Das ist keine Annahme über reale Pumpen. D5B bleibt über die alte Kühlreferenz verfügbar. Die neuen Wärmebetriebsarten lösen keinen D5B-Parallelbetriebspunkt und keine Rückkopplung zwischen Wärme, Fluidtemperaturen, Druckverlusten und Pumpenförderung. `require_fully_coupled=true` wird gesperrt.

## Mandanten, Revisionen und Freigaben

Der gespeicherte Aufruf verwendet denselben transaktionalen vollständigen D2B-Maschinensnapshot einschließlich aller relevanten inaktiven und anderen Betriebsartenabhängigkeiten. Maschinen-/Komponentenartikel, Profile, Owner, berechtigtes Parent-Child-Sharing, Revisionen, Payload-/Quellenhashes, Freigabebelege und Entzüge bleiben geprüft. Neue Punktwärmeübertrager und Ventilspezifikationen erhalten dieselbe tatsächliche Artikelbindung wie hydraulische Komponenten; bestehende v1-Hashverträge werden nicht rückwirkend verändert. Ein gleicher symbolischer Komponentenname genügt nicht, um ein neues Punktkennfeld an einen fremden Artikel umzubinden.

Synthetische Betriebspunkte dürfen nur ausdrücklich synthetische technische Quellen verwenden. Reale oder unklar gekennzeichnete Komponenten ersetzen keine fehlenden OEM-Nachweise. Die bestehenden synthetischen Freigabesperren werden nicht gelockert. Tests injizieren Entzugs-/Manipulationszustände ausschließlich in der temporären Testdatenbank; der normale Freigabe-/Entzugszyklus bleibt zusätzlich durch D2B abgedeckt.

`supersedes` bleibt eine historische Beziehung und wird nicht zu einer zusätzlichen physikalischen Abhängigkeit. Ein unabhängiger Nachfolger verändert keine historische Rechnung. Ein entzogenes tatsächlich referenziertes Modell sperrt die betroffene Maschinenrevision; ein eigenständig vollständiger Nachfolger bleibt unabhängig nutzbar. Manipulierte Historien-Fremdschlüssel und Hashes werden weiterhin abgewiesen. Wiederholte Bewertungen verändern weder technische Datensätze noch Artikel oder Geschäftsdokumente.

## Integrated Free Cooling: nachgewiesene Lücken und spätere Erweiterungen

Integrierte Freikühlung wurde ausschließlich gegen die tatsächlich vorhandenen Verträge geprüft. Es wurden keine Freikühlungsservices, speziellen Kennfeldtypen oder Tabellen ergänzt. `mechanical_cooling`, `free_cooling` und `hybrid_cooling` sind zukünftige Zustände; sie werden derzeit ausdrücklich abgewiesen und nicht automatisch mit `cooling` gleichgesetzt.

| Nachgewiesene heutige Grenze | Erforderliche spätere Erweiterung |
| --- | --- |
| `TechnicalSchema::machinePaths` verlangt in jeder Stufe mindestens einen aktiven Verdichter. | Expliziter Verdichter-Aus-Zustand mit belegtem Pumpen-/Lüfter-/Standbybetrieb für reine Freikühlung. |
| Die vorhandenen thermischen Kennfelder bilden Kältemittel/Sekundärmedium ab; die neue v2-Punkttabelle hat ebenfalls zwingend Kältemittelzustände. | Belegtes Luft-/Flüssigkeits- oder Flüssigkeits-/Flüssigkeits-Freikühlerverhalten einschließlich Betriebsgrenzen; keine erfundenen Kältemittelzustände für einen Freikühler. |
| `machinePaths` erlaubt genau einen Flüssigkeitsein-/austritt je Komponente und feste serielle äußere Loops. | Bedingte Sekundärwege, zusätzliche Freikühlungswärmeübertrager, tatsächliche Dreiwegeventile, Bypass-/Mischwege und belegte Schaltstellungen. Ein zusätzlicher serieller Wärmeübertrager allein löst diese Lücke nicht. |
| Der D6A-Ventilvertrag beschreibt ausschließlich Kältemittelanschlüsse. | Getrennter physikalisch passender Sekundärventil-/Regelzustandsvertrag; kein Missbrauch des Kältemittelschalters. |
| `air_paths` unterstützt nur `parallel_coils_common_fan` mit `air_condenser`-Rollen und fester Zuordnung. | Explizite Freikühler-Luftpfade, zusätzliche Register, gemeinsame/getrennte Lüfter und deren tatsächliche Regelzustände. |
| D5B verteilt einen Strom auf genau zwei Sektionen desselben Wärmeübertragers bei einheitlich vorgegebener Temperatur. | Nachgewiesene Betriebsartenverteilung über Freikühler-/Verdampfer-/Bypasszweige mit den tatsächlichen Temperaturen und Rückwirkungen. |
| D6A prüft einen tabellierten Einzelpunkt ohne thermisch-hydraulische Rückkopplung. | Hybridanteile aus unabhängigen thermischen und hydraulischen Nachweisen, korrekte Serien-/Mischenthalpien sowie eindeutige Regel-/Umschaltbedingungen. |

Artikel-/Profilidentitäten, generische technische JSON-Pakete, vollständige Revisionsgraphen sowie D5A-Fluidenthalpie und Mischungsprüfung sind wiederverwendbar. Für einfache zusätzliche Bauteilidentitäten ist keine neue Tabellenstruktur erforderlich. Die heutigen geschlossenen Topologie- und Betriebszustandsverträge reichen jedoch für eine allgemeine integrierte Freikühlmaschine nicht aus: gezielte versionierte Vertragserweiterungen sind nachweislich notwendig. Eine spätere Umsetzung ohne jede strukturelle Erweiterung kann deshalb nicht zugesichert werden. Diese Lücken werden hier dokumentiert und nicht als D6A-Mehrumfang implementiert.

## Tests und geänderte Dateien

- `php tests/items/operating-mode.php`: 91 synthetische Betriebsarten-, Bilanz-, Stufen-, COP-, Ventil-, Temperatur-, Numerik-, Hydraulik- und Kompatibilitätsprüfungen erfolgreich.
- `python3 tests/items/technical-data.py`: 235 gespeicherte D6A-Prüfungen erfolgreich. Außerdem 41 Initialprüfungen, 54 D2B-Matrixfälle mit 163 Assertions und gespeicherte D3/D4A/D4B1/D4B2/D5A/D5B-Regressionen mit 143/225/121/211/127/103 Assertions erfolgreich.
- Reine D3/D4A/D4B1/D4B2/D5A/D5B-Rechenprüfungen: 58/78/124/190/93/75 Assertions erfolgreich. Inquiry: `php tests/security/shop-inquiry-calculation.php` mit 16 Prüfungen sowie `php tests/security/inquiry-selection.php` mit 37 und 60 kumulativen Prüfungen erfolgreich.
- PHP-Syntax aller 13 geänderten/neuen PHP-Dateien und `git diff --check` erfolgreich.

Der Datenbankrunner verwendet ausschließlich eine temporäre synthetische MariaDB mit eigenem Socket und `--skip-networking`, einschließlich seiner bestehenden Fresh-/Upgrade-Kontrollen. Keine Produktionsdaten, Browser-/UI-Prüfung oder reale OEM-Validierung.

Geänderte Implementierung:

- `application/modules/items/services/OperatingModeSchema.php`: geschlossene Verträge und Bindung tatsächlicher Leitungen, Rollen, Ventilstellungen und Leistungsgrenzen.
- `application/modules/items/services/MachineOperatingMode.php`: unabhängige synthetische Zustands-/Energiebilanz, COP-Grenze und optionale D5A-Prüfung.
- `application/modules/items/services/TechnicalSchema.php`, `TechnicalDataset.php`: versionierte Import-/Snapshot-/Artikelbindung und privater gespeicherter Zugang.
- `application/modules/items/services/MachineStage.php`: vorhandene D3-Stufenauswertung mit expliziter Betriebsartenzuordnung wiederverwendbar.
- `application/modules/items/services/HeatExchangerMap.php`, `ThermalCircuit.php`, `ThermalMachine.php`: klare v1/v2-Aufrufgrenzen ohne Änderung bisheriger Berechnungen.
- `application/modules/items/services/FluidThermalAudit.php`: vorhandene Temperaturprüfung für die Wiederverwendung öffentlich verfügbar.
- `tests/items/fixtures/operating-mode.php`, `operating-mode.php`, `operating-mode-db.php`, `technical-data.php` und dieser Bericht: synthetische Referenzen und Regressionen.

## Offene Nachweise und Voraussetzungen für D6B

Reale Anwendung benötigt herstellergebundene Zustands-/Phasen- und Wärmeübertragerdaten, gültige Verdichterreferenzen und vollständige Verlust-/Leistungsgrenzen. Erforderlich sind reale Druckverluste von Leitungen und Ventilen, zulässige Druck-/Temperaturbereiche und Wassergrenzen, tatsächliche Pumpen-/Lüfter-/Steuer-/Standbyleistungen sowie Nachweise zur realen hydraulischen Verteilung. Eine reale Stoffzustandsfunktion, Gliding-/Gemischmodellierung oder fehlende Motor-/Umgebungsverluste werden nicht durch die synthetischen h-/T-/p-Punkte ersetzt.

Je nach tatsächlicher Konstruktion fehlen außerdem Abtauung, Ölmanagement, sicherer Umschaltablauf, Ventileignung, Schutzfunktionen und Hersteller-Betriebsgrenzen. Für eine gekoppelte Maschine müssen Wärmeübertragerleistung, Fluidenthalpien, Temperaturen, Teilströme und Pumpenbetrieb mit gemeinsamen konsistenten Randbedingungen gelöst und unabhängig geprüft werden. Dazu kommen technische Engineering-Freigaben und die unveränderten Mandanten-/Rechte-/Entzugsregeln. D6A schafft dafür prüfbare Referenzgrenzen, keine D6B-Produktfreigabe.

Keine SQL-Änderung, Migration oder manuelle Datenbanknacharbeit erforderlich. Neue Verträge sind als neue technische Revisionen zu importieren; bestehende Konfigurationen und historische Geschäftsdokumente werden nicht umgeschrieben. Kein Push, Merge, Tag oder Deployment. Keine Folgephase begonnen.
