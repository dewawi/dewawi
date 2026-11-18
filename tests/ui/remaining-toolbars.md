# B7 – Verbleibende risikoarme Standardtoolbars

Fortsetzung: [B8 – Qualitätsaudit und Freigabevorbereitung](button-quality-audit.md) prüft den lokalen Stand, präzisiert die Übersetzungsfixture und dokumentiert aktuelle Freigabegrenzen. Die folgenden Angaben beschreiben den historischen B7-Stand.

Stand: 18.11.2026. Lokale Grundlage: `AGENTS.md`, [Bestandsaufnahme](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5-Inventar](toolbar-buttons.md) und [B6](tasks-toolbar.md); Commits `b27f55e6`, `2af50303`, `20ebf9d9`, `aaca1571`, `f23f05fb`. Der unversionierte Analysebericht bleibt unverändert und außerhalb des Commits.

## Entscheidung und migrierter Umfang

Die verbleibenden geeigneten Gruppen verwenden bereits `DEEC_Form` und dieselben Kopf-/Fußtoolbar-Helper. B7 ergänzt ausschließlich ausdrückliche Präsentationsmetadaten in zwei vorhandenen Formularen sowie die begrenzte Symbolausgabe in den internen Layouts. Keine neuen Renderer, Helper, Icons, CSS-Regeln, JS-Handler oder Bibliotheken. Ursache der unterschiedlichen Darstellung waren fehlende SVG-Opt-ins in diesen Klassen; der gemeinsame Renderer benötigt keine Änderung.

| Gruppe / exakte Klasse | Freigegebene Controller | Requestaktionen | Controls und vorhandene semantische Keys |
| --- | --- | --- | --- |
| `Admin_Form_Toolbar` | `admin/country`, `uom`, `deliverytime`, `manufacturer`, `warehouse`, `shippingmethod` | `index`, `search`, `select`, `edit`, `view` | `add→add`, `edit→edit`, `copy→copy`, `filter→filter`, `reset→reset`, `clear→clear` (B3-Alias auf Close) |
| `Statistics_Form_Toolbar` | `statistics/customer`, `quote`, `turnover` | ausschließlich `index`, `search` | `filter→filter`, `reset→reset` |

Damit kommen **neun Controllerkontexte**, **36 Requestaktionskontexte** (6 × 5 + 3 × 2) und **42 controllerbezogene Controlkonfigurationen** (6 × 6 + 3 × 2) hinzu. Implementiert werden acht Metadateneinträge in zwei Klassen; wiederholte DOM-Ausgaben im Footer sind keine zusätzlichen Konfigurationen. Alle als kompatibel und risikoarm eingestuften Gruppen dieses Inventars sind in diesem Block berücksichtigt.

Die sechs Admincontroller verwenden `DEEC_Controller_AdminAction`/`DEEC_Controller_Action::buildListView()` und den gemeinsamen JSON-Copy-Vertrag `{ok,id}`. Versandarten sind hier beschreibende Stammdaten (`title`, Sprache, Mandant), keine Versandabwicklung; Controller, Entity und Form enthalten keinen abweichenden Toolbarhandler. UOM-Defaultregeln und Warehouse-Aktiv-/Defaultfelder sind fachliche Formularaktionen und bleiben unangetastet.

Die Statistikcontroller bauen ihre vorhandene Toolbar ausdrücklich in `indexAction()` auf; `searchAction()` ruft denselben Aufbau ohne Layout auf. Reportdaten und Exporte werden nicht umgebaut. Das jüngere `statistics/reporting` konstruiert keine `Statistics_Form_Toolbar` und erhält kein Opt-in.

## Darstellung vor / nach B7

