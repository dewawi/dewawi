# D7B1 – Persistente technische Prüfungen und Freigabeschutz

## Speicher- und Prüfworkflow

Die vorhandene D7A-Oberfläche bietet jetzt ausdrückliches Speichern, einen mandantenbezogenen Prüfungsverlauf, das erneute Öffnen historischer Ergebnisse und die Übernahme eines früheren Prüfstands als Ausgangspunkt einer neuen Version. Der bestehende Inquiry-Controller und dessen technische Auswahl bleiben der Einstieg. Neue JavaScript- oder Frontend-Strukturen wurden nicht eingeführt.

Der Mitarbeiter wählt weiterhin einen tatsächlichen Artikel, seine gebundene Variante und eine ausdrückliche technische Revision. Nach einer zulässigen Berechnung wird ein begrenzter serverseitiger Nachweis zum angezeigten Ergebnis vorbereitet. **Prüfung speichern** sendet nur einen zufälligen Nachweistoken und CSRF-Token. Es werden keine vom Browser behaupteten Ergebniswerte, Freigaben, Mandanten oder Herstellerdatensätze gespeichert.

**Prüfstand ohne Berechnung speichern** hält ausschließlich die geprüfte Artikel-/Varianten-/Revisionsbindung und gegebenenfalls die ausdrücklich gewählte Betriebsart/Stufe fest. Dieser Stand ist technisch unvollständig; ungesendete Recheneingaben werden nicht übernommen. Der Hinweis am Knopf erklärt, dass eingegebene Bedingungen und Ergebnisse erst nach „Prüfung berechnen“ Teil des serverseitigen Nachweises sind. Fehlende Werte bleiben fehlend.

Beim Wiederöffnen erscheint das damals gespeicherte Ergebnis ohne Neuberechnung. Aktuelle Quellen-/Revisionsintegrität, Freigaben, Entzüge und jüngere Revisionen werden separat geprüft und angezeigt. **Als Ausgangspunkt einer neuen Prüfung verwenden** stellt die alten Eingaben für eine ausdrückliche neue Prüfung bereit; es führt keine Berechnung aus und überschreibt keinen alten Datensatz.

## Tatsächliche Datenhaltung

Die bestehende `shopinquirydata`-JSON und ihre allgemeinen Änderungsfelder bieten keine getrennte unveränderliche technische Prüfhistorie. Die vorhandenen Archive beziehungsweise Dokumenthistorien sind für andere Fachobjekte bestimmt. Deshalb wird die ausdrücklich erlaubte einzelne zweckgebundene Tabelle **`inquirytechnicalreview`** ergänzt.

Jeder Eintrag enthält:

- Prüfungs-ID, Inquiry-ID und Inquiry-Datenowner (`clientid`).
- Artikel-ID und gesonderten Items-Datenowner (`itemsclientid`).
- Exakte Konfigurations-ID, Revision und Payload-Hash.
- Inquiry-bezogene fortlaufende Version und optionalen Vorgänger derselben Inquiry/desselben Owners. Abzweigungen von älteren Prüfständen sind erlaubt und nachvollziehbar.
- Getrennte mathematische und technische Status, Bearbeiter und Erstellungszeit nach bestehender Audit-Konvention.
- Einen begrenzten JSON-Nachweis mit Formatversion, damaligem ausgewähltem Mandanten/Bearbeiter, Hash der Kundenangaben, Artikel-/Basisartikelidentität, Variantensignatur/Ausstattung und konkreten Komponenten-/Profilbindungen.
- Tatsächlich validierte Recheneingaben mit den Einheiten des vorhandenen Vertrags, Berechnungstyp, Rechenversionskennung, Betriebsart/Stufe soweit fachlich vorhanden, Modellgrenze, konkrete Ergebnisgrößen mit Einheiten und damalige Blocker. Bei reinen Teilauswertungen sind nicht festgelegte Maschinenzustände ausdrücklich leer; es wird keine vollständige Maschinenprüfung behauptet.
- Exakte technische Paket-/Profilreferenzen und Hashes einschließlich damaliger Freigabe-/Entzugsdaten sowie Quellenprüfsummen.
- Separate Payload- und Metadatenprüfsummen und einen eindeutigen Wiederholungsschlüssel.

