# B11 – Aktuelle Gesamtinventur des Button-Systems

Stand: 19.11.2026, lokale Basis `f337ba70` einschließlich B2–B10 (`96549509`). Grundlage: `AGENTS.md` und [B1](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5](toolbar-buttons.md), [B6](tasks-toolbar.md), [B7](remaining-toolbars.md), [B8](button-quality-audit.md), [B9](apply-buttons.md), [B10](list-buttons.md). B11 ergänzt keine Button-/Iconmigration. Seine einzige Laufzeitänderung ist die [mobile Tabellenkorrektur](mobile-table-actions.md).

## Zählmethoden und überprüfbare Ergebnisse

Reproduzierbare, ausschließlich lokale Werkzeuge:

```sh
python3 tests/ui/button-inventory.py
python3 tests/ui/button-inventory.py --json
php tests/ui/button-inventory.php
```

Der Pythonlauf verwendet `rg --files` in `application`, `library/DEEC`, `js`, `css`, `templates`, `install`; Dateitypen PHP/PHTML/JS/CSS/HTML/SQL/JSON. Tests, Sprachen, Kundendateien, übrige Vendorbibliotheken, Blueprint-CSS und das übernommene `js/simpletreemenu.js` sind aus der eigenen Quellzählung ausgeschlossen. Ignorierte/unversionierte Kundenassets, aktive Themes und gespeicherte Inhalte werden dadurch nicht vollständig erfasst. Keine Datenbank oder produktive Session gelesen.

Literale `->addElement([…])` werden mit tokenisierten Strings/Kommentaren und ausgeglichenen Arrayklammern gelesen; nur der **oberste** `type` bestimmt die Gruppe. Dynamische Namen bleiben als solche markiert. Andere Argumentformen sind separat aufgeführt. HTML-/Linkzahlen sind ausdrücklich **lexikalisch**: Kommentare, Factorystrings und Umschreibungsregex können mitzählen, zusammengesetzte/dynamische Attribute können fehlen. `--json` liefert jeden Treffer mit Datei/Zeile; keine behauptete exakte Anzahl aktiver DOM-Buttons.

Der PHP-Lauf verwendet vorhandene Memoryfixtures, reale Klassen und dokumentierte Controller-/Aktionskontexte. Er zählt eindeutige `Klasse::Feld`-Konfigurationen mit explizitem `iconFormat=svg`, zusätzlich ihre Controllerzuordnung. Aktivierte Metadaten sind nicht automatisch sichtbar: Helper, `show`-Callbacks, ACL, Formularansicht und Daten entscheiden weiter über die Ausgabe. Die ausgegebenen `contexts` sind Konstruktorkontexte, keine vollständig inventarisierten HTTP-Routen.