| Kontext | Vorher | Nachher / unverändert |
| --- | --- | --- |
| Admin-Index, Kopf | Standardaktionen mit bisherigen PNG-/Legacy-Styles | Sechs SVG-Controls; Texte unverändert, nur Clear weiterhin Icon-only. Delete und ggf. zentral ergänzter Export bleiben Legacy. |
| Admin-Index, Fuß | Add/Edit/Copy/Delete aus derselben Formularinstanz | Drei wiederverwendete SVG-Controls; Delete bleibt Legacy. |
| Admin-Select, plain | Suche/Clear, vorhandene Filter; kein eigener Apply-Eintrag dieser Klasse | Clear mit SVG; kein neuer Auswahlbutton, keine Änderung am bestehenden Auswahlvertrag. |
| Admin-Edit/View | Datensatzbezogene Copy-Aktion, ggf. Legacy-Delete | Copy mit SVG; `data-id`, Modul und Controller bleiben gleich. |
| Statistik-Index | Reset/Filter/Export mit Legacy-Darstellung | Nur Reset/Filter mit SVG. Export bleibt Legacy und weiterhin rechteabhängig. Keine Fußtoolbar erfunden. |
| Search-Fragmente | `content`/`toolbar`; bestehende Haupttoolbar bleibt, Filterchips/Listeninhalt aktualisieren sich | SVG-Referenzen im realen ListResponse-Vertrag; keine zusätzlichen Symbolblöcke in Fragmenten. Adminfooter wird aus der gleichen Instanz neu gerendert. |

Alle neuen Buttons bleiben `type=button`, Defaultvariante/md, 16-px-Icons, 8-px-Abstand; die vorhandene B2/B3-Mobilregel liefert mindestens 44 px Höhe und 20-px-Icons. Clear erhält explizit `CLEAR`/`iconOnly=true` statt eines technischen Namens: „Leeren“/„Clear“. Dekorative SVGs sind `aria-hidden=true`, `focusable=false`, ihre Geometrie verwendet `currentColor`. Kein neuer Iconbedarf, keine leeren Platzhalter, keine neue Asset-/Lizenz-/CSP-Abhängigkeit.

## Vollständige Fortschreibung des Toolbar-Inventars

Die 42 Anwendungsdateien aus B5 sind weiterhin vollständig vorhanden: **34 Formulare und acht Helper**. B7 fügt keine Anwendungskomponente hinzu. Zähleinheit dieser Übersicht ist die deklarierte Toolbarfamilie bzw. Datei, keine Anzahl produktiv besuchter URLs.

| Dateigruppe | Anzahl | Nach B7 |
| --- | ---: | --- |
| Haupttoolbarfamilien, direkt von `DEEC_Form` abgeleitet | 13 | Sechs mit begrenztem SVG-Opt-in; sieben vollständig Legacy. Vor B7 vier / neun. |
| Inline-/Zeilentoolbars | 10 | Nur bestehende Inquiry-Inline aus B4 mit SVG; neun Legacy. In B7 keine geändert. |
| Positionsformulare | 6 | Alle Legacy. |
| Abgeleitete Spezialtoolbarformulare | 5 | Vier Items-Unterklassen plus Sales-Quote; alle Legacy. |
| Helper | 8 | Gemeinsame `Toolbar`/`ToolbarBottom` verwenden weiter dieselbe Ausgabe; sechs Positions-/Historyhelper bleiben Legacy. Kein Helpercode geändert. |

Damit enthalten **sieben von 34 Toolbarformular-Dateien** ein begrenztes SVG-Opt-in, **27 bleiben vollständig Legacy**. Das bedeutet ausdrücklich keine vollständige Migration aller Controls dieser sieben Dateien. Die sechs migrierten Hauptfamilien decken jetzt **13 Modul-/Controllergruppen** ab: bisher Inquiry, Contact, Item, Task plus neun B7-Gruppen. Die kontextbegrenzten Standardklassen aus B5–B7 besitzen zusammen 51 freigegebene Requestaktionspaare; Inquiry hat eigene, bereits in B4 dokumentierte Präsentationen und wird in diese Guard-Zahl nicht hineingerechnet.

