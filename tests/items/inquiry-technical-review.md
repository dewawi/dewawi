# D7A – Interne technische Auswahl und Inquiry-Integration

## Umsetzung und Bedienablauf

Auf der bestehenden Inquiry-Bearbeitungs- und Ansichtsseite steht für berechtigte Mitarbeiter der Bereich **Interne technische Prüfung** bereit. Er öffnet den privaten Pfad `/contacts/inquiry/select/id/<Inquiry-ID>`. Der Mitarbeiter sucht einen vorhandenen Artikel nach Artikelnummer/Bezeichnung, wählt den konkreten Artikel und anschließend ausdrücklich eine vorhandene technische Revision. Es gibt keine vorausgewählte Maschine, automatische Empfehlung oder Sortierung nach Eignung.

Die Ansicht zeigt die ursprünglichen Kundenangaben mit den tatsächlich hinterlegten Feldbeschriftungen und Einheiten. Fehlende aktive Angaben erscheinen als „Nicht angegeben“, numerische Null bleibt erhalten. Die vorhandene FormConfig-Normalisierung bestimmt die Feldabhängigkeiten; beispielsweise erscheint eine Heizleistungsfrage nicht als fehlende Kühlanforderung. Unbekannte oder ungültige Formularkonfigurationen werden nicht als vermutete Feldzuordnung verwendet; die ursprünglichen Anfragefelder bleiben lesbar.

Nach der Revisionsauswahl werden Maschinenfamilie, Artikel-/Basisartikelidentität, Betriebsarten, Kältekreis- und Verdichteranzahl, Komponentenartikel, Profilrevisionen, Variantensignatur und Ausstattung angezeigt. Die Quellenansicht enthält die gebundenen Datenrevisionen, Hashes, Quellenbelege, Freigabezeitpunkte/-bearbeiter und Entzüge. Komponentenartikel werden aus den tatsächlich gebundenen Profilen aufgelöst; sie müssen nicht im Wurzelpaket der Maschine stehen. Es gibt keine Namens- oder Leistungszuordnung zwischen Artikeln, insbesondere keine automatische DSH090-/ZCE114-Verknüpfung.

Die Rechnung wird ausdrücklich über ein getrenntes POST-Formular ausgelöst. Typisierte Temperatureingaben sind zunächst leer. Ergebnisse zeigen mathematischen Status, Modellgrenze, konkrete berechnete Werte und Einheiten, verwendete Prüfbedingungen, Sperrgründe und Datenherkunft. Häufige Sperrgründe haben deutsche/englische Erläuterungen; die ursprünglichen Kennungen bleiben nachvollziehbar. Weitere Vertragsdiagnosen bleiben in den Rechendetails sichtbar. Fehlende Ergebnisse erscheinen nicht als Null. Warnhinweise verwenden die vorhandenen `dw-alert--warning`-Stile und behaupten keine grüne Maschinenfreigabe.

Der Rückweg führt in die bestehende Inquiry-Ansicht und deren manuelle Kunden-/Angebotsbearbeitung. Die technische Prüfung ruft keine dieser schreibenden Workflow-Funktionen auf.

## Formular 865 und Einheiten

Die lokal vorhandene Pilot-Referenz `form-865.pilot.fields.json` unter `/tmp/dewawi-inquiry-pilot-865-2026-11-13/` wurde ausschließlich lesend geprüft. Sie enthält `selection.method=manual` und `automatic_quote=false`. Tatsächliche Felder sind unter anderem:

| Feld | Bedeutung / Einheit |
| --- | --- |
| `einsatzbereich` | Prozesskühlung / Komfortklimatisierung / Heizung / Warmwasser |
| `kuehlleistung` | Erforderliche Kälteleistung, kW; für Kühlanwendungen |
| `heizleistung` | Erforderliche Heizleistung, falls bekannt, kW; für Heizanwendungen |
| `vorlauf_temp` | Gewünschte Vorlauftemperatur zum Verbraucher, °C |
| `ruecklauf_temp` | Rücklauftemperatur vom Verbraucher, °C |
| `medium`, `glykol_typ` | Wasser / Wasser-Glykol / sonstiges sowie Glykoltyp |
| `glykol_anteil`, `glykol_bezugsart` | Prozentangabe mit ausdrücklich getrenntem Massen-/Volumenbezug |
| `glykol_bezugstemperatur` | Bezugstemperatur, °C; abhängig vom Konzentrationsbezug |
| `volumenstrom` | Erforderlicher Volumenstrom, falls bekannt, m³/h |

