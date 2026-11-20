# DEWAWI – Button-System-Inventur, aktualisiert mit B18


## Aktueller Stand B18 – 20.11.2026

Maßgeblich sind der lokale Stand `7ceb5e6b` vor B18 und die tatsächlich ausgeführten Scanner. [B18 – Standardbereiche](standard-area-buttons.md) migriert ausschließlich Vorgänge (`processes/process`), Preisregeln (`items/pricerule`) und Admin-Shops (`admin/shop`). Der [vollständige Quellenanhang](button-remaining-inventory.md) erfasst alle aktuellen literalen Formstellen und übrigen gefundenen Erzeugungsstellen mit Datei-/Zeilenbelegen. Die anschließenden B1–B17-Abschnitte bleiben als **historische Vergleichsbasis** erhalten; ihre älteren Zahlen sind nicht der aktuelle Gesamtstand.

| Aktuelle Zähleinheit | Nachgewiesener Bestand / Grenze |
| --- | --- |
| Eigene Quelldateien im identischen Scan | 1.083; Tests/Vendorinnere/gespeicherte Inhalte wie bisher ausgeschlossen. |
| Literale Formkonfigurationen | 246 in 50 Dateien: 237 Button, 9 Submit; dynamischer Bewegungsaktionsname weiterhin gesondert. |
| Andere Erzeugungsstellen | 149 lexikalische HTML-Buttonanfänge in 81 Dateien; 80 Buttonlinks in 34; 23 alte Helperaufrufe in sieben; 13 ZF1-Submitkonstruktoren; sieben andere `addElement`-Argumentformen. Keine Laufzeitmengen. |
| Toolbar-/Listenquellen | 36 Toolbarformulare, acht Toolbarhelper, 51 Listenmodelle; 44 mit Actionszelle. 13 Actionsmodelle haben mindestens ein in den geprüften Kontexten aktiviertes SVG-Feld, 31 liegen außerhalb dieser Zuordnung. |
| SVG-Feldkonfigurationen | 66 eindeutige Klasse/Feld-Schlüssel, 128 Controller/Feld-Zuordnungen, 17 erfasste Controllergruppen. |
| SVG-produzierende Quellen | 17 PHP/PHTML-Dateien: 15 Formulare (14 Toolbarquellen), ein Helper, eine View. 22 der 36 Toolbarquellen ohne eigene SVG-Konfiguration. |
| Referenz-DOM | 180 moderne Controls in zwölf Indexfixtures mit je zwei synthetischen Rows: bisherige neun 133, neu Process 17 / Pricerule 17 / Admin-Shop 13. Kein Produktionsgesamtbestand. |
| SVG-Filterhelper | Ein bestehender Renderweg, jetzt 28 logische Index-/Selectkontexte in 16 Gruppen; Shop-Order bleibt außerhalb dieser Filter-Allowlist. |
| Quellstellen mit/ohne erfassten SVG-Kontext | 66 / 180 der 246 Formstellen. Bedingte Felder bleiben in anderen Kontexten Legacy; kein Migrationsprozent und keine Behauptung von 180 tatsächlich sichtbaren Buttons. |

**Zählkorrektur gegenüber B17:** Die unabhängige ERP-Shopbestellintegration `17fa5f6a` bringt drei bereits vor B18 vorhandene SVG-Felder in `Shops_Form_OrderToolbar` (Filter/Reset/Clear). Der alte PHP-Inventarlauf erfasste diese nicht. Korrigierter Vorbestand daher 55 Felder / 99 Controller-Feld-Paare / 14 Gruppen statt 52/96/13. B18 selbst ergänzt elf Feld-Opt-ins und 29 Controller/Feld-Paare in drei Gruppen; Ergebnis 66/128/17. Keine Änderung an Shopbestellungen durch B18.

### Tatsächlich migrierte Standardbereiche

| Kontext | Haupttoolbar | Inline | Bewusst unverändert |
| --- | --- | --- | --- |
| `processes/process` | add/edit/copy/delete/filter/reset/clear | view/edit/copy/delete, Index/Search-Index | PDF, Pin, Status-/Liefer-/Zahlungssteuerung, Positionen/Sets, Dokumentaktionen. |
| `items/pricerule` | dieselben sieben Felder | edit/copy/delete, Index/Search-Index | Apply in sämtlichen Pricerulekontexten; Berechnung/Priorität/Kategorien/Gültigkeit. |
| `admin/shop` | dieselben sieben Felder | nur copy, Index/Search-Index | media-delete-Konflikt, Shop-/Slug-/Redirect-/Menü-/Seitenkonfiguration und Im-/Export. |

