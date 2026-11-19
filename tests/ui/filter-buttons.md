# B12 – Gemeinsame Standard-Filterbuttons

Stand: 19.11.2026. Grundlage: `AGENTS.md`, [aktuelle Inventur](button-system-inventory.md), [B11-Mobilbericht](mobile-table-actions.md), [B8-Audit](button-quality-audit.md) und die relevanten B1–B10-Berichte. Ausgangs-HEAD `83a51db1`. Zu Beginn vorhandene fremde Dokument-/Recoveryänderungen wurden weder bearbeitet noch gestaged; während der Arbeit separat als `b32241c1` committed. Lokaler Code ist maßgeblich.

## Priorität, Ursache und vollständiger gewählter Block

Die Inventur priorisiert zuerst Qualitätsgates und nennt den Filter-Disclosure-Unterblock ausdrücklich. Löschen ist hier ausgeschlossen; Positions-/Setaktionen haben zusätzliche Parent-/Bestands-/Reihenfolgerisiken. Gewählt ist deshalb der gemeinsame **Clear-Filterchipweg samt zugehöriger Filter-Disclosure** in bereits migrierten internen Standardtoolbars. Alle aktiven Filter dieses Wegs sind enthalten, keine Auswahl nach Beschriftungen oder einzelnen günstigen Werten.

Headerüberlauf und globale DE/EN-Kataloglücken teilen diesen Renderingweg nicht und werden nicht mitgebündelt. Allgemeine Submits, MultiForm-/JS-Factories und Legacy-Positionsparameter-Helper haben andere Verträge und bleiben ein eigener späterer Block. Keine breite Formular-/Modulmigration.

Ursache: `Toolbar::renderActiveFilters()` baute ein eigenes natives Buttonfragment mit `<strong>Label:</strong> Wert` und Unicode-×. Es nutzte weder die bestehende Buttonpräsentation noch deren Größen-/Fokus-/SVG-/Disabled-/Loadingkonventionen. Der Filteropener meldete seinen geöffneten Zustand nicht. Außerdem suchte der bisherige AJAX-Chiprefresh nur im Hauptheader; Plain-Popupchips konnten nach einer erfolgreichen Suche veraltet stehenbleiben.

| Exakte Klasse / Quelle unter `application/modules/` | Freigegebener Kontext |
| --- | --- |
| `Contacts_Form_InquiryToolbar`, contacts/forms/InquiryToolbar.php | contacts/inquiry, Index/Search-Index |
| `Contacts_Form_Toolbar`, contacts/forms/Toolbar.php | contacts/contact, Index/Search-Index und Select/Search-Select |
| `Items_Form_Toolbar`, items/forms/Toolbar.php | items/item, dieselben logischen Kontexte |
| `Tasks_Form_Toolbar`, tasks/forms/Toolbar.php | tasks/task, dieselben logischen Kontexte |
| `Admin_Form_Toolbar`, admin/forms/Toolbar.php | country/uom/deliverytime/manufacturer/warehouse/shippingmethod, dieselben logischen Kontexte |
| `Statistics_Form_Toolbar`, statistics/forms/Toolbar.php | customer/quote/turnover, nur Index/Search-Index |

Das sind **13 bestehende Controllergruppen und 22 logische Index-/Selectkontexte**: 13 Index, neun Select. Search entspricht dem vom vorhandenen Plugin gesetzten logischen Kontext. Voraussetzung sind exakte Klasse, passender Viewkontext und bestehendes ausdrückliches SVG-Opt-in des Filterfelds. Die Guardliste liegt im bestehenden Toolbar-Helper; kein neuer Registry-/Renderer-/Frameworklayer und keine globale automatische Aktivierung.

Unterklassen, PNG-Piloten, andere Controller, Edit/View/Export/Get/History sowie Inquiry-/Statistik-Select erhalten keine neue Chipausgabe. Dort bleibt der vorhandene HTMLzweig erhalten. Die Klassen-/Controllerlisten sind bewusst explizit; künftige Erweiterungen müssen Formulargrenzen, Layoutsymbole und Negativtests gemeinsam prüfen.

## Architektur und Kompatibilität