| Haupttoolbarfamilie / Fundstelle | Aktueller Kontext und bewusst verbleibende Wege |
| --- | --- |
| `Application_Form_Toolbar`, `IndexController`, `index/index.phtml` | Dashboard/Dokumentzeilen, Copy/PDF/Delete; Dokument-/Tabellenkontext, vollständig Legacy. |
| `Admin_Form_Toolbar` | Sechs genannte Stammdatencontroller migriert. Die übrigen 23 Admincontroller werden unten vollständig eingeordnet. |
| `Campaigns_Form_Toolbar` | Campaign samt Mail-/Kampagnen-/Positionsflow; vollständig Legacy. |
| `Contacts_Form_InquiryToolbar` | Bestehender B4-Pilot unverändert. |
| `Contacts_Form_Toolbar` | Contact aus B5 unverändert. Email, Download, Downloadset sowie Contact-Map/Import/Export bleiben Legacy. MultiEntity-Kontakt-/Adress-/Telefon-/Internet-/Bank-/Tagwege sind keine zusätzliche eigenständige Standardtoolbarfreigabe. |
| `Ebay_Form_Toolbar` | Account, Index, Listing, Log, Order: externe Integration/abweichende Legacycontroller; vollständig Legacy. |
| `Items_Form_Toolbar` | Item aus B5 unverändert. Inventory und Lagerflüsse, Attribute/Option, Itemlist und Preisregeln bleiben ausgeschlossen. Variant ist ein spezialisierter JSON-Endpunkt mit Indexredirect, keine neue Standardlistentoolbar. Shops/Category und Admin/Export verwenden ebenfalls diese Klasse, bleiben Legacy. |
| `Processes_Form_Toolbar` | Prozessstatus, Zahlungen, Lieferung und Positionen; vollständig Legacy. |
| `Purchases_Form_Toolbar` | Purchaseorder/Quoterequest, Dokumente/Positionen; vollständig Legacy. |
| `Sales_Form_Toolbar` | Quote, Invoice, Creditnote, Deliveryorder, Salesorder, Reminder; Dokument-/Mail-/Zahlungsworkflows, vollständig Legacy, einschließlich `QuoteToolbar`-Unterklasse. |
| `Shops_Form_Toolbar` | Account, Log, Order und Shop/CMS-Frontend; vollständig Legacy. |
| `Statistics_Form_Toolbar` | Alle drei tatsächlichen Report-Toolbaraufrufer mit Filter/Reset migriert; Export und `toolbar`-Sonderendpoint Legacy, Reporting ohne diese Klasse unverändert. |
| `Tasks_Form_Toolbar` | B6-Kontext unverändert; `get`, Sourcequote/Add, Inline und Positionsaktionen bleiben ausgeschlossen. |

Weitere Items-Unterklassen: `ToolbarStock` (`StockController::getToolbarClass`), `ToolbarMovements` (Ledger), `ToolbarStockplanning` und `ToolbarItemlist` bleiben auch bei künstlichem Aufruf im Itemkontext unverändert. Itemlist/Attribute/Option überschreiben `copyAction()` und geben eine rohe ID aus; das passt nicht zum gemeinsamen JSON-Handler. Inventory/Ledger/Stock/Stockplanning enthalten Bestands-/Buchungs- bzw. Spezialfilterkontexte. Preisregeln/Preisregelpositionen betreffen Kalkulation und Parentautorisierung. Diese Wege werden nicht durch Entfernen der bisherigen Guards aktiviert.

Vollständige Einordnung der **23 übrigen Admincontroller** (Controllerdateien, nicht 23 nachgewiesene Standardtoolbarseiten):

| Controller | Anzahl | Ausschlussgrund |
| --- | ---: | --- |
| Currency, Taxrate, Paymentmethod | 3 | Finanz-/Zahlungsstammdaten, bewusst sensitive Gruppe; generischer Renderer allein ist keine Freigabe dieses Kontexts. |
| Category, Footer, Menu, Menuitem, Page, Pageblock, Shop, Slide, Slug, Tag, Template | 11 | Shop/CMS, Typ-/Shop-/Parent-/Sprachsonderkontexte; Category/Tag mischen Kontakt-/Artikel-/Shoptypen. Keine pauschale Aktivierung. |
| Client, Config, Module, Permission, User | 5 | Mandanten-, System-, Rechte- und Featureverwaltung; teils eigene Toolbarunterdrückung/Views. |
| Export | 1 | Eigene Datenexportstrecken mit Contacts-/Items-Toolbar, keine Admin-Standardgruppe. |
| Info, Index, Media | 3 | phpinfo/Systeminfo, Dashboard bzw. Medienwidget; keine kompatible CRUD-Standardtoolbar, Drittanbieter-/Widgetaktionen bleiben ausgeschlossen. |

