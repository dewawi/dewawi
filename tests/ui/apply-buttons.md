# B9 – Auswahl- und Apply-Buttons

Stand: 18.11.2026. Lokale Grundlage: `AGENTS.md`, [B1](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B7](remaining-toolbars.md), [B8-Audit](button-quality-audit.md) und die tatsächlichen B2–B8-Commits bis `30db90b2`. Ausgangs-HEAD ist der unabhängige Dokumentcommit `0be82821`; Arbeitsverzeichnis zu Beginn sauber. Keine fremde Änderung oder Historie verändert.

## Entscheidung und Umfang

Vier bestehende Apply-Felder werden ausdrücklich auf die vorhandene `DEEC_Form`-Präsentation mit dem vorhandenen B3-Key `apply` umgestellt:

| Feld / Quelle unter `application/modules/` | Vorher → B9 | Genaue Grenze |
| --- | --- | --- |
| `contacts/forms/Toolbar.php::apply` | Text + PNG → Text + SVG, bestehende Default-/md-Präsentation | Exakte Klasse, `contacts/contact`, ausschließlich `select` oder `search` mit `context_action=select`. |
| `items/forms/Toolbar.php::apply` | Text + PNG → Text + SVG, Default/md | Exakte Klasse, `items/item`, dieselbe Grenze. |
| `contacts/forms/ToolbarInline.php::apply` | PNG mit technischem ARIA-Fallback → benanntes Icon-only-SVG, sm | Exakte Inlineklasse und derselbe Kontakt-Auswahlkontext. Nur dieses eine Feld, keine sonstige Tabellenaktion. |
| `items/forms/ToolbarInline.php::apply` | dieselbe Umstellung | Exakte Inlineklasse und derselbe Artikel-Auswahlkontext. |

Die Inlinefelder wurden nach eigener Aufruf-/Targetprüfung aufgenommen: Sie sind durch die bestehenden Listenspalten ausschließlich im Select-Kontext sichtbar und verwenden denselben Dispatcher mit einer expliziten Row-ID. Das ist eine gezielte Auswahlaktion, keine allgemeine Tabellenaktionsmigration. View/Edit/Copy/Delete/PDF/Pin bleiben unverändert. Synthetische Unterklassen und andere Controller erhalten kein Opt-in.

Die zwei Toolbarfelder werden oben und unten aus derselben Instanz gerendert. Mit zwei Fixturezeilen enthält Select jetzt fünf moderne Controls: Clear, zwei Wiederholungen des Toolbar-Apply und zwei Inline-Apply. Es werden weder neue Buttons noch neue Aktionen eingeführt. Index-Apply bleibt ausdrücklich Legacy, auch bei künstlichem `context_action=select`; ebenso Edit/View/Get/Export und Search im Index-/ungültigen Kontext.

Kein neuer Renderer, kein neuer Helper, keine neue CSS-Regel, kein neues Symbol, keine Bibliothek und kein JavaScriptumbau. Layouts liefern bereits für diese Select-/Search-Kontexte genau einen lokalen Symbolblock; Fragmente benötigen nur `<use>`-Referenzen.

## Tatsächliche Render-/Aufrufwege und unterschiedliche Bedeutungen

