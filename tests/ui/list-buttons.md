# B10 – Gewöhnliche Tabellen- und Listenaktionen

Fortsetzung: [B11 – mobile Tabellenaktionen](mobile-table-actions.md) korrigiert die hier dokumentierte mobile Zeilenhöhe. Die [aktuelle Gesamtinventur](button-system-inventory.md) trennt Quellstellen, Aktivierungskontexte und sichtbare Fixturecontrols. Die folgenden Messungen beschreiben den historischen B10-Stand.

Stand: 19.11.2026. Grundlage: `AGENTS.md`, die Berichte [B1](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5](toolbar-buttons.md), [B6](tasks-toolbar.md), [B7](remaining-toolbars.md), [B8](button-quality-audit.md), [B9](apply-buttons.md) und der lokale Code. Ausgangs-HEAD: `c8f9fdec`; Arbeitsverzeichnis zu Beginn sauber. Die unabhängigen Dokument-/Geocodingcommits bleiben erhalten. B2–B9 sind lokal vorhanden: `b27f55e6`, `2af50303`, `20ebf9d9`, `aaca1571`, `f23f05fb`, `b523e3b4`, `30db90b2`, `9ed0619f`.

## Umfang und Entscheidung

Ursache der unterschiedlichen Zeilenbuttons sind fehlende explizite Präsentationsmetadaten in den bestehenden Inlineformularen. B10 ergänzt nur diese Metadaten; die gemeinsame Routine `DEEC_Form::renderButtonHtml()` und die lokale SVG-Allowlist bleiben unverändert.

| Exakte Klasse unter `application/modules/` | Controller | Migrierte tatsächliche Zeilenfelder |
| --- | --- | --- |
| `contacts/forms/ToolbarInline.php` | `contacts/contact` | `view`, `edit`, `copy` |
| `items/forms/ToolbarInline.php` | `items/item` | `edit`, `copy`; **kein View-Feld vorhanden**, keines ergänzt |
| `tasks/forms/ToolbarInline.php` | `tasks/task` | `view`, `edit`, `copy` |
| `admin/forms/ToolbarInline.php` | `admin/country`, `uom`, `deliverytime`, `manufacturer`, `warehouse`, `shippingmethod` | ausschließlich `copy`; die tatsächlichen sechs Listen verwenden kein Inline-View/Edit |

Opt-in ausschließlich bei exakter Klasse, passendem Modul/Controller, Requestaktion `index` oder `search` **und** `$request->getParam('context_action', 'index') === 'index'`. Kein Opt-in für Unterklassen, Edit/View/History/Get/Export oder Search im Auswahl-/ungültigen Kontext. Die bereits bestehenden B9-Apply-Opt-ins für Select/Search-Select bleiben unverändert. Der reale `searchAction()` setzt den logischen Auswahlkontext vor dem Formaufbau; dieser Vertrag ist weiterhin durch B9 geprüft.

Neun zusätzliche Feldkonfigurationen in vier Formularquellen ergeben 14 controllerbezogene Controlkonfigurationen in neun Listenmodellen bzw. 18 Index-/Search-Routenpaaren. Das zählt Metadaten, keine gleichzeitig sichtbaren Buttons. Keine neuen Aktionen, Symbole, CSS-Regeln, JavaScript-Handler, Renderer oder Bibliotheken. Löschaktionen werden in B10 bewusst nicht umgestellt.

## Inventar der Render- und Aufrufwege