- `application/views/helpers/Toolbar.php` ruft für freigegebene Chips `getHelper('button')->renderButton()` auf. Dieser vorhandene B2-Helper delegiert an `DEEC_Form::renderButtonHtml()`. Keine zweite moderne Renderlogik. Ein semantischer Key **clear** aus der B3-Allowlist nutzt das lokale Close-Symbol; keine neue Icondatei, Bibliothek, Lizenz oder CSS-Regel.
- Bestehende Klasse `dw-filter-chip`, `type=button`, `data-action=clear-filter`, `data-filter` und fehlende Name/Value/Href bleiben erhalten. Text+Icon, Default/sm, vorhandene 32-px-Mindesthöhe bzw. 44 px mobil/grober Pointer. Der bisherige Feld-/Werttext bleibt sichtbar; Fettmarkierung und Unicode-× werden durch die gemeinsame Textspan-/SVG-Präsentation ersetzt. Keine leere Iconbox.
- Neue DE/EN-Beschriftung `TOOLBAR_CLEAR_FILTER`: „Filter entfernen: %s“ / „Remove filter: %s“. Der zugängliche Name enthält Feld und Wert; SVG dekorativ, keine eigene Ansage. Labels und Attribute werden von der gemeinsamen Routine einmal escaped. Tests decken Ampersand, Quotes, lange/ungetrennte Werte und `<unsafe>` als Text ab.
- Der existierende Filterbutton erhält temporäre Renderattribute `aria-controls` und `aria-expanded=false`; seine Formkonfiguration, Name/Value/Typ und übrigen Attribute bleiben gleich. Das zugehörige Panel bekommt einen stabilen, scopespezifischen `dw-toolbar-filters-<module>-<controller>`-Identifier. Filterbuttons werden nicht neu in Select eingeblendet, wenn die alte Selecttoolbar keinen Opener hatte.
- `js/global.js::DewawiToolbar.actions.filter` behält sein bestehendes Toggle bei und synchronisiert danach ausschließlich Controls mit `aria-controls`. Kein neuer Eventhandler, kein konkurrierendes Filtersystem.
- `DewawiToolbar.refresh()` behält die bisherige Hauptheaderauswahl. Nur wenn sie fehlt, erkennt es das Plain-Popup über den neu gesetzten Panel-ID-Präfix. Dies korrigiert veraltete Chips in den migrierten Popups. Unmarkierte Legacy-Popups bekommen keine neue Refreshlogik; der negative Test sichert genau diese Grenze ab.

Resetwerte, Optionen, skalare/Array-/Datumsfilter, Cookies mit tatsächlichem Modul-/Controllerpfad, Paginationreset, Search-Debounce/-Abort/-Fehlerzustand, Auswahlreset nach Erfolg und bestehende dynamische Eventdelegation bleiben erhalten. Search ersetzt die aktiven Chips, bewahrt aber den vorhandenen Filterbutton/-panelzustand. Kein neuer Requestlock, keine neue Chip-spezifische Loadingautomatik: Die bestehenden Such-/Loadingmechanismen bleiben maßgeblich. Wiederholung nach Fehler funktioniert; ein entfernter Chip erhält keinen neu erfundenen Fokuscallback. Moderne Disabled-/ARIA-Disabled-/Loadingzustände verwenden den B2-Capture-Schutz.

Bestehende Readonly-/View-only-Filterchips bleiben wie vorher bedienbar; sie ändern ausschließlich Listenfilter. Keine implizite Schreibfreigabe aus modernem Aussehen. ACL, Tenant-/Parent-Child-Sharing und SaaS-Entitlements verbleiben in Controller/Query/Server. Die Scopeprüfung wählt die Präsentation und blendet außerhalb des Scopes keine vorher erlaubte Aktion aus. Kein neuer Daten-/Berechtigungsweg.

## Dateien, Inventur und Ausschlüsse

Laufzeitdateien ausschließlich `application/views/helpers/Toolbar.php`, `js/global.js`, `languages/{de_DE,en_US}/default/toolbar.php`. `DEEC_Form`, Button-Helper, Module, Controller, Layouts, CSS, Icons und Datenbank bleiben unverändert.