Die Prüfstandsstruktur wird serverseitig aus den vorhandenen geprüften Schnittstellen erzeugt und durch ein geschlossenes, typisiertes Nachweisschema geprüft. Vollständige OEM-Koeffizienten, Druckverlusttabellen, Quellendateien und numerische Iterationstraces werden nicht dupliziert. Ergebniszahlen werden als rücklesbare Dezimaltexte des tatsächlich berechneten Binär64-Werts gespeichert; die bestehende exakte Import-Normalisierung wird dafür nicht gelockert. Der gesamte Nachweis ist auf 128 KiB begrenzt. Anfrageinhalt und personenbezogene Kundenangaben werden nicht als zweite Kopie archiviert; der Nachweis hält ihren Hash fest.

Auch bei gesperrten Teilauswertungen werden die verschachtelten Basiseingaben mit den vorhandenen technischen Feldtypen und den gebundenen Stufen-/Gruppen-/Komponenten-/Kreiskennungen geprüft. Manche bestehenden Rechner prüfen diese Teile nach einer frühen Freigabesperre noch nicht; ihre Sperrantwort allein reicht deshalb nicht als Speichernachweis. Unbekannte JSON-Felder, zusätzliche Quelltabellen und manipulierte Zusammensetzungsobjekte werden nicht archiviert. Erfolgreiche erweiterte D4-/D5-Auswertungen mit optionalen Zusatzverträgen bleiben lesend verwendbar, erhalten bis zur entsprechenden Erweiterung des begrenzten Nachweisvertrags jedoch keinen Speichertoken. Die Oberfläche weist darauf ausdrücklich hin.

## Historisierung, Status und Aktualität

Es existiert kein Bearbeiten-/Löschen-Pfad für gespeicherte Prüfungen. Das neue Tabellenmodell verweigert direkte `insert`, `update` und `delete`-Aufrufe; der Speicherpfad fügt einen neuen validierten Eintrag innerhalb seiner Transaktion an. Payload- und Metadatenintegrität werden beim Lesen geprüft. Fremdschlüssel sichern Inquiry, Artikel, technischen Owner und Vorgängerbeziehung. Die Anwendung überschreibt historische Ergebnisse auch bei späteren Änderungen nicht.

Die gespeicherten technischen Zustände sind `review_required`, `technical_incomplete` und `unsuitable`; mathematisch wird zwischen `calculated`, `failed` und `not_calculated` unterschieden. Eine nachgewiesene Einsatzgrenzverletzung wird gesondert als ungeeignet geführt. Fehlende technische Nachweise und fehlende Ergebnisse werden nicht mit Null ersetzt.

Beim Öffnen kommen unabhängig davon `historical_changed` oder `historical_invalid` hinzu: etwa bei neuerer technischer Revision, geänderter Freigabe, entzogener Abhängigkeit, veränderter Variantenbindung, veränderten Kundenangaben oder beschädigter technischer Integrität. Die aktuellen gebundenen Freigaben/Entzüge sind in einer eigenen Detailansicht nachvollziehbar. Die Verlaufsliste zeigt historische Status; die aktuelle technische Prüfung erfolgt beim Öffnen eines Eintrags. Der Verlauf ist mit 50 Einträgen pro Seite und Versionscursor begrenzt.

**Alle gespeicherten und aktuell bewerteten Maschinen bleiben `eligible=false`.** Ein synthetisches `fully_coupled=true`, das Speichern selbst oder die Benutzerkennung sind keine technische Hersteller-/Maschinenfreigabe. Kaufmännisch bleibt der Stand `rollout_blocked`; eine SaaS-Berechtigung wird nicht behauptet.

## Atomarität, Wiederholungen und Parallelität

Nachweistoken sind an Bearbeiter, ausgewählten Mandanten, Inquiry und technische Owner gebunden. Sie bleiben eine Stunde gültig; pro Sitzung werden höchstens acht vorbereitet gehalten. Abgelaufene oder verdrängte Nachweise führen zu einem sichtbaren Konflikt und erfordern eine neue ausdrückliche Prüfung. Ein Client kann den Nachweisinhalt nicht als JSON ersetzen.

Beim Speichern werden Login, Rechte und Owner erneut geprüft. Die bestehenden technischen Snapshot-Prüfungen und die Speicherung laufen unter derselben technischen Owner-Sperre und Datenbanktransaktion. Dafür unterstützt die vorhandene Maschineninspektion einen optionalen internen Consumer; bestehende Aufrufe ohne Consumer behalten ihr Ergebnis und Verhalten. Die tatsächliche Inquiry-Zeile wird gesperrt, bevor die nächste Versionsnummer vergeben wird. Geänderte Kundenangaben, technische Nachweise oder Varianten seit der Berechnung ergeben einen Konflikt, statt das alte Ergebnis einer neuen Grundlage zuzuordnen.