Exakte Klassen-/Requestprüfungen bleiben verpflichtend. Vorhandene Labels, ARIA-Namen, Klassen und native/Event-/Zielattribute bleiben gleich; die Filterbuttons erhalten ausdrücklich den bestehenden Disclosure-Vertrag. Die bisher technischen Inline-ARIA-Fallbacks werden in B18 absichtlich nicht nebenbei umbenannt. Select erhält keinen neuen Filter-/Resetbutton; dort werden der vorhandene Keyword-Clear und die aktiven Filterchips geprüft. Konstruktormetadaten in View sind kein Beleg einer funktionierenden vollständigen Viewseite.

### Verbleibender Bestand und Prioritäten nach B18

- Sieben klassische Haupttoolbarfamilien teilweise SVG: Admin, Inquiry, Contacts, Items, Tasks, Statistics, Processes. Application, Campaigns, eBay, Purchases, Sales und die alte Shops-Haupttoolbar bleiben Legacy; die separate ERP-Ordertoolbar ist bereits teilweise SVG. Alle sechs Positionsformulare und die fachlichen Familien behalten ihre eigenen Verträge.
- Wiederkehrende Formular-/Modal-/MultiForm-/Close-/Clearcontrols zuerst nach gemeinsamem Renderer, Submitter- und Auto-Save-Vertrag gruppieren. Weitere Module nur mit expliziten Contextgates und eigenem Funktionsnachweis.
- Index-Apply-/Parentvertrag, Admin-media-delete-Doppelattribute und Dashboard-Altcontrols ohne vollständige Targets sind getrennte Vertragskorrekturen. Keine reine Icon-Aktivierung, keine stillschweigende neue Fachfunktion.
- Dokumente/PDF, Mail, Export, Zahlung, Positions-/Set-/Pin-/Bestandsaktionen, öffentliche Shops/CMS, Installer und Drittanbieter bleiben eigene Abnahmegruppen. Die drei nativen Shopbestell-Status-/ERP-Submits sind keine B18-Standardtoolbar.
- Bestehende Layout-/A11y-Grenzen gesondert bearbeiten: Process-Tabelle bei 768 px, sehr hohe Shopzeilen mobil, deutscher Headerüberlauf bei 320 px, technische Inline-Namen. Vorher/Nachher zeigen keine B18-Verschlechterung; genaue Messwerte und Testgrenzen im B18-Bericht.
- Bekannte Assetkandidaten bleiben Kandidaten: vier SVG-Reservegeometrien, identische positions/select-PNGs, Root-Sprites, unversorgter Shop-Flag-Sprite und Fontplatzhalter. Keine Löschung ohne Nachweis für dynamische Themes und gespeicherte Inhalte.

Reproduktion: `python3 tests/ui/button-inventory.py --remaining --json` und `php tests/ui/button-inventory.php`. Vollständige aktuelle Form-/Literalquellen im verlinkten Quellenanhang. Authentifizierte Live-/Produktionsabnahme wird durch lokale Memoryfixtures nicht ersetzt. Kein Push, Deployment oder Datenbankeingriff.

---

Historische Gesamtprüfung: [B13 – Integration und Freigabematrix](button-release-audit.md) bestätigte die damaligen Bestandszahlen unverändert und ergänzt Firefox-/320-px-Prüfungen sowie konkrete offene Freigabegates. Keine zusätzliche Migration.

Korrektur B14: [SVG-Symbolversorgung vollständiger ERP-Seiten](svg-symbol-integration.md). Beide internen Vollseitenlayouts liefern den passiven Katalog jetzt immer einmal; die Button-Opt-ins und alle folgenden Controlzahlen bleiben unverändert. Neuer Originalcontroller-/HTTP-/Pixelnachweis und konkrete Legacy-HTML-Mengen im B14-Bericht.

B15: [Dashboard-Standardicons](dashboard-buttons.md) migriert drei direkte Edit-Aufrufstellen in einer View für Kontakte, Artikel und Lagerbewegungen über den bestehenden Button-Helper. Formular-Metadaten und bisherige Toolbar-/Listen-/Filter-Opt-ins bleiben unverändert. Aufgaben-/Dokumentbuttons einschließlich PDF bleiben wegen ihres abweichenden, unvollständigen Dashboard-Vertrags Legacy.

B17: [Standard-Löschen und Übernehmen](delete-apply-buttons.md) ergänzt sieben bestehende Feld-Opt-ins: vier reguläre Toolbar-Deletefelder und drei Inline-Deletefelder. Neun Controllergruppen, zwölf zusätzliche Controller/Feld-Paare; Index-Apply und Admin-media-delete ausdrücklich unverändert. B16 bleibt mit dem [Produktionsrechtebefund](svg-live-diagnosis.md) abgeschlossen.

Stand: 19.11.2026. B11-Basis `f337ba70` einschließlich B2–B10 (`96549509`), B11-Commit `83a51db1`. B12 ergänzt den [gemeinsamen Filterchip-/Disclosureblock](filter-buttons.md); der unabhängige Dokumentcommit `b32241c1` ist keine Buttonmigration. Grundlage: `AGENTS.md` und [B1](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5](toolbar-buttons.md), [B6](tasks-toolbar.md), [B7](remaining-toolbars.md), [B8](button-quality-audit.md), [B9](apply-buttons.md), [B10](list-buttons.md), [B11](mobile-table-actions.md).