Zur Laufzeit werden die mandanten- und shopbezogenen tatsächlichen Formulardefinitionen aus `Inquiry::getDetail()` verwendet, nicht die lokale Referenzdatei und keine fest codierte 865-Zuordnung. Vorlauf/Rücklauf werden nicht als Te/Tc oder als Verflüssigereintritt interpretiert. Es gibt keine Änderung oder produktive Aktualisierung von Formular 865.

## Wiederverwendete Berechnungsschnittstellen

Alle Rechnungen laufen über die bestehenden gespeicherten `Items_Service_TechnicalDataset`-Schnittstellen, nicht über frei aufrufbare reine Rechenkerne:

| Tatsächlicher Vertrag | Interner Aufruf / Eingabe |
| --- | --- |
| Gebundene Verdichterkennlinie | `evaluateCompressor`; Te/Tc mit expliziter Einheit °C |
| Maschinenkonfiguration Version 1 | `evaluateMachineStage`; ausdrückliche Stufe und Te/Tc pro Kältekreis |
| Gebundenes thermisches Modell, ein / mehrere Kreise | `evaluateThermalCircuit` / `evaluateThermalMachine` |
| Gebundener hydraulischer Pfad Version 1 / 2 | `evaluateHydraulics` / `evaluateHydraulicDistribution` |
| Maschinenkonfiguration Version 2, Betriebsmodell Version 1 | `evaluateOperatingMode`; gewählte Betriebsart/Stufe, Te/Tc und gebundene Ventilzustände |
| Betriebsmodell Version 2 / 3 | `evaluateThermohydraulic`; Flüssigkeitseintritt am Verdampfer und Verflüssiger; vorhandener D6B1-/D6B2-Rechner |

Thermische und hydraulische Teilauswertungen verwenden in dieser ersten Oberfläche eine explizite JSON-Eingabe des bestehenden Vertrags. Sie haben noch keinen eigenen vollständigen Feldeditor. Die Oberfläche erklärt Einheiten und Konzentrationsbezug; die vorhandenen Services prüfen Vollständigkeit, Grenzen und Topologie. Die Tests führen diese Eingabepfade mit vollständigen synthetischen Randbedingungen aus.

Die Berechnungsauswahl wird ausschließlich aus dem geprüften physischen Graphen aufgebaut. Ein nicht gebundener Paketbezeichner oder Methodenname ist nicht auswählbar. Betriebsarten und Stufen aus Version-2-Konfigurationen bleiben an ihre konkreten Betriebsmodelle und Ventilzustände gebunden. Der historische Begriff `reversible_heating` wird weiterhin ausdrücklich als historischer Vertrag angezeigt; keine Umdeutung in `heat_pump`.

Standardmäßig gilt der technische Modus der bestehenden Schnittstellen. Eine synthetische Test-/Demorechnung muss ausdrücklich angekreuzt werden, erfordert zusätzlich das bestehende technische Importrecht samt Artikelbearbeitungsrecht und akzeptiert nur Graphen, deren sämtliche Datenpakete `production_import_allowed=false` ausweisen. Die Oberfläche ergänzt niemals einen realen Vertrag um synthetische Ersatzwerte.

## Beispiel einer geprüften Anfrage

Die vollständig erfundene Test-Inquiry fordert 31 kW und nennt 0 °C Vorlauf; Rücklauf und Volumenstrom fehlen. Diese Angaben werden unverändert dargestellt. Der Mitarbeiter wählt den synthetischen Variantenartikel mit Basisartikel, dessen Maschinenrevision und **Cooling / full / Betriebsmodell v3**. Er gibt separat 20 °C am Verdampfer und 30 °C am Verflüssiger ein und bestätigt die Test-/Demorechnung.