| Weg / Fundstelle | Verarbeitung und Grenze |
| --- | --- |
| `library/DEEC/Controller/Action.php::{getToolbarInlineClass,buildListView,searchAction}` | Ermittelt vorhandene Modulklasse, baut Inlineform und konkretes Listenmodell, setzt Kontext/Optionen. Kontakt-/Artikel-/Taskscontroller und sechs Admincontroller verwenden diesen Weg. Datenabfragen und Controller bleiben unverändert. |
| `library/DEEC/List.php::{renderCell,renderActionsCell}` | Gemeinsame Actionszelle: bestehende `show`-Callbacks, dann `renderElement()` mit bestehender Rechteprüfung, anschließend Ergänzung von `data-action`, `data-id`, `data-module`, `data-controller`. Reihenfolge und Attributinjektion bleiben unverändert; keine Rechteentscheidung in der Präsentation. |
| `application/views/scripts/list/list.phtml` | Rendert Checkboxen/Hidden-Targets, reale Spalten/Zeilen und Footer. Keine separate SVG-Ausgabe und keine neue Tabellenstruktur. |
| `DEEC_List::{renderLinkCell,renderPinCell}` | Native Datensatzlinks bleiben Links mit unverändertem `href`. Anheften/Abheften hat einen eigenen Inline-/Handlervertrag und bleibt Legacy. Kein Ersetzen von Links durch Buttons. |
| `library/DEEC/Display.php` | Wertformatierung für Form-/AJAXwerte; kein Tabellenaktionsrenderer. Unverändert. |
| `DEEC_Form::{renderElementHtml,renderButtonHtml}`, `application/views/helpers/Button.php` | Bestehende gemeinsame Präsentation. Alter Positionsparameter-Helper bleibt Legacy; kein globales Aktivieren. |
| `js/global.js::{search,DewawiToolbar.bindActions,resolveSelection,copyEntities}` | Server liefert dieselben `content`-/`toolbar`-Fragmente; `#content` einschließlich Zeilen/Footer wird ersetzt. Die bereits delegierten `[data-action]`-Handler erfassen auch SVG-/use-Klicks. Explizite Row-ID hat weiterhin Vorrang vor markierten Checkboxen. |
| `application/plugins/ListResponse.php` | Bestehender Fragmentvertrag; kein Symbolblock im Fragment, genau ein vorhandener lokaler Block im Voll-/Plainlayout. Keine Layoutänderung. |
| `partials/history.phtml`, `Contacts_Model_List_History`, `reloadHistory()` | Verwendet die Kontakt-Inlineklasse auch für fremde Dokument-/Vorgangskontexte. Durch konkrete Request-/Klassengrenzen unverändert. |
| `application/views/helpers/Media.php`; Adminhelper `Pageblocks`, `Menuitems`, `SitePages`, `SiteMenus` | Gemeinsames Admin-Inlineformular in Spezial-/CMS-/Medienkontexten. Save/Delete/Sort bleiben Legacy, diese Controller sind nicht in der Allowlist. |
| alte Tabellenviews `ebay/{account,listing,order}/index.phtml`, `shops/{account,order}/index.phtml` | Direkte `Button('copy')`-/`Button('delete')`-Ausgabe und abweichende Integration; ausgeschlossen. Weitere Settings-, Redirect-, Kalender-/Teilnehmerformulare sind native Submit-/Spezialabläufe. |
| dynamische Controls in `global.js`, `MultiForm.php`, Positions-/Dialog-/Mailpartials | Filemanager, Alertschließen, Modalclose, Positions-/Setaktionen, Kontaktezuordnungen, Upload-/Mail-/Dokumentaktionen besitzen eigene Verträge. Keine neue dynamische SVG-Factory, keine Änderung gespeicherter Inhalte. |

Statisches Inventar: **50** PHP-Dateien unter `application/modules/*/models/List/`, davon **44** mit `'type' => 'actions'`. Reproduzierbar mit `rg -l "'type' => 'actions'" application/modules/*/models/List/*.php`. Das sind Definitionsdateien, keine gezählten erreichbaren HTTP-Endpunkte.

| Modul | Actions-Listenmodelle / Entscheidung |
| --- | --- |
| Admin, 23 | **Countries, Uoms, Deliverytimes, Manufacturers, Warehouses, Shippingmethods:** nur Copy in B10. **Categories, Clients, Currencies, Exports, Footers, Menuitems, Menus, Modules, Pageblocks, Pages, Paymentmethods, Shops, Slides, Tags, Taxrates, Templates, Users:** unverändert; Finanzen/Rechte/Mandanten/CMS/Shop/Export bzw. noch nicht separat nachgewiesene Spezialverträge. |
| Kontakte, 3 | **Contacts:** View/Edit/Copy in B10, B9-Auswahl bleibt. **Inquiries:** bereits begrenzter B4-Pilot, keine B10-Änderung. **History:** ausgeschlossen. |
| Artikel, 7 | **Items:** Edit/Copy in B10. **Inventories, Itemlists, Ledgers, Movements, Pricerules, Stockplannings:** Bestands-/Positions-/Preisregel-/Buchungskontexte bleiben unverändert. |
| Aufgaben, 1 | **Tasks:** View/Edit/Copy in B10; Status/Zuweisungen/Pin/Delete und Positionsform unverändert. |
| Kampagnen, 1 | **Campaigns:** fachliche Kampagnen-/Empfängerabläufe, unverändert. |
| Vorgänge, 1 | **Processes:** Parent-/Positions-/Dokumentkontext, unverändert. |
| Einkauf, 2 | **Purchaseorders, Quoterequests:** Dokumentaktionen, unverändert. |
| Verkauf, 6 | **Creditnotes, Deliveryorders, Invoices, Quotes, Reminders, Salesorders:** Dokument-/Zahlungsabläufe, unverändert. |

