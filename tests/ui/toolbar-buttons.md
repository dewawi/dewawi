# B5 – Gemeinsame Standardtoolbars für Kontakte und Artikel

Stand: 18.11.2026. Lokale Grundlage: B2 `b27f55e6`, B3 `2af50303`, B4 `20ebf9d9`, [Bestandsaufnahme](buttons-icons-analysis.md), [Button-Grundlage](buttons-foundation.md), [SVG-Katalog](button-icons.md) und [Inquiry-Pilot](inquiry-buttons.md). Der unversionierte Analysebericht bleibt unverändert und außerhalb des Commits.

Fortsetzung: [B6 – Tasks-Standardtoolbar](tasks-toolbar.md) übernimmt die gezielt geprüften Tasks-Controls. Die folgenden Gruppen-/Inventarangaben beschreiben den historischen B5-Stand; B5 bleibt durch eigene unveränderte Baselines abgesichert.

Aktuelle Konsolidierung: [B7 – verbleibende Standardtoolbars](remaining-toolbars.md) ergänzt sechs einfache Admin-Stammdatenkontexte und die Filter/Reset-Gruppe dreier Statistikberichte, mit aktualisierten Bestandszahlen und ausdrücklichen Ausnahmen.

## Architekturentscheidung und konkreter Umfang

Der gemeinsame Renderer existiert bereits: `DEEC_Form::renderElementHtml()` bereitet Rechte, Readonly, Übersetzung und Attribute vor und delegiert an `renderButtonHtml()`/`renderButtonIcon()`. Der View-Helper `Button::renderButton()` verwendet dieselbe Präsentation. `Toolbar()` und `ToolbarBottom()` rufen bereits `renderElement()` bzw. `renderElementWithAttribs()` auf. B5 aktiviert die vorhandene Präsentation über ausdrückliche Metadaten; eine zweite Renderarchitektur oder erneute Helperimplementierung ist unnötig.

Die Modulklassen sind auch in anderen Kontexten im Einsatz. Eine pauschale Änderung würde Mail-/Shop-/Adminansichten und vier Artikel-Unterklassen erfassen. Daher wird nur die **exakte Klasse im ausdrücklich gewählten Kontext** aktiviert:

| Gruppe | Kontext | Migrierte Controls |
| --- | --- | --- |
| `Contacts_Form_Toolbar` | `contacts/contact`, ausschließlich `index`, `search`, `select`, `edit`, `view` | `add`, `edit`, `copy`, `filter`, `reset`, `clear` |
| `Items_Form_Toolbar` | `items/item`, dieselben fünf Aktionen | dieselben sechs Controls |
| Gemeinsame Kopf-/Fußtoolbar | Vorhandene Aufrufstellen dieser beiden Gruppen | dieselbe Formularinstanz liefert die Metadaten; keine zweite Buttonausgabe. |
| Inquiry | Vorhandener B4-Kontext | unverändert. |

Das sind **12 logische Konfigurationen**. Im Index erscheinen je Profil sechs SVG-Controls oben und drei erneut verwendete Standardaktionen unten. Im Auswahlkontext wird aus dieser Gruppe nur `clear` sichtbar; Übernehmen bleibt Legacy. Edit/View rendern nach vorhandener Kopfkonvention `copy`; es werden keine zusätzlichen Aktionen eingeführt.

Feste, geprüfte Zuordnung: `add→add`, `edit→edit`, `copy→copy`, `filter→filter`, `reset→reset`, `clear→close` (vorhandener B3-Alias). Alle Symbole existieren lokal. Keine neue Bibliothek, kein Asset, keine Zuordnung aus beliebigen Texten oder technischen Namen. Filter bleibt Filterpanel; die bestehende Suche erfolgt über die Texteingabe. Für „Anzeigen“ besitzen diese beiden Kopf-Toolbarformulare keinen Button; andere Inline-/Tabellenaktionen werden deshalb nicht in B5 hineingezogen.

Alle sechs Controls erhalten `iconFormat=svg`. Die fünf beschrifteten Aktionen behalten Labels, Defaultvariante und Größe md. Nur `clear` ergänzt das vorhandene übersetzte `CLEAR` und `iconOnly=true` ausdrücklich: „Leeren“/„Clear“, unverändertes `rel=keyword`. SVGs bleiben dekorativ, ohne Tabstopp. Größen (36 px bzw. mindestens 44 px auf Mobile), Iconboxen, Abstand, Hover, Fokus, Disabled und Loading kommen unverändert aus B2/B3. Keine CSS-/JavaScriptänderung.