Der bestehende dynamische Rechner ergibt Te=5 °C, Tc=50 °C, 30 kW Kälte, 37,15 kW Wärmeabgabe und 7,15 kW Verdichteraufnahme. Diese Werte gehören ausschließlich zur synthetischen Modellgrenze und den separat eingegebenen Prüfbedingungen. Sie behaupten keine Erfüllung der Kundenanforderung und keine reale Maschinenleistung. Trotz Konvergenz und `fully_coupled=true` bleibt der Maschinenstatus `review_required`, `eligible=false`; kaufmännisch besteht keine Angebotsfreigabe. Fehlende reale OEM-, Kältemittelzustands-, Regelungs- und Wirkungsgradnachweise bleiben sichtbar.

## Persistenzentscheidung und unveränderte Workflows

`shopinquirydata` enthält die ursprüngliche Kundendaten-JSON sowie Status, Kontakt-/Angebotsreferenz und allgemeine Änderungsmetadaten. Es gibt kein getrenntes internes technisches Auswahl-/Prüfobjekt mit unveränderlicher Revisionsreferenz und historischen Prüfvermerken. Ein internes Ergebnis in `data` oder `_selection` würde Kunden-/öffentliche Auswahlmetadaten vermischen; das Überschreiben des einzigen JSON-/Auditstands bewahrt keine unabhängige technische Prüfhistorie.

Daher wird die ausdrücklich erlaubte **lesende D7A-Variante** umgesetzt. Auswahl, Berechnung und Prüfvermerk werden nicht gespeichert. Es gibt keinen scheinbar wirksamen Speichern-Knopf und keine ungesicherte Notizfunktion. Jede Navigation/POST-Anforderung löst ihre explizite Artikel-/Revisionsbindung erneut auf. Es wird keine allgemeine Historienverwaltung erfunden.

D7A verändert keine Kundenwerte, Artikelpreise, Angebotspositionen, Dokumente oder Shopformulare. Es startet keine Empfehlung, automatische Position/Preisfestlegung, PDF-Erstellung oder E-Mail. Die vorhandene manuelle Angebotsentwurfserstellung bleibt im bestehenden Workflow. D7A übernimmt auch keine synthetischen Kennwerte dorthin.

## Mandanten, Rechte und Feature-Grenzen

Der neue Dienst verlangt zunächst den bestehenden privaten D2B-Kontext: aktuelle Anmeldung mit passendem aktivem Benutzerkonto, zulässiger ausgewählter Mandant und tatsächliche zulässige Items-Owner-/Parent-Beziehung, `items.item.view` und ausdrücklich `items.technicaldataset.view`. Hinzu kommen `contacts.contact.view` und die konkrete, separat shopbezogen gelesene Inquiry mit zulässigem Shop-Datenowner. Übergebene `clientid`-Parameter werden nicht als Berechtigung verwendet.

`select` gehört bereits zur bestehenden ACL-Leseklasse. Direkte URL- und POST-/AJAX-Aufrufe durchlaufen zusätzlich die serverseitigen Dienstprüfungen. Der vollständige Graph einschließlich inaktiver Komponenten, aktueller Identitäten, Revisionen, Payload-/Quellen-/Freigabehashes und Entzügen wird über den vorhandenen transaktionalen Snapshot geprüft. Ein zurückliegender Seitenaufruf kann eine spätere Entziehung nicht umgehen. Revisions-/Integritätskonflikte ergeben HTTP 409, fehlende Berechtigungen HTTP 403 und ungültige Eingaben HTTP 422. Antworten sind `private, no-store`; Berechnungs-POSTs verlangen einen zufälligen sitzungsgebundenen CSRF-Token.

Die private technische Ansicht legt auch kein Anhangsverzeichnis an. Dafür überspringt ein kleiner Initialisierungs-Hook ausschließlich bei `contacts/inquiry/select` die sonst im Basiscontroller vorhandene Verzeichnisanlage. Alle bisherigen Controllerpfade behalten ihr Verhalten.