## Historische Zählmethoden und Ergebnisse bis B17

Reproduzierbare, ausschließlich lokale Werkzeuge:

```sh
python3 tests/ui/button-inventory.py
python3 tests/ui/button-inventory.py --json
php tests/ui/button-inventory.php
```

Der Pythonlauf verwendet `rg --files` in `application`, `library/DEEC`, `js`, `css`, `templates`, `install`; Dateitypen PHP/PHTML/JS/CSS/HTML/SQL/JSON. Tests, Sprachen, Kundendateien, übrige Vendorbibliotheken, Blueprint-CSS und das übernommene `js/simpletreemenu.js` sind aus der eigenen Quellzählung ausgeschlossen. Ignorierte/unversionierte Kundenassets, aktive Themes und gespeicherte Inhalte werden dadurch nicht vollständig erfasst. Keine Datenbank oder produktive Session gelesen.

Literale `->addElement([…])` werden mit tokenisierten Strings/Kommentaren und ausgeglichenen Arrayklammern gelesen; nur der **oberste** `type` bestimmt die Gruppe. Dynamische Namen bleiben als solche markiert. Andere Argumentformen sind separat aufgeführt. HTML-/Linkzahlen sind ausdrücklich **lexikalisch**: Kommentare, Factorystrings und Umschreibungsregex können mitzählen, zusammengesetzte/dynamische Attribute können fehlen. `--json` liefert jeden Treffer mit Datei/Zeile; keine behauptete exakte Anzahl aktiver DOM-Buttons.

Der PHP-Lauf verwendet vorhandene Memoryfixtures, reale Klassen und dokumentierte Controller-/Aktionskontexte. Er zählt eindeutige `Klasse::Feld`-Konfigurationen mit explizitem `iconFormat=svg`, zusätzlich ihre Controllerzuordnung. Aktivierte Metadaten sind nicht automatisch sichtbar: Helper, `show`-Callbacks, ACL, Formularansicht und Daten entscheiden weiter über die Ausgabe. Die ausgegebenen `contexts` sind Konstruktorkontexte, keine vollständig inventarisierten HTTP-Routen.

Seit B12 weist Python SVG-Formular- und Helperquellen getrennt aus; seit B15 erfasst es auch PHTML unter `svg_view_sources`. PHP prüft zusätzlich tatsächlich gerenderte aktive Filterchips in Index/Select und liefert deren Helperkontexte unter `shared_svg_renderers`. Diese bedarfsabhängige Ausgabe und die neuen direkten Dashboard-Helperaufrufe sind **keine zusätzlichen Formularfeldkonfigurationen**. Die Dashboardverträge werden separat durch vollständiges MVC-Rendering in `dashboard-buttons.php` und dem HTTP-Runner geprüft.

