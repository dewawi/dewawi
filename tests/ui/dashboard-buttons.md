# B15 – Dashboard-Standardicons über den vorhandenen Button-Helper

Stand: 19.11.2026. Ausgangs-HEAD `c6325897` (separates Übersetzungsaudit), davor B14 `d68f7a8c`. Arbeitsverzeichnis zunächst sauber. Grundlage: `AGENTS.md`, B1–B14, insbesondere [Gesamtinventur](button-system-inventory.md), [B14-Symbolversorgung](svg-symbol-integration.md) und [B13-Abnahmegrenzen](button-release-audit.md). Keine Datenbank-/Inhaltsänderung, kein produktiver Zugriff, Push, Merge oder Deployment.

Während B15 kamen fremde Sprach-/JS-/Shopänderungen hinzu; die unabhängige Übersetzungsbasis wurde zwischenzeitlich als `00a99d88` committed. Sie wird durch B15 weder geändert noch mitgestaged. Reproduzierbare isolierte Abschlussprüfungen verwenden `/tmp/dewawi-b15/validation` mit `c6325897` plus eigenem B15-Anwendungs-/Testcode; maßgebliche Logs unter `/tmp/dewawi-b15/validated/`. Die konkrete Dashboard-PHP-/Browserintegration wurde zusätzlich im aktuellen gemeinsamen Workspace geprüft. Die Layoutmesswerte unten gehören ausdrücklich zur festen Ausgangsbasis, nicht zum gleichzeitig bearbeiteten Übersetzungsstand.

## Gewählter Block und tatsächliche Dashboard-Aufrufstellen

Die drei direkten Editbuttons im Dashboard waren leere HTML-Buttons mit CSS-PNG-Hintergrund. Sie hatten bereits eigene Ziele und denselben bestehenden JavaScript-Vertrag. B15 ersetzt ausschließlich diese drei Erzeugungsstellen durch `getHelper('Button')->renderButton()`; dieser delegiert unverändert an `DEEC_Form::renderButtonHtml()`. Kein neuer Renderer, keine automatische Aktivierung in `Application_Form_Toolbar`, kein globales Klassenmapping.

| Dashboardgruppe in `application/views/scripts/index/index.phtml` | Bisheriger Weg / Ausführungsvertrag | B15 |
| --- | --- | --- |
| Kontakte | Direktes `button`, Klassen `dw-btn dw-btn--icon edit`; `data-action=edit`, eigene ID, `contacts/contact`. Originalcontroller lädt Daten nur bei Contact-Viewrecht. | Explizites bestehendes Edit-SVG, Icon-only, `sm`, bisheriges übersetztes `TOOLBAR_EDIT`. |
| Artikel | Derselbe direkte Weg, `items/item`, eigene ID; Item-Viewrecht und bestehender Modulowner. | Dieselbe Präsentationsroutine, eigener unveränderter Target. |
| Lagerbewegungen | Derselbe direkte Weg, `items/ledger`, eigene Ledger-ID; bestehende Item-Viewprüfung und dessen Mandantenscope. | Nur Navigation/Präsentation migriert. Keine Bestandsbuchung, Ledgerformular- oder Positionsmigration. |
| Aufgaben | Ein `Application_Form_Toolbar::renderElement('edit')` je Row. | Unverändert. |
| Angebote, Aufträge, Rechnungen, Angebotsanfragen, Bestellungen | Je drei `renderElement('edit'/'copy'/'pdf')` aus derselben Application-Toolbar. | Alle fünf Dokumentgruppen unverändert. |
| Acht „Alle anzeigen“-Links und zwei bedingte Chart-Links | Normale Navigationslinks ohne Buttonklasse; Chartdaten nur mit bestehendem Statistikrecht. | Unverändert, keine künstliche Buttonumwandlung. |
| Kalenderglocke im Menüpartial | Eigenständiges Inline-SVG und eigene Handler. | Unverändert, kein B3-Symbolverbraucher. |