Ein eindeutiger Schlüssel aus Owner, Bearbeiter und serverseitigem Wiederholungsschlüssel verhindert doppelte Einträge. Das erneute Absenden eines gültigen bereits gespeicherten Formulars liefert dieselbe Prüfungs-ID. Zwei neue gleichzeitige Speicherungen erhalten getrennte aufeinanderfolgende Versionen. Beides wurde mit unabhängigen PHP-Prozessen und getrennten DB-Verbindungen geprüft.

## Benutzerrechte, Owner und SaaS-Ergebnis

Die vorhandenen D2B-/D7A-Prüfungen bleiben Voraussetzung: aktuelles aktives Benutzerkonto, berechtigter ausgewählter Mandant, zulässige tatsächliche Parent-Child-Datenowner, Zugriff auf die konkrete shopbezogene Inquiry sowie `contacts.contact.view`, `items.item.view` und `items.technicaldataset.view`. Die Kenntnis einer Prüfungs-ID reicht nicht zum Lesen; Verlauf, Vorgänger, historische Ergebnisse und heutige technische Daten werden getrennt ownerbezogen geschützt.

Speichern benötigt zusätzlich **`contacts.contact.edit` und `items.technicaldataset.review`**. Dieses neue eng begrenzte Recht verwendet die bestehende Permission-JSON und wird durch die Migration niemandem erteilt. Es ersetzt weder technische Import-/Freigaberechte noch ein SaaS-Entitlement. Synthetische Berechnungen benötigen weiterhin die vorhandenen zusätzlichen Import-/Artikelbearbeitungsrechte der Berechnungs-API.

Die erneute Untersuchung ergab weiterhin keinen geeigneten internen Lizenz-/Abo-/Entitlement-Dienst. `DEEC_Permission` behandelt Benutzerrechte; öffentliche Shop-/Site-Features sind kein Selection-Tool-Produktanspruch. Eine allgemeine Lizenz- oder Abrechnungsplattform wurde nicht entwickelt.

D7B1 ist deshalb **standardmäßig deaktiviert** und ausschließlich als ausdrücklich konfigurierter interner Pilot nutzbar: Die serverseitige Prozessumgebung `DEWAWI_SELECTION_INTERNAL_CLIENTS` kann eine strikte kommaseparierte Liste berechtigter ausgewählter Mandanten-IDs enthalten. Leere/ungültige Angaben und Wildcards aktivieren nichts. Maßgeblich ist der ausgewählte Mandant aus der authentifizierten Sitzung; Parent-Datenfreigabe oder Administratorstatus schalten die Funktion nicht frei. Auch bei interner Pilotkonfiguration bleibt die kommerzielle Produktberechtigung als fehlend dokumentiert und der kommerzielle Rollout gesperrt. Ohne Konfiguration bleibt D7A lesend/berechnend verwendbar; neue Speicherung und Historie sind gesperrt.

Alle neuen Schreibaufrufe verlangen CSRF. GET speichert keinen Datensatz. Manipulierte IDs, zusätzliche Ergebnisfelder, falsche Tokens und zu große Eingaben werden abgewiesen. Antworten verwenden `private, no-store`. Die technische Auswahl und Speicherung bereiten keine Anhangsverzeichnisse vor. Fehlermeldungen geben keine vertraulichen Herstellerinhalte aus.

## Abgrenzung zum Angebotsworkflow und Beispiel

`Contacts_Service_InquiryWorkflow::createQuoteDraft()` und dessen ursprüngliche SKU-Auswertung wurden untersucht und nicht verändert. D7B1 schreibt keinen SKU und keine Auswahl in Kundendaten, setzt keinen Preis und erzeugt keine Position, PDF oder E-Mail. Formular 865 bleibt `method=manual`, `automatic_quote=false`.

Der synthetische Test wählt weiterhin Artikel 2604 mit Basisartikel 2603 und die Konfiguration `fixture:d7a:machine:1 @1`, Cooling/full. Bei ausdrücklich eingegebenen 20/30 °C sekundären Eintritten liefert das bestehende D6B2-Modell 30 kW Kälte, 37,15 kW Wärmeabgabe und 7,15 kW Verdichteraufnahme bei Te/Tc=5/50 °C. Der gespeicherte Prüfstand enthält genau diese Werte und Revisionsbezüge, bleibt aber technisch und kaufmännisch unfreigegeben.