Die [Gesamtinventur](button-system-inventory.md) und ihre Werkzeuge unterscheiden jetzt die 12 SVG-Formularquellen vom einen zusätzlich SVG-erzeugenden Helper. **45 Formularfelder, 84 Controller-/Feldpaare, 13 primäre Gruppen und 109 Controls der inaktiven Neun-Seiten-Fixture bleiben unverändert**; die alten JSON-Schlüssel wurden vor/nachher verglichen. Der neue Helperweg erzeugt bedarfsabhängig einen Chip pro aktivem Filter in 22 logischen Kontexten und wird separat gezählt. 243 DEEC-Konfigurationsstellen, 147 lexikalische Buttonanfänge, 79 Buttonklasselinks, 25 alte Helperaufrufe und 13 ZF1-Submits bleiben ebenfalls unverändert. Keine Fortschrittsprozente zwischen diesen Einheiten.

Lösch-, Dokument-, Mail-, Zahlungs-, Positions-/Pin-/Sortier-, komplexe Massen-, Shop-/CMS-/Vendor-, gespeicherte Inhalts- und allgemeine Submitaktionen sind ausgeschlossen. Sammelauswahllinks im Filterpanel, Keyword-Clear, Alert-/Modalclose und MultiForm sind keine zusätzliche Migration. Die alten 25 Positionsparameter-Aufrufe bleiben Legacy. Durch den Wechsel eines Unicode-× entsteht kein neu ungenutztes PNG. Bisher bestätigte Inaktivität des alten Drawercalls, PNG-Duplikat, fünf SVG-Reservegeometrien und unsichere Asset-/Helperkandidaten bleiben wie dokumentiert; keine Datei gelöscht.

## Tatsächlich ausgeführte Tests

| Prüfung | Ergebnis |
| --- | --- |
| Baseline vor Änderung | B2–B11-PHP bestanden; Quell-/Runtimeinventur unter `/tmp/dewawi-b12/before-*-inventory.json` gesichert. |
| `php tests/ui/filter-buttons.php` | PASS: **3.070** gezielte Checks plus eingebundene 165 B2-/2.259 B5-Checks. Alle 22 logischen Kontexte × DE/EN × Full/Plain, reale Form-/Helper-/Layoutausgabe, native Attribute, Labels/SVG, Panelbeziehung, AJAXfragmente, Defaults, Readonly/View-only, Array/Datum/Long-/Escapingfälle; erzwungenes SVG in ausgeschlossenen Modulen und Unterklassen bleibt Legacy. |
| `python3 tests/ui/filter-buttons.py` | PASS: **1.564** Assertions. 13 Gruppen × DE/EN × 320/390/768/1280 px, lange Werte je Gruppe, vier repräsentative Select-/Plaingruppen bei allen Breiten, Array-/Datumsreset und negative Legacy-Index-/Popupkontexte. Reale PHPausgabe/CSS/jQuery/global.js; AX-Namen, SVG-Geometrie, Textgap, Größen, Hover/Fokus/Tab/Enter/Space, Disabled/ARIA-Disabled, beide vorhandenen Loadingarten samt Node-Restore, Cookiepfad/Defaultwerte, exakte Searchrequests/Parent/Set-ID, Fehler/Retry, dynamische use-Klicks, Header-/Panelerhalt und nur scoped Popuprefresh. |
| `php tests/ui/button-icons.php` | PASS: **651** SVG-Prüfungen plus B2. Die historische PNG/SVG-Attributgleichheit akzeptiert ausschließlich die zwei beabsichtigten Disclosureattribute des SVG-Filterbuttons und prüft sie zusätzlich in sechs Profilen. Alle alten Attribute bleiben streng verglichen; PNG erhält keine neuen Attribute. |
| `php tests/ui/{inquiry-buttons,toolbar-buttons,tasks-toolbar,remaining-toolbars,apply-buttons,list-buttons,mobile-list-actions}.php` | PASS: 697 / 2.259 / 1.428 / 27.105 / 1.459 / 7.374 / 256 plus eingebundene Baselines. |
| `python3 tests/ui/buttons.py`; `--svg --audit`; `inquiry-buttons.py`; `apply-buttons.py` | PASS: 169 / 204 / 796 / 302 Assertions, einschließlich PNG, lokaler SVG-CSP, Forced Colors, Zoom, iframe-/Modal-/Apply-/Auto-Saveverträgen. Inquiry und Apply nach dem Popupfix erneut ausgeführt. |
| `python3 tests/ui/toolbar-buttons.py`; `--tasks`; `--remaining` | PASS: 1.000 / 524 / 3.247 Assertions. Gemeinsame Toolbar zusätzlich nach dem Popupfix erneut geprüft. |
| `python3 tests/ui/list-buttons.py`; `--mobile` | PASS: 1.470 / 822 Assertions; B10-Rownavigation und B11-Touch-/Zeilen-/Header-/Scrollgrenzen unverändert. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 Checks bei 390/1280 px, ausschließlich abgefangenes I/O; ausgeschlossene Dokument-/Mail-/Positions-/Auto-Savekontexte. |
| `php tests/security/{permissions,admin-access,tenant-access,bootstrap-auth}.php`; `php tests/lists/summary.php` | PASS: 415 / 68 / 269 / 29 Fälle bzw. Footer-/Summenprüfung. Keine neue Sicherheitslogik. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler erneut bestätigt: 370 Checks erfolgreich, danach `Sales_Service_DocumentRevisionService` fehlt in `sales/views/helpers/Finish.php:12`; Abbruch `notifications.php:211`. Keine B12-Änderung dieser Dateien. |
| Inventur / Syntax / Diff | Beide Inventurwerkzeuge tatsächlich ausgeführt und Einheiten verglichen; PHP-Lint aller sieben geänderten/neuen PHP-Dateien, Python-AST beider Pythondateien, relative Berichtlinks und `git diff --check` bestanden. Globales JavaScript wird in Chromium tatsächlich ausgeführt. |

