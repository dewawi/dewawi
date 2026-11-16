# D7B2A – Interner Kandidatenvergleich und technische Datenlücken

## Umfang und Bedienung

Die vorhandene interne Inquiry-Auswahl wurde um eine temporäre Vergleichsansicht erweitert. Einstieg ist der Link **Technischer Kandidatenvergleich** an der Anfrage beziehungsweise in der technischen Einzelprüfung. Ein Mitarbeiter sucht im berechtigten Artikelbestand, fügt die tatsächliche Artikel-/Varianten-ID hinzu und wählt anschließend ausdrücklich eine Konfigurationsrevision oder einen gespeicherten Prüfstand derselben Inquiry und desselben Artikels. Es gibt keine automatisch gewählte neueste Revision, Empfehlung, Punktzahl oder technische Sortierung. Die manuell gewählte Spaltenreihenfolge bleibt erhalten.

Höchstens drei Kandidaten werden nebeneinander angezeigt; mobil werden die Zellen je Merkmal untereinander mit wiederholter Artikelidentität dargestellt. Kandidaten lassen sich entfernen. **Technische Einzelprüfung öffnen / ausdrücklich speichern** öffnet den bestehenden D7A-/D7B1-Workflow. Dort werden Berechnungsart und Bedingungen weiterhin manuell gewählt und Prüfungen ausschließlich mit der bestehenden Speichertaste gespeichert. Historische Prüfstände können dort ausdrücklich als Ausgangspunkt einer neuen Prüfung verwendet werden.

Der Vergleich verwendet GET auf der bestehenden `contacts/inquiry/select`-Route mit `compare=1` und einer begrenzten Kandidatenliste. Die URL hält die aktuelle Auswahl; es gibt keine neue Tabelle, Vergleichssitzung, Sitzungshistorie oder automatische Speicherung. Vergleichs-POSTs werden zurückgewiesen. Die Route bleibt privat mit `Cache-Control: private, no-store`.

## Wiederverwendete Schnittstellen und Vergleichsvertrag

`Contacts_Service_InquiryTechnicalComparison::compare()` erzeugt **Vergleichsvertrag Version 1**. Wiederverwendet werden:

- `InquiryTechnicalReview::internalEnabled()` und der bestehende technische Prüfungszugriff. `canCompare()`/`comparisonContext()` ergänzen eine lesende Vergleichsgrenze mit `items.technicaldataset.review`.
- `InquiryTechnical::open()` für unveränderte Inquiry-Anforderungen, bestehende Artikelsuche, konkrete Revisionsauswahl, geprüften Maschinensnapshot und verfügbare Berechnungsarten.
- `TechnicalDataset::ownerForRead()`, `machineRevisions()` und `inspectMachine()` sowie das bestehende ownerbezogene Artikelmodell. Keine parallele Artikel-/Variantenauflösung und keine SKU-Heuristik.
- `InquiryTechnicalReview::history()` und `open()` für unveränderliche gespeicherte Ergebnisse und getrennte heutige Prüfung. Die Speichermethoden und das D7B1-Nachweisschema bleiben unverändert.

Pro Kandidat enthält der Vertrag Artikel-ID/SKU/Bezeichnung/Basisartikel, Variantensignatur/Optionen/Ausstattung, exakte Maschinenidentität mit Revision und SHA-256, Familie, Kühlart, Aufstellung, Kältemittel, Betriebsarten und Freigabestände, Kreis-/Verdichterzahl, physische Komponenten mit Profil-/Paketbindung, kompakte Paketidentitäten und Freigabemetadaten, Berechnungsmöglichkeiten, Anforderungsbewertungen, elf Nachweisbereiche und konkrete Blocker. Vollständige OEM-Koeffizienten und Quelltabellen werden nicht in den Vergleich kopiert.

Grenzen: drei Kandidaten, bestehende Suche mit 2–100 Zeichen und maximal 20 Treffern, bis zu 100 ausdrückliche Konfigurationsrevisionen nach bestehender API, letzte 50 Inquiry-Prüfungen in der jeweiligen Auswahlliste, maximal 128 physische Komponenten und 256 Paket-/Profilknoten je gewähltem aktuellem Snapshot. Ältere Prüfstände bleiben über den vorhandenen D7B1-Verlauf mit Versionscursor erreichbar und können mit expliziter Prüfungs-ID verglichen werden. Kandidateneingaben akzeptieren ausschließlich Artikel-ID und gegebenenfalls vollständige Paket-ID/Revision/Hash beziehungsweise Prüfungs-ID. Falsche Bindungen, doppelte identische Auswahlen und manipulierte IDs werden abgewiesen.