| Grundgesamtheit | Aktueller Befund | Aussagegrenze |
| --- | --- | --- |
| Eigene Quelldateien im definierten Scan | **1.074** | Dateien, keine UI-Instanzen. B1 hatte einen anderen historischen Scan; Roh-HTML-Differenzen sind nicht ohne Methodenabgleich eine Migration. |
| Literale DEEC-Button-/Submit-Konfigurationsstellen | **243 in 49 Dateien:** 234 Button, 9 Submit | Eine Stelle hat dynamischen Namen: `items/forms/ToolbarMovements.php::addMovementAction`, aus der fünf Bewegungsaktionen erzeugt werden. Quelle ≠ logisches Control. |
| Andere `addElement`-Argumentformen | **7** Aufrufstellen | Enthalten u. a. konfigurationsgesteuerte Pageblock-/DynamicForm-Felder und ZF1-Aufrufe. Keine sieben zusätzlichen nachgewiesenen Buttons. |
| Raw-HTML-Buttonanfänge | **147 in 79 Dateien** | Lexikalisch, einschließlich JS/PHP-Factorystrings und Regex; generisches `<'.$tag` ist keine solche Literalstelle. |
| Links mit literal erkennbarer Buttonklasse | **79 in 33 Dateien** | Heuristik für `dw-btn`, `dw-button`, `btn`, `button`; dynamische CTA-/Linkklassen und andere anklickbare Links nicht als vollständig gezählt ausgeben. Nicht mit den 88 anders erhobenen B1-Linktreffern verrechnen. |
| Direkte literale Inputtypen button/submit/reset/image | **0** im Scan | ZF1 rendert weiterhin Inputbuttons; generisches Inputrendering/konfigurierte Typen sind hiervon nicht ausgeschlossen. |
| ZF1-Konstruktoren | **13 Submitstellen in 13 Dateien** | Elf intern/Legacy, zwei öffentlich: `shops/forms/{Contact,Checkout}.php`. Kein moderner DEEC-Renderer automatisch beteiligt. |
| Alter `->Button(...)`-Helper | **25 Aufrufe in 8 Dateien** | Adminsettings: countries/manufacturers/paymentmethods; eBay: account/listing/order; Shopsverwaltung: account/order. Wiederholte Rowausgabe nicht zusätzlich gezählt. |
| Toolbarformulardateien / Helperdateien | **34 / 8** | 13 Haupttoolbarfamilien; zusätzlich Inline-/Positions-/Spezialklassen. Drei reine Ableitungen enthalten keine eigene Buttonkonfiguration: ToolbarItemlist, ToolbarStockplanning, QuoteToolbar. Sie sind deshalb nicht ungenutzt. |
| Listenmodelle | **50**, davon **44** mit Actionszelle | Zehn teilweise durch B4/B9/B10 abgedeckt, 34 Actions-Modelle außerhalb dieser SVG-Zeilenmigration. Modell ≠ erreichbarer Endpunkt. Vollständige Namensliste im [B10-Inventar](list-buttons.md). |
| Aktive SVG-Feldkonfigurationen | **45 in 12 Formularquellen** | Davon elf Toolbarformulardateien; die Inquiry-Form ist die zwölfte Quelle. 23 der 34 Toolbarformulardateien vollständig Legacy; die elf anderen nur teilweise migriert. |
| Controllerbezogene SVG-Konfigurationen | **84 in 13 primären Controllergruppen** | Mehrfachnutzung derselben Admin-/Statistikklasse erzeugt zusätzliche Zuordnungen, keine zusätzlichen Quellfelder. |
| Sichtbarer Referenz-DOM | **109 moderne Controls** in neun Index-Fixtureseiten | Je DE, ein Renderdurchlauf, zwei synthetische Rows, Kopf/Fuß wiederholt: Kontakte 15, Artikel 13, Tasks 15, sechs Adminlisten je 11. Kein Gesamtproduktionswert und nicht mit 45/84 addierbar. |

Keine Prozentangabe aus 45/243, 84/147 oder sichtbaren Fixtures: Diese Zahlen haben unterschiedliche Einheiten, Überlappungen und Kontextgrenzen. Selbst innerhalb einer bereits migrierten Datei bleiben Legacyfelder/Unterklassen und ausgeschlossene Controller bestehen. Statische fehlende Labels werden nicht als aktuelle Anzahl unbenannter Buttons ausgegeben: B9/B10 ergänzen übersetzte Labels bewusst erst durch kontextbezogene Metadaten.

## Tatsächlich vorhandene Erzeugungswege