| Grundgesamtheit | Aktueller Befund | Aussagegrenze |
| --- | --- | --- |
| Eigene Quelldateien im definierten Scan | **1.074** | Dateien, keine UI-Instanzen. B1 hatte einen anderen historischen Scan; Roh-HTML-Differenzen sind nicht ohne Methodenabgleich eine Migration. |
| Literale DEEC-Button-/Submit-Konfigurationsstellen | **243 in 49 Dateien:** 234 Button, 9 Submit | Eine Stelle hat dynamischen Namen: `items/forms/ToolbarMovements.php::addMovementAction`, aus der fünf Bewegungsaktionen erzeugt werden. Quelle ≠ logisches Control. |
| Andere `addElement`-Argumentformen | **7** Aufrufstellen | Enthalten u. a. konfigurationsgesteuerte Pageblock-/DynamicForm-Felder und ZF1-Aufrufe. Keine sieben zusätzlichen nachgewiesenen Buttons. |
| Raw-HTML-Buttonanfänge | **145 in 79 Dateien** | B15 ersetzt drei Literalstellen in der Dashboard-View durch Helperaufrufe. Lexikalisch, einschließlich JS/PHP-Factorystrings und Regex; generisches `<'.$tag` ist keine solche Literalstelle. |
| Links mit literal erkennbarer Buttonklasse | **79 in 33 Dateien** | Heuristik für `dw-btn`, `dw-button`, `btn`, `button`; dynamische CTA-/Linkklassen und andere anklickbare Links nicht als vollständig gezählt ausgeben. Nicht mit den 88 anders erhobenen B1-Linktreffern verrechnen. |
| Direkte literale Inputtypen button/submit/reset/image | **0** im Scan | ZF1 rendert weiterhin Inputbuttons; generisches Inputrendering/konfigurierte Typen sind hiervon nicht ausgeschlossen. |
| ZF1-Konstruktoren | **13 Submitstellen in 13 Dateien** | Elf intern/Legacy, zwei öffentlich: `shops/forms/{Contact,Checkout}.php`. Kein moderner DEEC-Renderer automatisch beteiligt. |
| Alter `->Button(...)`-Helper | **25 Aufrufe in 8 Dateien** | Adminsettings: countries/manufacturers/paymentmethods; eBay: account/listing/order; Shopsverwaltung: account/order. Wiederholte Rowausgabe nicht zusätzlich gezählt. |
| Toolbarformulardateien / Helperdateien | **34 / 8** | 13 Haupttoolbarfamilien; zusätzlich Inline-/Positions-/Spezialklassen. Drei reine Ableitungen enthalten keine eigene Buttonkonfiguration: ToolbarItemlist, ToolbarStockplanning, QuoteToolbar. Sie sind deshalb nicht ungenutzt. |
| Listenmodelle | **50**, davon **44** mit Actionszelle | Zehn teilweise durch B4/B9/B10 abgedeckt, 34 Actions-Modelle außerhalb dieser SVG-Zeilenmigration. Modell ≠ erreichbarer Endpunkt. Vollständige Namensliste im [B10-Inventar](list-buttons.md). |
| Aktive SVG-Feldkonfigurationen | **52 in 12 Formularquellen** | Davon elf Toolbarformulardateien; die Inquiry-Form ist die zwölfte Quelle. 23 der 34 Toolbarformulardateien vollständig Legacy; die elf anderen nur teilweise migriert. |
| B12: gemeinsamer SVG-Helperweg | **1 Erzeugungsstelle in 1 zusätzlichem Helper**, 22 logische Index-/Selectkontexte in den bestehenden 13 Controllergruppen | Ein Chip je aktivem Filter, kein fixer DOM-Zähler. Search bildet dieselben logischen Kontexte ab. |
| B15: direkter SVG-Viewweg | **3 Aufrufstellen in 1 PHTML-View**, 1 Dashboardkontext (`default/index/index`) mit 3 Zielgruppen | Insgesamt 14 SVG-produzierende PHP/PHTML-Dateien: zwölf Formulare, ein Helper, eine View. B15 änderte die damaligen 45 Feldkonfigurationen/84 Zuordnungen nicht; aktueller Stand nach B17: 52/96. |
| Controllerbezogene SVG-Konfigurationen | **96 in 13 primären Controllergruppen** | Mehrfachnutzung derselben Admin-/Statistikklasse erzeugt zusätzliche Zuordnungen, keine zusätzlichen Quellfelder. |
| Sichtbarer Referenz-DOM | **133 moderne Controls** in neun Index-Fixtureseiten | Je DE, ein Renderdurchlauf, zwei synthetische Rows, Kopf/Fuß wiederholt: Kontakte 19, Artikel 17, Tasks 19, sechs Adminlisten je 13. Kein Gesamtproduktionswert und nicht mit 52/96 addierbar. |

Keine Prozentangabe aus 52/243, 96/147 oder sichtbaren Fixtures: Diese Zahlen haben unterschiedliche Einheiten, Überlappungen und Kontextgrenzen. Selbst innerhalb einer bereits migrierten Datei bleiben Legacyfelder/Unterklassen und ausgeschlossene Controller bestehen. Statische fehlende Labels werden nicht als aktuelle Anzahl unbenannter Buttons ausgegeben: B9/B10 ergänzen übersetzte Labels bewusst erst durch kontextbezogene Metadaten.

B15-Referenz-DOM separat: Das reguläre Original-Dashboard mit 25 synthetischen Kontakten und einem Artikel hat weiterhin 28 Buttons, nun 26 moderne Edit-Controls statt null. Ein zusätzliches Testprofil mit allen acht Dashboardtabellen hat 44 Buttons: 27 moderne Edit-Controls, 16 unveränderte Aufgaben-/Dokumentbuttons und eine eigenständige SVG-Glocke. Diese laufzeit-/datensatzabhängigen Mengen nicht zu den Quellstellen, neun Indexfixtures oder Formularfeldern addieren.

## Tatsächlich vorhandene Erzeugungswege

| Weg und aktuelle Fundstellen | Verwendung / verbleibender Vertrag |
| --- | --- |
| `library/DEEC/Form.php::{addElement,renderElementHtml,renderButtonHtml,renderButtonIcon}` | Hauptweg; Rechte/Readonly/Werte/Attribute vor Präsentation. Opt-in über icon/iconFormat/variant/size/iconOnly, Legacyausgabe weiter vorhanden. Die einzige moderne Buttonroutine bleibt maßgeblich; keine neue B11-Schicht. |
| `application/views/helpers/Button.php::{Button,renderButton}` | Alter Positionsparametervertrag bleibt Legacy. B12 verwendet die moderne direkte Schnittstelle für die ausdrücklich freigegebenen Filterchips in `Toolbar::renderActiveFilters`; beide Wege delegieren an dieselbe DEEC-Routine. Kein Grund, die Kompatibilitätsschnittstelle als toten Code zu löschen. |
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