## Anforderungen und Aussagegrenzen

Die ursprünglichen aktiven Inquiry-Felder werden mit Beschriftung, Originalwert und Originaleinheit angezeigt. Nulltemperaturen bleiben `0 °C`; nicht vorhandene Werte werden nicht als null Leistung dargestellt. Für bekannte technische Bedeutungen müssen Feldname, Beschriftung und Einheit übereinstimmen. Bei Auswahlfeldern müssen außerdem der ursprüngliche Wert und seine tatsächliche Optionsbeschriftung passen. Eine umgedeutete Auswahl `0` ist deshalb kein automatischer Kühlbetrieb. Unbekannte Formulare/Feldbedeutungen werden angezeigt, aber nicht geraten.

Der Vertrag unterscheidet vorhandene, fehlende, uneindeutige und widersprüchliche Anforderungen; vergleichbare oder fehlende Artikelmerkmale; abweichende Aufstellung; nicht verfügbare Berechnung und unvollständigen technischen Nachweis. Nur eindeutig zugeordnete Aufstellung und deklarierter Modus werden als strukturelles Merkmal gegenübergestellt. Auch dieser Zustand ist ausdrücklich **kein Leistungsnachweis**.

Kälte-/Heizleistung, Temperaturen und Volumenstrom erhalten ohne passende vorhandene Berechnungsart den Status **Technische Berechnung nicht verfügbar**. Ist ein solches Modell vorhanden, bleibt der konkrete kundenseitige Leistungsnachweis **unvollständig**. Der Vergleich führt keine neue Betriebspunktberechnung aus und setzt keine Kundeneingaben automatisch in einen Rechner ein. Sonstige Optionen, Glykolkonzentration, Bezugsart, Produkt und Zusatzanforderungen bleiben mit ihren ursprünglichen Angaben sichtbar, soweit im Formular erfasst; unbestätigte technische Zuordnungen bleiben offen. W/kW werden nicht umgedeutet; es gibt weder angenommene Temperaturabstände noch Fluid-/Konzentrationsumrechnungen.

Nur eindeutig erfasste Vorlauf-/Rücklauftemperaturen in °C und eine bestätigte Kühl-/Heizanwendung werden auf widersprüchliche Reihenfolge geprüft. Ein historisch gespeicherter Zustand `unsuitable` wird als **Grenzverletzung am damaligen Betriebspunkt** gekennzeichnet. Er wird weder zur pauschalen Maschinenbewertung noch zum Nachweis einer Grenzverletzung für heutige Kundeneingaben umgedeutet.

## Nachweisabdeckung und Beschaffungsbedarf

Je Kandidat werden diese elf Bereiche mit exakten gebundenen Referenzen und verständlichem Prüf-/Beschaffungsbedarf unterschieden:

| Bereich | Benötigter Nachweis |
| --- | --- |
| Verdichter / elektrische Ausführung | Tatsächlich verbaute Komponenten, Spannung in V, Phasen und Ausführung |
| Kältemittel / Kennlinie | Passende Kennlinie für den exakt gebundenen Verdichter und das jeweilige Kältemittel |
| Einsatzgrenzen / Bedingungen | Envelope, Temperaturbasis, Drehzahl/Frequenz, Überhitzung/RGT und Unterkühlung |
| Verdampfer | Kennfeld des tatsächlich verbauten Wärmeübertragers und gültige Medien-/Durchflussbedingungen |
| Verflüssiger | Kennfelder gebundener Verflüssiger und gegebenenfalls Rückgewinnungswärmeübertrager |
| Pumpen / Hydraulik | Pumpenkennlinie, Fluiddaten und Druckverluste des tatsächlichen Strömungswegs |
| Regelung / Betriebsstufen | Gebundene Kreis-/Stufenregelung und zulässige Teillastzustände |
| Parallelbetrieb / Öl | Herstellergrenzen bei mehreren Verdichtern je Kreis; bei Einzelverdichtern als nicht erforderlich gekennzeichnet |
| Wärmeverluste / Bilanz | Gebundenes Bilanzmodell und reale Verlust-/Nebenleistungsnachweise |
| Betriebsartenfreigabe | Freigabe jeder vorgesehenen Betriebsart der konkreten Konfiguration |
| OEM-Referenzen | Eindeutig gebundene reale Maschinenmessung/Herstellerreferenz samt technischer Abnahme |

