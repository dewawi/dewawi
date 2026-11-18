# B6 – Tasks-Standardtoolbar

Stand: 18.11.2026. Geprüft: lokaler B2–B5-Stand bis `aaca1571`, `AGENTS.md`, [Bestandsaufnahme](buttons-icons-analysis.md), [Button-Grundlage](buttons-foundation.md), [SVG-Katalog](button-icons.md), [Inquiry-Pilot](inquiry-buttons.md) und [gemeinsame Toolbars](toolbar-buttons.md). Der unversionierte Analysebericht bleibt unverändert und außerhalb des Commits.

Fortsetzung: [B7 – verbleibende Standardtoolbars](remaining-toolbars.md) konsolidiert die geprüften Admin- und Statistikgruppen. Die nachstehenden B6-Ergebnisse und Tasks-Kontextgrenzen gelten unverändert.

## Entscheidung und migrierte Controls

`Tasks_Form_Toolbar` nutzt bereits `DEEC_Form`. B6 ergänzt nur die vorhandenen Präsentationsmetadaten und erweitert die begrenzte Symbolblockbedingung in den zwei internen Layouts. Keine neuen Renderer, Icons, CSS-Regeln, JavaScript-Handler, Bibliotheken oder allgemeinen Formular-/Tabellenänderungen.

Opt-in ausschließlich für die **exakte Klasse** `Tasks_Form_Toolbar`, Modul/Controller `tasks/task` und Aktionen `index`, `search`, `select`, `edit`, `view`. Die bestehende geschützte Methode `getCurrentRequest()` ermöglicht einen weiterhin unveränderten Legacy-Aufruf ohne MVC. Lokal gibt es keine davon erbenden Tasks-Toolbar-Klassen; eine synthetische Unterklasse sichert den Ausschluss künftiger Spezialklassen ab.

| Control | Bisher | B6 | Unverändert |
| --- | --- | --- | --- |
| `add` | Text + PNG | Text + `add`-SVG | `TOOLBAR_NEW`, `button`, Name, Klasse, Auswahl-/Addroute einschließlich vorhandener Parameter. |
| `edit` | Text + PNG | Text + `edit`-SVG | `TOOLBAR_EDIT`, bestehende Einzelauswahl und Ziel-ID. |
| `copy` | Text + PNG | Text + `copy`-SVG | `TOOLBAR_COPY`, Auswahl/Datensatzkontext, Request-/Loading-Sperren. |
| `filter` | Text + PNG | Text + `filter`-SVG | `TOOLBAR_FILTER`, vorhandenes Filterpanel. |
| `reset` | Text + PNG | Text + `reset`-SVG | `TOOLBAR_RESET`, Filterdefaults, Suche und Cookiekonventionen; kein nativer Form-Reset. |
| `clear` | PNG, Icon-only | explizites `CLEAR`, `iconOnly=true`, `clear`-SVG (B3-Alias auf Close) | Übersetzter zugänglicher Name „Leeren“/„Clear“, `rel=keyword`, Name, Klassen, `data-action`. |

Alle sechs verwenden ausdrücklich `iconFormat=svg`, bestehende Default-/md-Präsentation, 16-px-Icons und 8-px-Abstand. Bei schmaler Ansicht/grobem Pointer mindestens 44 px Controlhöhe und 20-px-Icons. Labels bleiben sichtbar; nur Clear bleibt Icon-only. Dekorative SVGs sind ausgeblendet für Assistenztechnik und nicht separat fokussierbar. Es werden keine leeren Iconplätze oder unbekannten Symbolnamen erzeugt.

Kopf- und Fußtoolbar verwenden dieselbe bestehende Formularinstanz und die bisherigen `Toolbar`-/`ToolbarBottom`-Helper. Im Index sind sechs SVG-Controls oben und drei wiederverwendete Standardaktionen unten sichtbar. Edit/View zeigen nach bestehender Konvention Copy im Kopf. Select zeigt aus dieser Gruppe nur Clear; die Tasks-Standardtoolbar besitzt bisher keinen Apply-Eintrag. B6 fügt ihn nicht hinzu und ändert die bestehende Auswahlansicht nicht.