| Weg / konkrete Fundstellen | Vertrag / B9-Entscheidung |
| --- | --- |
| `DEEC_Controller_Action::{selectAction,searchAction,buildListView}` | Select verwendet `plain` und rendert die Indexview. Search setzt bei vorhandenem Parent den logischen Select-Kontext **vor** dem Aufbau von Toolbar und Inlineklasse. B9 liest diesen bestehenden Kontext; keine Controlleränderung. |
| `Contacts_ContactController::buildIndexView`, `Items_ItemController::buildIndexView` | Bestehende gemeinsame Listenvorbereitung und jeweilige Modulklassen. Daten-/Options-/Auswahl-/Berechtigungslogik unverändert. |
| `application/views/helpers/Toolbar.php::renderSelectToolbar`, `ToolbarBottom.php::ToolbarBottom` | Kopf und Select-Fuß rufen dasselbe `renderElement('apply')` auf. Kopf zusätzlich Suche/Filter, Footer im echten Listenpartial. B9 ergänzt nur Metadaten in den Formularen. |
| `contacts/models/List/Contacts.php:67`, `items/models/List/Items.php:60`, `DEEC_List::renderActionsCell` | Die bestehende `show`-Bedingung zeigt Row-Apply nur bei `getContext('action') === 'select'`. Der Listenkern ergänzt weiterhin `data-action`, `data-id`, `data-module`, `data-controller` und rendert über die bestehende Inline-Form. Kein Spalten-/Listencode geändert. |
| `js/global.js::DewawiToolbar.{resolveSelection,validateSelection,actions.apply,getSelectContext}` | Row-ID hat Vorrang vor Checkboxen. Ohne Row-ID werden die bisherigen ausgewählten IDs genommen. Apply ist als `selection=multiple` registriert, akzeptiert also ≥1 ID. Leer bleibt ohne Aktion. |
| Kontakt-Auswahlbestätigung | `actions.apply` ruft `window.parent.applyContact(selection.ids[0], $button)` auf. Auch bei mehreren angehakten Kontakten wird weiterhin nur die erste vom bestehenden Selektor gelieferte ID übernommen. B9 macht daraus keine Massen-Kontaktänderung. Row-Apply übergibt nur die eigene Zeile. |
| Artikel-Mehrfachübernahme | `applyPosition()` übernimmt alle Checkbox-IDs sequenziell; bei Row-Apply genau eine. Parenttyp, Parent-ID und `setid` bleiben erhalten. Nach vollständigem Erfolg genau ein Calculation-/Positionsrefresh und Dialogschluss. B9 ändert keine Positions-/Kalkulationslogik. |
| Konkrete Popup-Aufrufer: `processes/views/helpers/Popup.php`, `sales/views/helpers/Popup.php`, `purchases/views/helpers/Popup.php` | Bestehende Kontakt-iframe-URL mit `parent=<module>|<controller>` und optionaler Kontaktnummer; Artikel-URL mit zusätzlicher Parent-ID. B9 ändert keinen dieser Helper. Der Processes-Helper wird mit/ohne vorhandene Kontaktnummer tatsächlich isoliert gerendert und geprüft. Sales/Purchases sind reine Aufrufanalyse; keine Dokumentaktion migriert. |
| Parent-Callbacks `js/contacts.js::applyContact`, `js/global.js::applyPosition` | Kontakt liest Stammdaten/Adressen und speichert über das bestehende Parent-`edit()`. Artikel sendet bestehende Positionsrequests. Beide bleiben bytegleich. Der neue Browserfall benutzt einen synthetischen Parent im konkreten Processes-Kontext; kein vollständiger Prozess-/Dokumentworkflow behauptet. |
| Search/`Application_Plugin_ListResponse` | `context_action`, Parent und Set-ID werden wie bisher gesendet. Content/Footer werden ersetzt, die bestehende Haupttoolbar bleibt. Checkboxen im neu gerenderten Suchergebnis folgen dem alten Vertrag; B9 führt keine neue Auswahlpersistenz ein. Wiederöffnen desselben nicht neu geladenen Dialogs erhält die Auswahl. |

Die Auswahlkontexte sind damit von folgenden gleich oder ähnlich benannten Fällen getrennt:

| Nicht migrierter Fall | Grund / unveränderter Weg |
| --- | --- |
| Toolbar-Apply im Index oder fremden Modulcontroller | Kein freigegebener Auswahlbestätigungskontext. Keine Zuordnung allein anhand von Name/Klasse. |
| Contacts-Email-Zuordnung | `Contacts_Form_Email::renderAdditionalMultiActions` nutzt `.apply`, aber `data-email-contactperson-assign`, eigene IDs/Adressen, Dialoge und Validierung. Kein Toolbar-Apply. |
| Kontakte-Karte | `contact/map.phtml` enthält einen nativen `submit` mit `CONTACTS_MAP_APPLY`; `contacts-map.js` steuert die Filterübernahme. Eigener Formular-/Kartenfluss, kein Auswahlcallback. Native Submitsemantik bleibt erhalten. |
| Bestandsplanung | `items/stockplanning/edit.phtml` und `StockplanningController::applyAction` sind fachliche POST-Buchungsaktionen. Keine optische oder fachliche Änderung. |
| Autocomplete | `data-autocomplete-apply=contact/position`, `handleAutocompletePick` und `applyAutocompleteValue` verwenden eigene Feld-/Autosave-/Parentverträge. Keine dynamische Factory migriert. |
| Massenaktionen Copy/Delete/Tags | Andere `actionConfig`-Einträge, Rechte und Bestätigungs-/Requestverträge. Die Auswahlcheckboxen werden gemeinsam verwendet, die Aktionen bleiben unangetastet. |
| Preisregeln und Auswahlfelder | `priceruleaction`, Subkategorien, Länder-/Sprach-/Statusselects sind fachliche Werte bzw. Formfelder, keine Apply-Buttons. |
| Legacy-Settings, Medien-/Dateiauswahl, Shop/CMS, Drittanbieter | Eigenes Callback-/Widget-/HTMLsystem; auch `js/settings.js::applyContact` bleibt unverändert. Kein ZF1-Input- oder Link-/Widgetumbau. |

## Semantik, Maße und Zustände

Alle vier Felder bleiben native `type=button`, `name=apply`; kein Submit, Reset oder künstlicher Link. `TOOLBAR_APPLY` ergibt „Übernehmen“/„Apply“. Die Toolbar behält sichtbaren Text, die Inlineaktion bekommt einen expliziten übersetzten zugänglichen Namen statt des bisherigen technischen Fallbacks. SVGs sind dekorativ (`aria-hidden=true`, `focusable=false`). Das ist die beabsichtigte Änderung der Inline-ARIA-Beschriftung; Target-/Funktionsattribute bleiben erhalten.

Toolbar: mindestens 36 px md / 16-px-Icon; Inline: 32 px sm / 16-px-Icon; bei ≤767 px oder grobem Pointer mindestens 44 px / 20-px-Icon. Bestehender 8-px-Textabstand, sichtbare 2-px-Fokusoutline, Hover, Disabled und Loading kommen aus B2/B3. Kein leerer Iconplatz und kein zusätzliches PNG hinter dem SVG.

Kontaktübernahme verwendet weiterhin `beginActionRequest('contact-apply', <parent>, [<parent-id>])`: tatsächlicher Loadingzustand des geklickten Controls, SVG-Ausblendung/Restore und Sperre auch gegen einen erneuten Footeraufruf. Artikelübernahme sperrt weiterhin über `dw-modal-pending` am Parentdialog; sie besitzt im bestehenden Ablauf **keinen zusätzlichen Button-Loadingindikator**. Die Mehrfachklicksperre ist für diesen vorgesehenen Parentdialog nachgewiesen; für einen Direktaufruf ohne vorhandenen Modalzustand wird kein neuer Guard eingeführt. B9 ergänzt keinen konkurrierenden Sperrmechanismus. Die moderne Disabled-/Loading-Präsentation wird zusätzlich ausdrücklich ausgelöst und geprüft. Escape darf einen laufenden Artikelbatch nicht schließen.

Bei Fehler bleibt der Dialog mit Auswahl offen; Kontakt-Loading und SVG-Nodes werden wiederhergestellt, der Artikel-Pendingzustand wird freigegeben. Wiederholung und Fokus auf den ursprünglichen Öffner nach erfolgreichem Abschluss bleiben erhalten. Keine neue Bestätigung eingeführt; bestehende fachliche Dialoge werden nicht umgangen.