Zustände sind fehlend, unvollständig, nur synthetische Referenzdaten, vorhanden/nicht freigegeben, Datenpaket freigegeben, entzogen und topologisch nicht erforderlich. Ein freigegebenes Datenpaket wird ausdrücklich nicht als Maschinenfreigabe ausgegeben. Ein Bilanzmodell allein beweist noch keine realen Wärmeverluste; die Beschaffungsangabe und globalen technischen Blocker bleiben sichtbar.

Es werden nur sicher importierte, exakt im geprüften Snapshot gebundene Daten betrachtet. Eine ungebundene Artikelbezeichnung wie **ZCE114** erhält keine erfundene Komponentenliste. **DSH090/R410A und DSH090/R454B** werden nicht anhand des Namens zusammengeführt; Artikel-, Profil-, Paket-, Revisions- und Kältemittelbindungen bleiben maßgeblich. Zusätzliche Danfoss-Dateien und mathematische Komponentenreferenzen werden nicht als OEM-Maschinenabnahme verwendet. In diesem Auftrag wurden keine echten OEM-Pakete importiert oder produktiven Herstellerdaten geprüft.

Nachgewiesene Modellgrenze: Bestehende `reference_points` können Maschinenreferenzen mit Maschinenrevision, Maschinenprofil und Abnahme dokumentieren. Der aktuelle Maschinenkonfigurationsvertrag besitzt jedoch keinen eigenen Vorwärtsverweis auf einen solchen OEM-Referenzsatz. Der Vergleich sucht deshalb nicht global nach ähnlich benannten Referenzen und meldet fehlende **im Snapshot gebundene** OEM-Nachweise. Für eine spätere direkte Referenzabdeckung ist eine ausdrücklich beauftragte, owner-/revisionsgeprüfte Referenzbindung nötig; hier wurde weder das Schema erweitert noch eine vermeintliche OEM-Abnahme konstruiert.

## Reproduzierbares Beispiel mit zwei Kandidaten

Ausschließlich in der isolierten synthetischen Inquiry **#918** werden zwei Varianten desselben Basisartikels #2603 verglichen:

| Merkmal | Variante #2804 | Variante #2805 |
| --- | --- | --- |
| SKU | DEMO-D7B2A-R410A | DEMO-D7B2A-R454B |
| Konfiguration | fixture:d7b2a:R410A:machine:2 @1 | fixture:d7b2a:R454B:machine:2 @1 |
| Kältemittel | R410A | R454B |
| Verdichter / Kreise | 4 / 2 | 4 / 2 |
| Verdichterpolynom / Stufensumme | Synthetische Referenzdaten vorhanden | Synthetische Referenzdaten vorhanden |
| Maschinenkennfelder / OEM-Abnahme | Fehlend | Fehlend |
| Anfrage: 31 kW | Keine vollständige Maschinenberechnung verfügbar | Keine vollständige Maschinenberechnung verfügbar |
| Technischer Status | review_required, eligible=false | review_required, eligible=false |

Die Reihenfolge entspricht dem Hinzufügen, auch wenn die Eingabereihenfolge umgekehrt wird. Es wird keine der Varianten empfohlen. Alternativ lässt sich ein vorhandener gespeicherter Prüfstand für Artikel #2604 in eine Spalte nehmen. Sein synthetischer früherer Betriebspunkt mit 7,15 kW Verdichterleistung bleibt unverändert sichtbar, samt damaligen Bedingungen, Variante, Umfang und Gründen. Das ist keine reale Herstellerleistung und kein Vergleichswert zur Erfüllung der angefragten 31 kW.

## Rechte, Historie und kaufmännische Grenzen