## Gemeinsame und abweichende Erzeugungswege

| Fundstellen | Abhängigkeit / B5-Entscheidung |
| --- | --- |
| `DEEC_Controller_Action::{getToolbarClass,getEditToolbar,buildListView}` | Modulklasse, Options-/Quellenauflösung und Requestkontext; Controller können eigene Klassen konfigurieren und Export ergänzen. Unverändert. |
| `application/views/helpers/Toolbar.php` | Indexbereiche, Edit/View-Recordtargets, Select/Filter. Vorhandener gemeinsamer Verbraucher der aktivierten Metadaten. |
| `application/views/helpers/ToolbarBottom.php`, `application/views/scripts/list/list.phtml` | Add/Edit/Copy/Delete im Index, Apply im Select, derselbe Bulk-Kontext. B5 aktiviert nur Add/Edit/Copy. |
| `Contacts_ContactController::buildIndexView`, `Items_ItemController::buildIndexView` | Repräsentative reale Aufrufer der gemeinsamen Listenvorbereitung. Geschäfts-/Optionsdaten und Controllercode bleiben unverändert. |
| `DEEC_List::renderActionsCell`, modulare `ToolbarInline`-Formulare | Separate Row-ID-/Modul-/Controllerattribute; außerhalb B5. Keine flächendeckende Tabellenmigration. |
| `application/forms/Toolbar.php`, `application/views/scripts/index/index.phtml` | Dashboardformular wird auch für Dokumentzeilen mit PDF benutzt. Bewusst Legacy. |
| `ToolbarPositions`, modulare Positionsformulare/`ToolbarPositionSets`, `DEEC_Form`-Multiaktionen | Parent/Set/Location-/Positionsauswahl sowie abweichende alte Namen `addset/copyset/sortup`; eigener Migrationsblock nötig. |
| `contacts/views/helpers/HistoryToolbar.php` | Direktes HTML und zustandsabhängige Dokument-/Processaktionen; bleibt Legacy. |
| `Button(...)`, direkte Partials | Alte Positionsparameter/Klassen/Inlinehandler; seit B2 gemeinsame Präsentation im Legacy-Modus, kein automatisches SVG-Opt-in. |
| `js/global.js`, Modul-JS, `Application_Plugin_ListResponse` | Delegierte Aktionen, Keyword-/Filtercookies, Suche und AJAX. Haupttoolbar bleibt bestehen, aktive Filter und Listenfooter werden aktualisiert. Keine Event-/Factoryänderung. |
| `layout.phtml`, `plain.phtml` | Vorhandener Symbolblock einmal pro Dokument, bisher Inquiry, zusätzlich nur die beiden Controller/Aktionsgruppen. Eigene iframe-Dokumente erhalten eigene Symbole; `site.phtml` bleibt unberührt. |

Ein Standalone-Aufruf von `Items_Form_Toolbar` funktioniert weiterhin ohne geladenen MVC-Frontcontroller und bleibt dann Legacy. Die neue Kontextprüfung darf diesen bisherigen Aufrufvertrag nicht brechen; dies wurde durch die isolierte Notification-Suite aufgedeckt und korrigiert.

## Ausnahmen und Verhaltensvertrag

- Andere Kontakte-Controller einschließlich `email`/`contactperson`, Karte sowie `import`/`export` bleiben unverändert. Innerhalb der migrierten Klassen bleiben `delete`, `apply`, `tags`, Export und sämtliche Formularfelder Legacy.
- `Items_Form_ToolbarStock`, `ToolbarMovements`, `ToolbarItemlist`, `ToolbarStockplanning` bleiben auch bei künstlichem Aufruf im Artikelkontext unverändert. Exakte Klassenprüfung verhindert unbeabsichtigte Vererbung. Lager-/Routen-/Shopsonderfälle werden nicht migriert.
- Admin/CMS, Shops, eBay, Sales/Purchases, Processes/Zahlung, Campaigns/Mail, Statistics und Tasks sowie Dokument-/Mail-/Drittanbietercontrols bleiben außerhalb B5. Keine gespeicherten Inhalte verändert.
- Rechte, Tenant-/Sharingprüfungen und Entitlements bleiben an ihren vorhandenen Stellen. Metadaten erteilen keine Rechte; `hasModule()` wird nicht als SaaS-Buchungsrecht umgedeutet. Keine eigenen Datenabfragen oder Featureentscheidungen ergänzt.
- Namen, native Typen, vorhandene Funktionsklassen, `data-action`, Recordtargets, Defaults, Cookiepfade, URLs, Formattribute und Dialoge bleiben erhalten. Die bestehende B2-Capture-Sperre gilt auch für die neuen SVG-Kinder. Kein Auto-Save-/Submitumbau.