Damit sind **drei direkte Quellstellen in einer View** migriert, nicht 27 Formularfelder. **16 Dashboard-Toolbaraufrufstellen** (Aufgaben: eine; fünf Dokumentgruppen: je drei) bleiben Legacy. `Application_Form_Toolbar` selbst bleibt mit fünf Feldkonfigurationen unverändert; View/Delete werden im Dashboard nicht aufgerufen.

### Warum Aufgaben-/Dokumentbuttons einschließlich PDF ausgeschlossen bleiben

Die Application-Toolbar definiert `name`, Klasse, Typ und ein technisches bzw. allgemeines ARIA-Fallback. Die Dashboardaufrufer übergeben aber weder `data-action` noch eigene `data-id/module/controller`; die Elemente haben auch keine Toolbargruppe, die `data-action` automatisch ergänzen würde. `global.js` bindet den normalen Dispatcher an `[data-action]`, nicht an `button[name=pdf]`. Ohne expliziten Target würde die Auflösung außerdem auf den Dashboardkontext bzw. eine globale Auswahl zurückfallen. Isoliertes Hinzufügen dieser Hooks wäre eine fachliche Verhaltenskorrektur und wird nicht mit einer SVG-Darstellungsänderung vermischt.

Auch `pdf` ist kein semantischer B3-Key. `download` ist vorhanden, aber eine neue ungeprüfte PDF-Zuordnung würde den fehlenden Ausführungsvertrag nicht klären. Alle PDF-/Edit-/Copybuttons dieser Toolbar behalten ihr bisheriges Markup bytegenau. Der Befund ist eine **bestehende Dashboard-Integrationslücke**, keine durch B15 verursachte Regression und kein Fehler im SVG-Katalog. Dokumentlisten und deren echte Download-/Navigationshandler bleiben separat geschützt.

## Erhaltene Attribute, Handler und Rechte

Die drei neuen Helperaufrufe behalten exakt `type=button`, `data-action=edit`, eigene `data-id`, `data-module`, `data-controller` und den bisherigen übersetzten `aria-label`. Alle bisherigen Klassentokens bleiben; hinzu kommen ausschließlich `dw-btn--control`, `dw-btn--default`, `dw-btn--sm`. Keine neuen `id/name/value/href/rel/onclick/title` und kein Formular-Submit. Das dekorative SVG ist `aria-hidden=true`, `focusable=false` und referenziert ausschließlich `#dw-icon-edit`.

Vorher geprüft: `global.js::bindActions`, `runAction`, `resolveSelection`, `getButtonTarget`, `actions.edit`, Capture-Sperre, `setButtonLoading`, `beginActionRequest`, sowie `settings.js` und das nicht im Standardlayout geladene `trash.js`. Die aktiven Selektoren bleiben erhalten. Durch B15 keine JS-Datei, Eventdelegation, ACL, Query, Controller- oder SaaS-/Sharingentscheidung geändert. Die parallel bearbeiteten JS-Übersetzungstexte gehören nicht zum Buttonauftrag.

`IndexController::indexAction()` begrenzt Kontakte/Artikel/Bewegungen weiterhin anhand der vorhandenen Viewrechte; Kontakte/Artikel/Ledger verwenden weiter ihre bisherigen Mandantenowner. Eine rein lesende Rolle sah diese direkten Editbuttons bereits vor B15. B15 verändert diese Sichtbarkeitsregel nicht und erteilt kein Editrecht; die Zielcontroller schützen die Aktion weiterhin serverseitig. Die bestehenden Rechte-/Mandanten-/Parent-Child-Baselines ergänzen den Darstellungsnachweis. Der Memoryadapter der Seitenfixture wertet SQL nicht vollständig aus und beweist keine reale Mandanten-/SaaS-Konfiguration.

Unveränderte lokale Iconquelle: Lucide 0.468.0, bestehende 15 semantische Keys/14 Geometrien, ISC und Feather-MIT gemäß `images/icons/README.md`. Keine neuen Symbole, PNG-Entfernung, CSS-/Layoutänderung oder Netzabhängigkeit. B14 liefert je Vollseite/iframe einmal den Symbolblock; Search-/AJAXfragmente verwenden den vorhandenen Dokumentblock.