## Rechte, Mandanten und Kompatibilität

`apply` benötigt nach bestehendem `DEEC_Permission::ACTIONS` das Recht **edit** im jeweiligen ausgewählten Modul. View-only/Add-only erhalten dadurch kein Apply. B9 ändert diese Einstufung nicht, auch wenn eine andere Zuordnung fachlich denkbar wäre. Serverseitige Parent-/Objekt-/Tenantvalidierungen und SaaS-Featureprüfungen verbleiben an ihren bisherigen Stellen. Rendering vergibt keine Rechte; `hasModule()` wird nicht als Entitlement interpretiert. Bestehende B8-Lücken bei vollständiger SaaS-/Rollenabnahme bleiben offen.

Die HTML-Prüfung vergleicht Toolbar-Ausgabe mit einer unveränderten Unterklassenreferenz: native Typen, Namen, bisherige Value-Ausgabe, `data-*`, Eventattribute und Texte bleiben gleich; Klassen werden nur um Präsentation ergänzt. Inline prüft zusätzlich präzise Row-Targets und die bewusst neue ARIA-Beschriftung. Bestehende rendererbedingte Attributnormalisierung wird nicht als B9-Nebenauftrag verändert.

Keine Datenbank-/Schema-/gespeicherten Inhaltsänderung, keine Änderung an ACL/Permission/Queries, Popuphelpern, Dispatchern, `applyContact`, `applyPosition`, Dokument-, Mail-, Zahlungs- oder Drittanbieterlogik. Der unabhängige Dokumentcommit `0be82821` bleibt erhalten.

## Tatsächlich ausgeführte Tests