Der interne Pilot bleibt standardmäßig aus. Die bestehende strikte Allowlist `DEWAWI_SELECTION_INTERNAL_CLIENTS` und `items.technicaldataset.review` sind zusätzlich zu Inquiry-/Artikel-/Technikleserechten erforderlich. Der Vergleich selbst benötigt kein Inquiry-Bearbeitungsrecht; das ausdrückliche Speichern weiterhin schon. Elternfreigaben ersetzen weder die Pilotfreigabe des aktuellen Mandanten noch Benutzerrechte. Der tatsächliche ausgewählte Mandant, berechtigte Items-/Shops-Owner, aktive Identität und indirekte Komponenten-/Profil-/Optionsreferenzen werden durch die vorhandenen Schnittstellen geprüft. Öffentlicher Site-Kontext und fremde Kandidaten sind gesperrt.

Historische Resultate und die damalige Varianten-/Komponentenidentität stammen unverändert aus dem vorhandenen Prüfungsnachweis. Heutige Revisionen, Entzüge, Integritätsfehler und Kundenänderungen bleiben separat. Kann ein heute ungültiger technischer Graph nicht mehr geöffnet werden, bleibt der zugängliche geprüfte historische Nachweis sichtbar; aktuelle Paketdaten werden dann nicht ausgegeben. Rechtefehler werden dadurch nicht übergangen. Es gibt keine Neuberechnung oder Überschreibung gespeicherter Werte.

Alle Kandidaten behalten `eligible=false` und `review_required`, kaufmännisch `rollout_blocked`. Die interne Freischaltung ist kein SaaS-Entitlement. `InquiryWorkflow::createQuoteDraft()`, ursprüngliches Kunden-JSON, SKU, Preise, Angebotspositionen und historische PDFs wurden nicht geändert. Formular 865 bleibt `manual`/`automatic_quote=false`.

## Verifikation

Abschließende Läufe erfolgreich:

- `python3 tests/items/technical-data.py` mit Screenshot-Ausgabe unter `/tmp/dewawi-d7b2a-browser`: **44 Initialprüfungen**, **54 D2B-Matrixfälle / 166 Assertions**, gespeicherte D3/D4A/D4B1/D4B2/D5A/D5B/D6A/D6B1/D6B2-Prüfungen mit **143/225/121/211/127/103/235/127/125 Assertions**, **63 D7A**, **103 D7B1** und **74 neue D7B2A-Assertions** bestanden. Echte Neuinstallation, Upgrade vom Repository-Tag 1.1.7, partielle/wiederholte gezielte DDL, Fremdschlüssel und Parallelität eingeschlossen. Ausschließlich temporäre MariaDB mit eigenem Socket und `--skip-networking`.
- Die 74 D7B2A-Fälle umfassen ein/zwei Kandidaten und konkrete Varianten, Revisionen/Hashes, getrennte Kältemittel, fehlende Kennfelder, vorhandene/unverfügbare Berechnungen, Nulltemperatur und Originaleinheiten, fehlende/negative/uneindeutige/widersprüchliche Anforderungen, umgedeutete Auswahlbeschriftung, unveränderte alte Resultate/Varianten, neue Revision, Entzug, beschädigten heutigen Graph, historische Grenzverletzung, fremde Artikel/Inquiry/indirekte Komponenten, geänderte Variantenidentität, Gast-/Site-Kontext, technische Rechte, Parent-Child-Grenzen, Pilotstandard aus, Such-/Kandidatenlimits und manipulierte Eingaben. Vollständige Vorher-/Nachher-Snapshots sichern unveränderte Kunden-, Formular-, Angebots- und Prüfdaten ab.
- Echte lokale HTTP-Aufrufe und **Chromium bei 390 und 1280 px**: bestehende D7A-Auswahl/Berechnung sowie D7B1-Speichern/Öffnen/Replay/Übernahme unverändert erfolgreich. Neuer Vergleich: Einstieg über Inquiry, manuelle Suche/Hinzufügen, ausdrückliche Revisionen, zwei Varianten/Kältemittel, Kennfeldlücken und Originaleinheiten, Entfernen, Öffnen der Einzelprüfung mit ausdrücklich gewählter Berechnungsart, vorhandene Speichertaste, historische Ergebnisse samt heutigem Status, keine Vergleichsspeicherung, keine Eignungsfreigabe, HTML-Escaping und kein horizontaler Überlauf. Fremde Inquiry/Gast/manipulierte Parameter und Kandidatenlimits über HTTP abgewiesen. Screenshots `comparison-390.png` und `comparison-1280.png` visuell geprüft.
- `python3 tests/security/shop-inquiry-quote.py`: **57 Katalog-, 95 Varianten- und 764 Angebots-/Transaktions-/PDF-/Replay-Prüfungen** sowie vorhandene Inquiry-/Katalog-/Varianten-HTTP- und Chromium-Abläufe bei 390/1280 px bestanden.
- `php tests/security/contact-inquiries.php`: **46**, `permissions.php`: **415**, `inquiry-selection.php`: **37/60 kumulativ**, `inquiry-pilot.php`: **37/70 kumulativ**, `shop-inquiry-calculation.php`: **16**, `tests/sales/quote-monitoring.php`: **293**, `tests/documents/pdf.php`: **183**, `pdf-totals.php`: **219** Prüfungen bestanden.
- Syntax aller **14 geänderten/neuen PHP-/PHTML-Dateien** und beider Python-Dateien sowie Diff-Prüfung erfolgreich.