Tabellenaktionen (`ToolbarInline`/`DEEC_List::renderActionsCell`), `ToolbarPositions`, Sethelper, History-Direkt-HTML, JS-/Dialog-Controlfabriken, allgemeine Formularbuttons, dynamische MultiEntity-Aktionen und Third-Party-Widgets bleiben außerhalb. Bereits vorhandene AJAX-Neuausgabe der migrierten Standardfooter wird geprüft; daraus folgt keine Migration anderer dynamischer Controls. Es verbleiben **keine in diesem Block als risikoarm und kompatibel bewerteten Haupttoolbargruppen**; die verbleibenden sieben Hauptfamilien und die genannten Sonderkontexte haben ausdrückliche Ausschlussgründe. Eine vollständige Anzahl aller dynamisch erreichbaren URLs wird aus statischen Klassenzahlen nicht abgeleitet.

## Verträge, Rechte und Risiken

Aktivierung verlangt Request, exakte konkrete Klasse, Modul, Controllerallowlist und Aktionsallowlist. Ohne MVC und in synthetischen Unterklassen bleiben beide Formulare Legacy. Layouts geben die festen lokalen Symbole nur für die genannten neuen Controlleraktionen aus; `site.phtml` bleibt unverändert. Kopf und Fuß erhalten keine voneinander unabhängige Renderlogik.

`type`, `name`, `value`, bestehende Klassen, `rel`, URLs, `data-*`, Recordtargets und Eventattribute werden mit der unveränderten Unterklassenreferenz verglichen. Alle Nicht-Präsentationskonfigurationen einschließlich Filterdefaults bleiben identisch. Suche, Auswahl, Copy-Sperren über Kopf/Fuß, Loading, AJAX-Fragmente und Auto-Save verwenden unverändert `global.js`.

`Application_Plugin_Acl` schützt Admin weiterhin serverseitig durch `user.admin`; `admin` ist keine reguläre Modulrechtsspalte. Die bestehende Legacy-Buttonsichtbarkeit ohne Modulspalte erteilt keine Dispatchberechtigung. Statistik benötigt bestehende View-Rechte je Report. Negative ACL-Fälle prüfen Nicht-Admins bzw. fehlende Reportrechte, außerdem Rendering mit geschütztem abweichendem Ziel und fremden Benutzerrechten. `hasModule()` ist kein SaaS-Entitlement. Keine Rechte-, Feature-, Mandanten-, Parent-/Sharing-, Status- oder Querylogik geändert. Die Mandantensuite wird zusätzlich erneut ausgeführt; das ersetzt keinen vollständigen produktiven Rollen-/Buchungstest.

| Risiko | Begrenzung / verbleibende Abnahme |
| --- | --- |
| Gemeinsame Klassen bedienen Shop/System-/Spezialkontexte | Exakte Klassen-/Routengrenzen, vollständige Admin-/Statistik-Kontextmatrix und negative Browserseiten. |
| SVG-/use-Klicks verändern Target oder Requestanzahl | Bestehender delegierter Handler; Browser prüft Kopf/Fuß, Copy-Sperre, Restore und dynamische Footer. |
| Filter/Datumsdefaults oder Auto-Save ändern sich | PHP-Konfigurationsvergleich; Browser prüft Datumsbereich, Von-Datum, Reset, Feld-Auto-Save und Opt-out. |
| Icons ohne Namen/Geometrie, schlechter Fokus auf Mobile | DE/EN, reale Assets/Layouttemplates, AX-Baum, SVG-Geometrie, Größe/Abstand, Hover, Fokus und Tastatur automatisiert. |
| Produktive Themes/Rollen, echte Report-/Formdaten | Nicht vollständig abgedeckt: fachliche/visuelle Produktfreigabe, echte Mandanten-/Entitlementkonfigurationen und vollständige Geschäftsviews bleiben manuell zu prüfen. |
| Bestehender englischer Umsatzreport läuft bei 390 px über | Mit nicht migrierter Unterklasse reproduziert: 528 px Dokumentbreite durch unübersetzte `STATISTICS_TURNOVER_*`-Überschriften. Neue Toolbar bleibt im Viewport; Nachherbreite muss exakt der Legacy-Referenz entsprechen. Keine globale CSS-/Katalogkorrektur in B7. |