### Historische Feldliste bis B17

| Quelle unter `application/modules/` | SVG-Felder | Kontextgrenze |
| --- | --- | --- |
| `contacts/forms/Inquiry.php` | save (Submit) | Eigener Inquiry-Statusworkflow, B4. |
| `contacts/forms/InquiryToolbar.php` | filter, reset, clear | Eigener Inquiry-Pilot. |
| `contacts/forms/InquiryToolbarInline.php` | view, edit | Inquiry-Rowaktionen. |
| `contacts/forms/Toolbar.php` | add, edit, copy, delete, filter, reset, clear; apply | Exakte Klasse/`contacts/contact`, fünf Standardaktionen; Apply nur Select/Search-Select. |
| `contacts/forms/ToolbarInline.php` | view, edit, copy, delete; apply | Exakte Klasse/Controller, Index/Search-Index; Apply getrennt Select/Search-Select. |
| `items/forms/Toolbar.php` | add, edit, copy, delete, filter, reset, clear; apply | Exakte Klasse/`items/item`, dieselben Grenzen. |
| `items/forms/ToolbarInline.php` | edit, copy, delete; apply | Dieselben Grenzen; **kein View-Feld vorhanden**, keines erfunden. |
| `tasks/forms/Toolbar.php` | add, edit, copy, delete, filter, reset, clear | Exakte Klasse/`tasks/task`, fünf Standardaktionen. |
| `tasks/forms/ToolbarInline.php` | view, edit, copy, delete | Exakte Klasse/Controller, Index/Search-Index. |
| `admin/forms/Toolbar.php` | add, edit, copy, delete, filter, reset, clear | Nur country/uom/deliverytime/manufacturer/warehouse/shippingmethod und fünf Standardaktionen. |
| `admin/forms/ToolbarInline.php` | copy | Dieselben sechs Controller, Index/Search-Index. Save/View/Edit/Delete/Sort nicht migriert. |
| `statistics/forms/Toolbar.php` | filter, reset | Nur customer/quote/turnover, Index/Search. Export und reporting unberührt. |

B17 erweitert diese bestehenden Konstruktorgates nur um Delete; tatsächlich sichtbar sind Kopf/Fuß in Index, der Detailkopf in Edit und die erneuerten Search-Fragmente. Select/View erhalten dadurch keinen neuen sichtbaren Löschweg. Die fünf Standardaktionen sind Index/Search/Select/Edit/View. Seit B14 geben die beiden internen Vollseitenlayouts einmal lokale Symbole unabhängig vom View-Routenkontext aus; iframes besitzen ihren eigenen Block, AJAXfragmente keinen. Diese Ressourcenverfügbarkeit aktiviert keine zusätzlichen Controls; die genannten Formular-/Requestgrenzen bleiben bestehen. B11 hatte keine neue Aktivierung vorgenommen.

### B12: ein gemeinsamer aktiver Filterchipweg

`application/views/helpers/Toolbar.php::renderActiveFilters` verwendet jetzt für dieselben 13 Controllergruppen den Button-Helper, `icon=clear`/B3-Alias Close, Text+Icon und sm. Index in allen 13 Gruppen, Select zusätzlich in Contact/Item/Task und den sechs einfachen Adminlisten: **22 logische Renderkontexte**. Exakte Klassen, bestehendes Filter-SVG-Opt-in und Viewkontext sind Voraussetzung. Inquiry-/Statistik-Select, Unterklassen, PNG-Piloten, andere Controller und sämtliche Edit/View-/Dokument-/Shop-/CMSwege bleiben beim bisherigen HTML. Alle bestehenden Filterwerte/-typen dieses Wegs sind enthalten; keine Heuristik aus Beschriftungen.

Filterbutton und Panel erhalten in diesen Kontexten einen korrekten Disclosure-Vertrag. Der bestehende Handler synchronisiert `aria-expanded`; die Toggle-/Filter-/Cookie-/Searchlogik bleibt erhalten. Der AJAX-Refresh erkennt neu markierte Plain-Filterpanels zusätzlich zum bisherigen Hauptheader. Er korrigiert dadurch veraltete Filterchips im migrierten Popup; unmarkierte Legacy-Popups bleiben unverändert. Filter-Sammelauswahllinks, normale Formularsubmits, Clear-Keyword, Alert-/Modalclose, MultiForm, Pin und fachliche Aktionen sind kein zusätzlicher B12-Block.