## Aufrufstellen und ausdrückliche Grenzen

| Fundstelle / Gruppe | Prüfung und B6-Status |
| --- | --- |
| `Tasks_TaskController::buildIndexView()` | Reale Listen-/Toolbarvorbereitung über `DEEC_Controller_Action::buildListView`; keine Controlleränderung. |
| `DEEC_Controller_Action::{getToolbarClass,getEditToolbar,searchAction,selectAction,viewAction}` | Modulklasse, Optionsauflösung, Datensatzattribute, Search ohne Layout, Select mit plain-Layout; Präsentation aktiviert nur im genannten Kontext. |
| `Tasks_TaskController::getAction()` | Direkte zweite Instanziierung zur Optionsausgabe; Aktion `get` bleibt Legacy. Keine Options-/JSONänderung. |
| `Tasks_TaskController::addAction()` / `task/edit.phtml` | Angebots-Follow-up mit `sourcequote`, nativer `create_followup=1`-Submit und Auto-Save-Opt-out. Aktion `add` bleibt ausgeschlossen; kein Umbau dieses Formularflows. |
| `Tasks_Form_ToolbarInline`, `Tasks_Model_List_Tasks` | View/Edit/Copy/Delete, Pin/Statusspalten und Select-Sonderfall sind Tabellenaktionen, bleiben unverändert. Keine zusätzliche Anzeigen-Aktion in der Kopftoolbar erfunden. |
| `Tasks_Form_ToolbarPositions`, `Tasks_PositionController`, `position/index.phtml` | Parent-/Set-/Positionsaktionen, Autocomplete und Dialoge bleiben Legacy; kein neues Icon-/Routenmapping. |
| `Tasks_Form_Task`, `task/edit.phtml`, `task/view.phtml` | Formularfelder, Status, Verantwortliche und Submit/Auto-Save bleiben unverändert. Keine neue Formularaktion migriert. |
| `Application_Form_Toolbar`, Dashboard, Kontakte-History | Andere Renderwege, keine Tasks-Standardtoolbar; B6 verändert sie nicht. |
| Dynamische Ausgabe | Das echte `Application_Plugin_ListResponse` liefert weiterhin `content`/`toolbar`; Listenfooter wird ersetzt, Haupttoolbar bleibt bestehen. Fragmente erhalten nur SVG-Referenzen und keinen zweiten Symbolblock. |

`delete`, Statusselect und sämtliche Filter-/Datumsfelder bleiben in ihrer bisherigen Ausgabe. Andere Module und alle Dokument-, Mail-, Zahlungs-, Shop-/CMS- und Drittanbieteraktionen sind unverändert. Haupt- und Popup-Layout geben den vorhandenen Symbolblock einmal zusätzlich für den freigegebenen Tasks-Kontext aus; B4/B5-Kontexte bleiben erhalten, öffentliches `site.phtml` bleibt unangetastet.

## Rechte, Mandanten und Datenvertrag

Wichtiger bestehender Vertrag: `tasks` ist aktuell **nicht** in `DEEC_Permission::MODULES` registriert. `DEEC_Form::canRenderAction()` erhält für nicht registrierte Module die Legacy-Sichtbarkeit. B6 führt weder eine Tasks-Berechtigungsspalte noch neue Rechte-/Entitlementregeln ein und interpretiert `hasModule()` nicht als SaaS-Buchung. Ein Nachweis granularer Tasks-Rechte lässt sich daraus nicht ableiten; das bleibt eine eigene fachliche/architektonische Aufgabe.

Tests vergleichen diese Sichtbarkeit mit der unveränderten Referenz und prüfen zusätzlich ausdrücklich geschützte andere Ziele mit fehlenden bzw. fremden Benutzerrechten. Bestehende Auth-/ACL-, Entity-/Parent-/Child-/Tenantprüfungen, Statusvalidierung und Zuweisungscode werden nicht verändert. Die vorhandene Mandantensuite und der Follow-up-Test sichern auch Tasks-/Quote-/Kundengrenzen ab. Dies ist kein vollständiger produktiver Rollen-/Entitlementtest.