Die sechs übrigen Listenmodelle ohne Actionszelle sind `campaigns/Recipients`, `items/{StockHistory,Stocks}`, `statistics/{Customers,Quotes,Turnover}`; keine Zeilenaktion erfunden. Es gibt zehn Inlineformularquellen: neun `ToolbarInline.php` plus `contacts/InquiryToolbarInline.php`. Kontakte/Artikel/Tasks/Admin/Inquiry besitzen jetzt begrenzte Opt-ins; Kampagnen/eBay/Vorgänge/Einkauf/Verkauf bleiben vollständig Legacy. Keine produktiven Inlineunterklassen gefunden; synthetische Unterklassen sichern die Grenze zusätzlich ab.

Damit sind zehn der 44 Actions-Listenmodelle teilweise durch B4/B9/B10 abgedeckt, 34 bleiben außerhalb dieser SVG-Zeilenmigration. Innerhalb der zehn bleiben insbesondere Delete, Pin und Spezialfelder Legacy. Bezogen auf die historische B9-Metadatenzählung: 45 Präsentationskonfigurationen / 84 controllerbezogene Konfigurationen, zwölf SVG-erzeugende Formularquellen einschließlich Inquiry-Form, elf von 34 Toolbarformulardateien mit begrenztem Opt-in, 23 vollständig Legacy. Haupttoolbarfamilien und primäre Controllergruppen bleiben bei sechs von 13 bzw. 13; keine Haupttoolbar neu migriert.

## Semantik, Darstellung und Verhalten

Alle neun Felder bleiben native `button`, `type=button`; kein Submit/Reset, kein neuer Formularkontext. Namen, Values, bestehende Aktionsklassen, Row-Targets, `data-*`, `onclick` und rendererbedingte Attributnormalisierung bleiben erhalten. Die weiterhin vorhandene Normalisierung in `DEEC_Form` wird nicht nebenbei geändert. Ein Vorher/Nachhervergleich ignoriert nur die beabsichtigte zusätzliche Präsentation sowie den neuen zugänglichen Namen/Tooltip.

Explizite Keys: `view→view`, `edit→edit`, `copy→copy`. `TOOLBAR_VIEW`, `TOOLBAR_EDIT`, `TOOLBAR_COPY` ergeben „Anzeigen / Bearbeiten / Kopieren“ bzw. „View / Edit / Copy“. Icon-only erhält denselben übersetzten Text als `aria-label` und natives `title`; keine technische Namensheuristik. Kein Tooltipplugin und kein versteckter leerer Text-/Iconplatz. Native Tooltipdarstellung und Screenreaderbeschreibung müssen auf realen Browser-/Hilfsmittelkombinationen noch gesichtet werden. SVG bleibt dekorativ mit `aria-hidden=true`, `focusable=false`, `currentColor`; kein eigenes SVG-Title und kein zweites PNG hinter dem Icon.

Bestehende Default-/sm-Präsentation: mindestens 32 px, bei ≤767 px/grobem Pointer 44 px; Icon 16/20 px, zentriert. Fokus, Hover, Disabled und Loading ausschließlich aus B2/B3. Herkunft/Lizenz unverändert gemäß [B3](button-icons.md): lokal kuratierte Lucide-0.468.0-Symbole mit ISC-/Feather-MIT-Nachweisen, keine neue Bibliothek/CDN-Anfrage oder CSP-Anpassung.

View/Edit navigieren weiterhin zur Row-ID, auch wenn eine andere Zeile ausgewählt ist. Copy bleibt ein einzelner Row-POST über die vorhandene Kopierlogik; kein Umschalten auf Checkbox-/Massenkopie. Die bestehende ID-bezogene Sperre verhindert überlappende Row-/Footer-Aufrufe. Fehler erhält Auswahl und ursprüngliche SVG-Nodes und erlaubt Wiederholung; Erfolg verwendet denselben Searchrefresh samt bestehendem Auswahlreset. Neue Zeilen/Footer reagieren ohne neue Initialisierung auf delegierte SVG-/use-Klicks. Keine neue Bestätigung hinzugefügt.