Historischer B12-Befund: Die damals 45 Formularfelder, 84 Controller-/Feldpaare, 13 primären Gruppen und 109 Controls der **inaktiven** Neun-Seiten-Referenz blieben byte-/wertgleich zur B11-Inventur; B17 aktualisiert die aktuellen Mengen oben auf 52/96/13/133. Aktive Filter erzeugen zusätzliche Instanzen des einen Helperwegs; nicht zu 45 addieren oder als Migrationsprozent interpretieren. Vollständige Regressionen, Quellen und Abnahmegrenzen im [B12-Bericht](filter-buttons.md).

Assetstatus nach B12: Die alten Filterchips verwendeten ein Unicode-× und kein PNG; durch diese Migration wird deshalb kein PNG neu verwaist. Die fünf ungenutzten SVG-Reservegeometrien, das bestätigte PNG-Duplikat und die statischen Sprite-/Helperkandidaten der folgenden Tabelle bleiben unverändert. Kein neuer globaler Löschbeweis und keine Assetentfernung.

### Verbleibende Toolbarfamilien und Sondergruppen

Sechs von 13 Haupttoolbar-Formularfamilien sind teilweise migriert: Admin, Inquiry, Contacts, Items, Statistics, Tasks. Die anderen sieben – Application, Campaigns, eBay, Processes, Purchases, Sales, Shops – bleiben als Formularfamilien vollständig Legacy. Seit B15 verwendet das Dashboard jedoch für drei direkte Edit-Zielgruppen den gemeinsamen View-Helper; dies aktiviert keine Felder in `Application_Form_Toolbar`. Auch die sechs teilweise migrierten Formularfamilien haben Ausnahmen:

- Kontakte: Email/Download/Downloadset/Karte/Import/Export, History, MultiEntity-Zuordnungen und fachliche Inquiry-Aktionen außerhalb des Piloten.
- Artikel: Inventory/Stock/Ledger/Stockplanning/Movements/Pricerules, Attribute/Option/Itemlist, vier Spezialunterklassen; teilweise abweichender Copy-Roh-ID-Vertrag statt JSON. Keine Aktivierung allein wegen desselben Iconnamens.
- Tasks: Positions-/Gruppen-/Pin-/Status-/Zuweisungs-/Follow-upaktionen. Fehlende reguläre Tasks-Modulrechte in `DEEC_Permission` bleiben dokumentierte Legacy-/Freigabegrenze, keine durch Darstellung zu lösende Rechteentscheidung.
- Admin: weitere 17 Actions-Listenmodelle, insbesondere Rechte/Mandanten/Finanzen/CMS/Shop/Export/Medien. Adminzugriff kommt aus Controller/ACL, isoliertes Formrendering ist kein Berechtigungsnachweis.
- Alle: Delete außerhalb der neun B17-Standardgruppen, Mail/PDF/Dokumentabschluss/-generierung, Zahlung, komplexe Massenaktionen, Upload/Senden, Sortierung, Tabellenpin, normale Formularsubmits und dynamische Factories sind keine implizit freigegebenen Folgemigrationen.

Zehn Inlineformularquellen: Admin/Contacts/Items/Tasks/Inquiry teilweise SVG; Campaigns/eBay/Processes/Purchases/Sales vollständig Legacy. Die sechs Positionsformulare bleiben Legacy. Vollständige konkrete Listenmodellnamen und Löschrisiken im [B10-Bericht](list-buttons.md); diese 44er-Grundgesamtheit ist unverändert.

## Icons, Styles und belegbare Altlasten