| Prüfung | Ergebnis |
| --- | --- |
| Baseline vor Änderung | PHP B2/B3/B5/B6/B7/B8 bestanden; Kontakte-/Artikelbrowser 928 Assertions. Die B8-Übersetzungskorrektur ist weiterhin aktiv. |
| `php tests/ui/apply-buttons.php` | PASS: 1.459 neue gezielte Checks, zusätzlich eingebundene 165 B2-/2.259 B5-Checks. Kontext-/Unterklassengrenzen, vollständige Metadaten, Label-/Native-/Targetattribute, Kopf/Fuß, Row-IDs, Rechte, Readonly, reale parent-only-`searchAction()` und realer Processes-Popuphelper. |
| `python3 tests/ui/apply-buttons.py` | PASS: 302 Assertions, DE/EN × 390/768/1280 px. Echte iframe-/Modalintegration mit unveränderten JS-Handlern, synthetischer Parent und abgefangenem I/O. Text- und Icon-only-Namen, Größen, Gap/Geometrie, Hover, Fokus, Tab/Shift-Tab/Enter/Space, SVG-/use-Klicks, leere/mehrfache/zeilenbezogene Auswahl, Parent-/Set-ID in Query und Pfad, neu gerenderte Footer/Zeilen, reentranter Kontaktaufruf, sequenzielle Artikel, Doppelklicks über Kopf/Fuß, Validation-/Batchfehler, Wiederholung, Escape-Sperre, Openerfokus sowie Parent-Auto-Save/Opt-out. |
| Snapshotvergleich | 480 B5-Konstruktor-Kontexte: genau zwei Änderungen, ausschließlich Toolbar-Apply in den zwei Select-Kontexten; 478 identisch. 350 B6 und 1.900 B7 identisch. Zusätzliche 80 Inline-Kontexte: genau vier Änderungen (zwei Select, zwei Search/Select); nur Apply-Metadaten, 76 identisch. Root-Search/Select zusätzlich ausdrücklich geprüft, da die historische B5-Matrix keinen `context_action` mitführt. |
| `php tests/ui/{button-icons,inquiry-buttons,tasks-toolbar,remaining-toolbars}.php` | PASS: bestehende 645 / 697 / 1.428 / 27.105 Checks plus eingebundene Baselines. B5 bleibt bei 2.259; nur erwartete Select-Ausgabe-/Scopewerte angepasst. |
| `python3 tests/ui/toolbar-buttons.py` | PASS im abschließenden Vier-Felder-Stand: 1.000 Assertions statt 928 durch 72 zusätzliche Geometrie-/Labelchecks für die zwei zusätzlich sichtbaren Apply-SVGs pro Select-Profil. Bisherige Checks bleiben erhalten. |
| `python3 tests/ui/toolbar-buttons.py --tasks`, `--remaining` | PASS im abschließenden Stand: 524 / 3.247 Assertions für unveränderte Task-/Admin-/Statistik-/Legacy-Kontexte. |
| `python3 tests/ui/buttons.py`, `--svg --audit`, `inquiry-buttons.py` | PASS: 169 / 204 / 796 Assertions, einschließlich PNG-Kompatibilität, strenger lokaler Icon-CSP, iframe, Forced Colors und B8-Kontrast-/Touch-/Zoom-/AX-Proben. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 bestehende Mail-/Dokument-/Dialog-/Auto-Savechecks bei 390/1280 px; keine echten Mail-/Dokumentaktionen. |
| `php tests/security/{admin-access,permissions,tenant-access,bootstrap-auth}.php` | PASS: 68 / 415 / 269 / 29 Fälle; negative `NO_PERMIT`-Logs erwartbar. |
| `python3 tests/security/session-http.py`, `tenant-access-http.py` | PASS: 25 / 48 isolierte Session-/Cookie-/Objekt-/ACL-/AJAXfälle. Diese ersetzen keine durchgehende ERP-Anmeldung mit produktiver Geschäftsview. |
| `php tests/security/{contact-inquiries,form-input-validation,inquiry-dynamic-form}.php` | PASS: 46 / 20 / 31 Fälle. |
| `php tests/sales/quote-monitoring.php`, `tests/lists/summary.php` | PASS: 293 Fälle bzw. zentrale Listenfooter-/Summenprüfung. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler erneut bestätigt: 370 erfolgreiche Checks, danach `Sales_Service_DocumentRevisionService` fehlt in `sales/views/helpers/Finish.php:12`, Fixtureabbruch `tests/security/notifications.php:211`. Keine betreffende B9-Änderung. |
| Syntax / Diff | PHP-Lint aller sechs geänderten/neuen PHP-Dateien, AST-Prüfung beider Pythondateien, Berichtlinks und `git diff --check` bestanden. Neue Inlineklassen bleiben ohne Zend/MVC im Legacy-Modus. |

Die bestehende PHP-Fixture setzt den logischen Search-Kontext jetzt **vor** der Formkonstruktion, wie der reale Controller. Nur für die neue B9-Fixture werden vorhandene Inlineform und die echte `DEEC_List`-Actionsausgabe zusätzlich eingebunden; kein paralleles Buttonmarkup. Die B5-/B6-/B7-Standardmodi behalten ihre bisherigen Kontexte. Die neue Python-Fixture lädt tatsächliches jQuery, `global.js`, `contacts.js`, CSS und echte Select-Layouts/Controls; die Parentfelder und I/O-Antworten sind synthetisch. Calculation-/Positionsrefresh werden als Callbackgrenzen protokolliert, keine echten Kalkulationen geschrieben.

Screenshots: `/tmp/dewawi-b9-<contacts|items>-<390|768|1280>.png` aus dem iframe-/Parenttest; Contacts/390 und Items/768/1280 wurden gesichtet. Text-/Iconabstände, Toolbar- und Zeilengrößen passen. Die Breiten der iframe-Matrix beziehen sich auf das äußere Browserfenster; die tatsächliche innere Viewportbreite wird gemessen und für die Touchschwelle verwendet. B5 prüft Select zusätzlich direkt bei 390/768/1280 px. Das bestehende mobile Popup nutzt weiterhin seine bisherige Breitenbegrenzung; kein CSS-Layoutumbau. Bestehende Baselineartefakte stehen zusätzlich unter ihren B3–B7-Präfixen bereit. Kein vollständiger authentifizierter ERP-E2E-Lauf, keine produktiven Reads/Writes und keine produktive Adminsession. `tests/lists/footer-http.py` weiterhin ausgeschlossen, da es Produktionskonfiguration und eine echte Adminsession verwendet. Physische Touchgeräte, Safari/Firefox, NVDA/VoiceOver sowie echte Rollen-/Entitlement-/Options-/Datenkonfigurationen bleiben manuelle Freigaben nach B8.

