# Process-View – vollständiger lesender Datenvertrag

Stand: 20.11.2026. Ausgangs-HEAD `f4f167b3`, Arbeitsverzeichnis zu Beginn sauber. B18 (`2ff9f2c3`) bleibt fachlich und in seiner Buttonimplementierung unverändert. Anwendungsversion 1.1.8; keine Migration, keine Produktionszugriffe.

## Reproduktion und Ursache

Der im [B18-Bericht](../ui/standard-area-buttons.md) dokumentierte vollständige MVC-Test wurde **vor Änderungen unverändert** ausgeführt:

```sh
php tests/ui/button-page-integration.php /processes/process/view/id/17
```

Ergebnis: Exit 255, `TypeError: count(): Argument #1 ($value) must be of type Countable|array, null given` in `application/modules/processes/views/scripts/process/view.phtml:98`. Der Stack führt über `Zend_View::_run`, `Zend_View_Abstract::render`, `ViewRenderer::renderScript` und den MVC-Dispatch. Zusätzlich greift die Vorlage auf einen nicht zugewiesenen Kontakt zu. Weitere Undefined-Key-Warnungen betreffen die unvollständige synthetische Processzeile des bisherigen Fixtures; die echten nullable Spalten stehen bereits in `install/config/structure.sql`.

Aufrufpfad:

1. ACL-Plugin und Controllerinitialisierung mit bestehendem Benutzer-/Mandantenkontext.
2. Geerbtes `DEEC_Controller_Action::viewAction()` → `requireRow()` → `loadRow()` → Processmodell/`getById()`: bestehender Scope auf `Client.id` und nicht gelöschte Datensätze.
3. `buildViewForm()` setzt den Formularmodus `readonly`; anschließend werden Processzeile, Formular, Status, Toolbar und Meldungen zugewiesen.
4. Der vorgesehene `getViewAssigns()`-Hook lieferte mangels Processoverride `[]`. Deshalb fehlten `positions`, `contact` und `activeTab`.
5. Das Template zählt und iteriert Positionen und baut den Kontaktlink. Der vorhandene `ProcessEditViewModel` wird im Viewpfad nicht automatisch verwendet und lädt weitere, hier unnötige Kontaktunterdaten.

Unmittelbar verbundene Vorlagenfehler: Der Titelfallback verwendete `processid`, obwohl die Process-Tabelle nur `id` besitzt. `EmailMessages()` rendert einen Versandcomposer mit nicht vorhandenem `emailForm`; der vorhandene Nachrichtenlesebereich braucht lediglich das Ziel `#emailmessages` für seinen unveränderten Historyhandler. Ein nullable Positionsbeschreibungstext darf unter PHP 8 nicht ungeprüft an `str_replace()` übergeben werden.

Der nachgelagerte Composerfehler wurde gesondert bestätigt: Die unveränderte Vorlage aus `f4f167b3` wurde temporär außerhalb des Repositorys mit dem inzwischen vollständig versorgten Viewdatenbestand gerendert. Nach erfolgreicher Positionsausgabe endet sie mit `Call to a member function getElement() on null` in `partials/email-messages.phtml:19`. Beleg: `legacy-composer.log`. Das ist derselbe unvollständige Leseviewvertrag; kein unabhängiger Fehler im E-Mail-Versandbackend.

## Gezielte Korrektur

### `application/modules/processes/controllers/ProcessController.php`

- Bestehenden `getViewAssigns()`-Hook implementiert: `positions` ist immer ein Array, auch bei null Treffern.
- `Processes_Model_DbTable_Processpos::getPositions()` direkt verwendet, mit **Eigentümermandant aus der zuvor autorisierten Processzeile**. Bestehendes SQL behält `parentid`, `clientid`, `deleted = 0`, `ordering ASC, id ASC`. Eine Positionsabfrage; keine Berechnungshelper, Positionsupdates oder Summenneuberechnung.
- Kontakt über die gespeicherte geschäftliche `contactid` und `Contact::getContactWithID()` aufgelöst; die primäre Kontakt-ID wird nur für den bestehenden Link benutzt. Scope aus dem vertrauenswürdigen Contacts-Modulowner, sonst `Client.id`; zusätzliche bestehende Contact-View-Berechtigung. Höchstens eine Kontaktabfrage, keine bei fehlender Referenz oder fehlendem Leserecht. Fehlender/gelöschter/fremder Kontakt wird `[]`; gespeicherte Rechnungsadressdaten bleiben sichtbar und unverändert.
- Bestehendes Tabcookie auf die drei vorhandenen Tab-IDs begrenzt, sonst Übersicht.
- Bestehenden `shouldPrepareAttachmentDirectory()`-Hook nur für Process-`view` deaktiviert. Damit erzeugt bereits das Öffnen keine Attachmentverzeichnisse; Edit und andere Aktionen behalten den bisherigen Pfad.

### `application/modules/processes/views/scripts/process/view.phtml`

