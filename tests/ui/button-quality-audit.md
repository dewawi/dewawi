# B8 – Button-/SVG-Qualitätsaudit und Freigabevorbereitung

Stand: 18.11.2026, lokaler Code auf `b523e3b4`. Geprüft: `AGENTS.md`, [B1-Bestandsaufnahme](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5](toolbar-buttons.md), [B6](tasks-toolbar.md), [B7](remaining-toolbars.md). Laufumgebung: PHP 8.3.6, Chromium 157.0.8103.78 unter Linux Mint.

**Ergebnis:** Die vorhandenen Opt-ins bestehen die isolierten technischen Prüfungen. Keine neue Laufzeitregression durch B2–B7 nachgewiesen, daher keine Änderung an Anwendungs-PHP, CSS, JS, Icons oder Geschäftslogik. Eine nachgewiesene Übersetzungsabweichung der Testfixture wurde korrigiert. Die Gesamtfreigabe bleibt ausstehend: authentifizierte vollständige Geschäftsviews, reale Rollen-/SaaS-Konfigurationen und manuelle Browser-/Screenreader-/Produktabnahme fehlen.

Der bisher unversionierte B1-Bericht wird auf ausdrücklichen Benutzerauftrag **unverändert** aufgenommen: SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`. Seine Zahlen/Fundstellen beschreiben den historischen Stand vor B2. Die Aussagen in B2–B7 über seinen damaligen unversionierten Zustand bleiben historische Angaben; für den aktuellen Bestand gilt dieses Audit.

## 1. Tatsächliche lokale Commitbasis und Aktivierung

| Stufe | Vorhandener Commit / Message |
| --- | --- |
| B2 | `b27f55e6` – `add shared button presentation and inquiry pilot` |
| B3 | `2af50303` – `add isolated svg button icons` |
| B4 | `20ebf9d9` – `migrate inquiry buttons to svg` |
| B5 | `aaca1571` – `migrate shared contact and item toolbars` |
| B6 | `f23f05fb` – `migrate tasks toolbar to svg` |
| B7 | `b523e3b4` – `consolidate remaining standard toolbars` |

Die dazwischenliegenden fremden Geo-/Dokumentcommits wurden nicht umgeschrieben oder in B8 übernommen. Maßgeblich ist der lokale Dateistand, kein GitHub-Abgleich.

| Modul/Controller | Aktive Klassen/Controls | Kontextgrenze / sichtbare Ausgabe |
| --- | --- | --- |
| `contacts/inquiry` | `InquiryToolbar`: Filter, Reset, Clear; `InquiryToolbarInline`: View/Edit; `Inquiry`: Save | Sechs feste Opt-ins in diesen konkreten Formularen. Index: drei Toolbarbuttons + zwei Aktionen pro Fixturezeile; Edit: nativer Save-Submit; View: keine SVG-Buttons. Symbolblock im Inquiry-Layoutkontext auch bei anderen Aktionen; keine globale Formularfreigabe. |
| `contacts/contact` | `Contacts_Form_Toolbar`: Add/Edit/Copy/Filter/Reset/Clear | Exakte Klasse, Modul/Controller und fünf Standardaktionen; Index sechs oben + drei unten; Select Clear; Edit/View Copy. |
| `items/item` | `Items_Form_Toolbar`: dieselben sechs | Dieselbe Grenze; vier abgeleitete Spezialtoolbars ausdrücklich ausgeschlossen. |
| `tasks/task` | `Tasks_Form_Toolbar`: dieselben sechs | Dieselbe Grenze; Get/Add/Sourcequote/Positionen/Tabellenaktionen bleiben unverändert. |
| `admin/country` | `Admin_Form_Toolbar`: dieselben sechs | Exakte Klasse und fünf Standardaktionen. |
| `admin/uom` | dieselbe Klasse / sechs Controls | Fachliche Defaultregeln und Formularaktionen unverändert. |
| `admin/deliverytime` | dieselbe Klasse / sechs Controls | Keine Liefer-/Dokumentaktion migriert. |
| `admin/manufacturer` | dieselbe Klasse / sechs Controls | Generischer CRUD-/JSON-Copy-Vertrag unverändert. |
| `admin/warehouse` | dieselbe Klasse / sechs Controls | Lagerbuchungs-/Bestandsaktionen bleiben ausgeschlossen. |
| `admin/shippingmethod` | dieselbe Klasse / sechs Controls | Nur beschreibende Stammdatenverwaltung, keine Versandabwicklung. |
| `statistics/customer` | `Statistics_Form_Toolbar`: Filter/Reset | Exakte Klasse, ausschließlich Index/Search; kein SVG-Export, kein Footer. |
| `statistics/quote` | dieselbe Klasse / zwei Controls | Keine Angebots-/Dokumentaktion migriert. |
| `statistics/turnover` | dieselbe Klasse / zwei Controls | Reportberechnung, Export und Reporting-Controller unverändert. |

Die fünf Standardaktionen sind `index`, `search`, `select`, `edit`, `view`. Es bleiben **13 Controllergruppen**, **51 begrenzte Standard-Requestaktionspaare** aus B5–B7 plus der formulargebundene Inquiry-Pilot. **32 Präsentationskonfigurationen in acht Formularquellen** ergeben durch die wiederverwendeten Admin-/Statistikklassen **66 controllerbezogene Controlkonfigurationen**. Das sind keine 66 stets sichtbaren DOM-Buttons. Sieben der 34 Toolbarformulardateien enthalten begrenzte Opt-ins; 27 bleiben Legacy. Sechs von 13 Haupttoolbarfamilien sind teilweise migriert, sieben vollständig Legacy. Alle Zahlen stimmen mit B4–B7 überein; keine neue Gruppe aktiviert.

Reproduzierbar über `rg -n 'iconFormat|renderButtonSymbols' application` und die tatsächlichen `getElements()`-Snapshots. Die acht Quellen sind `contacts/forms/{Inquiry,InquiryToolbar,InquiryToolbarInline,Toolbar}.php` sowie `{items,tasks,admin,statistics}/forms/Toolbar.php` unter `application/modules/`.

## 2. Komponentenprüfung und Rückwärtskompatibilität

| Bereich / Fundstellen | Befund |
| --- | --- |
| `library/DEEC/Form.php:1526`, `:2026` | `renderElementHtml()` behält Rechte, Readonly, Übersetzung und `data-action` bei und delegiert an genau eine Präsentationsroutine. `type=submit` bleibt Submit; Toolbar-Reset bleibt ein JS-Button. Labels/Attribute werden einmal escaped. Name/Value einschließlich `0`, Recordtargets und temporäre Attribute bleiben durch Baselines geprüft. |
| `application/views/helpers/Button.php` | Neuer direkter Helper delegiert an denselben Renderer; alte acht Positionsparameter bleiben im Legacy-Modus. Kein zweiter moderner Buttonrenderer. Die bewusste B2-Grenze „Text statt HTML-Label“ bleibt bestehen; keine neue Umdeutung alter Aufrufer. |
| Form-Metadatenschlüssel | Wiederholung in Normalisierung, Elementablage und Renderübergabe entspricht den vorhandenen Verarbeitungsschritten. Kein Anlass für eine zusätzliche Abstraktionsschicht. Alte Multi-/Positions-/Direkt-HTML-Ausgaben bleiben getrennte Legacy-Wege. |
| SVG-Allowlist / `images/icons/` | 15 semantische Keys, 14 lokale Symbole; Clear ist Alias auf Close. Nur explizite SVG-Keys, keine Text-/Namensheuristik. Sichere feste Datei, kein DB-/Benutzer-SVG, keine externen Referenzen/Skripte. Lucide 0.468.0, gepinnter Commit und ISC-/Feather-MIT-Nachweise unverändert vorhanden. |
| `layout.phtml:65`, `plain.phtml:54` | Dieselben Opt-in-Bedingungen; eine Symboldefinition pro vollem Dokument, eigene Definition pro iframe, keine Definition im Search-Fragment. Die Inquiry-Bedingung ist absichtlich weiter als die Standardaktionsliste; nur die drei spezifischen Formulare erzeugen dort SVG. Site-Layout unverändert. |
| Wiederholte Kontextlisten | Zwei Layouts und die jeweiligen Formulargrenzen wiederholen einige Controller-/Aktionslisten. Aktuell konsistent und durch Render-/Negativtests abgedeckt. Wartungsrisiko bei künftigen Ergänzungen, kein nachgewiesener Fehler und kein Anlass für ein neues Registry-/Rendering-System in B8. |
| `css/ui.css:717–841` | Moderne Regeln auf `.dw-btn--control` begrenzt. Bestehende PNG-URLs werden von Legacy- und inneren PNG-Spans geteilt. Der äußere moderne Button unterdrückt sein PNG; SVG nutzt `currentColor`, ohne Doppelicon. Alte globale Hover-/Fokus-/Paddingregeln werden durch spätere gezielte Regeln überstimmt; kein widersprüchliches Verhalten nachgewiesen. |
| `js/global.js:6`, `:2227`, `:3443` | Nur B2-Capture-Schutz für deaktivierte moderne Controls kam hinzu. Loading-Restore, Requestlocks, Filter, Auswahl, Search, Auto-Save und übrige Dispatcher sind gegenüber vor B2 unverändert. Kein konkurrierender Handler eingeführt. |

Die derzeit produktiv aktivierten SVG-Controls sind Buttons; nur Inquiry-Save ist Submit. View/Edit-Navigation bleibt gemäß bestehendem Vertrag ein JS-Button. Die direkte Link-Schnittstelle wird isoliert mit `href`, Target/Rel, Disabled/Loading und Tastatur geprüft; B8 macht daraus keine neue Linkmigration.

Erwartete Präsentation: Default/md bei Standardtoolbars, Primary bei Inquiry-Save, sm bei Inquiry-Zeilenaktionen. 36 px md / 32 px sm, bei ≤767 px oder grobem Pointer mindestens 44 px; 16-/20-px-Icons, 8-px-Textabstand. Sichtbarer 2-px-Fokus, dekorative SVGs ohne eigenen Tabstopp. Nur Clear sowie Inquiry-View/Edit sind in dieser Migration Icon-only; ihre tatsächlichen DE/EN-Namen sind übersetzt und ohne technische Keys. Danger/Ghost/Touch/Links/native Reset bleiben zusätzlich geprüfte Schnittstellenvarianten, keine neuen fachlichen Aktionen.

## 3. Prüfmatrix und tatsächliche Ergebnisse

### Rendering und Verhalten

| Gruppe | DE/EN × Breiten | Ansichten / Abdeckung | Ergebnis |
| --- | --- | --- | --- |
| Inquiry | 390 / 768 / 1280 | Index und Edit, jeweils Haupt- und Popup-Layout: 24 Kombinationen; View mit null SVG-Controls zusätzlich per PHP. | PASS: 697 PHP-/796 Browserchecks. |
| Contact + Item | dieselben | Je Index/Hauptlayout, Select/Popup, Edit und View: 48 Kombinationen. | PASS: 2.259 PHP-/928 Browserchecks. |
| Task | dieselben | Dieselben vier Profile: 24 Kombinationen, zusätzlich Status-/Datums-/Zuweisungs-/Auto-Save-Verträge. | PASS: 1.428 PHP-/524 Browserchecks. |
| Sechs Admincontroller | dieselben | Dieselben vier Profile: 144 Kombinationen, beschreibende echte Formularfelder als Auto-Save-Probe. | PASS im vollständigen Abschlusslauf nach Fixture-Korrektur: gemeinsam mit Statistik und Negativseiten 3.247 Browserassertions. |
| Drei Statistikcontroller | dieselben | Index/Hauptlayout: 18 Kombinationen, Custom-Datum, Von-Datum, Resetdefaults, Legacy-Export und Search-Fragmente. | PASS im selben vollständigen Abschlusslauf; inklusive 24 ausgeschlossener Adminseiten und direkter Legacy-Umsatzreport-Referenz. |

Das sind **258 reguläre Layout-/Sprach-/Breitenkombinationen**, zusätzlich Readonly-, ausgeschlossene Legacy- und fragmentbezogene Prüfungen. Die betroffenen B5-/B6-/B7-Matrizen bestehen auch vollständig nach der unten beschriebenen Fixture-Korrektur.

| Zusätzliche tatsächlich ausgeführte Suite | Ergebnis / Grenze |
| --- | --- |
| `php tests/ui/button-icons.php` | PASS: 165 B2- + 645 B3-Checks, XML-/Geometrie-/Allowlist-/Escaping-/Legacy-/Rechteprüfung. |
| `php tests/ui/remaining-toolbars.php` | PASS: jetzt 27.105 gezählte Prüfungen einschließlich 100 neuer gezielter Vergleiche mit dem echten Translate-Plugin und zusätzlicher bestehender Adapterassertions. Die ursprünglichen 26.925 Prüfungen bestehen weiterhin, separat mit alter Assertionsdatei und korrigierter Fixture bestätigt. Eingebundene 165/2.259 Baselines nicht als einzigartige zusätzliche Fälle addieren. |
| `python3 tests/ui/buttons.py`, `--svg` | PASS: unveränderte 169 PNG-/194 SVG-Baselineassertions, einschließlich strenger Icon-CSP, eigener iframe-Symbole und Forced Colors. |
| `python3 tests/ui/buttons.py --svg --audit` | PASS: 204 Checks = bestehende 194 + zehn neue gezielte Proben in DE/EN. Textkontrast normal Default 14,68:1, Primary 8,66:1, Danger 6,95:1, Ghost 13,68:1 auf den tatsächlich gerenderten Hintergründen. Desktop mit grobem Pointer: ≥44-px-Controls/20-px-Icons. 200%-CSS-Zoom bei 768 px ohne Seiten-/Labelclipping oder leere SVG-Geometrie. Loading-Clear behält im Chromium-AX-Baum seinen Namen sowie Busy/Disabled; danach SVG und Zustand restauriert. Keine vollständige WCAG-/Browserzoom-/Screenreaderprüfung. |
| `python3 tests/ui/notifications.py` | PASS: 1.985 Checks je 390/1280 px; bestehende Dialog-/Mail-/Dokument-/Modal-/Auto-Save-Verträge mit abgefangenen Requests. |
| `php tests/security/{admin-access,permissions,tenant-access,bootstrap-auth}.php` | PASS: 68 / 415 / 269 / 29 Fälle; Adminschutz, fehlende/fremde Rechte, Identität, ausgewählter Mandant und Parent-/Child-Sharing. |
| `python3 tests/security/session-http.py` | PASS: 25 echte lokale PHP/Zend-Session-/Cookie-HTTP-Fälle, isolierte Dateien, keine DB. Identität im Test gesetzt; keine produktive Anmeldung und kein vollständiger ERP-UI-Workflow. |
| `python3 tests/security/tenant-access-http.py` | PASS: 48 isolierte Apache-/ACL-/Objekt-/AJAX-/Trash-Fälle, Root- und Unterverzeichnisinstallation, Memoryadapter. Testrollen werden durch Fixtureheader bereitgestellt; keine echte Passwort-/UI-Anmeldung. |
| `php tests/security/{contact-inquiries,form-input-validation,inquiry-dynamic-form}.php` | PASS: 46 / 20 / 31 Fälle. |
| `php tests/sales/quote-monitoring.php`, `tests/lists/summary.php` | PASS: 293 Fälle bzw. zentrale Footer-/Summenprüfung. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler erneut bestätigt: 370 erfolgreich, dann fehlende `Sales_Service_DocumentRevisionService` in `sales/views/helpers/Finish.php:12`, Fixtureabbruch `tests/security/notifications.php:211`. Keine zugehörige Änderung in B8. |

Alle UI-Matrizen prüfen Kopf-/Fußtoolbar, SVG-Geometrie, Icon-/Textmaße, AX-Namen, sichtbaren Fokus, Hover, Disabled, Loading/Node-Restore und Tastatur. Die profilspezifischen Checks sichern SVG-/use-Eventtargets, Einzelauswahl, URLs, Copy-Sperren über Kopf/Fuß, dynamisch neu erzeugte Standardfooter, Keyword/Clear, Reset, Status und Auto-Save/Opt-out. Sie verwenden tatsächliche Komponenten/Assets/Layouttemplates, synthetische Datensätze und abgefangene AJAX-Aufrufe. Ganze Geschäftsviews, Optionsabfragen, umfangreiche Reportdaten und echte Controller-Schreibvorgänge werden damit nicht als E2E geprüft.

Snapshotnachweis: 480 B5-, 350 B6- und 1.900 B7-Konfigurationen erneut erzeugt, bytegleich mit den gesicherten B7-Ausgaben. SHA-256 der JSON-Ausgaben: B5 `6f7c6ba6eb94cc1c1cde1a29474002051fe53d1a733fe521b9bb2982675c9f81`, B6 `9240a863f35292401ae0f4014c9d4fcbb95fb6ed8c0aa4881e32cabbf9c67a5f`, B7 `58e4e4413a5988559778e1e21a33718bd18572bc116119e36db8008bf7410350`. Das sind Konstruktor-Konfigurationen, keine Pixelreferenzen oder erfolgreichen authentifizierten Seitenaufrufe.

### Korrigierte Testregression

`toolbarPage()` verwendete seit B5 `buttonTranslations()`, das immer Kontakte-Kataloge vorlud. Das echte `Application_Plugin_Translate` lädt dagegen Default, alle vorhandenen Common-Kataloge und das aktive Modul. Dadurch erschien beispielsweise `MENU_CONTACTS` auf Items-/Tasks-/Admin-/Statistik-Fixtureseiten übersetzt, obwohl es im realen englischen Kontext nicht geladen wird. Der neue Vergleich scheiterte vor Korrektur mit `Fixture translation differs from route plugin: en_US/items/item/MENU_CONTACTS`.

B8 korrigiert ausschließlich die Fixture-Ladereihenfolge in `toolbar-buttons.php` und ergänzt in `remaining-toolbars.php` 100 Vergleiche über fünf Modulkontexte, DE/EN und zehn relevante Toolbar-/Menü-/Reportkeys. Dabei läuft das echte Route-Plugin; sein optionaler Benutzer-/Sprachumschalter-DB-Zweig bleibt durch temporär entfernten User und eine isolierte Viewresource ausgeschlossen. Die echte Anwendung, Katalogdateien und Autorisierung werden nicht geändert. Der ursprüngliche B2-Komponentenpilot und die Inquiry-Fixture bleiben eigene, ausdrücklich begrenzte Fixtures.

Die neuen B8-Zusatzproben sind über `--svg --audit` ausdrücklich optional; Standardaufrufe behalten ihre bisherigen 169/194 Assertions. Es wurde keine erwartete Geschäftsantwort zum Bestehen eines Tests geändert und kein bekannter Fehler pauschal ausgeblendet.

## 4. Bestehende Fehler, Grenzen und manuelle Sichtprüfung

| ID / Priorität | Befund / Ursache | Einordnung und Folge |
| --- | --- | --- |
| Q1 / hoch vor EN-Gesamtfreigabe | Globale englische Menüs/Breadcrumbs zeigen technische Keys. `languages/en_US/default/menu.php` fehlt; manche Menükeys stehen nur im jeweiligen Modulkatalog, andere fehlen ganz. `MENU_ITEMS` ist z. B. im aktiven Itemmodul verfügbar, nicht automatisch in Inquiry. | Keine SVG-Buttonlabels betroffen. Bootstrap/Translate-Plugin und Kataloge gegenüber vor B2 unverändert. Kontextabhängige Lade-/Kataloglücke, kein neuer Buttonfehler. B8 korrigiert die bislang beschönigende Fixture, nicht die Anwendungskataloge. Separaten kleinen Lokalisierungsauftrag vor EN-Freigabe vorsehen. |
| Q2 / hoch vor mobilem EN-Umsatzreport | `STATISTICS_TURNOVER_MONTHS`, `...CUSTOMER_GROUPS_YEARLY`, `...YEARLY_COMPARISON` bleiben technische englische Überschriften. Bei 390 px beträgt die Dokumentbreite 528 px. | Schon mit unveränderter Legacy-Unterklasse reproduziert, im vollständigen B7-/B8-Test direkt verglichen. Reportcontroller, View und EN-Reportkatalog sind gegenüber vor B2 unverändert. Die Toolbar bleibt innerhalb des Viewports. Nur diese konkrete gleiche Legacybreite wird toleriert; kein allgemeines Abschalten der Überlaufprüfung. |
| Q3 / mittel | Mobile Kopf-/Menüblöcke beanspruchen viel Höhe; die Admin-Seitenleiste steht vor dem Listeninhalt. | Die entsprechenden Layoutteile sind gegenüber vor B2 unverändert; Layoutdiff zeigt dort ausschließlich die zusätzliche Symboldefinition. Die vorgesehenen 44-px-Touchcontrols vergrößern zusätzlich manche Toolbarzeile bewusst. Kein Beleg einer unbeabsichtigten neuen Regression. Produktentscheidung zu Dichte/Reihenfolge bleibt erforderlich; kein globaler CSS-Umbau in B8. |
| Q4 / mittel, Accessibility-Abnahme | Filter-Button/Panel melden den offenen Zustand nicht über `aria-expanded`/`aria-controls`; `DewawiToolbar` schaltet nur `is-open`. | Bereits vor B2 so vorhanden; kein durch SVG eingeführter Fehler. Eigener kleiner semantischer Verbesserungsblock mit Fokus-/Screenreadertest erforderlich. Die bestandenen Tastaturtests beweisen keine vollständige Disclosure-Semantik. |
| Q5 / Freigabegrenze | `tasks` ist kein registriertes reguläres Modulrecht in `DEEC_Permission::MODULES`; unbekannte Module erhalten Legacy-Sichtbarkeit. Admin wird separat über `user.admin` im ACL geschützt. | Kein neuer B8-Rechtefehler; keine Rechte-/Schemaänderung vorgenommen. Renderingbeobachtung ist keine Berechtigungserteilung. Granulare produktive Tasks-Rollen müssen fachlich separat geklärt werden. |
| Q6 / Freigabegrenze | Vollständige SaaS-Entitlement-Matrix für interne Ansichten lokal nicht nachgewiesen. `hasModule()` ist eine Berechtigungs-/Legacyklassifikation; `Client.modules` beschreibt Sharing. `DEEC_Site_Context::hasFeature()` betrifft die ausgeschlossenen Site-/Shopwege. | Keine neue Featureprüfung erfunden oder vorhandene Prüfung umgangen. Die betroffenen Bootstrap-/ACL-/Permission-/Site-Komponenten sind seit vor B2 unverändert. On-/Off-Buchungskonfigurationen bleiben eine reale Abnahmeaufgabe; die UI-Fixtures liefern dafür keinen vollständigen E2E-Nachweis. |
| Q7 / unabhängig | Sales-Notification-PHP-Fixture lädt die neue DocumentRevisionService-Abhängigkeit nicht. | Seit B2 dokumentiert, erneut unabhängig reproduziert; kein Testgrün für diese Teilsuite behauptet. Separat im Dokument-/Testkontext beheben. |

Repräsentative in B8 gesichtete, durch die Runner neu erzeugte Screenshots: Inquiry-Edit EN mobil mit nativem Save und sichtbarem Fokus, Item-Select mobil mit bewusst weiterhin Legacy-Apply, sowie neue vergrößerte DE/EN-Komponentenproben. Zusätzlich stehen die durch die vollständigen Matrizen erzeugten Desktop-/Mobilartefakte der übrigen Profile zur Freigabe bereit. Kein systematischer menschlicher Pixelvergleich aller 258 Kombinationen behauptet.

Artefakte liegen nur unter `/tmp`: die Runner verwenden weiter ihre historischen Präfixe `dewawi-b4-after-*`, `dewawi-b5-*`, `dewawi-b6-tasks-*`, `dewawi-b7-*`; die neuen Proben heißen `dewawi-b8-quality-<de_DE|en_US>-zoom.png`. Logs dieses Audits: `/tmp/dewawi-b8-*.log`. 768 px werden automatisiert gemessen; die bestehenden Screenshotrunner speichern hauptsächlich 390/1280 px. Eine gesonderte manuelle 768-px-Sichtung gehört zur Freigabematrix.

### Authentifizierte E2E-Verfügbarkeit und Sicherheitsgrenzen

Eine isolierte vollständige ERP-Installation mit Testkonten, allen 13 Ansichten und belegbar unproduktiver Datenhaltung war für diesen Lauf nicht nachgewiesen. Der vorhandene `tests/lists/footer-http.py` bootet ausdrücklich `production` mit `configs/database.ini`, liest einen realen Admin und schreibt eine Session. Er wurde **nicht** ausgeführt. Auch ein lokal erreichbarer Webserver wäre allein kein Nachweis isolierter Kundendaten. Keine Produktionskonfiguration oder Kundeninhalte ausgelesen, keine produktiven Sessions erzeugt oder Schreibaktionen ausgeführt.

Die sicheren vorhandenen Session-/Objekt-HTTP-Suites wurden tatsächlich ausgeführt und sind oben mit ihrer jeweiligen Authentifizierungsgrenze ausgewiesen. Sie werden nicht als durchgehender Benutzerlogin → Toolbar → echte Geschäftsaktion ausgegeben. Die UI-Suites selbst setzen synthetische Identitäten und intercepten AJAX. Ein neues Datenbank-/E2E-Testsystem einzuführen wäre keine notwendige eng begrenzte B8-Korrektur; es wurde auch keine wegwerfbare Datenbankinstallation gestartet. Keine Migration oder Änderung bestehender Datenbanken, gespeicherter Inhalte, installierter Browserbibliotheken oder Drittanbieterwidgets.

## 5. Konkreter Freigabeplan

| Gate / Verantwortungsrolle | Durchführung und nachprüfbares Abnahmekriterium | Aktueller Status |
| --- | --- | --- |
| G1 – Entwicklung: technischer Stand | Verwendeten Commit festhalten; alle aufgeführten PHP-/Browser-/HTTP-Suites, stabile Konfigurationen und enge Opt-in-Grenzen prüfen. Bekannte unabhängige Fehler eindeutig abtrennen. | Isolierter Umfang bestanden, einschließlich vollständiger B5-/B6-/B7-Abschlussläufe nach Fixture-Korrektur. Keine Laufzeitkorrektur erforderlich. |
| G2 – QA/Plattform: sichere E2E-Umgebung | Belegbar isoliertes Staging mit synthetischen Daten und zwei Mandanten plus Parent-/Child-Beziehung bereitstellen; Outbound-Mail/API begrenzen. Testkonten für Admin, View-only, Add/Edit und ohne Modulrecht sowie gebuchte/nicht gebuchte SaaS-Features dokumentieren. Keine Produktionssession übernehmen. | Offen. Keine entsprechende vollständige Umgebung in B8 benutzt. |
| G3 – QA: reale Geschäftsabläufe | Auf der 13-Gruppen-Matrix echte Index-/Select-/Edit-/View-Seiten mit kurzen/langen Datensätzen und gefüllten Reports prüfen. Add/Edit/Copy, Kopf/Fuß, Filter/Datum/Reset, Popup-Rückgabe, AJAX-Austausch und Auto-Save inklusive Opt-out/Fehler/Loading testen; unberechtigte/fremde Ziele serverseitig ablehnen. Tasks-Status/Zuweisung und UOM-Default nur mit Testdaten prüfen. | Offen; Fixture-/Memory-/HTTP-Verträge grün, vollständige Controller-/Options-/DB-Pipelines nicht abgenommen. |
| G4 – Produkt/Design: visuelle Abnahme | DE/EN, 390/768/1280 px, Kopf/Fuß/Popup, Fokus/Hover/Disabled/Loading und lange Labels sichten. Q1/Q2 vor EN-Gesamtfreigabe lösen oder den betroffenen Umfang ausdrücklich zurückstellen; Q3 als bekannte Produktentscheidung festhalten. | Offen. Automatische Geometrie und repräsentative Sichtung bestehen; das ist keine fachliche Unterschrift. |
| G5 – QA/Accessibility: Browser und Assistenztechnik | Unterstützte Browserliste festlegen; reale Desktopbrowser, iOS/Android-Touch, Safari/Firefox sowie NVDA/VoiceOver prüfen. Tab/Shift-Tab/Enter/Space, Scroll-/Fokusverlauf, Namen, Busy-/Fehlermeldungen, Popupfokus und Q4 prüfen; echte Zoom-/Textvergrößerung und Betriebssystem-Kontrastmodi testen. | Offen. Nur Chromium/Linux automatisiert, AX-Daten sind kein tatsächlicher Screenreaderlauf; CSS-Zoomprobe ersetzt keinen plattformweiten Zoomtest. |
| G6 – Freigabeverantwortliche | Ergebnisse der Gates pro Kontext/Locale festhalten, Q5/Q6 fachlich klären, gegebenenfalls begrenzte Freigabe ausdrücklich benennen. Erst danach separat genehmigten Rollout planen. | Keine Gesamt-/Produktivfreigabe durch B8, kein Rollout ausgeführt. |

Aktuell vertretbar ist die Aussage **„technisch für den getesteten isolierten Renderingumfang vorbereitet“**. Nicht vertretbar sind „alle authentifizierten ERP-Abläufe E2E abgenommen“, „SaaS-/Rollenmatrix vollständig nachgewiesen“ oder „vollständig barrierefrei“. Bestehende fehlende Nachweise werden nicht durch neue UI-Metadaten ersetzt.

## 6. Priorisierter B9-Backlog

Vor einer weiteren Migration Q1/Q2 lokalisieren und die offenen Freigabegates bearbeiten; Q4 kann als gesonderter kleiner Accessibility-Block beauftragt werden. Diese bestehenden Mängel sind keine stillschweigend in B8 umgesetzten Änderungen.

| Priorität / verbleibende Gruppe | Konkreter Umfang, Abgrenzung und Tests |
| --- | --- |
| 1 – statische Übernahme in Kontakt-/Artikelauswahl | `Contacts_Form_Toolbar::apply` und `Items_Form_Toolbar::apply`, gemeinsam genutzter Select-Kopf/-Fuß. Vorhandener SVG-Key `apply`; keine neuen Icons nötig. Parent-/Dialog-/Callback-/Auswahlverträge, mehrere IDs, richtiger Zielmandant, Search-Neuausgabe, Tastatur und Fokusübernahme prüfen. Nicht pauschal Inline-Apply mitnehmen. Tasks/Admin besitzen hier keinen Apply-Eintrag; keinen erfinden. |
| 2 – gewöhnliche Zeilen-Navigation/Kopie in bereits freigegebenen Modulen | Kontextspezifische View/Edit/Copy-Controls aus `Contacts_Form_ToolbarInline`, `Items_Form_ToolbarInline`, `Tasks_Form_ToolbarInline` und eng begrenzten Admin-Inlineaufrufen. Nur tatsächlich vorhandene Controls migrieren; Items hat dort z. B. keinen View-Eintrag. Row-IDs, Rechte, Parent-/Selectkontext und SVG-/use-Klicks nach AJAX absichern; Labels explizit, alte PNG-Sonderkontexte unverändert. PDF, Apply, Save, Sortierung und Delete dieser Klassen zunächst ausnehmen. |
| 3 – zentrale Standard-Löschcontrols der bereits migrierten Kontexte | Erst mit eigener Abnahme des bestehenden Bestätigungs-/Cancel-/Fehler-/Loading-/Fokusvertrags. Keine Dokument-, Zahlungs-, Status- oder Medienlöschung einbeziehen. `delete`-SVG existiert. Serverrechte und negative Mandantenfälle prüfen; Gefahrdarstellung nicht als Änderung der fachlichen Löschregeln behandeln. |
| 4 – Positions-/Gruppentoolbars nach Erreichbarkeitsprüfung | `ToolbarPositions`, `ToolbarPositionSets`, alte `addset`-/`add-position-set`-Namenswege, Parent-/Set-/Auswahl-/Sortierzustände zuerst klären. Kein globales Aktivieren der vorhandenen Klassen. Daten-/Reihenfolgeverträge und bestehende Spezialkontexte machen diese Gruppe deutlich riskanter. Nicht als automatisch freigegebener B9-Massenblock behandeln. |
| Separat / später | Dokumente/PDF/Exportformate, Mail, Zahlungen, Finanz-/Rechte-/Mandantenkonfiguration, Preisregeln, Bestandsbuchungen, Kampagnen, Shop/CMS, eBay und Drittanbieterwidgets. Eigene fachliche Abnahme und expliziter Auftrag erforderlich; kein pauschales Entfernen der aktuellen Guards. |

Der Restbestand bleibt unverändert zu B7: 42 Toolbar-Dateien (34 Formulare/acht Helper), sechs von 13 Hauptfamilien begrenzt migriert, sieben Hauptfamilien vollständig Legacy. Sieben Toolbarformular-Dateien besitzen begrenzte SVG-Opt-ins; 27 bleiben vollständig Legacy. Die allgemeine Inquiry-Form gehört zusätzlich zu den acht tatsächlichen SVG-Erzeugerdateien. B8 migriert keine weitere Gruppe.

## 7. Änderungen und Abschluss

Nur Test-/Dokumentationsänderungen: `toolbar-buttons.php` präzisiert den realen Übersetzungskontext, `remaining-toolbars.php` prüft ihn gegen das Route-Plugin, `buttons.py --svg --audit` liefert zehn ergänzende Qualitätsproben. Dieser Bericht verlinkt Befunde, Grenzen, Freigabeplan und B9-Reihenfolge; B7 erhält einen Fortsetzungslink. B1 wird mit ausdrücklicher Zustimmung unverändert versioniert. Keine Änderung am Laufzeitverhalten.

Abschließende PHP-Syntaxprüfung der zwei geänderten PHP-Fixtures, Python-AST, Berichtlinks, `git diff --check`, Status und vorgesehene Dateiliste geprüft. Ausschließlich B8-Tests/-Berichte und den ausdrücklich gewünschten unveränderten B1-Bericht lokal committen; der konkrete Hash wird im Abschluss genannt. Keine Datenbankmigration oder manuelle Datenbanknacharbeit. Kein Push, Merge, Deployment oder automatische Fortsetzung mit B9.
