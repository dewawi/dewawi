# B4 – Begrenzter SVG-Pilot in den Inquiry-Toolbars

Stand: 18.11.2026. Lokale Grundlage: B2 `b27f55e6`, B3 `2af50303`, [Bestandsaufnahme](buttons-icons-analysis.md), [Button-Grundlage](buttons-foundation.md) und [SVG-Vertrag/Katalog](button-icons.md). Der unversionierte Analysebericht bleibt unverändert und außerhalb dieses Commits.

Fortsetzung: [B5 – gemeinsame Standardtoolbars](toolbar-buttons.md) erweitert den begrenzten Einbau auf Kontakt-/Artikelstammdaten. Die folgenden Scopeangaben beschreiben den B4-Stand; der Inquiry-Pilot bleibt erhalten.

## Ergebnis und Abnahmestatus

Die sechs bereits in B2 vorgesehenen Controls verwenden jetzt ausdrücklich SVG. Der Renderer, seine Allowlist, Iconassets, Varianten und Größen aus B3 bleiben unverändert. Beide internen vollständigen Layouts liefern den vorhandenen Symbolblock einmal, ausschließlich für `contacts/inquiry`. Es gibt keine pauschale Umstellung anderer Toolbars und keine neuen Symbole, CSS-Regeln oder JavaScript-Handler.

Die technische Prüfung umfasst echte lokale Inquiry-Views, Listenpartial, Toolbar-/Übersetzungshelper, Haupt-/Popup-Layout, Navigation, CSS und JavaScript. Deutsch/Englisch und 390/768/1280 px sind automatisiert geprüft. Screenshot-Sichtungen sind durchgeführt. **Eine fachliche Freigabe durch Produktverantwortliche, reale Mandanten-/Themeabnahme und vollständige Browser-/Screenreaderabnahme liegen damit nicht vor.** Der B4-Auftrag autorisiert diese begrenzte lokale Pilotmigration; aus den Tests wird keine Freigabe für weitere Module abgeleitet.

Die neue vollständige Renderingprüfung hat einen zuvor von den schmalen B2-/B3-Fixtures verdeckten Fehler gefunden: `save` war direktes Kind des zwölfspaltigen `.dw-form-row`-Grids. Auf Desktop belegte der Button nur eine Spalte; „Status speichern“ brach buchstabenweise um. In der Inquiry-Edit-View steht er nun in einem eigenen einfachen Block innerhalb desselben Formulars. Native Submitsemantik und Formularattribute bleiben erhalten; keine globale Grid-/Buttonregel wird verändert. Die neue Browserregression begrenzt seine Höhe auf 44 px und erkennt den alten Umbruch.

## Konkreter Umfang vorher/nachher

| Ort / Control | Vorher (B2/B3 produktiv) | Jetzt (B4) | Erhaltener Vertrag |
| --- | --- | --- | --- |
| `InquiryToolbar`: `filter` | Text + filter.png (Lupe) | Text + explizites `filter`-SVG (Trichter) | `button`, `name=filter`, `data-action=filter`, Aktionsklasse; vorhandenes Filterpanel. |
| `InquiryToolbar`: `reset` | Text + reset.png | Text + `reset`-SVG (rotate-ccw) | Vorhandenes Filter-Reset und Suchrequest, kein nativer Form-Reset. |
| `InquiryToolbar`: `clear` | clear.png, Icon-only | `clear`-SVG, Alias auf `close` | Übersetztes `CLEAR`, `rel=keyword`, Aktionsklasse, `data-action=clear`. |
| `InquiryToolbarInline`: `view` | view.png, Icon-only, `sm` | `view`-SVG (eye), Icon-only, `sm` | `TOOLBAR_VIEW`, Zeilen-ID/Modul/Controller, unveränderte View-Zielroute. |
| `InquiryToolbarInline`: `edit` | edit.png, Icon-only, `sm` | `edit`-SVG (pencil), Icon-only, `sm` | `TOOLBAR_EDIT`, bestehende Sichtbarkeits-/Editprüfung, Zielroute. |
| `Inquiry`: `save` | Text + save.png, Primary-Submit; fehlerhafte Desktop-Gridanordnung | Text + `save`-SVG, Primary-Submit; eigener Block | Übersetztes `CONTACT_INQUIRIES_SAVE_STATUS`, `type=submit`, `name=save`, unveränderte Value-Abwesenheit und kein `data-action`; gleicher Status-/Auto-Save-Ablauf. |

Filter/Reset/Save bleiben Text+Icon. Leeren/Anzeigen/Bearbeiten bleiben Icon-only mit übersetzten Namen. DE: „Leeren“, „Anzeigen“, „Bearbeiten“; EN: „Clear“, „View“, „Edit“. SVGs bleiben dekorativ und ohne eigene Tabstopps. Es werden weder technische Schlüssel in `aria-label`/`title` eingeführt noch Textlabels bei schmalen Ansichten entfernt.