| Weg und aktuelle Fundstellen | Verwendung / verbleibender Vertrag |
| --- | --- |
| `library/DEEC/Form.php::{addElement,renderElementHtml,renderButtonHtml,renderButtonIcon}` | Hauptweg; Rechte/Readonly/Werte/Attribute vor Präsentation. Opt-in über icon/iconFormat/variant/size/iconOnly, Legacyausgabe weiter vorhanden. Die einzige moderne Buttonroutine bleibt maßgeblich; keine neue B11-Schicht. |
| `application/views/helpers/Button.php::{Button,renderButton}` | Alter Positionsparametervertrag delegiert seit B2 mit Legacyoptionen an dieselbe Routine. Die moderne direkte Schnittstelle ist verfügbar; keine zusätzliche externe Produktionsmigration gefunden. Kein Grund, die Kompatibilitätsschnittstelle als toten Code zu löschen. |
| Haupt-/Fußtoolbars: gemeinsame `Toolbar.php`, `ToolbarBottom.php`, Modulformulare | Wiederholung derselben Felder, unterschiedliche Index/Select/Edit/View-/Filter-/Bulkverträge. Gemeinsame Darstellung erteilt keine ACL-/Tenant-/SaaS-Rechte. |
| `DEEC_Controller_Action::{buildListView,getToolbarClass,getToolbarInlineClass}`, `DEEC_List::{renderActionsCell,renderLinkCell,renderPinCell}` | Actionszelle rendert über Inlineform, ergänzt danach Rowtargets; native Links und Pin eigener Weg. `DEEC_Display` formatiert Werte und ist kein Buttonrenderer. |
| Positions-/Setwege | Sechs `ToolbarPositions`-Formulare; gemeinsamer Helper, vier modulare `ToolbarPositionSets`-Helper, Positions-/Optionspartials. Parent/Set/Status/Sortier-/Auswahlverträge, keine B11-Migration. Nicht aus alten Inventartexten automatisch schließen, alle alten Namen seien noch aktuell: z. B. Tasks verwendet heute `add-position`, `copy-selected-position`, `add-position-set` passend zum Helper. |
| `DEEC_Form` Multi-/MultiEntity, `MultiForm.php`, `Media.php` | Wiederholte/dynamische Add/Copy/Delete/Email-/Auswahl-/Sortiercontrols; Media verwendet Admin-Inline mit Spezialattributen. Eigene bestehende Context-/Handlerverträge erhalten. |
| Direkte Partials/Views | `HistoryToolbar.php`, `partials/{history,downloads,email-messages}.phtml`, Upload-/Dokument-/Kalender-/Export-/Settingsviews. Native Buttons, Links, Submit, `dw-button` und Bootstrapklassen bleiben unterschiedliche Legacywege. |
| `js/global.js`, `contacts.js`, `settings.js`, `filemanager.js`, Kalender-/Shop-JS | Delegierte Aktionen und wenige direkte Fabriken: Filemanagerauswahl, Alert-/Modalclose, Filterchips, Kontakte-/Mail-/Teilnehmeraktionen. Search/History/Positionen übernehmen servergenerierte Fragmente; keine allgemeine SVG-Factory, keine automatische nachträgliche Migration. |
| ZF1-Formulare | 13 native Submit-Konstruktoren; DEEC darf deren Formulartyp, Submittername/value, Validierung oder alte Eventanbindung nicht blind ersetzen. |
| Öffentliches CMS/Shop | `DEEC_Site_Block`, Site-Layout, DynamicForm, Cart/Checkout/Contact/Inquiry, CTA-/Hero-/Carousel-/Warenkorb-/Formcontrols; Inhalte/Themes teils konfiguriert oder gespeichert. Der Ordner `shops` enthält zugleich alte interne Account-/Orderverwaltung. Domäne je Aufrufer bestimmen. |
| Installer | Eigene HTML-/CSS-/Buttonlinks und Blueprint-Abhängigkeit; getrennte Oberfläche, vollständig außerhalb der internen SVG-Migration. |
| Drittanbieter | Responsive FileManager/MediaManager, TinyMCE/CKEditor, elFinder, jQuery UI/Datepicker und Leaflet verwenden eigene Controls/Assets/Fonts. Anwendungseigene Öffner sind separate mögliche Migrationsobjekte, Widgetinnere kein interner DEEC-Buttonbestand. |

### Bereits migrierte Felder – vollständige Liste

| Quelle unter `application/modules/` | SVG-Felder | Kontextgrenze |
| --- | --- | --- |
| `contacts/forms/Inquiry.php` | save (Submit) | Eigener Inquiry-Statusworkflow, B4. |
| `contacts/forms/InquiryToolbar.php` | filter, reset, clear | Eigener Inquiry-Pilot. |
| `contacts/forms/InquiryToolbarInline.php` | view, edit | Inquiry-Rowaktionen. |
| `contacts/forms/Toolbar.php` | add, edit, copy, filter, reset, clear; apply | Exakte Klasse/`contacts/contact`, fünf Standardaktionen; Apply nur Select/Search-Select. |
| `contacts/forms/ToolbarInline.php` | view, edit, copy; apply | Exakte Klasse/Controller, Index/Search-Index; Apply getrennt Select/Search-Select. |
| `items/forms/Toolbar.php` | add, edit, copy, filter, reset, clear; apply | Exakte Klasse/`items/item`, dieselben Grenzen. |
| `items/forms/ToolbarInline.php` | edit, copy; apply | Dieselben Grenzen; **kein View-Feld vorhanden**, keines erfunden. |
| `tasks/forms/Toolbar.php` | add, edit, copy, filter, reset, clear | Exakte Klasse/`tasks/task`, fünf Standardaktionen. |
| `tasks/forms/ToolbarInline.php` | view, edit, copy | Exakte Klasse/Controller, Index/Search-Index. |
| `admin/forms/Toolbar.php` | add, edit, copy, filter, reset, clear | Nur country/uom/deliverytime/manufacturer/warehouse/shippingmethod und fünf Standardaktionen. |
| `admin/forms/ToolbarInline.php` | copy | Dieselben sechs Controller, Index/Search-Index. Save/View/Edit/Delete/Sort nicht migriert. |
| `statistics/forms/Toolbar.php` | filter, reset | Nur customer/quote/turnover, Index/Search. Export und reporting unberührt. |