## Vorher/Nachher und Zählmethodik

| Vollständige Original-MVC-Ausgabe mit synthetischen Daten | Vor B15 | B15 |
| --- | --- | --- |
| Reguläres Dashboard, 25 Kontakte + ein Artikel + Angebotsentwurf | 28 Buttons, null moderne Controls/`use`, eine eigenständige SVG-Glocke, 14 passive Definitionen. 26 Edit-PNGs und ein PDF-PNG. | Weiterhin 28 Buttons; 26 moderne Edit-Controls/Referenzen, 27 SVG-Buttons einschließlich Glocke, 14 Definitionen, keine ungelösten Referenzen. PDF bleibt PNG. |
| Testprofil mit allen acht Tabellen: zusätzlich Ledger, Aufgabe und allen fünf Dokumentgruppen | 44 Buttons, null moderne Controls; 27 direkte Edit-PNGs, 16 Aufgaben-/Dokumentbuttons, eine Glocke. | Weiterhin 44 Buttons; 27 moderne Edit-Controls, 16 unveränderte Toolbarbuttons und eine Glocke. |
| Ohne Contact-Viewrecht / ohne Item-Viewrecht | Contactgruppe fehlt / Item- und Ledgergruppe fehlen. | Weiterhin zwei / 25 moderne Controls im vollen Testprofil; keine neue Sichtbarkeit. |
| Reine Contact-/Item-Leserechte | Direkte Editbuttons bereits sichtbar, keine Editfreigabe durch Präsentation. | Dieselben 27 direkten Controls, unveränderte Ziele; kein neues Recht. |

Das sind datensatzabhängige Fixture-DOM-Mengen, keine Produktions- oder Quellstellenzahlen. `getLatest*`-Limits werden vom Memoryadapter nicht als echte SQL-Selektion simuliert. Die Benutzerzahlen aus B14 sind deshalb weiterhin kein identischer Datenbestand.

Reproduzierbare Sourceinventur: 1.074 Dateien; **144 statt 147 Raw-Buttonanfänge in 78 statt 79 Dateien**, weil drei Literalstellen durch Helperaufrufe ersetzt wurden. Weiterhin 243 Formular-Konfigurationen in 49 Dateien, 45 SVG-Felder in zwölf Formularquellen, 84 Controller-/Feldpaare und 13 Konstruktorkontextgruppen; vollständiges Runtime-JSON wertgleich zur B14-Baseline. Der Sourcescanner erfasst jetzt auch `.phtml`: 14 SVG-produzierende PHP/PHTML-Dateien = zwölf Formulare + ein Helper + eine View. Das zusätzliche `svg_view_sources`-Feld verhindert die bisherige Blindstelle bei direkten View-Aufrufen. Keine Prozentwerte aus diesen unterschiedlichen Einheiten.

## Tatsächlich ausgeführte Tests und Abnahmegrenzen

Alle Artefakte liegen lokal unter `/tmp/dewawi-b15/`; sie enthalten ausschließlich synthetische Daten. Tests starten keine produktive Session und laden keine Datenbankkonfiguration. Das bestehende PHP-Fixture ist außerhalb CLI/CLI-Server gesperrt; Schreibmethoden bleiben gesperrt. Die Ergänzungen sind ausschließlich Testdaten, Rollenprofile und ein optionaler Snapshot-Viewpfad für Originalcontroller-Vergleiche.