**Eigenständige SaaS-Entitlements:** Im geprüften Repository existiert kein interner Abo-/SaaS-Entitlement-Dienst und kein entsprechender D2B-Schlüssel. `DEEC_Permission` prüft Benutzerrechte. `DEEC_Site_Context::hasFeature()` und die Shop-Flags steuern öffentliche Website-Funktionen; sie sind keine interne SaaS-Lizenz und werden nicht dafür umgedeutet. Ein SiteContext wird für private technische Daten ausdrücklich abgewiesen, auch wenn er `inquiry`/`catalog` erlaubt. Es wurde kein paralleles Lizenzsystem eingeführt. Eine spätere vermarktete Selection-Funktion braucht einen separat definierten, serverseitigen Entitlement-Vertrag; diese bestehende Plattformlücke ist nicht durch Benutzerrechte gelöst.

## Geänderte Dateien

- `contacts/controllers/InquiryController.php`: private lesende Prüfaktion, Eingabe-/CSRF-/HTTP-Fehlerbehandlung und Verzeichnis-Hook.
- `contacts/services/InquiryTechnical.php`: autorisierte Inquiry-/Artikelauswahl, Feldabhängigkeiten, vertragsgebundene Berechnungsauswahl und Weiterleitung an gespeicherte APIs.
- `contacts/services/InquiryView.php`, `inquiry/detail.phtml`: tatsächliche Feld-Einheiten auch in bestehenden Inquiry-Details.
- `inquiry/edit.phtml`, `view.phtml`, `technical-entry.phtml`, `select.phtml`, `technical.phtml`: Einstieg, Anforderungen, Konfiguration, Quellen, Eingaben und Ergebnisdarstellung.
- `items/services/TechnicalDataset.php`: begrenzte revisionsbezogene Artikelsuche und lesende Maschineninspektion auf dem vorhandenen Graphprüfer; keine Änderung der Rechenmodelle.
- `library/DEEC/Controller/Action.php`: standardmäßig unveränderter Hook für Anhangsverzeichnis-Initialisierung.
- `css/ui.css`, deutsche/englische Inquiry-Übersetzungen: begrenztes responsives Layout und Beschriftungen. **Keine JavaScript-Datei geändert**, keine neue AJAX- oder Frontend-Struktur. Alle neuen Formulare setzen `data-autosave=false`; Navigation erfolgt mit bestehenden serverseitigen Views und normalen Formularen.
- `tests/items/inquiry-technical-db.php`, `technical-data.php/.py`, `tests/security/fixtures/inquiry-technical-http.php`, `tests/ui/inquiry-technical.py`: isolierte DB-, Controller-, HTTP- und Browserprüfungen.

## Ausgeführte Prüfungen