Die fünf Standardaktionen sind Index/Search/Select/Edit/View. Die Layouts geben nur für die bestehenden begrenzten internen Kontexte einmal lokale Symbole aus; iframes besitzen ihren eigenen Block, AJAXfragmente keinen. Keine neue Aktivierung in B11.

### Verbleibende Toolbarfamilien und Sondergruppen

Sechs von 13 Hauptfamilien sind teilweise migriert: Admin, Inquiry, Contacts, Items, Statistics, Tasks. Die anderen sieben – Application/Dashboard, Campaigns, eBay, Processes, Purchases, Sales, Shops – bleiben vollständig Legacy. Auch die sechs teilweise migrierten Familien haben Ausnahmen:

- Kontakte: Email/Download/Downloadset/Karte/Import/Export, History, MultiEntity-Zuordnungen und fachliche Inquiry-Aktionen außerhalb des Piloten.
- Artikel: Inventory/Stock/Ledger/Stockplanning/Movements/Pricerules, Attribute/Option/Itemlist, vier Spezialunterklassen; teilweise abweichender Copy-Roh-ID-Vertrag statt JSON. Keine Aktivierung allein wegen desselben Iconnamens.
- Tasks: Positions-/Gruppen-/Pin-/Status-/Zuweisungs-/Follow-upaktionen. Fehlende reguläre Tasks-Modulrechte in `DEEC_Permission` bleiben dokumentierte Legacy-/Freigabegrenze, keine durch Darstellung zu lösende Rechteentscheidung.
- Admin: weitere 17 Actions-Listenmodelle, insbesondere Rechte/Mandanten/Finanzen/CMS/Shop/Export/Medien. Adminzugriff kommt aus Controller/ACL, isoliertes Formrendering ist kein Berechtigungsnachweis.
- Alle: Delete, Mail/PDF/Dokumentabschluss/-generierung, Zahlung, komplexe Massenaktionen, Upload/Senden, Sortierung, Tabellenpin, normale Formularsubmits und dynamische Factories sind keine implizit freigegebenen Folgemigrationen.

Zehn Inlineformularquellen: Admin/Contacts/Items/Tasks/Inquiry teilweise SVG; Campaigns/eBay/Processes/Purchases/Sales vollständig Legacy. Die sechs Positionsformulare bleiben Legacy. Vollständige konkrete Listenmodellnamen und Löschrisiken im [B10-Bericht](list-buttons.md); diese 44er-Grundgesamtheit ist unverändert.

## Icons, Styles und belegbare Altlasten