Die neue Darstellung behält alle bisherigen HTML-Attribute außer den bewusst ergänzten Präsentationsklassen: native Buttontypen, Name/Value, `data-*`, URLs und Recordtargets. Texte, ARIA-Namen und bisherige Funktionsklassen werden gegen die Legacy-Referenz verglichen. Statusdefaults `100`, ausgewählte Zustände `100…104`, Sortierung, Datumsauswahl und alle Nicht-Button-Konfigurationen bleiben gleich. Keine gespeicherten oder historischen Werte werden berechnet/geändert.

## Tests und tatsächliche Ergebnisse

Vorher/Nachher wurden **350 synthetische Tasks-Konstruktor-Kontexte** verglichen: sieben Modul-/Controllerkontexte × zehn Aktionen × fünf Klassen (Standard, synthetische Unterklasse, Inline, Positions-Toolbar, Taskformular). Genau fünf Kontexte ändern ausschließlich die sechs vorgesehenen Präsentationen; 345 bleiben unverändert. Alle 480 B5-Konstruktor-Konfigurationen bleiben zusätzlich bytegleich. Das sind keine 350 authentifizierten HTTP-Seiten.

| Prüfung | Ergebnis |
| --- | --- |
| `php tests/ui/tasks-toolbar.php` | PASS: 1.428 neue Tasks-Checks, zusätzlich die eingebundenen 165 B2-/2.259 B5-Checks. Metadaten, SVGs, Labels, alte Klassen/Attribute, Status-/Datumsdefaults, Layouts, Index-/Select-JSON, Readonly, Legacy-Sichtbarkeit, geschützte Ziele, fremde Benutzerrechte und ausgeschlossene Aktionen/Klassen. |
| `python3 tests/ui/toolbar-buttons.py --tasks` | PASS: 524 Browserassertions. DE/EN × Index/Hauptlayout, Select/Popup, Edit/Hauptlayout, View/Hauptlayout × 390/768/1280 px (24 Kombinationen), zusätzliche Readonly-Seiten. Kopf/Fuß, AX-Namen, Fokus, Hover, Disabled, Loading, Tab/Shift-Tab/Enter/Space, SVG-/use-Klicks, Auswahl, Copy-Sperre über Kopf/Fuß, richtige Ziele und dynamischer Footer. Zusätzlich Statusfilter, Custom-Datumsbereich, Von-Datum, Resetdefaults, Headerstatus, Formularstatus, Verantwortlichen-Auto-Save und Opt-out. |
| `php tests/ui/button-icons.php`, `python3 tests/ui/buttons.py`, `python3 tests/ui/buttons.py --svg` | PASS: B2/B3 weiterhin 165 + 645 PHP-, 169 PNG-/194 SVG-Browserchecks einschließlich strenger CSP, iframe und Forced Colors. |
| `php tests/ui/inquiry-buttons.php`, `python3 tests/ui/inquiry-buttons.py` | PASS: B4 weiterhin 697 PHP-/796 Browserchecks. |
| `php tests/ui/toolbar-buttons.php`, `python3 tests/ui/toolbar-buttons.py` | PASS: B5 weiterhin 2.259 PHP-/928 Browserchecks; Browserbaseline auch vor B6 gesichert. Standardaufruf bleibt auf Kontakte/Artikel begrenzt; Tasks ist ausdrücklich ein zusätzlicher Testmodus. |
| `php tests/security/permissions.php`, `tenant-access.php` | PASS: 415 / 269 Fälle, einschließlich Tasks-Parent-/Childfälle; negative `NO_PERMIT`-Ausgaben erwartbar. |
| `php tests/sales/quote-monitoring.php` | PASS vor/nach B6: 293 Fälle, einschließlich Tasks-Follow-up, Defaults, Verantwortliche, Fristen, unzulässiger Zugriffe und Mandantengrenzen. Keine echte Datenbank. |
| `php tests/security/contact-inquiries.php`, `form-input-validation.php`, `inquiry-dynamic-form.php` | PASS: 46 / 20 / 31 Fälle. |
| `php tests/lists/summary.php` | PASS: zentrale Listenfooter-/Summenprüfung ohne Datenbank. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 bestehende Dialog-/Mail-/Dokument-/Auto-Save-Browserchecks bei 1280/390 px. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler: 370 Checks erfolgreich, danach `Sales_Service_DocumentRevisionService` nicht gefunden in `sales/views/helpers/Finish.php:12`; Abbruch der Sales-Fixture in `tests/security/notifications.php:211`. Bereits vor B6 dokumentiert; keine betreffende Datei geändert. |
| Standalone-Probe | PASS: `Tasks_Form_Toolbar` ohne Zend/MVC geladen; keine SVG-Metadaten, bisheriger Legacy-Vertrag bleibt. |
| Syntax / Diff | PHP-Lint für alle fünf geänderten/neuen PHP/PHTML-Dateien, Python-AST ohne Bytecode und `git diff --check` bestanden. |