- Gezielt `python3 tests/items/technical-data.py --inquiry-technical-only`: **63 D7A-Assertions** erfolgreich. Enthalten sind D3, D4A, beide thermischen Teilpfade, Hydraulik/Parallelverteilung, alle vier D6A-Betriebsarten und dynamische D6B2-Kopplung, originale Null-/fehlende Werte, Varianten/Komponenten, falsche Paket-/Artikel-/Inquiry-IDs, Mandanten, zulässige Parent-Freigabe, technische/Artikel-/Inquiry-Leserechte, Importrecht, Gast, deaktiviertes Konto, SiteContext, CSRF, manipulierte Arrays, Revision/Hash, Entzug und unveränderte Business-/Technikdaten.
- Neue reale lokale HTTP-Requests: privates GET/POST/AJAX, Gast-/Fremd-Inquiry-Sperre, CSRF-Sperre, Hashkonflikt sowie unveränderter DB-Zustand erfolgreich.
- **Chromium 390 und 1280 px:** tatsächlicher Inquiry-Einstieg, Artikel-/Revisionsauswahl, Komponenten/Variante, zunächst leere Felder, Browserpflichtfelder, synthetische POST-Rechnung, getrennte Statusdarstellung, OEM-Blocker, HTML-Escaping und kein horizontaler Seitenüberlauf erfolgreich. Screenshots wurden unter `/tmp/dewawi-d7a-ui/technical-390.png` und `technical-1280.png` visuell geprüft.
- Gespeicherte D2B–D6B2-Regressionen: 41 Initialprüfungen, 54 D2B-Matrixfälle mit 163 Assertions sowie D3/D4A/D4B1/D4B2/D5A/D5B/D6A/D6B1/D6B2 mit **143/225/121/211/127/103/235/127/125** Assertions erfolgreich. Echte Fresh-/1.1.7-Upgrade-Kontrollen in temporärer MariaDB mit eigenem Socket und `--skip-networking`.
- Reine D3–D6B2-Rechenregressionen: **58/78/124/190/93/75/91/69/456** Assertions erfolgreich, einschließlich **2760 unabhängiger D3-Vergleiche** in D6B2.
- `python3 tests/security/shop-inquiry-quote.py`: **57 Katalog-, 95 Varianten- und 764 Angebots-/Transaktions-/PDF-/Replay-Prüfungen** sowie bestehende Inquiry-/Katalog-/Varianten-HTTP- und Chromium-Abläufe erfolgreich.
- `python3 tests/ui/inquiry-pilot.py`: bestehende Formular-865-Navigation und Abhängigkeiten bei 390/1280 px erfolgreich.
- `shop-inquiry-calculation.php`: 16; `inquiry-selection.php`: 37/60 kumulativ; `inquiry-pilot.php`: 37/70; `contact-inquiries.php`: 46; `permissions.php`: 415; `quote-monitoring.php`: 293; `pdf.php`: 183; `pdf-totals.php`: 219 Prüfungen erfolgreich.
- PHP-Syntax aller geänderten/neuen PHP-/PHTML-Dateien, Python-Syntax der beiden Runner und `git diff --check` erfolgreich.

Die ersten Socket-/Chromium-Aufrufe wurden durch die Sandbox eingeschränkt; die isolierten Tests wurden anschließend mit gezielter Ausführungsfreigabe erfolgreich durchgeführt. Im Entwicklungsdurchlauf wurden eine Testisolation mit verbleibenden POST-Werten und die anfangs unvollständige Komponentenanzeige korrigiert. Die Produktionsprüfungen wurden dafür nicht gelockert.

Die HTTP-/Browser-Fixture verwendet ausschließlich synthetische Benutzer-/Mandantenkontexte, den echten neuen Controllerpfad, Dienste und Views. Die bestehende Inquiry-Detail-/Einstiegspartial wird gerendert; Produktions-Bootstrap, Loginformular, Hauptmenü und Anhangsverzeichnisanlage werden dabei nicht als vollständige ERP-Sitzung durchlaufen. Keine Produktions- oder Kundendatenbank, reale OEM-Validierung, produktiven Datenimporte oder Live-ERP-Browserprüfung durchgeführt.

## Grenzen, Datenbank und Folgeschritte

Keine SQL-Änderung, neue Tabelle, Migration oder manuelle Datenbanknacharbeit. Bestehende Installationen benötigen nur den normalen, vom Benutzer gesteuerten Code-Übernahmeprozess; es wurde nichts deployt. Für die Nutzung sind bereits vorhandene private technische Leserechte und passende autorisiert importierte Datenbindungen erforderlich. Testdaten werden nicht in ein bestehendes System importiert.

Offen für einen gesonderten Auftrag bleiben revisionssichere interne Prüfpersistenz mit Bearbeiter/Zeit, ein eigenständiger SaaS-Entitlement-Vertrag, weitergehende Feldeditoren für D4-/D5-Randbedingungen und gegebenenfalls Pagination über mehr als 100 Maschinenrevisionen eines Artikels. Aktuelle technische Freigaben sind keine dauerhafte historische Prüfhistorie. Quellenrechte, vollständige reale OEM-Daten und Engineering-Freigaben bleiben Voraussetzung realer Maschinennutzung. Integrated Free Cooling, neue Rechenmodelle, automatische Empfehlungen und Angebotsfreigaben gehören nicht zu D7A.

Lokaler Commit und exakte Commitkennung werden im Abschluss genannt. Kein Push, Merge, Tag oder Deployment. D7B wurde nicht begonnen.