- Primäre Process-ID für den Titelfallback; Kontaktlink nur bei einem aufgelösten berechtigten Kontakt, sonst gespeicherte Referenz als Text. Hidden-Contactfeld, IDs, URLs und Historyhandler bleiben erhalten.
- Genau ein zum Cookie passender aktiver Tab/Panel; keine neue Tabimplementierung und keine JavaScriptänderung.
- Nullable Beschreibung nur zur Textausgabe in String umgewandelt; keine Umwandlung oder Neuberechnung von Mengen, Preisen, Steuern oder Summen.
- Nicht versorgten Versandcomposer durch das bestehende Historyziel ersetzt. Kein Versandformular initialisiert; keine Änderung an E-Mail-Backend, Dokumenten, PDFs oder Versandaktionen.
- Nach bestehender FileManagerinitialisierung die Session-Writability für diese Leseansicht auf `false` gesetzt. Der gemeinsame Helper setzt sie sonst bei vorhandenem Kontakt auch hier auf `true`. Bestehender Media-Zugang und FileManagerkonfiguration werten dieses Flag aus; keine Änderungen am Fremdwidget oder an dessen Schreiblogik.

Die allgemeine Basisklasse, Modelle, Preise/Steuern, ACL, Modulfreigaben, Buttonrenderer, SVGs, CSS, JavaScript, Schema und Versionsnummer bleiben unverändert. Insbesondere erweitert ein konfigurierter Process-Modulowner allein weiterhin nicht die bestehende generische `getById()`-Freigabe. Die Korrektur führt kein neues Parent-Process-Sharing ein.

## Regressionen und Testumgebung

`tests/ui/button-page-integration.php` bleibt der ursprüngliche vollständige lokale MVC-Router mit originalen Controllern, ViewRenderer, Modellen, Layout und Assets. Sein synthetischer Adapter kann keine Datenbankverbindung öffnen und lehnt sämtliche Schreibbefehle ab. Ergänzt wurden:

- Schemaidentische nullable Processfelder statt unvollständiger künstlicher Zeilen, ohne Änderung der SQL-Datei.
- Explizit begrenzte Process-View-Fehlerfallrouten und opt-in `fixture_process_view`.
- Nur in diesem Opt-in vollständige Filterung nach Parent, Mandant, Kontakt und Löschflag sowie Positionssortierung. Tatsächliche SQL-Bedingungen und Reihenfolge werden zusätzlich geprüft; der Memoryadapter ist kein SQL-Parser oder Datenbankintegrationstest.
- Harte Fehler bei versuchter Attachmentvorbereitung oder Datensatzlocks im Process-Viewpfad. Andere B18-Kontexte behalten ihre bisherigen Fixturehelper.

Neu `tests/processes/fixtures/process-view.php`: gezielt unsortierte Positionen, gleicher Sortierwert mit ID-Tiebreak, fremde/gelöschte Positionen und andere Parent-ID. Gespeicherte Dezimalwerte und Summen sind absichtlich nicht rechnerisch konsistent: Eine Darstellung darf sie trotzdem nicht aktualisieren. Vollständige Zeilen-Snapshots müssen unverändert bleiben. Normale PHP-Warnungen und auch im Processmodul unterdrückte Deprecations werden zu Testfehlern; bestehende globale ZF1-Deprecationunterdrückung des ursprünglichen Fixtures bleibt erhalten.

Neu `tests/processes/process-view.py`: HTTP und Chromium gegen diesen vollständigen MVC-Kontext. Alle Daten synthetisch; keine echten Benutzeranmeldungen, keine produktiven Sessions oder Kundendaten. Drittanbieter-PHP im Dateimanageriframe wird im Testserver nicht ausgeführt.