| Bestand / Befund | Aktueller Nachweis und Einordnung |
| --- | --- |
| 23 PNG-Aktionszuordnungen | `.dw-btn.<action>` plus innere `.dw-btn__icon--<action>` in `css/ui.css`; alle 23 Dateien vorhanden, je 16×16 px, zusammen 15.712 Bytes. Weitere UI-PNG-URLs wie `list.png` sind keine zusätzliche Aktionszuordnung. Kein flächiges Entfernen. |
| Lokale SVG-Allowlist | 15 semantische Keys, 14 Geometrien, 2.836-Byte-Sprite. Lucide 0.468.0 mit gepinnter Herkunft/ISC-/Feather-MIT-Nachweisen gemäß [B3](button-icons.md) und `images/icons/README.md`. Keine CDN-/Font-/CSP-Änderung. |
| Aktuell benutzte SVG-Keys | add, apply, clear, copy, edit, filter, reset, save, view: neun Keys in den 45 Feldkonfigurationen. close-Geometrie wird bereits durch clear genutzt. |
| SVG-Reserve | delete/download/search/send/upload als fünf noch nicht durch diese Produktionskonfigurationen genutzte Geometrien; close als eigener semantischer Key ebenfalls ohne eigenen Feldverbraucher. Absichtlich kuratierte Schnittstellenreserve, **keine zu löschende Altlast**. |
| PNG-Duplikat | `images/positions.png` und `select.png` SHA-256-identisch; bestätigt, aber weiter verschiedene semantische Namen/Verbraucher. Kein automatischer Lösch-/Umbenennungsauftrag. |
| Alte Root-Sprites/PNG-Zuordnungen | `toolbar.png`, `icons-small.png`, `icons-big.png` sowie die B1-Kandidaten settings/close/details/positions/back/get/daterange bleiben ohne bestätigten eigenen Literalverbraucher. Gespeicherte Inhalte, Themes und dynamische Klassen verhindern einen globalen Löschbeweis. Nicht als sieben bewiesen ungenutzte Laufzeitcontrols zählen. |
| Nachweislich inaktiver Repository-Pfad | `MainMenu.php::renderMobileDrawer()` hat nur einen auskommentierten Aufruf (`MainMenu.php:13`) und die geschützte Definition; eigene Quellen enthalten keinen aktiven Aufruf. Zwei alte Drawer-Labels sind damit kein aktueller Buttonflow. Nicht durch B11 entfernt. |
| Verwaister Helperkandidat | `application/views/helpers/Ordering.php` erzeugt Pfeil-Spans; kein eigener `->Ordering()`/`->ordering()`-Aufruf gefunden. Dynamische Helperauflösung/externe Templates separat ausschließen, bevor die Datei gelöscht werden dürfte. |
| Verwaister Spriteverweis im Repository | `.language-flag` verweist in `css/shop.css` auf fehlendes `../flags.png`; kein eigener Markupverbraucher dieser Klasse gefunden. Die 254 Einzeldateien unter `images/flags/` ersetzen den Sprite nicht. Belegter unversorgter CSS-Verweis, kein Nachweis eines aktuell sichtbaren Ausfalls. |
| Fünf Shop-Fontplatzhalter | checkout success/error, contact success/error, inquiry success: `icon-check fas fa-check-circle`; keine eigene geladene Iconfontversorgung im Standard-Site-Layout nachgewiesen. Bestehende öffentliche Baustelle, keine interne SVG-Migration. |
| Legacy-Modifikatoren | `hidden-sm`, `dw-button` und `dw-btn--secondary` haben keine entsprechende eigene durchgängige Präsentation. Moderne Danger/Primary/Ghost/Größenregeln sind auf `dw-btn--control` begrenzt und machen Legacyverbraucher nicht automatisch modern. `nolabel` wird normalisiert; nicht als wirksame CSS-Regel oder alleinige A11y-Lösung behandeln. |

Keine Datei wurde als Inventur-Nebenwirkung gelöscht. Statische Nichtreferenz ist schwächer als bestätigte Unerreichbarkeit; byteidentische Assets sind kein Nachweis ihrer Entbehrlichkeit. Die internen SVG-Controls haben geprüfte übersetzte Namen, daraus folgt keine vollständige DE/EN- oder Screenreaderfreigabe aller verbleibenden Legacywege.

## Prioritäten für B12 und spätere Freigabe

| Priorität | Konkreter nächster Block / Nutzen | Risiko und Abnahmekriterium |
| --- | --- | --- |
| B12 empfohlen, klar begrenzte Qualitätskorrektur | Den bei 320 px reproduzierten **globalen Headerüberlauf** und technische DE/EN-Spalten-/Menü-/Reportkeys separat lokalisieren und korrigieren. Mobile Tabellen-/Benutzerkopf- und Übersetzungsabnahme vor zusätzlicher Migration. | Mittel, gemeinsame Layout-/Katalogwirkung. Vorher/Nachher DE/EN × 320/390/768/1280, mehrere Module, lange Benutzer-/Mandantennamen und Plainlayouts; keine stillschweigende Toolbar-/Workflowmigration. Filter-Disclosure (`aria-expanded`/`aria-controls`, B8-Q4) als eigenständig prüfbarer Accessibility-Unterblock. |
| Danach Standard-Delete | Gemeinsame zentrale/Inline-Deletewege der bereits migrierten Kontakt-/Artikel-/Taskkontexte: großer Wiederverwendungsnutzen, vorhandenes SVG vorhanden. | Erhöht: Confirm/Cancel, exakte IDs, Rechte/Tenants, Kaskaden/eBay, Teilergebnis, Loading/Retry/Fokus nachweisen. Admin-`media-delete`/zusätzlich injiziertes `data-action=delete` zuerst klären; keine Medien-/Dokumentlöschung mitnehmen. |
| Danach wiederkehrende Positions-/Set-/Pinaktionen | Dieselben bestehenden Renderer nutzen, tatsächliche Aufrufnamen/Unterklassen vorher neu prüfen. | Hoch: Parent/Set/Options-/Bestands-/Reihenfolgeverträge. Kein globales Opt-in in gemeinsamen Klassen, keine Ableitung allein aus Add/Copy/Delete-Text. |
| Gesondert Formulare/dynamische Controls | Wiederkehrende normale Submit-/Modal-/MultiForm-/Clear-/Closecontrols mit eindeutigem Vertrag; Test- und Namensverbesserung. | Native Submitterwerte, Auto-Save-Opt-out, Fokus-/Dialoggrenzen erhalten. Keine neue parallele JS-Renderingarchitektur; ZF1-/DEEC-Unterschiede beachten. |
| Später eigene fachliche Blöcke | Dokumente/PDF/Export, Mail/Senden, Zahlung, SaaS-/Rechte-/Mandantenverwaltung, Kampagnen, Shop/CMS und Widgetöffner. | Fachliche Abnahme, historische Werte, Rechte/Entitlements, gespeichertes HTML/Themes und Fremdwidgets. Nicht durch Entfernen vorhandener Guards freigeben. |
| Aufräumen separat | Inaktiver Drawerpfad und verwaiste Helper-/Assetkandidaten mit vollständigem Erreichbarkeits-/Theme-/Datenbestandnachweis. | Niedriger Nutzen gegenüber UI-Qualität; keine automatische Löschung aufgrund von `rg`-Nulltreffern. |