## Tatsächlich ausgeführte Prüfungen

Vor B5 bestehen 165 B2-, 645 zusätzliche B3-, 697 B4-PHP- und 796 B4-Browserchecks. Zusätzlich wurden sechs Toolbar-Klassen in **480 synthetischen Konstruktor-Kontexten** vor/nach B5 verglichen: genau zehn Kombinationen ändern ausschließlich die sechs erlaubten Controls; 470 Konfigurationen sind unverändert. Das sind keine 480 authentifizierten HTTP-Workflows.

| Prüfung | Ergebnis |
| --- | --- |
| `php tests/ui/toolbar-buttons.php` | PASS: 2.259 neue Checks plus eingebundene 165 B2-Checks. Beide Profile, DE/EN, Kopf-/Fuß-/Index-/Select-/Edit-/View-Kontexte, Layoutblock, SVGs, Typen/Attribute, Labels, Readonly, fehlende Rechte, echtes ListResponse-JSON für Index und Auswahl, ausgeschlossene Kontexte/Unterklassen/Dashboard-/Inlineformulare. |
| `python3 tests/ui/toolbar-buttons.py` | PASS: 928 Browserassertions. 48 Kombinationen aus zwei Modulen, DE/EN, Index/Hauptlayout, Select/Popup, Edit/Hauptlayout, View/Hauptlayout und 390/768/1280 px; zusätzliche Readonly-Seiten. Geometrie/currentColor, AX-Namen, kein Seitenüberlauf, Größen/Zentrierung/Abstand, Hover, Tab/Shift-Tab/Enter/Space, Disabled/Loading und Kindklicksperre, Bulk-Selektion, Add-/Edit-/Copyziele, Requestsperre, Restaurierung derselben Iconknoten, Filter/Reset/Keyword/Clear und dynamischer Footer im richtigen Kontext. |
| B2/B3: `php tests/ui/button-icons.php`, `python3 tests/ui/buttons.py`, `python3 tests/ui/buttons.py --svg` | PASS: weiterhin 165 + 645 PHP-, 169 PNG-/194 SVG-Browserchecks einschließlich Forced Colors, strenger CSP und iframe. |
| B4: `php tests/ui/inquiry-buttons.php`, `python3 tests/ui/inquiry-buttons.py` | PASS: 697 PHP-/796 Browserchecks. Der negative Layoutfall verwendet jetzt den weiterhin ausgeschlossenen Controller `contactperson`; `contact` wird durch B5 bewusst aufgenommen und positiv im neuen Test geprüft. Keine Inquiry-Assertion entfernt. |
| `php tests/security/permissions.php`, `tenant-access.php` | PASS: 415 / 269 Fälle; erwartete `NO_PERMIT`-Diagnosen bei negativen Fällen. |
| `php tests/security/contact-inquiries.php`, `form-input-validation.php`, `inquiry-dynamic-form.php` | PASS: 46 / 20 / 31 Fälle. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 bestehende Dialog-/Mail-/Dokument-/Auto-Save-Browserchecks bei 1280/390 px. |
| `php tests/lists/summary.php` | PASS: bestehende zentrale Listenfooter-/Summenprüfung ohne Datenbank. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger B2–B4-Fehler: zuerst 370 Checks erfolgreich, danach `Sales_Service_DocumentRevisionService` nicht gefunden in `sales/views/helpers/Finish.php:12`; Sales-Fixtureabbruch in `tests/security/notifications.php:211`. Der durch B5 zwischenzeitlich verursachte fehlende MVC-Klassenbezug ist behoben; verbleibend ist ausschließlich der bekannte Sales-Fehler. |
| Syntax / Diff | PHP-Lint für alle sechs geänderten/neuen PHP/PHTML-Dateien, Python-AST-Prüfung ohne Bytecode und `git diff --check` bestanden. |