| Prüfung | Tatsächliches Ergebnis |
| --- | --- |
| Ursprünglicher unveränderter CLI-Aufruf nach Korrektur | PASS, vollständiges HTML, Exit 0, leeres stderr; vorher Exit 255 mit `count(null)`. |
| Neue Process-View-Suite | **PASS: 465 Assertions, 16 Chromiumprofile**, DE/EN × 320/390/768/1280 px × volle/leere Positionsliste. |
| Fachlicher Lesedatenvertrag | PASS: leere/mehrere Positionen, abgeschlossener Vorgang und Entwurf, stabile Reihenfolge, gespeicherte Dezimalstrings einschließlich Steuersätzen, gespeicherte Summen/Adresssnapshot unverändert; genau eine Positionsabfrage. |
| Kontakt und Sharing | PASS: vorhanden/fehlend/gelöscht/fremd, ohne Referenz, ohne Contactrecht; Child-eigener Vorgang mit freigegebenem Parent-Kontakt. Positionen des Modulowners werden nicht statt Positionen des autorisierten Eigentümers gelesen. |
| IDs und ACL | PASS: fehlende, 0, negative, nicht numerische und unbekannte ID; gelöschter Vorgang; fremder bzw. nur per Modulowner referenzierter Parent-Vorgang; JSON-Not-found oder ursprünglicher Redirect; 403 bei verweigertem Recht und erfolgreicher readonly-Rolle. |
| Schreibfreiheit beim Öffnen | PASS: DB-Schreibbefehle, Transaktionen, Directoryvorbereitung und Lockversuche verboten; Adapterdaten unverändert; Formularmodus readonly, keine Formulare/Auto-Save-Aktivierung/Composer, keine schreibenden XHR beim Öffnen, FileManager-Writability false. Session-/Cacheinfrastruktur ist damit nicht als völlig schreibfrei behauptet. |
| Browserintegration | PASS: gespeicherte Zeilen/Summen im vollständigen DOM, Tabcookie/aktive Panels, Hiddenkontakt readonly, SVG-Symbolauflösung unverändert, native Tastaturnavigation am bestehenden Kontaktlink. |
| PHP B18 | PASS: `standard-area-buttons.php` **8.108** Assertions. |
| Gemeinsame Toolbar-/B17-Baselines | PASS: `remaining-toolbars.php` **28.835**, `delete-apply-buttons.php` **1.738**; eingebundene Grundlagen **165 / 2.451**. |
| PHP Sicherheit | PASS: `permissions.php` **450**, `tenant-access.php` **269**, `admin-access.php` **68**, `bootstrap-auth.php` **29**. Erwartete NO_PERMIT-Ausgaben gehören zu Negativfällen. |
| Positions-Lese-/Schreibbaseline | PASS: `tests/documents/position-read.php` **244** Checks. |
| Sicherheits-HTTP | PASS: `tenant-access-http.py` **60** Apache-Objekt-/ACL-/AJAX-/Trashprüfungen, Root und Unterpfad, ausschließlich Memorydaten. |
| B18 Chromium/Firefox | **PASS jeweils 1.606 Assertions / 72 Profile**: ursprünglicher vollständiger MVC-/HTTP-Vergleich gegen historische B18-Formulare, Index/Edit/Select, Search/Filter/Reset, AJAX-Refresh, Auswahl, Copy/Delete/Cancel/Loading/403/Retry und unveränderte Hookattribute. 24 native Firefox-Index-Tastatur-/Fokusfälle bleiben ausdrücklich NOT TESTED; Chromium prüft sie tatsächlich. |
| PHP-Syntax | PASS: beide Laufzeitdateien, erweiterter MVC-Router und neue PHP-Fixturedatei. |

Rohbelege dieser Ausführung unter `/tmp/dewawi-process-view/`, insbesondere `before.log`, `after.html`, `after.log`, `process-view-result.log` und die B18-/HTTP-Testprotokolle. Temporäre HTML- und Browserartefakte werden nicht eingecheckt.

## Grenzen und Freigabe

- Keine reale Datenbankintegration oder produktive Mandanten-/SaaS-Paketabnahme durchgeführt. Der Original-MVC-Pfad und das echte Model-SQL laufen gegen einen verweigernden Memoryadapter; bestehende Mandanten-/Bootstrap-/HTTP-Baselines ergänzen diesen Nachweis.
- Dateimanagerinnere, echte Attachmentdateien, Thumbnail-/Cacheerzeugung und mehrere parallel geöffnete FileManagerkontexte wurden nicht ausgeführt. Die Session-Writability ist nachgewiesen; vollständige physische Dateisystem-Schreibfreiheit wird nicht behauptet.
- Der Nachrichtenlesebereich behält `onclick="getEmailmessages()"` und das Hiddenkontaktfeld. Echte E-Mail-History, Versand, Dokument-/PDFaktionen sowie Dateiaktionen sind keine durchgeführten Workflows. Der versehentlich vorausgesetzte Versandcomposer wird in dieser reinen Leseansicht nicht benötigt.
- B18 dokumentiert bereits Tabellenüberlauf bei 768 px und globalen Headerüberlauf. Diese Layoutprobleme, ältere Tabellen-IDs und die bestehende Tab-JavaScriptbedienung wurden nicht umgebaut. Responsive Renderingtests sind keine uneingeschränkte visuelle Abnahme.
- In den ausgeführten Baselines bleiben keine unabhängigen Testfehler offen. Kleine Pixelzahlen erzeugen im bestehenden B18-Runner Diagnoseartefakte namens `paint-failure`, obwohl dessen unveränderte Assertions bestehen; diese Dateien allein sind kein fehlgeschlagener Testlauf.
- Safari/WebKit, Screenreader und reale Rollenlogins nicht geprüft. Manuell sinnvoll: Processübersicht, Kontakt-/Datei-/Nachrichtenlesezugriff mit zulässigen Testrollen und freigegebenen Testmandanten abnehmen.
- Keine Datenbankmigration oder Datenkorrektur erforderlich. Keine Produktivdatei, keine gespeicherten Inhalte und keine fachlichen Aktionen verändert. Kein Push, Merge, Tag oder Deployment.

Reproduktion:

```sh
php tests/ui/button-page-integration.php /processes/process/view/id/17
python3 tests/processes/process-view.py
php tests/ui/standard-area-buttons.php
python3 tests/ui/button-release-audit.py --standard-areas --engine chromium --output /tmp/dewawi-process-view/b18-chromium
python3 tests/ui/button-release-audit.py --standard-areas --engine firefox --output /tmp/dewawi-process-view/b18-firefox
python3 tests/security/tenant-access-http.py
```