Freigabeplan: technische B11-/B2–B10-Prüfungen gemäß [mobilem Bericht](mobile-table-actions.md), danach manuelle reale DE/EN-Testinstallation mit repräsentativen langen Daten, Rollen/Mandanten/Sharing und gebuchten SaaS-Features; mobile Scroll-/Touch-/Fokus-/Tooltipprüfung; Firefox/Safari und NVDA/VoiceOver. Bestehende B8-Altbefunde und der unabhängige Notification-Fixturefehler bleiben offen. Keine produktive Session erzeugen, um eine fehlende Testumgebung zu ersetzen.

## Quellenanhang: 243 literale Konfigurationen

| Bereich | Stellen / Dateien | Konkrete Dateigruppen |
| --- | --- | --- |
| application/forms | 6 / 2 | Toolbar (5), Upload (1) |
| Admin | 14 / 2 | Toolbar (7), ToolbarInline (7) |
| Campaigns | 25 / 3 | Toolbar (7), ToolbarInline (4), ToolbarPositions (14) |
| Contacts | 24 / 7 | Inquiry (1), InquiryAssignment (2), InquiryContact (1), InquiryToolbar (3), InquiryToolbarInline (2), Toolbar (9), ToolbarInline (6) |
| eBay | 11 / 2 | Toolbar (7), ToolbarInline (4) |
| Items | 34 / 6 | controllers/StockController (1); forms/Toolbar (8), ToolbarInline (5), ToolbarMovements (2), ToolbarPositions (14), ToolbarStock (4) |
| Processes | 26 / 3 | Toolbar (7), ToolbarInline (5), ToolbarPositions (14) |
| Purchases | 29 / 5 | Purchaseorder (1), Quoterequest (1), Toolbar (8), ToolbarInline (5), ToolbarPositions (14) |
| Sales | 33 / 9 | Creditnote/Deliveryorder/Invoice/Quote/Reminder/Salesorder (je 1), Toolbar (8), ToolbarInline (5), ToolbarPositions (14) |
| Shops | 9 / 2 | DynamicForm (2), Toolbar (7) |
| Statistics | 3 / 1 | Toolbar (3) |
| Tasks | 25 / 3 | Toolbar (7), ToolbarInline (4), ToolbarPositions (14) |
| Users | 3 / 3 | Login, Password, User (je 1) |
| library/DEEC | 1 / 1 | Controller/Action (dynamisch ergänzter Exportbutton) |

Ohne Pfadpräfix genannte Moduldateien liegen unter `application/modules/<modul>/forms/`. Die vollständigen Zeilennachweise einschließlich Raw-HTML/JS/Links/ZF1/Helper stehen reproduzierbar im JSON-Output des Pythonwerkzeugs; die vollständigen 45 Feldnamen mit Aktivierungskontexten im PHP-Output. Die Auswertungen wurden tatsächlich ausgeführt und liegen zusätzlich unter `/tmp/dewawi-b11/{source,runtime}-inventory.json`. Keine Datenbank-/gespeicherten Inhaltsänderung, keine neue breite Migration und kein Push.