| Prüfung | Ergebnis |
| --- | --- |
| Neue PHP-Regression `php tests/ui/dashboard-buttons.php` | **PASS: 3.620 Assertions**; vor Implementierung erwarteter roter Lauf wegen fehlender gemeinsamer Präsentation. DE/EN, alle Gruppen, View-/Negativrollen, native Typen, Hooks, übersetzte Namen, unveränderte Legacy-/PDFbuttons, SVG-Auflösung und Vergleich zu den vorhandenen Rowlinks. |
| Neuer Browsermodus `python3 tests/ui/button-page-integration.py --dashboard --compare-dashboard=/tmp/dewawi-b15/legacy-views --output=/tmp/dewawi-b15/validated/dashboard-pages` | **PASS: 1.614 Assertions**, auch im isolierten Abschlusslauf; 40 DE/EN-/320/390/768/1280-Profile plus vier echte Select-iframes. Zehn vollständige HTML-Vertragsvergleiche gegen das Originaltemplate aus `git show c6325897:…`, zusätzlich zehn Prüfungen der tatsächlich alten PNG-Ausgabe. |
| B14-Vollseitenbaseline vor B15 | **PASS: 828 Assertions** ohne optionalen Layoutvergleich; 88 Seiten-/Breitenprofile plus vier iframes. Der historische B14-Wert 850 enthielt zusätzlich 22 Layoutmarkupvergleiche. |
| B14-Vollseitenbaseline nach B15 | **PASS: 852 Assertions**, dieselben 88 Profile/vier iframes. 24 zusätzliche bestehende Pixel-/Loading-/Fokusassertions werden nun auch für das Dashboard ausgeführt. Dashboarderwartung gezielt auf 26 moderne Edits aktualisiert; Dokument-PDF-Negativprüfungen bleiben bestehen. |
| Zusätzliche Integration im gemeinsamen Workspace bei HEAD `00a99d88` | **PASS: 3.620 PHP-/1.614 Browserassertions** mit dem neuen Übersetzungsloader und den dort vorhandenen weiteren Änderungen. Währenddessen änderten sich zwei fremde Sprachdateien; deshalb ersetzt dieser ergänzende Lauf nicht die reproduzierbare isolierte Baseline. Hashprotokoll: `current-code-result.json`. Keine fremde Änderung übernommen. |
| Zusätzliche Vorher/Nachher-Layoutmessung | **PASS: acht DE/EN-/Breitenpaare** gegen nachweislich altes Template. Dokumentbreite, Tabellenbreite, Actions-Spalten und vorhandenes Tabellenscrolling unverändert. Rohdaten/Bilder unter `layout-probe/`. |
| PHP B2–B12 plus Sicherheit vor/nach | **PASS: 19 bestehende Suites**, zusätzlich Dashboard; nur der unten benannte unabhängige Notificationsfehler unverändert. Isolierter Abschlusslauf: 21 Befehle, 20 erfolgreich, ein bekannter Fehler. UI-Baselines buttons/icons/inquiry/toolbar/tasks/remaining/apply/list/mobile/filter: 165 / 651 / 697 / 2.259 / 1.428 / 27.105 / 1.459 / 7.374 / 256 / 3.070 Assertions. Rechte/Admin/Tenant/Bootstrap: 415 / 68 / 269 / 29; Contact-Inquiry/Formvalidation/DynamicForm: 46 / 20 / 31; Quote-Monitoring 293 und zentrale Summenprüfung. Exits/Logs: `validated/php-results.json`. |
| Bestehende B2–B13-Browserbaselines | **PASS: alle zwölf Befehle, jeweils Exit 0**, auch vollständig im isolierten Checkout. Buttons 169; SVG/audit 204; Inquiry 796; Toolbar 1.000; Tasks 524; Remaining 3.247; Apply 302; Listen 1.470; Mobile 822; Filter 1.564; B13 Chromium 628; Notifications je 1.985 bei 390/1280 px. Exits/Logs: `validated/browser-baselines.json`. Keine überlappenden Zähler als zusätzliche einzigartige Fälle addiert. |
| Syntax/Inventur/Diff | **PASS:** PHP-Lint für alle drei geänderten/neuen PHP/PHTML-Dateien, Python-AST beider geänderter Pythondateien, relative Berichtslinks und `git diff --check`. Source-/Runtimeinventur auch im aktuellen Workspace ausgeführt und obige Mengen bestätigt; Runtime-JSON gegenüber Ausgangsstand unverändert. |

Die alten Views für einen reproduzierbaren Vergleich bereitstellen:

```sh
mkdir -p /tmp/dewawi-b15/legacy-views/scripts/index
git show c6325897:application/views/scripts/index/index.phtml > /tmp/dewawi-b15/legacy-views/scripts/index/index.phtml
python3 tests/ui/button-page-integration.py --dashboard --compare-dashboard=/tmp/dewawi-b15/legacy-views --output=/tmp/dewawi-b15/dashboard-pages
```

Der neue Browsermodus führt den **Original-Editdispatcher** mit Enter, Space und echtem Klick auf das Kind-SVG aus. Ein fremdes ausgewähltes Control darf das jeweilige Ziel nicht ändern. Navigation wird an `setLocation` aufgezeichnet; Editseiten und echte Schreibaktionen werden nicht aufgerufen. Disabled/Loading unterdrücken Aufrufe; Restore erhält dieselben SVG-DOM-Knoten, Attribute, Auswahl und erneute Bedienbarkeit. Es werden keine AJAX-Schreibrequests ausgelöst. Nicht behauptet wird ein echter Editor-/Dokumentworkflow im Kundenmandanten.

Die Darstellung wird über aufgelöste IDs, `getBBox`, berechnetes currentColor/Größe/Display, echte SVG-Screenshotpixel, 44-px-Touchflächen bzw. 32-px-Desktopgröße, unbeschnittene Controls in ihren Zellen, Hover, native Tab/Shift-Tab und sichtbaren 2-px-Fokus geprüft. Der tatsächliche Chromium-AX-Baum enthält übersetzte Buttonnamen; bereits vorhandene Legacy-Edits können denselben übersetzten Namen besitzen. Loading mit Ersatztext darf das Icon wie bisher abtrennen und muss exakt denselben Knoten restaurieren. Bilder für alle verbliebenen berechneten PNG-Hintergründe müssen tatsächlich geladen werden.

**Bestehender unabhängiger Fehler:** `php tests/security/notifications.php` besteht vor/nach 370 allgemeine Checks und bricht im Sales-Fixture wegen fehlender `Sales_Service_DocumentRevisionService` in `sales/views/helpers/Finish.php:12` ab (Exit 255). Keine B15-Korrektur an Dokumentlogik oder Fixtureabhängigkeiten.

Der Snapshotvergleich bindet das alte View-Basisverzeichnis **vor** Controller-/ViewRenderer-Initialisierung ein (`scripts/index/index.phtml`) und verlangt ausdrücklich null moderne Controls im alten HTML. Ein anfänglich zu später Eingriff in `postDispatch` hätte bereits die aktuelle View verglichen; das wurde im Testaufbau korrigiert und die Vergleiche wiederholt. Originalpartials bleiben erreichbar. Damit wird keine rein scheinbare Vorher/Nachher-Gleichheit als Nachweis verwendet.

Visuelle Sichtung der alten/neuen Dashboardausgabe bei 320/1280 px bestätigt Stiftgeometrie und Fokus. **Bestehende Layoutprobleme bleiben:** Bei 320 und 768 px wird die Titelspalte im Testbestand extrem schmal, Texte laufen fast senkrecht, Zeilen werden sehr hoch. DE-Headerüberlauf bei 320 px und technische englische Menü-/Spaltenkeys sind ebenfalls bereits vorher vorhanden. B15 ändert weder deren Ursache noch Tabellen-/Headerregeln. Gemessene erste Zeilen, DE/EN identisch:

| Viewport | Kontakt / Ledger vorher → nachher | Artikel vorher → nachher | Editfläche vorher → nachher |
| --- | --- | --- | --- |
| 320 px | 341,34 → 341,34 px | 265,97 → 265,97 px | 36×36 → 44×44 px |
| 390 px | 58,69 → 65 px | 58,69 → 65 px | 36×36 → 44×44 px |
| 768 px | 341,34 → 341,34 px | 265,97 → 265,97 px | 36×36 → 32×32 px |
| 1280 px | 58,69 → 58,69 px | 58,69 → 58,69 px | 36×36 → 32×32 px |