Die B5-Fixture/der Browserrunner werden wiederverwendet, keine zweite Toolbar-Ausgabe implementiert. Nur für Tasks werden synthetische Statusoptionen und zwei reale `Tasks_Form_Task`-Felder (`state`, `responsible`) gerendert. Sie prüfen die bestehenden Eventverträge; die ganze Geschäfts-/Positionsview wird nicht als vollständiger Workflow nachgebildet. Kategorien/Benutzer/Datensätze sind Fixtures. Alle AJAX-/Copy-/Save-Aufrufe und Navigation werden abgefangen; kein produktiver Request oder echter Schreibvorgang. Die Datenbereitstellung der Controller und die produktive Optionsdatenbank werden nicht aufgerufen.

Nicht ausgeführt: produktive/authentifizierte E2E-Aktionen oder `tests/lists/footer-http.py`, das mit Produktionskonfiguration und echter Adminsession arbeitet. Keine isolierte Datenbank dafür nachgewiesen. Die Rendering-/Footer-Verträge werden stattdessen mit den isolierten vorhandenen/neuen Suites geprüft.

## Sichtprüfung, offene Freigaben und nächster Block

Screenshots unter `/tmp/dewawi-b6-tasks-<index|select|edit|view>-<390|1280>.png` aufgenommen; Desktop-Kopf-/Fußtoolbar und mobile Edit-Ausgabe gesichtet. SVGs, Textabstände, sichtbarer Fokus und Touchmaße passen; Legacy-Löschen sowie native Statusfelder bleiben bewusst in ihrer bisherigen Darstellung. Die Bilder stammen aus dem abgefangenen Testworkflow, nicht aus produktiven Aufgaben.

Offen bleiben fachliche/visuelle Produktfreigabe, vollständige Taskgeschäftsview, reale Mandanten-/Rollen-/Theme-/Entitlementabnahme, physische Touchgeräte und Safari/Firefox/Screenreader. Bestehende mobile Gesamtseiten-/Menüdichte und globale Kataloglücken werden nicht durch B6 verändert. Die Tests behaupten keine vollständige Barrierefreiheits- oder Sicherheitskonformität.

Nächster sinnvoller, gesondert zu beauftragender Block nach Abnahme: ausschließlich `filter`/`reset` von `Statistics_Form_Toolbar` mit ihren eigenen Datums-/Reportkontexten; Export bleibt separat. Keine automatische Fortsetzung und keine Aufweichung der Tasks-Kontextgrenzen.

Geänderte Laufzeitdateien: `application/modules/tasks/forms/Toolbar.php`, `application/layouts/scripts/{layout,plain}.phtml`. Tests: bestehende `tests/ui/toolbar-buttons.php`/`.py` um Tasks ergänzt, neuer `tasks-toolbar.php`. Dokumentation: dieser Bericht und B5-Fortsetzungslink. Keine Änderungen am gemeinsamen Renderer, Iconkatalog, CSS, JS, Controllern, Datenbank oder gespeicherten Inhalten. Keine Migration/manuelle Datenbanknacharbeit nötig. Analysebericht weiterhin unversioniert, SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`.

Kein Push, Merge oder Deployment; ausschließlich eigene B6-Dateien lokal committen.