ACL/Objekt-/Mandanten-/Sharing-/SaaS-Prüfungen bleiben serverseitig an ihren bisherigen Stellen; Metadaten entscheiden keine Rechte. Kontakt-/Artikel-View/Edit benötigen weiterhin View/Edit, Copy weiterhin **Add**. Tasks und Admin sind nach bestehendem `DEEC_Permission::MODULES` keine regulär registrierten Modulrechte: Formrendering behält Legacy-Sichtbarkeit, Adminzugriff wird im Controller/ACL gesperrt. Eine isoliert konstruierte Adminform ist kein Adminberechtigungsnachweis. Negative Controller-/HTTPbaselines sind getrennt ausgeführt; granulare Tasks- und reale SaaS-Konfigurationen bleiben B8-Abnahmegrenzen.

## Ausgeschlossene Aktionen und Löschrisiken

- Auswahl-/Apply-Aktionen aus B9 sind eigene Kontexte und unverändert. Index-Apply bleibt Legacy; keine Ausweitung auf Filterübernahme, native Submits oder komplexe Massenaktionen. Tabellencheckboxen und Fuß-/Kopftoolbars behalten ihre Verträge.
- `Contacts/Items/Tasks::delete` bleibt exakt Legacy. `TrashController::addAction()` prüft Zielrechte, Tenantowner, gelockte Rows und fachliche Sperren; Kontakte besitzen Kaskaden/Referenzsperren, Artikel zusätzliche eBay-Verknüpfungen. Bestehende Kontaktlöschtests und generische Trash-/Tenantbaselines bestehen, ersetzen aber keine vollständige UI-Abnahme aller betroffenen Löschvarianten.
- `Admin::delete` trägt den Spezialhook `media-delete`; die Actionszelle ergänzt bereits unabhängig davon `data-action=delete`. Dieser bestehende doppelte Attributvertrag wird weder bereinigt noch für eine SVG-Migration als geklärt angenommen. Medien-/CMS-Kontexte bleiben ausgeschlossen.
- Save/PDF/Export, Sortierung, Pin, Positions-/Gruppenaktionen, Dokumente, Mail, Zahlung, Shop/CMS, eBay/Drittanbieter, Kalender-/Teilnehmeraktionen sowie Finanz-/Rechte-/Mandantenkonfiguration bleiben unverändert. Gleiche CSS-Klasse oder Beschriftung ist keine Migrationsfreigabe.

## Tatsächlich ausgeführte Tests

Alle Geschäftszeilen, Rechtekonfigurationen und Antworten der UI-Fixtures sind synthetisch. Kein Produktionsbootstrap, keine Produktionssession, keine Datenbank-/SMTP-Verbindung oder produktive Schreibaktion. `tests/lists/footer-http.py` wurde ausdrücklich nicht ausgeführt: verwendet Produktionskonfiguration und erzeugt eine echte Adminsession.