Die neue Fixture verwendet echte Toolbar-Helper, `DEEC_List`, Listen-/Footerpartial und interne Layouts mit synthetischen Kategorien, Tags, Datensätzen und Rollen. Das ist die Rendergrenze, keine vollständige Kontakte-/Artikelgeschäftsview mit produktiver Datenbeschaffung. Das tatsächliche ListResponse-Plugin liefert AJAX-Fragmente; der lokale Browserrunner fängt alle AJAX-Aufrufe und Navigation ab. Kopieren wird ausschließlich simuliert. Keine Datenbank, SMTP- oder Produktiv-HTTP-Verbindung.

Nicht ausgeführt: `tests/lists/footer-http.py`. Der bestehende Runner bootstrapped `production` mit `configs/database.ini`, liest einen echten Administrator und erzeugt eine authentifizierte Session. Dafür ist keine isolierte Testdatenbank nachgewiesen. Der B5-Footer wird stattdessen im neuen vollständig isolierten PHP-/Browserrunner und der bestehenden speicherbasierten Summenprüfung getestet. Keine Produktions-/Mandantendaten für eine reine Präsentationsmigration angesprochen.

## Sichtprüfung, Risiken und nächste Gruppe

Chromium-Screenshots unter `/tmp/dewawi-b5-<contacts|items>-<index|select|edit|view>-<390|1280>.png` aufgenommen; Kopf-/Fußtoolbar, SVGs, Labelabstände, Fokus und Popupumbruch gesichtet. Gemischte SVG-/PNG-Darstellung bleibt in diesem Übergang bewusst sichtbar, z. B. neben Legacy-Löschen/Übernehmen. Alte Controls erhalten keine globalen Größen-/Farb-Overrides.

Offen bleiben fachliche/visuelle Produktfreigabe, reale Mandanten-/Theme-/Entitlementabnahme, physische Touchgeräte und Safari/Firefox/Screenreader. Die aus B4 bekannten dichten mobilen Seiten-/Menülayouts und unvollständigen globalen EN-Menü-/Breadcrumb-Kataloge sind nicht Gegenstand dieser Buttonmigration. Keine vollständige Accessibility-/Browserkonformität behauptet.

Empfohlene nächste Gruppe nach Pilotfreigabe: nur Standard-Kopf-/Fußaktionen von `Tasks_Form_Toolbar`, mit eigener Prüfung von Status, Datumsfiltern, Auswahl und Berechtigungen. Inline-/Positionsaktionen, Prozesse mit Zahlungsstatus und Dokument-/Mailcontrols bleiben getrennte Aufgaben. Über weitere Kontakt-/Artikelkontexte ausdrücklich entscheiden; Guards nicht einfach entfernen.