Filter/Reset/Clear verwenden weiterhin Default/`md`, Zeilenaktionen Default/`sm`, Save Primary/`md`. Größen bleiben 36/32 px am Desktop, mindestens 44 px bei ≤ 767 px/grobem Pointer; Iconbox 16 bzw. 20 px, Abstand 8 px, Stroke/currentColor aus B3. Keine leeren Iconelemente und kein ungeprüfter automatischer Fallback. Alle benötigten Keys sind bereits in der B3-Allowlist vorhanden.

**Bewusst unverändert:** Der gemeinsame Controller `DEEC_Controller_Action::buildListView()` ergänzt im echten Index zusätzlich `export`. Dieser alte Textbutton ist im neuen Renderingtest enthalten und bleibt Legacy. Die schmalen B2-/B3-Fixtures enthielten ihn zuvor nicht. Auch aktive Filterchips, Zuweisungs-/Kontakterstellungsbuttons, technische Auswahl, Mail-/Dokumentaktionen, andere Kontakttoolbars, Shop/CMS und Drittanbieter bleiben unverändert.

## Einbau und tatsächlicher Requestpfad

- `application/layouts/scripts/layout.phtml` und `plain.phtml`: unmittelbar im Body `renderButtonSymbols()` nur bei Modul `contacts` und Controller `inquiry`. Auch eine unabhängige Inquiry-iframe-Seite kann dadurch ihre eigenen Symbole auflösen. Andere Controller bekommen keinen Block. Das öffentliche `site.phtml` bleibt unberührt.
- `Contacts_InquiryController::buildIndexView()` baut weiterhin die gleiche Liste/Toolbars und deaktiviert Zeilenselektion. Die Testliste verwendet dieselbe `Contacts_Model_List_Inquiries` und `list/list.phtml`; tatsächliche Rowbuttons bekommen weiterhin die Ziele 17/18 in der synthetischen Fixture.
- Suchrequests laufen weiterhin über `/contacts/inquiry/search`. `searchAction()` schaltet das Layout aus; `Application_Plugin_ListResponse` erzeugt die bestehenden JSON-Felder `content` und `toolbar`. Der neue Test verwendet dieses echte Plugin. Die Fragmente enthalten SVG-Referenzen, keinen Symbolblock.
- `DewawiToolbar.refresh()` ersetzt die Haupttoolbar nicht: nur aktive Filterchips werden aktualisiert. `#content` mit Liste/Zeilenaktionen wird ersetzt. Tests sichern ausdrücklich die Identität der Haupttoolbar und die erneute Funktion/Geometrie eingefügter SVG-Zeilenaktionen. Kein Iconinitialisierungscode erforderlich.
- Status-Auto-Save bindet weiterhin an `.edit form`. Im tatsächlichen Hauptlayout ist dieser Wrapper vorhanden. Das bestehende Popup-Layout besitzt ihn nicht; die vorsorglich geprüfte Popup-Einbettung löst daher bereits vor B4 kein Status-Auto-Save aus. Dieses Verhalten wird erhalten. Die aktuellen Inquiry-Index/Edit-Routen schalten selbst nicht auf `plain`; Popupprüfungen sind Layout-/Einbettungsprüfungen, keine Behauptung eines neuen Popup-Workflows.
- Das Statusformular behält POST, native Validierung und Submitter; die neue Viewanordnung ändert seine Form-/Feldzugehörigkeit nicht. Bestehende serverseitige Statusvalidierung, Transaktion, Autorisierung und Mandantenscopes werden nicht geändert.

## Testaufbau und Baseline

`tests/ui/inquiry-buttons.php` verwendet den vorhandenen speicherbasierten B2-Adapter und die realen Views/Helper. Datensätze, Nutzer und Optionen sind synthetisch; Datenbankverbindungen und Mutationen werden vom Adapter nicht zugelassen. Gemeinsame Sprachkataloge werden wie im Translate-Plugin geladen. Nur die Datenbereitstellung und die vorhandene dynamische Index-Exportkonfiguration werden in der Fixture vorbereitet; es ist kein vollständiger authentifizierter Controller-/Datenbank-End-to-End-Test.

`tests/ui/inquiry-buttons.py` liefert die gerenderten Seiten und tatsächlichen lokalen Assets über einen temporären Server auf 127.0.0.1 aus und nutzt den vorhandenen ChromeDriver. Ein ausschließlich im Test eingebundenes Headscript fängt **alle** jQuery-AJAX-Aufrufe ab, protokolliert Navigation und verhindert echte Formularübermittlung. Keine E-Mail, echten Statusänderungen, produktiven HTTP-Requests oder produktiven Daten. Das vorhandene CSP-Plugin wird nicht gelockert; der Testserver verwendet dessen interne `frame-ancestors 'self'`-Policy. Die separate strenge B3-CSP-Prüfung bleibt bestehen.