| Bestand / Befund | Aktueller Nachweis und Einordnung |
| --- | --- |
| 23 PNG-Aktionszuordnungen | `.dw-btn.<action>` plus innere `.dw-btn__icon--<action>` in `css/ui.css`; alle 23 Dateien vorhanden, je 16×16 px, zusammen 15.712 Bytes. Weitere UI-PNG-URLs wie `list.png` sind keine zusätzliche Aktionszuordnung. Kein flächiges Entfernen. |
| Lokale SVG-Allowlist | 15 semantische Keys, 14 Geometrien, 2.836-Byte-Sprite. Lucide 0.468.0 mit gepinnter Herkunft/ISC-/Feather-MIT-Nachweisen gemäß [B3](button-icons.md) und `images/icons/README.md`. Keine CDN-/Font-/CSP-Änderung. |
| Aktuell benutzte SVG-Keys | add, apply, clear, copy, delete, edit, filter, reset, save, view: zehn Keys in den 52 Feldkonfigurationen. close-Geometrie wird bereits durch clear genutzt. |
| SVG-Reserve | download/search/send/upload als vier noch nicht durch diese Produktionskonfigurationen genutzte Geometrien; close als eigener semantischer Key ebenfalls ohne eigenen Feldverbraucher. Absichtlich kuratierte Schnittstellenreserve, **keine zu löschende Altlast**. |
| PNG-Duplikat | `images/positions.png` und `select.png` SHA-256-identisch; bestätigt, aber weiter verschiedene semantische Namen/Verbraucher. Kein automatischer Lösch-/Umbenennungsauftrag. |
| Alte Root-Sprites/PNG-Zuordnungen | `toolbar.png`, `icons-small.png`, `icons-big.png` sowie die B1-Kandidaten settings/close/details/positions/back/get/daterange bleiben ohne bestätigten eigenen Literalverbraucher. Gespeicherte Inhalte, Themes und dynamische Klassen verhindern einen globalen Löschbeweis. Nicht als sieben bewiesen ungenutzte Laufzeitcontrols zählen. |
| Nachweislich inaktiver Repository-Pfad | `MainMenu.php::renderMobileDrawer()` hat nur einen auskommentierten Aufruf (`MainMenu.php:13`) und die geschützte Definition; eigene Quellen enthalten keinen aktiven Aufruf. Zwei alte Drawer-Labels sind damit kein aktueller Buttonflow. Nicht durch B11 entfernt. |
| Verwaister Helperkandidat | `application/views/helpers/Ordering.php` erzeugt Pfeil-Spans; kein eigener `->Ordering()`/`->ordering()`-Aufruf gefunden. Dynamische Helperauflösung/externe Templates separat ausschließen, bevor die Datei gelöscht werden dürfte. |
| Verwaister Spriteverweis im Repository | `.language-flag` verweist in `css/shop.css` auf fehlendes `../flags.png`; kein eigener Markupverbraucher dieser Klasse gefunden. Die 254 Einzeldateien unter `images/flags/` ersetzen den Sprite nicht. Belegter unversorgter CSS-Verweis, kein Nachweis eines aktuell sichtbaren Ausfalls. |
| Fünf Shop-Fontplatzhalter | checkout success/error, contact success/error, inquiry success: `icon-check fas fa-check-circle`; keine eigene geladene Iconfontversorgung im Standard-Site-Layout nachgewiesen. Bestehende öffentliche Baustelle, keine interne SVG-Migration. |
| Legacy-Modifikatoren | `hidden-sm`, `dw-button` und `dw-btn--secondary` haben keine entsprechende eigene durchgängige Präsentation. Moderne Danger/Primary/Ghost/Größenregeln sind auf `dw-btn--control` begrenzt und machen Legacyverbraucher nicht automatisch modern. `nolabel` wird normalisiert; nicht als wirksame CSS-Regel oder alleinige A11y-Lösung behandeln. |

Keine Datei wurde als Inventur-Nebenwirkung gelöscht. Statische Nichtreferenz ist schwächer als bestätigte Unerreichbarkeit; byteidentische Assets sind kein Nachweis ihrer Entbehrlichkeit. Die internen SVG-Controls haben geprüfte übersetzte Namen, daraus folgt keine vollständige DE/EN- oder Screenreaderfreigabe aller verbleibenden Legacywege.

## Verbleibende Prioritäten nach B12 und spätere Freigabe

Historischer B15-Folgestand (Standard-Delete inzwischen in B17 umgesetzt): Die drei direkten Dashboard-Editgruppen sind umgesetzt. Als nächster eigener Block ist der unvollständige Aktions-/Rowtargetvertrag der 16 Dashboard-Aufgaben-/Dokumentaufrufe zu klären; PDF nicht allein anhand der Klasse aktivieren. Danach Standard-Index-Apply, abgesicherte Standard-Deletewege und eigene Tags/Export/Pin-/Modalgruppen prüfen. Details, Mengen und Abnahmekriterien im [B15-Bericht](dashboard-buttons.md). Die folgende B12-Priorisierung beschreibt die weiterhin offenen größeren Qualitäts-/Legacygruppen; sie ist keine Freigabe zur gemeinsamen Umsetzung dieser fachlich verschiedenen Blöcke.