Laufzeitdateien: `application/modules/{contacts,items}/forms/Toolbar.php`, `application/layouts/scripts/{layout,plain}.phtml`. Tests: `tests/ui/toolbar-buttons.php`, `toolbar-buttons.py`, ein aktualisierter negativer Kontext in `inquiry-buttons.php`. Dokumentation: dieser Bericht und Fortsetzungslink in B4. Analysebericht weiterhin unversioniert, SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`.

Keine Datenbank-/Migrations-/gespeicherten HTML-/JSON-Änderungen oder manuelle Datenbanknacharbeit. Kein Push, Merge oder Deployment. Nur eigene explizite B5-Dateien gehören in den lokalen Commit.

## Vollständiges statisches Toolbar-Pfadinventar

Lokaler Stand: 42 PHP-Dateien mit `Toolbar` im Dateinamen unter `application/`: 34 Formular- und acht Helperdateien. Die vollständige Liste ergänzt die funktionalen Wege oben; Aufrufe in Controllern, Listen, Layouts und JS sind keine eigenständigen Formular-/Helperdateien. Reproduzierbar mit `Path('application').rglob('*Toolbar*.php')`; Basisklassen aus tatsächlichen Deklarationen. Umfassende direkte HTML-/JS-/CSS-Fundstellen stehen in der unveränderten Bestandsaufnahme.

| Datei | Basisklasse | B5-Status |
| --- | --- | --- |
| [application/forms/Toolbar.php](../../application/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/admin/forms/Toolbar.php](../../application/modules/admin/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/admin/forms/ToolbarInline.php](../../application/modules/admin/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/campaigns/forms/Toolbar.php](../../application/modules/campaigns/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/campaigns/forms/ToolbarInline.php](../../application/modules/campaigns/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/campaigns/forms/ToolbarPositions.php](../../application/modules/campaigns/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/contacts/forms/InquiryToolbar.php](../../application/modules/contacts/forms/InquiryToolbar.php) | `DEEC_Form` | B4-SVG unverändert |
| [application/modules/contacts/forms/InquiryToolbarInline.php](../../application/modules/contacts/forms/InquiryToolbarInline.php) | `DEEC_Form` | B4-SVG unverändert |
| [application/modules/contacts/forms/Toolbar.php](../../application/modules/contacts/forms/Toolbar.php) | `DEEC_Form` | Sechs Standardaktionen; nur explizite Kontexte |
| [application/modules/contacts/forms/ToolbarInline.php](../../application/modules/contacts/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/contacts/views/helpers/HistoryToolbar.php](../../application/modules/contacts/views/helpers/HistoryToolbar.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
| [application/modules/ebay/forms/Toolbar.php](../../application/modules/ebay/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/ebay/forms/ToolbarInline.php](../../application/modules/ebay/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/items/forms/Toolbar.php](../../application/modules/items/forms/Toolbar.php) | `DEEC_Form` | Sechs Standardaktionen; nur explizite Kontexte |
| [application/modules/items/forms/ToolbarInline.php](../../application/modules/items/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/items/forms/ToolbarItemlist.php](../../application/modules/items/forms/ToolbarItemlist.php) | `Items_Form_Toolbar` | Legacy / außerhalb B5 |
| [application/modules/items/forms/ToolbarMovements.php](../../application/modules/items/forms/ToolbarMovements.php) | `Items_Form_Toolbar` | Legacy / außerhalb B5 |
| [application/modules/items/forms/ToolbarPositions.php](../../application/modules/items/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/items/forms/ToolbarStock.php](../../application/modules/items/forms/ToolbarStock.php) | `Items_Form_Toolbar` | Legacy / außerhalb B5 |
| [application/modules/items/forms/ToolbarStockplanning.php](../../application/modules/items/forms/ToolbarStockplanning.php) | `Items_Form_Toolbar` | Legacy / außerhalb B5 |
| [application/modules/items/views/helpers/ToolbarPositionSets.php](../../application/modules/items/views/helpers/ToolbarPositionSets.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
| [application/modules/processes/forms/Toolbar.php](../../application/modules/processes/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/processes/forms/ToolbarInline.php](../../application/modules/processes/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/processes/forms/ToolbarPositions.php](../../application/modules/processes/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/processes/views/helpers/ToolbarPositionSets.php](../../application/modules/processes/views/helpers/ToolbarPositionSets.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
| [application/modules/purchases/forms/Toolbar.php](../../application/modules/purchases/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/purchases/forms/ToolbarInline.php](../../application/modules/purchases/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/purchases/forms/ToolbarPositions.php](../../application/modules/purchases/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/purchases/views/helpers/ToolbarPositionSets.php](../../application/modules/purchases/views/helpers/ToolbarPositionSets.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
| [application/modules/sales/forms/QuoteToolbar.php](../../application/modules/sales/forms/QuoteToolbar.php) | `Sales_Form_Toolbar` | Legacy / außerhalb B5 |
| [application/modules/sales/forms/Toolbar.php](../../application/modules/sales/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/sales/forms/ToolbarInline.php](../../application/modules/sales/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/sales/forms/ToolbarPositions.php](../../application/modules/sales/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/sales/views/helpers/ToolbarPositionSets.php](../../application/modules/sales/views/helpers/ToolbarPositionSets.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
| [application/modules/shops/forms/Toolbar.php](../../application/modules/shops/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/statistics/forms/Toolbar.php](../../application/modules/statistics/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/tasks/forms/Toolbar.php](../../application/modules/tasks/forms/Toolbar.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/tasks/forms/ToolbarInline.php](../../application/modules/tasks/forms/ToolbarInline.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/modules/tasks/forms/ToolbarPositions.php](../../application/modules/tasks/forms/ToolbarPositions.php) | `DEEC_Form` | Legacy / außerhalb B5 |
| [application/views/helpers/Toolbar.php](../../application/views/helpers/Toolbar.php) | `Zend_View_Helper_Abstract` | Vorhandener gemeinsamer Verbraucher; Code unverändert |
| [application/views/helpers/ToolbarBottom.php](../../application/views/helpers/ToolbarBottom.php) | `Zend_View_Helper_Abstract` | Vorhandener gemeinsamer Verbraucher; Code unverändert |
| [application/views/helpers/ToolbarPositions.php](../../application/views/helpers/ToolbarPositions.php) | `Zend_View_Helper_Abstract` | Legacy / außerhalb B5 |