## Verbleibende Risiken und B10

- Fachliche/visuelle Produktfreigabe bleibt offen. B8-Altbefunde (globale EN-Menükeys, mobiler englischer Umsatzreport, Filter-Disclosure, bestehende Gesamtlayoutdichte) werden nicht durch B9 korrigiert. Die neuen Apply-Namen selbst sind DE/EN übersetzt.
- Kontakt-Mehrfachauswahl bleibt absichtlich „erste ID“. Eine Änderung zu Single-Select oder mehreren Parentübernahmen wäre ein eigener fachlicher Auftrag.
- Artikelbatches bleiben sequenziell und nicht durch B9 zu einer gemeinsamen Transaktion zusammengefasst. Scheitert ein späterer Request, können frühere Übernahmen bereits erfolgt sein; der bestehende Dialog hält Auswahl und ermöglicht Wiederholung. Desktoptests prüfen den Fehler nach dem ersten Erfolg ausdrücklich. Ob und wie ein kompletter Retry fachlich Duplikate verhindern soll, ist keine Darstellungskorrektur und benötigt einen gesonderten Auftrag. Kein Idempotenz-/Rollbacknachweis aus der Fixture ableiten.
- Index-/fremde Apply-, native Karten-Submit-, E-Mail-, Autocomplete-, Bestands-, Preisregel-, Dokument-/Mail-/Zahlungs- und Widgetaktionen bleiben Legacy. Gleiche Beschriftung oder CSS-Klasse genügt nicht für eine automatische Migration.

Nächster sinnvoller B10-Block: gewöhnliche View/Edit/Copy-Zeilenaktionen in den bereits freigegebenen Kontakt-/Artikel-/Taskkontexten, mit eigenen exakten Klassen-/Routengrenzen, expliziten zugänglichen Namen und Tests für Row-ID versus Checkboxauswahl sowie AJAX-Neuausgabe. Nicht existente View-Felder, z. B. im aktuellen Items-Inlineformular, nicht erfinden. Delete/Bestätigungsabläufe, Positions-/Gruppentoolbars, Export/PDF, Mail, Zahlung, Finanz-/Rechte-/Mandantenkonfiguration sowie Shop/CMS/Widgets weiterhin getrennt behandeln. Keine automatische Fortsetzung.

Fortschritt: vier zusätzliche Apply-Konfigurationen, weiterhin 13 primäre Controllergruppen und dieselben sechs teilweise migrierten Haupttoolbarfamilien. Jetzt zehn SVG-erzeugende Formularquellen (zuvor acht), darunter neun von 34 Toolbarformular-Dateien mit begrenztem Opt-in; 25 vollständig Legacy. 36 Präsentationskonfigurationen ergeben 70 controllerbezogene Controlkonfigurationen. Diese Zahlen zählen Metadaten, nicht gleichzeitig sichtbare DOM-Buttons.

Laufzeitdateien ausschließlich `application/modules/{contacts,items}/forms/{Toolbar,ToolbarInline}.php`. Tests: neue `apply-buttons.php`/`.py`, gezielte Erweiterung von `toolbar-buttons.php`/`.py`. Dokumentation: dieser Bericht und B8-Fortsetzungslink. Keine Datenbankmigration oder manuelle Datenbanknacharbeit. Nur eigene Dateien lokal committen; kein Push, Merge oder Deployment.