| Prüfung | Ergebnis |
| --- | --- |
| Vorher-Baseline | B2/B3/B4/B5/B6/B7/B9-PHP bestanden; bestehender Kontaktbrowser 500 Assertions. Vier vollständige reale Listenrenderings und historische Konstruktor-Snapshots vor der Laufzeitänderung unter `/tmp/dewawi-b10/` gesichert. |
| `php tests/ui/list-buttons.php` | PASS: **7.374** neue Checks, zusätzlich eingebundene 165 B2-/2.259 B5-Checks. Exakte Metadaten, Routengrenzen, Unterklassen, native/Target-/Eventattribute, DE/EN-Namen/Tooltip, reale neun Listenmodelle mit allen Spalten, Layout/Plain, AJAXfragmente, Readonly, native Links, tatsächliche Row-IDs und positive/negative Kontakt-/Artikelrechte sowie fremder Benutzer. |
| `python3 tests/ui/list-buttons.py` | PASS: **1.470** Assertions, neun Listen × DE/EN × 390/768/1280 px, zusätzlich Readonlyseiten. Reale PHP-Layouts/Listenzellen, CSS, jQuery und `global.js`; AX-Namen, dekorative Icons, SVG-Geometrie/Farbe, kein PNG-Doppelicon, Größe/Ausrichtung, Hover, sichtbarer Fokus, Enter/Space/Tab/Shift-Tab, Row-ID gegen fremde Checkboxwahl, gemeinsamer Row-/Footer-Kopierlock, Fehler/Retry, Loading-/Disabled-/ARIA-Disabled-Schutz, dynamische Searchzeilen/-Footer und delegierte use-Klicks. Kein fachlicher Copy-Schreibzugriff. |
| Historische Snapshots | B5: **480/480 identisch**. B6: **2/350** geändert, ausschließlich Tasks-Inline-View/Edit/Copy bei Index/Search. B7: **12/1.900** geändert, ausschließlich Admin-Inline-Copy in sechs Index-/Searchkontexten. B9: **2/80** geändert, ausschließlich Kontakt-/Artikel-Inline-Standardaktionen bei Search/Index; sämtliche Auswahl-/Apply-Konfigurationen identisch. Neue PHP-Matrix ergänzt den in der historischen B9-Matrix fehlenden gewöhnlichen Indexkontext. |
| `php tests/ui/{button-icons,inquiry-buttons,toolbar-buttons,tasks-toolbar,remaining-toolbars,apply-buttons}.php` | PASS: 645 / 697 / 2.259 / 1.428 / 27.105 / 1.459 Checks zuzüglich eingebundener Baselines. Frühere pauschale Inline-Legacy-Erwartungen ausschließlich durch genaue B10-Feld-/Kontextlisten ersetzt; keine Sonderkontextprüfung entfernt. |
| `python3 tests/ui/buttons.py`; `--svg --audit`; `inquiry-buttons.py`; `apply-buttons.py` | PASS: 169 / 204 / 796 / 302 Assertions; PNG-Kompatibilität, lokale CSP, iframe, Forced Colors, Zoom, Kontrast-/Touchproben, native Typen und reale Auswahl-/Modal-/Auto-Save-Grenzen bleiben abgedeckt. |
| `python3 tests/ui/toolbar-buttons.py`; `--tasks`; `--remaining` | PASS: 1.000 / 524 / 3.247 Assertions. B5–B8-Kopf-/Fußtoolbars, Filter und Sonderkontexte unverändert. |
| `python3 tests/ui/notifications.py` | PASS: 1.985 Assertions je 390/1280 px, insgesamt 3.970; Dokument-/Mail-/Dialog-/Auto-Save-Baselines mit abgefangenem I/O. |
| `php tests/security/{permissions,admin-access,tenant-access,bootstrap-auth}.php` | PASS: 415 / 68 / 269 / 29 Fälle. Keine neue Rechte-/Tenantlogik; erwartete NO_PERMIT-Logs. |
| `python3 tests/security/{session-http,tenant-access-http}.py` | PASS: 25 / 48 isolierte lokale Session-/ACL-/Objekt-/AJAX-/Trashfälle, Root-/Unterverzeichnisinstallation. Kein vollständiger authentifizierter ERP-E2E-Lauf. |
| `php tests/security/{contact-inquiries,form-input-validation,inquiry-dynamic-form}.php` | PASS: 46 / 20 / 31 Fälle. |
| `php tests/contacts/contact-deletion.php`; `tests/sales/quote-monitoring.php`; `tests/lists/summary.php` | PASS: 16 / 293 Fälle bzw. zentrale Footer-/Summenprüfung. Erwartete simulierte Löschfehlerausgabe; keine Migration von Delete. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fixturefehler erneut bestätigt: 370 Checks bestehen, danach fehlt `Sales_Service_DocumentRevisionService` in `sales/views/helpers/Finish.php:12`; Abbruch in `tests/security/notifications.php:211`. Keine betreffende B10-Änderung. |
| Syntax / Diff | PHP-Lint der vier Laufzeitformulare und fünf PHP-Testdateien, Python-AST, relative Dokumentlinks und `git diff --check` bestanden. |

Die bestehende Toolbarfixture erhält einen ausdrücklich optionalen `list-actions`-Modus. Dieser verwendet die **wirklichen neun Listenklassen und alle tatsächlichen Spalten**, Inlineform, gemeinsame Partials und Layouts; keine nachgebaute Actionszelle. Der reale Currencyhelper wird für die Artikelspalte lokal registriert, kein Datenbankkurs geladen. Alle bisherigen Fixturemodi bleiben erhalten. Neue Tests führen View/Edit über abgefangenes `setLocation` und Copy über strikt abgefangene Requests aus; dies ist kein Nachweis echter Backendkopien oder einer vollständig gebuchten SaaS-Umgebung.

## Responsive Befunde und Freigabestatus