Der neue PHP-Test erweitert ausschließlich die bestehende Memoryfixture um Filterwerte/-optionen und den bereits vorhandenen Inquiry-Formtyp; kein neuer Buttonrenderer. Batchausgabe vermeidet unnötige wiederholte Testbootstraps. Die Browserseiten verwenden kanonische Modul-/Controllerpfade, damit die echten Cookiepfade geprüft werden; jede Fallnavigation beginnt mit leeren **lokalen Fixturecookies**. Alle Geschäftsrequests werden strikt auf synthetische Searchantworten abgefangen. Keine Datenbank-/SMTP-/Produktionsverbindung, keine produktive Session, keine echte Filterabfrage oder Statusänderung. `tests/lists/footer-http.py` wegen produktiver Konfiguration/Session ausdrücklich nicht ausgeführt.

## Verbleibende Freigaben und nächste Prioritäten

Technische Regressionen bestanden; keine fachliche/visuelle Gesamtfreigabe behauptet. Repräsentative Fokusbilder unter `/tmp/dewawi-b12-<contacts-contact|tasks-task|admin-country|statistics-customer>-<320|390|1280>.png`; Kontakt/320, Admin/390 und Tasks/1280 gesichtet. Die vorhandenen globalen Header-/Menü-/Report- und Lokalisierungsprobleme bleiben sichtbar und sind keine neuen Chip-/ARIA-Namen. Lange Chips umbrechen innerhalb der bestehenden Toolbarbreite.

B13 empfohlen: separate Qualitätskorrektur von 320-px-Headerüberlauf und technischen DE/EN-Keys; danach manuelle reale Testinstallation mit langen Daten, Rollen/Tenants/Sharing/SaaS, Touchgerät, Firefox/Safari und NVDA/VoiceOver. Das Filter-Disclosure-Gate ist nur für die neuen Scopes geschlossen; Legacyfilter nicht pauschal freigegeben. Standard-Delete erst nach eigener Confirm-/Rechte-/Tenant-/Kaskaden-/Fehlerabnahme, Positions-/Setaktionen nach ihrem eigenen Datenvertrag. Keine weitere Migration automatisch begonnen.

Nur eigene B12-Dateien lokal committen. Keine Datenbankmigration, Änderungen gespeicherter Inhalte, produktiven Eingriffe, Assetlöschungen, Merge, Deployment oder Push.