Keine Datenbank-, gespeicherten Inhalts-, Controller-, Fachaktions-, CSS- oder JavaScriptänderungen. Keine Berechnung historischer Werte und keine Datenbanknacharbeit.

## Verifikation

Vor Änderung bestehen die bisherigen PHP-Baselines und 928 B5-Browserassertions. Snapshot vor/nach B7: **1.900 Konstruktor-Kontexte**, aus 29 Admin- + vier Statistik- + fünf modulübergreifenden Controllern × zehn Aktionen × fünf Klassen. **Genau 36** Standardklassenkontexte ändern ausschließlich die vorgesehenen Präsentationen, **1.864** bleiben identisch. Zusätzlich bleiben alle **480 B5-** und **350 B6-Snapshots** unverändert. Diese Zahlen sind synthetische Konfigurationen, keine authentifizierten HTTP-Seiten.

| Tatsächlich ausgeführte Prüfung | Ergebnis |
| --- | --- |
| `php tests/ui/remaining-toolbars.php` | PASS: 26.925 neue Metadaten-/Kontext-/HTML-/Label-/Layout-/AJAX-/Readonly-/ACL-Checks, zusätzlich eingebundene 165 B2- und 2.259 B5-Checks. |
| `python3 tests/ui/toolbar-buttons.py --remaining` | PASS: 3.247 Browserassertions im vollständigen Abschlusslauf. Matrix: 144 Admin- und 18 Statistik-Kombinationen, 18 Readonly-Seiten, 24 ausgeschlossene Adminseiten und eine Legacy-Reportreferenz. |
| `php tests/ui/buttons.php`, `button-icons.php`, `inquiry-buttons.php`, `toolbar-buttons.php`, `tasks-toolbar.php` | PASS: bestehende Baselines 165 / 645 / 697 / 2.259 / 1.428; eingebundene Baselines nicht nochmals zu einzigartigen Fällen addiert. |
| `python3 tests/ui/buttons.py`, `--svg`, `inquiry-buttons.py` | PASS: 169 PNG-, 194 SVG- und 796 Inquiry-Browserassertions, einschließlich strenger CSP, iframe, Forced Colors und bestehender Submit-/Auto-Save-Verträge. |
| `python3 tests/ui/toolbar-buttons.py`, `--tasks` | PASS: 928 B5- und 524 B6-Browserassertions, auch im abschließenden Lauf nach Ergänzung der Überlaufmessung. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 Browserchecks bei 1280/390 px, einschließlich Dialog-/Mail-/Dokument-/Auto-Save-/Positions-/Modalverträgen. |
| `php tests/security/admin-access.php`, `permissions.php`, `tenant-access.php` | PASS: 68 / 415 / 269 Fälle. Negative `NO_PERMIT`-Ausgaben erwartbar. |
| `php tests/security/contact-inquiries.php`, `form-input-validation.php`, `inquiry-dynamic-form.php` | PASS: 46 / 20 / 31 Fälle. |
| `php tests/sales/quote-monitoring.php`, `tests/lists/summary.php` | PASS: 293 Follow-up-/Default-/Mandantenfälle bzw. zentrale Listenfooter-/Summenprüfung. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler: 370 erfolgreiche Checks, anschließend fehlende `Sales_Service_DocumentRevisionService` in `sales/views/helpers/Finish.php:12`, Fixtureabbruch in `tests/security/notifications.php:211`. Keine betreffende Datei geändert. |
| Standalone / Syntax / Diff | Beide neuen Toolbar-Opt-ins ohne Zend/MVC weiterhin Legacy; PHP-Lint aller sechs geänderten/neuen PHP/PHTML-Dateien, Python-AST und `git diff --check` bestanden. |

Die automatisierten Prüfungen umfassen DE/EN × 390/768/1280 px; Admin-Index/Hauptlayout, Select/Popup, Edit und View, Statistik-Index/Hauptlayout. Geprüft werden Kopf/Fuß, richtige Iconkeys, tatsächlich dargestellte SVG-Geometrie, AX-Namen, Fokus, Hover, Disabled, Loading mit identischer Wiederherstellung der SVG-Nodes, Tab/Shift-Tab/Enter/Space, SVG-/use-Klicks, Einzelauswahl, URLs, Copy-Sperren über Kopf/Fuß, neue Footer nach Search, Keyword/Clear sowie Auto-Save/Opt-out realer beschreibender Felder. Statistik prüft zusätzlich Custom-Datumsbereich, Von-Datum, Resetdefaults und unverändert gerenderten Legacy-Export. Negative Browserkontexte Currency, Taxrate, Paymentmethod, Shop, User und Category müssen die vorhandenen PNG-Hintergründe und null moderne Controls/Symbolblöcke behalten.