Beim späteren Entzug des tatsächlich gebundenen Verdichterprofils wird derselbe Prüfstand als aktuell ungültig gekennzeichnet; sein gespeichertes 7,15-kW-Ergebnis und seine damaligen Nachweise bleiben bytegleich erhalten. Eine neue Revision wird als Änderung sichtbar. Eine weitere Prüfung aus diesem Stand erhält eine neue ID/Version und den Vorgängerbezug.

Zusätzlich wurde die bestehende manuelle Angebotsentwurfserstellung auf genau dieser Inquiry mit gespeicherter technischer Auswahl ausgeführt: Ohne ursprünglichen SKU entsteht weiterhin ein leerer manueller Entwurf, **keine** technische Angebotsposition, **keine** synthetischen Werte und **keine** Änderung der ursprünglichen Kundendaten. Wiederholter manueller Aufruf öffnet denselben Entwurf.

## Tatsächlich ausgeführte Tests

- `python3 tests/items/technical-data.py`: vollständiger gemeinsamer Lauf erfolgreich mit **44 Initialprüfungen**, **54 D2B-Matrixfällen/166 Assertions**, gespeicherten D3/D4A/D4B1/D4B2/D5A/D5B/D6A/D6B1/D6B2-Prüfungen mit **143/225/121/211/127/103/235/127/125 Assertions**, **63 D7A-Assertions** und **103 neuen D7B1-Assertions**.
- Echte Neuinstallation und Upgrade vom tatsächlichen Repository-Schema 1.1.7 in temporärer MariaDB; zusätzlich partieller beziehungsweise wiederholter gezielter DDL-Aufruf mit identischem neuem Tabellenschema. Keine Produktiv-DB verwendet; der Testserver hat einen eigenen temporären Socket und `--skip-networking`.
- D7B1 umfasst Speichern/Öffnen/Verlauf/Vorgänger, unveränderte historische Ergebnisse, alle geforderten Statusklassen, konkrete gespeicherte thermische/hydraulische Teilergebnisse, neue Revision, Entzug, Daten-/Header-Tamper, fremde Inquiry/Artikel/Varianten, technische Rechte, ausgewählten Mandantenwechsel, Parent-Child-Pilotgrenze, unbekannte/abgelaufene Nachweise, CSRF, große/ungeprüfte Eingaben, gleichzeitige Wiederholungen und neue Versionen, unbekannte Wurzel-/verschachtelte JSON-Felder hinter Freigabesperren, die unveränderte erweiterte lesende Berechnung ohne ungeprüften Speichertoken sowie den erhaltenen manuellen Angebotsworkflow.
- Echte lokale HTTP-Aufrufe und **Chromium bei 390 und 1280 px**: D7A-Bedienung sowie ausdrückliches Speichern, Wiederöffnen, wiederholter POST ohne Duplikat, Verlauf, Übernahme alter Eingaben ohne Berechnung, gesperrter Nachfolger und unveränderter Vorgänger erfolgreich. Historische IDs bleiben über HTTP privat und Inquiry-bezogen; CSRF wird erzwungen. Screenshots unter `/tmp/dewawi-d7b1-ui/review-390.png` und `review-1280.png` wurden visuell geprüft.
- `python3 tests/security/shop-inquiry-quote.py`: **57 Katalog-, 95 Varianten- und 764 Angebots-/Transaktions-/PDF-/Replay-Prüfungen** sowie bestehende Inquiry-/Katalog-/Varianten-HTTP- und Chromium-Abläufe erfolgreich.
- `php tests/security/contact-inquiries.php`: **46**, `permissions.php`: **415**, `inquiry-selection.php`: **37/60 kumulativ**, `shop-inquiry-calculation.php`: **16**, `inquiry-pilot.php`: **37/70**, `tests/sales/quote-monitoring.php`: **293**, `tests/documents/pdf.php`: **183**, `pdf-totals.php`: **219** Prüfungen erfolgreich.
- Syntax aller geänderten/neuen PHP-/PHTML-Dateien und Python-Dateien sowie Diff-Prüfung erfolgreich.

Während der Entwicklung wurden Unterschiede zwischen PHP-Map-/JSON-Objektnormalisierung und berechneten Float-/exakten Dezimalwerten korrigiert; die bestehenden technischen Importprüfungen wurden nicht gelockert. Ein Test, der eine durch Fremdschlüssel bereits verbotene Manipulation versuchte, wurde auf eine zulässige physische Zeilenmanipulation umgestellt, damit auch die Anwendungs-Integritätsprüfung getestet wird. Endgültige Läufe sind erfolgreich.