Während der Entwicklung wurden eine unvollständige synthetische Artikelzuordnung und zwei Browser-Testannahmen an den bestehenden ausdrücklichen Berechnungsablauf beziehungsweise die vorhandene Übersetzung korrigiert. Der neue indirekte Owner-Negativfall führte zur oben beschriebenen frühen Ablehnung im vorhandenen Variantenhelfer. Der abschließende gemeinsame Lauf ist ohne diese Fehler und ohne PHP-Warnungen erfolgreich.

Nicht ausgeführt: produktive Datenzugriffe/-änderungen, echte OEM-Validierung oder eine vollständige ERP-Sitzung mit produktivem Loginformular/Hauptmenü. Die HTTP-Fixture bootstrapt einen synthetischen Benutzer/Owner und verwendet echte Controlleraktionen, Dienste und Views. Die unveränderten reinen Rechenkerne wurden über die gespeicherten technischen Regressionen geprüft; separate vollständige numerische Standalone-Matrizen wurden nicht zusätzlich wiederholt.

## Dateien und Betrieb

Geändert wurden ausschließlich der bestehende Inquiry-Controller, `InquiryTechnicalReview`, die Inquiry-Partials `select`, `technical`, `technical-entry`, `technical-reviews`, `css/ui.css`, deutsche/englische Inquiry-Übersetzungen und die bestehende isolierte Testintegration. `TechnicalDataset::options()` weist einen nicht mehr lesbaren Artikel jetzt vor dem Zugriff auf seine Variantenfelder zurück; der neue indirekte Owner-Negativfall hatte dort bisher Warnungen vor der späteren Ablehnung ausgelöst. Neu sind `InquiryTechnicalComparison`, `technical-comparison.phtml`, `inquiry-technical-comparison-db.php`, `inquiry-technical-comparison.py` und dieser Prüfbericht. Keine Rechenkerne, Artikel-/Variantenmodelle, Quote-/PDF-Dienste, `global.js` oder SQL-Dateien wurden geändert.

**D7B2A benötigt keine Migration und keine SQL-Nacharbeit.** Die bereits vorhandene D7B1-Tabelle muss regulär installiert sein; ihre früher dokumentierte Nachführung bleibt unabhängig davon bestehen. Es wurden keine Rechte oder Pilotkonfigurationen außerhalb der isolierten Tests gesetzt. Ein interner Einsatz benötigt die bestehende ausdrücklich autorisierte Allowlist und passenden Benutzerrechte.

Eine spätere automatische Auswahl benötigt reale freigegebene Maschinen-/OEM-, Komponenten-, Fluid-/Hydraulik-, Regelungs- und Betriebsartennachweise, eine sichere direkte OEM-Referenzbindung, eindeutig typisierte kundenseitige Betriebspunkte und einen separat beauftragten Auswahl-/Freigabevertrag. D7B2A führt keine Folgephase aus.

Lokaler Commit und Kennung werden im Abschluss genannt. **Kein Push, Merge, Tag oder Deployment.**