Die vorhandenen B2-/B3-Assertions bleiben erhalten. `buttonPilot()` setzt das jeweilige Fixtureformat jetzt ausdrücklich auch für PNG; dadurch bleibt die B2-PNG-Kompatibilitätsprüfung nach Änderung der produktiven Defaults aussagekräftig. **Die neue Inquiry-Prüfung verwendet dagegen unveränderte produktive Formkonfigurationen** und verlangt dort SVG. Vier komplette PNG-/SVG-Fixture-JSONs (DE/EN) wurden vor B4 gesichert und sind danach bytegleich. Keine alte Assertion wurde entfernt oder zur bloßen Erfolgsmeldung umgeschrieben.

| Tatsächlich ausgeführte Prüfung | Ergebnis |
| --- | --- |
| Vor Änderung: `php tests/ui/buttons.php`, `php tests/ui/button-icons.php` | PASS: 165 B2- und zusätzlich 645 B3-Checks. |
| Vor Änderung: `python3 tests/ui/buttons.py`, `python3 tests/ui/buttons.py --svg` | PASS: 169 PNG- / 194 SVG-Browserchecks. |
| Vor Änderung: neuer Real-Rendering-Runner im damaligen `--before`-Modus | PASS: 660 PNG-Checks in 24 Kombinationen aus DE/EN, Haupt-/Popup-Layout, Index/Edit und 390/768/1280 px. Der Gridfehler wurde dabei als visueller Vorherbefund festgestellt; die gezielte Höhenregression gilt für die korrigierte Nachherausgabe. `--before` bezeichnet den historischen produktiven PNG-Stand und ist kein aktueller Testaufruf für die inzwischen auf SVG gesetzten Forms. |
| `php tests/ui/inquiry-buttons.php` | PASS: 697 neue Checks plus eingebundene 165 B2-Checks. Unveränderte produktive Opt-ins, vollständige Index/Edit/View-Ausgaben, einmaliger Symbolblock, andere Controller ohne Block, Typen/Attribute, Labels, Zeilenziele, Readonly/Rechte und echtes ListResponse-JSON ohne Symbolduplikate. |
| `python3 tests/ui/inquiry-buttons.py` | PASS: 796 Browserassertions. 24 normale Kombinationen wie oben; zusätzlich 16 echte Readonly-Seiten bei 390/1280 px. Geometrie/currentColor, Namen im AX-Baum, kein Seitenüberlauf oder über die Tabellenzelle ragender Pilotbutton, vertikale Zentrierung/8-px-Abstand, Hover nach CSS-Transition, sichtbarer Tastaturfokus, Tab/Shift-Tab/Enter/Space, Loading und Restaurierung derselben Iconknoten, Disabled-/Kindklicksperre, AJAX-Inhaltsersatz, persistente Toolbar, Feld-Leeren, neue Zeilenaktionen, Auto-Save/Opt-out und nativer Status-Submit. |
| Nach Änderung: `php tests/ui/button-icons.php`, `python3 tests/ui/buttons.py`, `python3 tests/ui/buttons.py --svg` | PASS: unverändert 165 + 645 PHP-, 169 PNG- und 194 SVG-Browserchecks einschließlich Forced Colors, strenger CSP und eigenem iframe. |
| Vorher/Nachher-Vergleich der vier B2-/B3-JSONs | PASS: DE/EN jeweils PNG/SVG bytegleich. |
| `php tests/security/permissions.php` | PASS: 415 Fälle; negative `NO_PERMIT`-Ausgaben erwartbar. |
| `php tests/security/tenant-access.php` | PASS: 269 Mandanten-/Objekt-/Sharingfälle im Speicheradapter. |
| `php tests/security/contact-inquiries.php` | PASS: 46 Fälle. |
| `php tests/security/form-input-validation.php` | PASS: 20 Fälle. |
| `php tests/security/inquiry-dynamic-form.php` | PASS: 31 Fälle. |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 bestehende Browserchecks bei 1280/390 px. Dialog-, Dokument- und Mailverträge werden durch den Pilot nicht geändert. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler aus B2/B3: 370 allgemeine Checks bestehen, danach fehlt `Sales_Service_DocumentRevisionService` in `application/modules/sales/views/helpers/Finish.php:12`; Sales-Helper-Fixture bricht in `tests/security/notifications.php:211` ab. Keine betreffende Datei geändert. |
| Syntax / Diff | `php -l` für alle acht geänderten/neuen PHP/PHTML-Dateien, Python-AST-Prüfung ohne Bytecode und `git diff --check` bestanden. |