Nicht ausgeführt: Produktion, echte OEM-Datenvalidierung und eine vollständige ERP-Produktionssitzung mit Loginformular/Hauptmenü. Die HTTP-Fixture verwendet echte Controlleraktionen, Dienste und Views mit synthetischem Benutzer-/Owner-Bootstrap. Die unveränderten reinen Rechenkerne wurden nicht zusätzlich als vollständige separate numerische Matrix erneut ausgeführt; sie sind über die gespeicherten D2B–D7A-Regressionen und neuen Persistenzfälle eingebunden.

## Geänderte Dateien

- `application/modules/contacts/controllers/InquiryController.php`: Erweiterung der vorhandenen Auswahlaktion, neue ausdrückliche Speicheraktion, CSRF-/Eingabe-/Fehlerbehandlung.
- `application/modules/contacts/services/InquiryTechnicalReview.php`: begrenzter Nachweis, interne Pilotgrenze, Historisierung, erneute technische Integritätsprüfung und atomare Speicherung.
- `application/modules/contacts/models/DbTable/Inquirytechnicalreview.php`: zweckgebundene unveränderliche Datensätze, Owner-/Inquiry-bezogene Historie und Wiederholungsschlüssel.
- `application/modules/items/services/TechnicalDataset.php`: optionaler interner Consumer innerhalb des bestehenden geprüften Maschinen-Snapshots. Rechenmodelle und bisherige Aufrufe bleiben erhalten.
- Vorhandene Inquiry-Views `select.phtml`, `technical.phtml`, `technical-entry.phtml`; neue Partials `technical-reviews.phtml` und `save.phtml`; deutsche/englische Inquiry-Übersetzungen. Kein neues JavaScript und keine Änderung an `global.js` oder dem manuellen Quote-Dienst.
- `install/config/update-1.1.7-1.1.8.sql` und `install/config/structure.sql`: ausschließlich derselbe neue abschließende CREATE-Block.
- `tests/items/technical-data.php`, `inquiry-technical-review-db.php`, `inquiry-review-worker.php`, `tests/security/fixtures/inquiry-technical-http.php`, `tests/ui/inquiry-technical.py`, `inquiry-technical-review.py`: Migration, Persistenz, Parallelität, HTTP und Browser.

## Migration und manuelle Nachführung

Es wurde **keine** produktive Datenbank verändert und nichts deployt. Neue Installationen beziehungsweise reguläre 1.1.7-Upgrades verwenden die bestehende aktive Migration nach 1.1.8 und das synchronisierte Fresh-Schema.

Falls eine Installation diese Migration bereits vor D7B1 ausgeführt hat, benötigt sie nach gesondert autorisierter administrativer Prüfung **nur den neu angehängten `CREATE TABLE IF NOT EXISTS inquirytechnicalreview`-Block** aus `install/config/update-1.1.7-1.1.8.sql`. Die komplette aktive Migration enthält ältere ALTER-Anweisungen und soll nicht blind erneut ausgeführt werden. Die neue Tabelle setzt die bereits vorhandenen technischen Tabellen und Inquiry-Struktur voraus. Es gibt keine weitere offizielle Migration und keine 1.1.9-Datei.

Für einen begrenzten internen Piloten sind anschließend ausdrücklich festgelegte ausgewählte Mandanten in der serverseitigen Prozessumgebung und das neue Benutzerrecht `items.technicaldataset.review` samt Inquiry-Bearbeitungsrecht notwendig. Diese Konfiguration und Rechtevergabe wurden ausschließlich in isolierten Tests gesetzt, nicht in bestehenden Mandanten. Für andere Mandanten wird nichts automatisch aktiviert. Ein kommerzieller Selection-Tool-Rollout bleibt ohne eigenständigen Produktberechtigungsvertrag gesperrt.

Die Persistenz optionaler erweiterter D4-/D5-Eingabeverträge ist zusätzlich zu beauftragen; die derzeitige Speicherung verwendet die geschlossenen Basisverträge. Automatische Kandidatenauswahl benötigt weiterhin passende vollständige und freigegebene reale OEM-/Maschinen-/Fluid-/Hydraulik-/Regelungsnachweise, eindeutige technische Modellgrenzen sowie eine separat beauftragte technische/kaufmännische Übernahme in den Angebotsworkflow. D7B1 implementiert keine automatische Rangliste, Preis-/Margenprüfung, Produktfreigabe, Free-Cooling-Berechnung oder Angebotsübernahme.

Lokaler Commit und exakte Kennung werden im Abschluss genannt. **Kein Push, Merge, Tag oder Deployment. D7B2 wurde nicht begonnen.**