Der erste breitere Browserlauf machte außerdem den bestehenden englischen Reportüberlauf sichtbar. Eine zusätzliche echte Legacy-Renderprobe reproduziert ihn: `STATISTICS_TURNOVER_MONTHS`, `STATISTICS_TURNOVER_CUSTOMER_GROUPS_YEARLY`, `STATISTICS_TURNOVER_YEARLY_COMPARISON` fehlen in diesem englischen Katalogstand und brechen nicht um. Der Test toleriert ausschließlich diesen konkret verglichenen Bodybefund bei `en_US/statistics/turnover/390`; Toolbarüberlauf bleibt ein Fehler. Für alle anderen geprüften Seiten gilt weiterhin kein horizontaler Seitenüberlauf.

Screenshots `/tmp/dewawi-b7-admin-<controller>-<index|select|edit|view>-<390|1280>.png` und `/tmp/dewawi-b7-statistics-<controller>-index-<390|1280>.png` aufgenommen. Gesichtet: Country-Kopf mobil/Desktop, Desktopfooter, mobiles Select-Popup, Desktop-Edit sowie Customer-Toolbar mobil und Turnover-Toolbar am Desktop. Icons, Abstände und Clear-Fokus sind sichtbar. Mobile Adminseiten behalten die vorhandene lange Seitenleiste vor dem Listeninhalt; technische Breadcrumb-/Reporttexte außerhalb der Buttonlabels sind bestehende Kataloglücken. Fachliche und visuelle Produktfreigabe bleiben offen.

Die bestehende Fixture rendert echte `DEEC_Form`-Controls, Toolbar-/Footerhelper, ListResponse und interne Haupt-/Popup-Layouts. Für Admin wird jeweils ein echtes beschreibendes Formularfeld als Auto-Save-Probe genutzt, einschließlich tatsächlichem AdminMenu. Die drei Statistik-Indextemplates werden mit synthetischen/Leerdaten und festen Testpixeln gerendert; Reportabfragen und Chartberechnungen werden nicht ausgeführt. Browser-AJAX und Navigation sind vollständig abgefangen. Keine Produktionskonfiguration, echte Adminsession, Datenbank oder Kundendaten verwendet.

Nicht ausgeführt: produktive/authentifizierte E2E-Schreibaktionen und `tests/lists/footer-http.py` (Produktionsbootstrap/echte Adminsession). `tests/admin/uom.php` benötigt den separat gestarteten wegwerfbaren Installations-DB-Test; dieser fachliche DB-Lauf ist für unveränderte UOM-Logik kein Teil von B7. Physische Touchgeräte, Safari/Firefox und Screenreader sowie vollständige Geschäftsviews bleiben manuell offen.

Nächste sinnvolle Gruppe nach Sicht-/Fachfreigabe: bestehende statische Apply-Buttons der Kontakte-/Artikel-Auswahldialoge mit eigenen Parent-/Popup-/Übernahmeverträgen prüfen; der SVG-Key existiert bereits. Keine automatische Fortsetzung. Finanz-/Rechte-/Dokument-/Mail-/Shop-/Tabellen-/Positionsmigrationen bleiben gesonderte, fachlich zu prüfende Blöcke.

Laufzeitdateien: `application/modules/{admin,statistics}/forms/Toolbar.php`, `application/layouts/scripts/{layout,plain}.phtml`. Tests: `tests/ui/remaining-toolbars.php`, erweiterte `toolbar-buttons.php`/`.py` (`--remaining`; Standard-/Tasks-Modi bleiben Baselines). Dokumentation: dieser Bericht, Links in B5/B6. Analysebericht weiter unversioniert, SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`.

Nur eigene auftragsbezogene Dateien lokal committen. Kein Push, Merge oder Deployment.