Die zusätzlichen 6,31 px bei 390 px sind die erwartete Folge der gemeinsamen 44-px-Touchfläche. Die hohen schmalen Zeilen bei 320/768 px sind kein SVG-Effekt; Spalten-/Scrollbreiten stimmen mit dem alten Template überein. Kein neuer Controlüberlauf oder verdecktes Icon nachgewiesen. Keine Behauptung einer vollständigen mobilen Layoutfreigabe. Native Firefox-Tastatur, Safari/WebKit, physische Touchgeräte, Screenreader und authentifizierte reale Rollen-/SaaS-/Sharingabläufe sind in B15 nicht abgenommen. Die produktive Installation und deren tatsächlicher Release-/Assetstand bleiben gemäß B14 außerhalb dieser lokalen Prüfung.

## Verbleibende Legacygruppen und nächste Prioritäten

„Kein SVG“ bedeutet nicht „unsichtbares Icon“: PNG-Klassen und erfolgreich geladene Assets sind weiter gültige Legacydarstellung. Die B14-Inventur von Kontakt-/Artikel-/Aufgaben-/Dokumentlisten bleibt erhalten; B15 migriert keine dieser Listen.

| Priorität / gemeinsamer Weg | Beleg und verbleibender Bestand | Risiko / konkreter Folgeauftrag |
| --- | --- | --- |
| 1: Dashboard-Applicationtoolbar erst funktional klären | 16 Viewaufrufstellen, fünf Felder der gemeinsamen Application-Toolbar; im vollen DOM sechs Edit-, fünf Copy- und fünf PDFbuttons ohne eindeutige Rowhooks. | Eigenen eng begrenzten Auftrag für Target-/Aktions-/Rechtevertrag, alle fünf Dokumenttypen und Tasks. Original-URLs, Draft-/Readonlyzustände, Downloadlock/Popupblocker und ACL belegen, erst dann Präsentation opt-in migrieren. |
| 2: Standard-Index-Apply | B14 zeigt je einen Kontakt-/Artikel-Indexbutton als PNG; B9 gilt ausschließlich in definierten Auswahlkontexten. | Auswahlbestätigung von Massenaktion trennen; Parent/Set, Validation, Lade-/Fehler-/Retryverhalten und Berechtigungen sichern. Bestehendes Apply-SVG reicht als Asset. |
| 3: Standard-Delete bereits teilweise migrierter Listen | Im B14-Testbestand Kontakte 27, Artikel drei, Tasks drei PNGbuttons; verschiedene Datenmengen, keine Quellenzählung. | Confirm/Cancel, IDs, Kaskaden, Mandanten/Rechte, Teilergebnisse, Requestlocks und Fehlerwiederholung zuerst nachweisen. SVG bereits vorhanden; keine Dokument-/Medienlöschung mitnehmen. |
| 4: Tags/Export/Pin und Modal-Close | Kontakte Tags/Export je ein Textbutton, Tagdialog zwei, Exportdialog drei; Pin je Row. Dynamisches Close bleibt eigener Modalweg. | Sichtbarer Text ohne Icon ist kein leeres SVG. `data-contact-…`, Inline-Pin, Exportprofile, Fokus-/Dialoggrenzen erhalten; passende Icons nur bei belegtem Bedarf ergänzen. |
| 5: Restliche Toolbar-/Form-/Positionsfamilien | Application/Campaigns/eBay/Processes/Purchases/Sales/Shops bleiben als Formularfamilien Legacy; auch teil migrierte Familien haben Sondergruppen. | Nach gemeinsamem Renderer und konkretem Kontext bündeln. Dokument/Mail/Zahlung/Shop/CMS/Drittanbieter, gespeicherte Inhalte und komplexe Positions-/Massenaktionen brauchen eigene fachliche Abnahme. |

Kein PNG oder sonstiges Asset wird durch B15 als global ungenutzt erklärt oder entfernt. Der B1-Bericht, historische Recovery-Code und das separate Übersetzungsaudit bleiben unverändert. Vor Commit ausschließlich eigene Dateiliste, Status und staged Diff prüfen. **Kein Push, Merge oder Deployment.**