## Sichtprüfung und noch nötige Freigaben

Screenshots der echten Haupt-/Popup-Layouts wurden für DE/EN bei 390/1280 px aufgenommen; gezielte Ausschnitte zeigen zusätzlich die Tabellenaktionen. Unter `/tmp` liegen `dewawi-b4-before-…` und `dewawi-b4-after-…`, z. B. `dewawi-b4-after-de_DE-layout-edit-1280.png` und `dewawi-b4-after-de_DE-layout-rows-390.png`. Sie sind lokale Prüfarbeitsdateien und keine versionierten Produktassets. Der normale Runner erzeugt die Nachherbilder erneut. Vorherbilder zeigen den damaligen PNG-/Gridzustand.

Gesichtet: klare SVG-Geometrie, weiße Save-Striche auf Primary, sichtbarer Fokus, Zentrierung, Touchgröße, Toolbarumbruch sowie der beseitigte Buchstabenumbruch am Statusbutton. Bei schmaler Ansicht bleiben bestehende Seiten-/Tabellengestaltung erkennbar: Navigation/Userbar sind hoch, die vielen festen Tabellenspalten umbrechen Überschriften stark. In der englischen Gesamtseite erscheinen außerhalb der Pilotbuttons weiterhin technische Menüs-/Breadcrumb-Schlüssel wie `MENU_ITEMS` und `INQUIRY`; die sechs Pilotnamen selbst sind übersetzt. Diese bereits vor der SVG-Änderung vorhandenen übergeordneten Layout-/Katalogfragen werden nicht durch globale Overrides oder andere Sprachdateiänderungen in B4 verändert. Die Pilotbuttons selbst müssen innerhalb ihrer Zelle bleiben; dafür gibt es jetzt eine Regression.

| Noch offen | Konkrete Freigabe / Grenze |
| --- | --- |
| Fachliche Semantik und visuelle Produktfreigabe | Verantwortliche prüfen Trichter statt bisheriger Filter-Lupe, Resetpfeil, Eye/Pencil, dezentes Clear-X und Save; gewünschte Text/Icon-only-Regeln im echten Anfrageworkflow bestätigen. Automatische Tests können diese Entscheidung nicht treffen. |
| Reale Mandantenumgebung | Konfigurierte Sprachen-/Mandantenswitcher, lange reale Inhalte und verwendete lokale/externe Themes mit berechtigten Rollen, Readonly und zulässigem Sharing prüfen. Keine Freigabe anderer Toolbars aus den Fixtures ableiten. |
| Geräte / Accessibility | Physische Touchgeräte, Safari/Firefox und reale Screenreader prüfen. Chromium-AX-/Tastaturchecks sind keine vollständige Konformitäts- oder Browserabnahme. |
| Dichte mobile Gesamtseite | Tabellenüberschriften, Navigation und vertikale Dichte bei 390 px getrennt fachlich/visuell beurteilen; bestehendes Tabellenlayout ist keine neue SVG-Regression. |
| Popup-Auto-Save | `plain` besitzt keinen `.edit form`-Wrapper. Falls ein echter Inquiry-Popup-Workflow gewünscht wird, dessen Speichervertrag gesondert festlegen und testen. B4 erfindet diesen Workflow nicht. |
| Weitere Migration | Erst nach Pilotfreigabe eine ausdrücklich begrenzte nächste Toolbargruppe beauftragen. Export, Assignment/Kontakterstellung und technische Auswahl bleiben Legacy; Mail/Dokument/Shop/CMS benötigen eigene Aufträge. |

## Dateien und Schutz fremder Arbeit

Laufzeitänderungen: `application/modules/contacts/forms/{Inquiry,InquiryToolbar,InquiryToolbarInline}.php`, `application/layouts/scripts/{layout,plain}.phtml` und `application/modules/contacts/views/scripts/inquiry/edit.phtml`. Tests/Dokumentation: `tests/ui/buttons.php`, `inquiry-buttons.php`, `inquiry-buttons.py`, dieser Bericht und Fortsetzungslinks in B2/B3.

Der Analysebericht behält SHA-256 `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721` und bleibt unversioniert. Während der Arbeit erschienene fremde Änderungen an Geocoding-Dateien und deren Tests werden nicht bearbeitet oder mitgestaged. Nur die explizite eigene B4-Dateiliste gehört zum lokalen Commit.

Keine Datenbankänderung/Migration, keine gespeicherten HTML-/JSON-Änderungen, keine manuelle Datenbanknacharbeit. Kein Push, Merge oder Deployment.