Alle 54 neuen Browserprofile enthalten Tabellenüberlauf innerhalb von `.dw-table-wrap`; kein zusätzlicher Dokumentüberlauf. Ein separater Browservergleich verwendet vier **vor** der Laufzeitänderung gesicherte Vollrenderings bei jeweils 390/768/1280 px. Tabellenbreiten und Linkgeometrie sind in allen zwölf Vergleichen gleich. Zeilenhöhe der zwei synthetischen Datensätze:

| Liste | 390 px vorher → B10 | 768 px | 1280 px |
| --- | --- | --- | --- |
| Kontakte | 171,75 → **207 px** | 115,22 → 115,22 px | 99 → 95 px |
| Artikel | 171,75 → 171,75 px | 99 → 95 px | 99 → 95 px |
| Aufgaben | 171,75 → **207 px** | 99 → 95 px | 99 → 95 px |
| Einheiten | 171,75 → 171,75 px | 57 → 57 px | 57 → 57 px |

Die zusätzliche mobile Höhe bei Kontakten/Tasks folgt den vorhandenen 44-px-Touchflächen. Sie ist eine beabsichtigte, sichtbare Folge von B10 und benötigt Produktabnahme. Schmale/teilweise zeichenweise umbrochene Texte und Tabellenüberschriften waren dagegen bereits vorher vorhanden; ebenfalls unveränderte technische Spalten-/Menükeys sind keine neuen SVG-ARIA-Namen. Kein engerer Spalten-/CSS-Sonderstil wird zur Beschönigung eingeführt. Reale lange Namen, Daten-/Optionsdichte und horizontales Scrollen müssen vor Freigabe in einer freigegebenen Testinstallation geprüft werden.

Artefakte: `/tmp/dewawi-b10/` enthält Logs, Vorher-/Nachher-Snapshots, Vergleichsmetriken und Vorher-/Nachherbilder; `/tmp/dewawi-b10-<contacts|items|tasks|admin-uom>-<390|768|1280>.png` zeigt den Fokus-/Fehler-/Retryzustand. Repräsentativ gesichtet: Kontakt mobil vor/nachher, Artikel 768 px und Aufgaben/Admin-Desktop. Die automatische vollständige Breiten-/Sprachmatrix ist keine menschliche Pixelabnahme aller Profile.

Technische Regressionstests bestanden; **keine fachliche oder visuelle Gesamtfreigabe behauptet**. Offen bleiben die B8-Gates: reale authentifizierte DE/EN-Rollen-/Mandanten-/SaaS-Konfigurationen, echte Copy-/Filterdaten im freigegebenen Testsystem, mobile Dichte, lange Inhalte, native Tooltips, physische Touchgeräte, Firefox/Safari und NVDA/VoiceOver. B8-Altbefunde zu englischen Menü-/Reportkeys und Filter-Disclosure bleiben separate Aufgaben. Keine produktiven Eingriffe für diese Abnahme erzeugt.

## Nächster sinnvoller B11-Block

1. Fachliche/visuelle Abnahme der gewachsenen Kontakt-/Taskszeilen und der bestehenden schmalen Tabellen vor breiterer Freigabe. Allgemeine Tabellenresponsivität/Übersetzung nur als eigener klarer Korrekturauftrag.
2. Danach gezielte Standard-Delete-Abnahme: Confirm/Cancel, exakte IDs, Fehler/Teilerfolg, Loading/Retry/Fokus und negative serverseitige Rechte-/Tenantfälle zusammen nachweisen. Kontaktkaskaden, Artikel/eBay-Verknüpfungen und Tasks-Rechte gesondert einordnen; Admin-`media-delete`-Doppelattributvertrag zuerst klären. Keine implizite Freigabe durch B10.
3. Positions-/Gruppen-/Pin-/Sortieraktionen erst nach vollständiger Parent-/Set-/Status-/Reihenfolgeinventarisierung. Dokument-/Mail-/Zahlungs-/Shop-/CMS-/Drittanbieterwege weiterhin getrennt beauftragen.

Laufzeitdateien ausschließlich `application/modules/{contacts,items,tasks,admin}/forms/ToolbarInline.php`. Tests: neue `list-buttons.php`/`.py`, optionale Ergänzung von `toolbar-buttons.php`, genaue Scope-Erwartungen in `tasks-toolbar.php`, `remaining-toolbars.php`, `apply-buttons.php`. Dokumentation: dieser Bericht und B9-Fortsetzungslink. B1-Analyse unverändert, SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`. Keine Datenbankmigration oder manuelle Datenbanknacharbeit. Nur eigene Änderungen lokal committen; kein Push, Merge oder Deployment.