| Priorität | Konkreter nächster Block / Nutzen | Risiko und Abnahmekriterium |
| --- | --- | --- |
| B13 empfohlen: verbleibende Qualitätskorrektur | Den bei 320 px reproduzierten **globalen Headerüberlauf** und technische DE/EN-Spalten-/Menü-/Reportkeys separat lokalisieren und korrigieren. B12 hat den risikoarmen Filter-Disclosure-Unterblock und den dazugehörigen gemeinsamen Clear-Filterweg umgesetzt. Header-/Katalogänderungen teilen diesen Renderer nicht und wurden nicht mitgebündelt. | Mittel, gemeinsame Layout-/Katalogwirkung. Vorher/Nachher DE/EN × 320/390/768/1280, mehrere Module, lange Benutzer-/Mandantennamen und Plainlayouts; keine stillschweigende Toolbar-/Workflowmigration. Disclosure ist nur für die B12-Scopes geschlossen, nicht global für jeden Legacyfilter. |
| B17 abgeschlossen: Standard-Delete | Neun reguläre Toolbarkontexte und drei Inlinekontexte, sieben Feldquellen. Keine neue fachliche Aktion. | Aktuelle Tests/Abnahmegrenzen im [B17-Bericht](delete-apply-buttons.md). Admin-media-delete bleibt Legacy. |
| Historische Folgeempfehlung (nicht B18-Scope) | Kontakte-/Artikel-Index-Apply ohne sinnvollen Parentvertrag, Admin-media-delete mit doppelten Actionattributen und Dashboard-Legacy ohne explizite Targets zunächst fachlich prüfen. | Keine reine SVG-Aktivierung: gegebenenfalls Verhaltensänderung erforderlich. Dokument-/Medien-/Buchungsaktionen weiterhin getrennt freigeben. |
| Danach wiederkehrende Positions-/Set-/Pinaktionen | Dieselben bestehenden Renderer nutzen, tatsächliche Aufrufnamen/Unterklassen vorher neu prüfen. | Hoch: Parent/Set/Options-/Bestands-/Reihenfolgeverträge. Kein globales Opt-in in gemeinsamen Klassen, keine Ableitung allein aus Add/Copy/Delete-Text. |
| Gesondert Formulare/dynamische Controls | Wiederkehrende normale Submit-/Modal-/MultiForm-/Clear-/Closecontrols mit eindeutigem Vertrag; Test- und Namensverbesserung. | Native Submitterwerte, Auto-Save-Opt-out, Fokus-/Dialoggrenzen erhalten. Keine neue parallele JS-Renderingarchitektur; ZF1-/DEEC-Unterschiede beachten. |
| Später eigene fachliche Blöcke | Dokumente/PDF/Export, Mail/Senden, Zahlung, SaaS-/Rechte-/Mandantenverwaltung, Kampagnen, Shop/CMS und Widgetöffner. | Fachliche Abnahme, historische Werte, Rechte/Entitlements, gespeichertes HTML/Themes und Fremdwidgets. Nicht durch Entfernen vorhandener Guards freigeben. |
| Aufräumen separat | Inaktiver Drawerpfad und verwaiste Helper-/Assetkandidaten mit vollständigem Erreichbarkeits-/Theme-/Datenbestandnachweis. | Niedriger Nutzen gegenüber UI-Qualität; keine automatische Löschung aufgrund von `rg`-Nulltreffern. |

Freigabeplan: technische B12-/B2–B11-Prüfungen gemäß [Filterbericht](filter-buttons.md) und [mobilem Bericht](mobile-table-actions.md), danach manuelle reale DE/EN-Testinstallation mit repräsentativen langen Daten, Rollen/Mandanten/Sharing und gebuchten SaaS-Features; mobile Scroll-/Touch-/Fokus-/Tooltipprüfung; Firefox/Safari und NVDA/VoiceOver. Bestehende B8-Altbefunde außerhalb des Filter-Scopes bleiben separat zu beurteilen. Der historische Notification-Fixturefehler besteht im aktuellen B17-Abschlusslauf nach dem unabhängigen T6-Commit `c196e6ad` nicht mehr; keine B17-Fachlogikkorrektur. Keine produktive Session erzeugen, um eine fehlende Testumgebung zu ersetzen.

## Historischer Quellenanhang: 243 literale Konfigurationen

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

Ohne Pfadpräfix genannte Moduldateien liegen unter `application/modules/<modul>/forms/`. Die vollständigen Zeilennachweise einschließlich Raw-HTML/JS/Links/ZF1/Helper stehen reproduzierbar im JSON-Output des Pythonwerkzeugs; die vollständigen 52 Feldnamen mit Aktivierungskontexten im PHP-Output. Die historischen Auswertungen liegen unter `/tmp/dewawi-b11/{source,runtime}-inventory.json`; die tatsächlich erneut ausgeführten aktuellen B17-Auswertungen unter `/tmp/dewawi-b17/{source,runtime}-after.json`. Keine Datenbank-/gespeicherten Inhaltsänderung, keine neue breite Migration und kein Push.


## B17-Zählabgleich

Aktueller Quellscanner: 1.074 Dateien, 243 literale Formkonfigurationen (234 Button/9 Submit), 145 lexikalische `<button`-Treffer, 79 Buttonlinks, 25 alte Helperaufrufe, 13 ZF1-Stellen. Diese Mengen sind vor/nach B17 identisch; der historische B11-Rohwert darf nicht als aktueller DOM-Bestand gelesen werden. Keine neuen Assets oder Renderer. PHP liefert jetzt zusätzlich `standard_action_fields` (7 Delete-/4 Applyfelder) und `legacy_standard_action_fields` mit den ausdrücklich nicht aktivierten Konstruktorkontexten. Derselbe Feldname kann dabei in beiden Gruppen vorkommen, weil z. B. Apply nur in Select aktiviert ist. Keine neue Prozentangabe.
