# B13 – Gesamtprüfung und Freigabematrix des Button-Systems

Fortsetzung/Korrektur: [B14 – SVG-Symbolversorgung](svg-symbol-integration.md) reagiert auf die nach Deployment gemeldeten fehlenden Definitionen. B13 prüfte passend vorbereitete Viewkontexte; daraus durfte kein Nachweis einer identischen produktiven Auslieferung abgeleitet werden. B14 entkoppelt Definitionen von den View-Routengates und ergänzt Originalcontroller-/HTTP-/Pixeltests. Die folgenden Ergebnisse beschreiben den historischen B13-Stand.

Stand: 19.11.2026. Ausgangs-HEAD **`27f18ce3`**, Arbeitsverzeichnis zu Beginn sauber. Maßgeblich: lokale Dateien, `AGENTS.md` und vollständig gelesene Berichte [B1](buttons-icons-analysis.md), [B2](buttons-foundation.md), [B3](button-icons.md), [B4](inquiry-buttons.md), [B5](toolbar-buttons.md), [B6](tasks-toolbar.md), [B7](remaining-toolbars.md), [B8](button-quality-audit.md), [B9](apply-buttons.md), [B10](list-buttons.md), [B11 mobil](mobile-table-actions.md), [Gesamtinventur](button-system-inventory.md) und [B12](filter-buttons.md).

**Entscheidungsvorlage: keine uneingeschränkte Deploymentfreigabe.** Die isolierten technischen Buttonregressionen bestehen. Keine neue, eindeutig durch B2–B12 verursachte Laufzeitregression nachgewiesen; deshalb keine Änderung an Anwendungs-PHP, CSS, JavaScript, Layouts, Icons oder Fachlogik. B13 ergänzt ausschließlich gezielte Integrationsprüfungen und Dokumentation. Authentifizierte vollständige ERP-Abläufe, reale Entitlements, Safari/WebKit, Firefox-Tastatur/Fokus und Screenreader sind nicht abschließend abgenommen. Die bestehenden globalen Layout-/Katalogfehler und der unabhängige Sales-Fixturefehler bleiben offen.

Die B12-Empfehlung, Header/Kataloge als nächsten Block zu korrigieren, wird durch den ausdrücklich engeren B13-Auditauftrag **nicht umgesetzt**. Keine weitere Migration, keine Datenbank-/gespeicherten Inhaltsänderung, keine produktiven Eingriffe.

## Lokale Commitbasis

| Stufe | Tatsächlich vorhandener Commit / Message |
| --- | --- |
| B2 | `b27f55e6` – `add shared button presentation and inquiry pilot` |
| B3 | `2af50303` – `add isolated svg button icons` |
| B4 | `20ebf9d9` – `migrate inquiry buttons to svg` |
| B5 | `aaca1571` – `migrate shared contact and item toolbars` |
| B6 | `f23f05fb` – `migrate tasks toolbar to svg` |
| B7 | `b523e3b4` – `consolidate remaining standard toolbars` |
| B8 | `30db90b2` – `audit button quality` |
| B9 | `9ed0619f` – `unify selection apply buttons` |
| B10 | `96549509` – `unify standard list actions` |
| B11 | `83a51db1` – `improve mobile list actions and inventory buttons` |
| B12 | `27f18ce3` – `unify standard filter buttons` |

Dazwischenliegende Dokument-/Recovery-/Geocodingcommits bleiben unverändert. Kein GitHub-Abgleich, Merge, Rebase oder Übernehmen fremder Änderungen. B1 ist seit B8 versioniert; seine historischen Aussagen „unversioniert“ sind keine aktuellen Git-Anweisungen. SHA-256 weiterhin `bc142252c7400fb344ddeaee84acdd6ebaf1b008c8be37b423b68940ea416721`. `tools/document-historical-recovery.php` bleibt unverändert, SHA-256 `2368528ed004d4a0b20bac2556562bdff0478680100737e66725b6185d375663`.

## Verifizierter Migrationsumfang

`python3 tests/ui/button-inventory.py --json` und `php tests/ui/button-inventory.php` wurden erneut ausgeführt. **Beide vollständigen JSON-Ergebnisse sind mit den vorhandenen B12-Abschlussartefakten wertgleich.** Keine zusätzliche Aktivierung oder Abweichung von der Inventur.

| Quelle unter `application/modules/` | Tatsächlich migrierte Felder | Begrenzung |
| --- | --- | --- |
| contacts/forms/Inquiry.php | save | Nativer Inquiry-Status-Submit, Primary. |
| contacts/forms/InquiryToolbar.php | filter, reset, clear | Eigener Inquiry-Pilot. |
| contacts/forms/InquiryToolbarInline.php | view, edit | Eigene Inquiry-Liste. |
| contacts/forms/Toolbar.php | add, edit, copy, filter, reset, clear; apply | Exakte Klasse, contacts/contact, Index/Search/Select/Edit/View; Apply nur Select/Search-Select. |
| contacts/forms/ToolbarInline.php | view, edit, copy; apply | Exakte Klasse/Controller, Navigation/Kopie nur Index/Search-Index; Apply nur Select/Search-Select. |
| items/forms/Toolbar.php | dieselben sieben wie Kontakte | Exakte Klasse, items/item, gleiche Grenzen. |
| items/forms/ToolbarInline.php | edit, copy; apply | Gleiche Grenzen; kein View-Feld vorhanden. |
| tasks/forms/Toolbar.php | add, edit, copy, filter, reset, clear | Exakte Klasse, tasks/task, fünf Standardaktionen; kein Apply-Feld. |
| tasks/forms/ToolbarInline.php | view, edit, copy | Exakte Klasse/Controller, Index/Search-Index. |
| admin/forms/Toolbar.php | add, edit, copy, filter, reset, clear | Exakte Klasse; country, uom, deliverytime, manufacturer, warehouse, shippingmethod; fünf Standardaktionen. |
| admin/forms/ToolbarInline.php | copy | Dieselben sechs Controller, Index/Search-Index. |
| statistics/forms/Toolbar.php | filter, reset | Exakte Klasse; customer, quote, turnover; nur Index/Search. |
| application/views/helpers/Toolbar.php | gemeinsamer Clear-Filterchip | Ein Helperweg: 13 Index- und neun Selectkontexte; exakte Klasse, Viewkontext und vorhandenes Filter-SVG-Opt-in erforderlich. |

Zähleinheiten bleiben getrennt: **45 Feldkonfigurationen / zwölf Formularquellen**, **84 Controller-/Feldpaare / 13 Gruppen**, zusätzlich **ein Helperweg / 22 logische Kontexte**. **109** moderne Controls der neun inaktiven Index-Referenzseiten mit je zwei Fixturezeilen sind eine andere Grundgesamtheit; aktive Chips kommen bedarfsabhängig hinzu. Keine Fortschrittsprozente daraus ableiten.

Quellinventur unverändert: 1.074 Dateien, 243 literale DEEC-Konfigurationen in 49 Dateien, 147 lexikalische Buttonanfänge, 79 Buttonklasselinks, 25 alte Helperaufrufe, 13 ZF1-Submits. 34 Toolbarformulare/acht Helper; elf Toolbarformulare teilweise SVG, 23 vollständig Legacy. Zehn von 44 Actions-Listenmodellen teilweise migriert; 34 außerhalb. Die vollständigen Legacy-/Assetlisten und methodischen Grenzen stehen in der Gesamtinventur; keine Altlast entfernt.

Unverändert ausgeschlossen: Delete samt Admin-`media-delete`-Sondervertrag, Pin/Positionen/Set/Sortierung, komplexe Massenaktionen, Dokumente/PDF/Export, Mail, Zahlung, Bestands-/Preis-/Statussonderaktionen, allgemeine Formularsubmits, MultiForm-/JS-Factories, Shop/CMS/gespeicherte Inhalte, Installer und Widgets. Kontakte-/Artikel-Auswahl ist keine allgemeine Massenaktionsfreigabe. Technische Namen verbliebener Legacycontrols werden nicht als Namen migrierter Controls gezählt.

## Komponenten-, Integrations- und Sicherheitsprüfung

| Fundstelle | Ergebnis / Grenze |
| --- | --- |
| `library/DEEC/Form.php:1360`, `:1443`, `:1526`, `:2028` | `canRenderElement()` und Readonly vor derselben Präsentationsroutine; native Typen/Name/Value, einmaliges Text-/Attributescaping und temporäre Rowattribute bleiben erhalten. Der Renderer entscheidet keine ACL-/Tenant-/Featureberechtigung. |
| `application/views/helpers/Button.php` | Moderner Helper delegiert an DEEC_Form; alter achtparametriger Helper bleibt Legacy. Keine doppelte moderne Renderlogik. Direkte Aufrufer bleiben für erlaubte URLs, Übersetzung und Sichtbarkeit verantwortlich; die Präsentation ist kein HTML-/URL-Sanitizer. |
| `Form.php:164`, `:2085`, `images/icons/` | 15 explizite semantische Keys / 14 geprüfte lokale Geometrien, Clear→Close. Feste versionierte Datei, keine Benutzer-/DB-SVGs, externen Referenzen, Skripte oder Iconinitialisierung. Lucide 0.468.0, Pin `f12b0de177fbc2a6795e99be065887e72b237123`, ISC und Feather-MIT samt Manifest unverändert. |
| `css/ui.css:717–841` | Opt-in `.dw-btn--control`, konsistente Größen/Varianten, 8-px-Gap, currentColor, dekorative SVGs. Keine PNG-Doppelbilder oder leeren Icons in den geprüften Ausgaben. Alte globale Regeln und späte Chipregeln ergeben keine nachgewiesene neue Kollision. |
| `css/ui.css:2322–2345` | B11 weiterhin nur mobile Kontakt-/Tasks-Indextabellen in Full/Plain; 94-px-Aktionsinhalt, zwei 44-px-Controls, vorhandene Scrollfläche. Keine Select-/Desktop-/andere Modulaktivierung. |
| `layout.phtml:65`, `plain.phtml:54`, `site.phtml` | Full und Plain haben gleiche begrenzte Symbolbedingungen, genau ein Block pro Dokument; iframe eigener Block, AJAX keine Definition. Inquiry bleibt absichtlich formulargebunden. Site unverändert. Wiederholte Guardlisten sind ein Wartungsrisiko, aktuell konsistent. |
| `Toolbar.php:167`, `:247`, `:284` | Disclosure und Chips nur in B12-Scopes, eindeutige Panel-ID; negativer Unterklassen-/Fremdcontroller-/PNG-/Edit-/View-Kontext bleibt Legacy. Keine globale automatische Aktivierung. |
| `js/global.js:6`, `:2226`, `:3121`, `:3444` | Seit vor B2 ausschließlich Capture-Sperre sowie B12-Popuprefresh/ARIA-Synchronisierung hinzugekommen. Loading-Restore, Requestsperren, Submitter, Auto-Save, Auswahl und fachliche Dispatcher unverändert. Capture schützt auch SVG-Kindklicks. |
| Rechte/Tenants/Sharing | PHP- und isolierte HTTP-Negativtests bestehen. `Permission.php`, ACL, Bootstrap, TrashController, DEEC_Controller_Action, DEEC_List und DEEC_Display haben seit vor B2 keinen Diff. Erlaubtes Parent-/Child-Sharing bleibt separat geprüft; UI-Ausgabe erteilt keine Objektberechtigung. |
| SaaS / Tasks | `hasModule()` ist kein Buchungsrecht. Tasks hat weiterhin keine reguläre Modulrechtsspalte; Admin wird durch ACL geschützt. Site-`hasFeature()` betrifft ausgeschlossene Wege. Keine vollständige interne On/Off-Entitlementmatrix nachgewiesen, keine neue Rechteentscheidung erfunden. |

## Tatsächlich ausgeführte Tests

Alle UI-Daten/Identitäten sind synthetisch, Geschäfts-AJAX und Navigation abgefangen. Die PHP-Tests verwenden bestehende Memoryadapter. HTTP-Tests verwenden temporäre Sessions bzw. isoliertes Apache mit Memorydaten. Keine echte Anmeldung im Kundenbestand, kein Produktionsbootstrap, kein SMTP-/DB-Zugriff oder echter Copy/Apply/Status-Schreibvorgang.

| Aufruf, jeweils unter tests/ | Ergebnis |
| --- | --- |
| `php ui/buttons.php`, `ui/button-icons.php`, `ui/inquiry-buttons.php` | PASS: 165 / 651 / 697 Assertions, zusätzlich eingebundene Baselines. |
| `php ui/toolbar-buttons.php`, `ui/tasks-toolbar.php`, `ui/remaining-toolbars.php` | PASS: 2.259 / 1.428 / 27.105. |
| `php ui/apply-buttons.php`, `ui/list-buttons.php`, `ui/mobile-list-actions.php`, `ui/filter-buttons.php` | PASS: 1.459 / 7.374 / 256 / 3.070. Filterprüfung nach Fixture-Ergänzung erneut ausgeführt. |
| `python3 ui/buttons.py`; `--svg --audit`; `ui/inquiry-buttons.py` | PASS: 169 / 204 / 796. PNG, sicherer SVG-Katalog, strenge isolierte Icon-CSP, eigenes iframe, Forced Colors, Zoom, native Submit/Reset/Required, Linkschutz, Auto-Save. |
| `python3 ui/toolbar-buttons.py`; `--tasks`; `--remaining` | PASS: 1.000 / 524 / 3.247. Reale Layouts, Kopf/Fuß/Select/Edit/View, DE/EN × 390/768/1280, echte Tastatur, Fokus/Hover, Disabled/Loading, Copy-Locks und negative Kontexte. |
| `python3 ui/apply-buttons.py` | PASS: 302. Echtes iframe/Modal mit synthetischem Processes-Parent, Row-ID vs. Checkbox, Parent/Set, Fehler/Retry/Pending/Escape, Callback-/Auto-Savegrenzen. |
| `python3 ui/list-buttons.py`; `--mobile` | PASS: 1.470 / 822. Neun reale Listen, dynamische SVG-Aktionen/Targets, Touch/Scroll/Zeilen, lange Inhalte, Readonly und unveränderte Sonderkontexte. |
| `python3 ui/filter-buttons.py` | PASS: 1.564. Alle 13 Gruppen, DE/EN × vier Breiten, Array-/Datum-/Cookie-/Resetwerte, Disclosure, AX-Namen, Fehler/Retry, markiertes Plain-Popuprefresh, negative Legacyfälle. |
| `python3 ui/notifications.py` | PASS: 1.985 bei 1280 und 1.985 bei 390 px. Bestehende Dialog-/Dokument-/Mail-/Positions-/Auto-Saveverträge mit strikt abgefangenem I/O. |
| `php security/{permissions,admin-access,tenant-access,bootstrap-auth}.php` | PASS: 415 / 68 / 269 / 29. Erwartete negative NO_PERMIT-Ausgaben. |
| `python3 security/session-http.py`, `security/tenant-access-http.py` | PASS: 25 / 48. Session/Cookies/Regeneration, ACL/Objekt/AJAX/Trash, Root und Unterverzeichnis. Identitäten durch Fixture gesetzt; kein echter Passwortlogin. |
| `php security/{contact-inquiries,form-input-validation,inquiry-dynamic-form}.php` | PASS: 46 / 20 / 31. |
| `php contacts/contact-deletion.php`, `sales/quote-monitoring.php`, `lists/summary.php` | PASS: 16 / 293 bzw. zentrale Footer-/Summenprüfung. Simulierter Löschfehler erwartbar; keine Delete-Migration. |
| `php security/notifications.php purchases` | PASS: 24. |
| `php security/notifications.php` | **FAIL, unabhängig:** 370 Checks erfolgreich; dann `Sales_Service_DocumentRevisionService` fehlt in `sales/views/helpers/Finish.php:12`; Fixtureabbruch `notifications.php:211`, Exit 255. Seit B2 bekannt; keine Korrektur im Buttonaudit. |
| Inventur und Snapshots | Source-/Runtimeinventur identisch zu B12; 480 B5-, 350 B6-, 1.900 B7- und 80 B9-Inline-Konstruktorkontexte erneut erzeugt. Historische B8-Hashes gelten wegen der späteren B9/B10-Migration nicht als aktueller Sollstand. Aktuelle Hashliste: `/tmp/dewawi-b13/snapshots.json`. |

Assertions aus eingebundenen Suites werden nicht zu einzigartigen Testfällen addiert. Temporäre Logs/JSON/Screenshots: `/tmp/dewawi-b13/`; alte Runner erzeugen zusätzlich ihre bisherigen B3–B12-Dateipräfixe unter `/tmp` neu.

### Neue gezielte Integrationsprüfung

`tests/ui/button-release-audit.py` verwendet die vorhandenen PHP-Fixtures, echte Listenmodelle, Helper, Layouts, CSS und jQuery/global.js. Der optionale **`audit`-Batchmodus** in `filter-buttons.php` liefert zusätzlich dieselben Index-/Select-/Edit-/View-Profile und passende reale Searchfragmente. Standardaufrufe und bestehende Assertions bleiben unverändert. Keine zweite HTML-/Buttonarchitektur und keine neue installierte Abhängigkeit; Firefox nutzt das vorhandene Pythonmodul `websockets` und seine lokale BiDi-Schnittstelle.

Geprüft werden 44 Profile je Sprache: neun CRUD-Gruppen mit Index/Select/Edit/View, vier weitere Index-Filterkontexte (Inquiry/drei Statistiken), zusätzlich echter Inquiry-Index/Edit jeweils Full/Plain. **Chromium ergänzt nur die fehlende 320-px-Matrix**, statt alle bestehenden drei Breiten nochmals redundant abzudecken. **Firefox prüft diese Profile bei 320/390/768/1280 px.** Ziele: einmalige Symbole/IDs, sichere übersetzte moderne Namen, native Typen, Touchhöhe, SVG-Geometrie/currentColor/Gaps, Disabled/Loading samt Node-Restore und AJAX-Chips/Zeilenaktionen. Neue Searchausgabe muss Zeilencontrols erhalten; delegierter SVG-use-Klick auf View/Edit muss dieselbe Rowroute liefern. Es wird keine Geschäftsroute wirklich aufgerufen.

| Umgebung / tatsächlicher Aufruf | Ergebnis und Grenze |
| --- | --- |
| `python3 tests/ui/button-release-audit.py --engine=chromium --output=/tmp/dewawi-b13` | **PASS: 628 Assertions**, 88 Seitenprofile bei 320 px, zusätzlich zu den bestehenden 390/768/1280-Prüfungen. |
| `python3 tests/ui/button-release-audit.py --engine=firefox --output=/tmp/dewawi-b13` | **PASS: 2.152 Assertions**, 352 Seiten-/Breitenprofile. Firefox 160.0.1, isoliertes Headless-Profil. Rendering und **skriptierte** Interaktion; Tastatur/Fokus ausdrücklich eingeschränkt. |

Firefox meldet lokal trotz `browsingContext.activate`/nativem Seitenrandklick `document.hasFocus()=false`. Dieselbe **reine HTML-Minimalfixture ohne ERP-CSS/JS** liefert vertrauenswürdige Space-/Enter-Keyevents, aber keinen nativen Buttonclick und kein `:focus-visible` (`firefox-input-probe.json`). Das ist kein Nachweis einer Buttonregression. Der Runner meldet diese Teilprüfungen ausdrücklich **NOT TESTED** und ersetzt sie für die separat benannte skriptierte Interaktionsprobe durch DOM-Clicks. Bei tatsächlich fokussiertem Dokument bleibt die strenge native Tab/Shift-Tab-/2-px-Fokusprüfung aktiv. Kein vollständiger Firefox-Tastatur-/Hover-/Screenreadernachweis aus diesem Lauf.

## Responsive und visuelle Befunde

| Messung | Chromium 157.0.8103.78 | Firefox 160.0.1 | Einordnung |
| --- | --- | --- | --- |
| Kontakt/Tasks, normale Fixturezeilen, 320/390 px, DE/EN | 115 px | 115 px | B11-Korrektur bleibt wirksam, 44-px-Ziele erhalten. Keine feste Zeilenhöhe; lange Inhalte separat in B11-Baseline geprüft. |
| Deutscher Header, 320 px | Metablock rechts x=334 px | x≈348,6 px, Dokument 349 px | **FAIL bestehend.** Globaler Header ohne Flexwrap, keine neue SVG-Ursache. Headerregeln unverändert seit vor B2. |
| EN-Umsatzreport, 390 px | Dokument 528 px | Dokument 525 px | **FAIL bestehend.** Technische lange Reportüberschriften; Chromium-Legacyvergleich in B7-Runner besteht. Toolbar bleibt passend. Abweichende native Schriftmetriken sind keine neue Toolbaraktivierung. |
| 768/1280 px und weitere mobile Profile | Baselines und neue Messungen | Neue Renderingmatrix | Buttonprüfungen gelten innerhalb des jeweiligen Tabellen-/Toolbarbereichs. Bekannte Gesamtseitenüberläufe werden separat gemessen und nicht als bestanden ausgegeben. |

Unverändert bestehen globale technische EN-Menü-/Breadcrumbkeys, einzelne DE/EN-Spalten-/Reportkeys und hohe mobile Menü-/Adminsidebarblöcke. Moderne geprüfte Buttonnamen sind übersetzt; davon lässt sich keine vollständige Lokalisierung der Gesamtansicht ableiten. Keine globale CSS-/Katalogkorrektur in B13.

Firefox zeigt den bestehenden Headerengpass auch auf englischen 320-px-Fixtureseiten (Metablock rechts x≈327,27 px). Die Kopfgestaltung hängt von nativen Schriftmetriken ab; daraus folgt keine neue Buttonregression. Nicht als allgemeines EN-/320-PASS behandeln.

Repräsentative neu erzeugte Chromium- und Firefox-Bilder wurden gesichtet; klare SVGs, erhaltene PNG-Sonderbuttons, Wrap und kompakte mobile Aktionen. Das ist kein menschlicher Pixelvergleich aller Profile und keine fachliche Unterschrift. Native Tooltips, langer realistischer Datenbestand, Touch/Wischen, Fokusverlauf in echtem Fenster und aktive Themes bleiben manuell abzunehmen.

## Freigabematrix

PASS bezeichnet ausschließlich den genannten tatsächlich geprüften Umfang. NOT TESTED ist kein PASS; FAIL bestehend ist keine durch B13 korrigierte Regression.

| Gate | Status | Beleg / Blocker / nötiger Schritt |
| --- | --- | --- |
| Inventur/Opt-in-Grenzen/Legacy | PASS | 45/84/13 plus Helper/22 identisch zu B12; PHP-Negativmatrizen, keine neue Aktivierung. |
| HTML/SVG/Varianten/Größen/Namen | PASS | PHP und Chromium, Firefox-Renderingmatrix. Native Links/Submits, keine Leericons oder Doppelansagen im geprüften Chromium-AX-Baum. |
| SVG-Sicherheit/Lokalität/Lizenzen | PASS | Statische Allowlist, XML-/Feindinputtests, fester Pfad, vorhandene ISC-/MIT-Notices. |
| Chromium DE/EN × 320/390/768/1280 | PASS, Buttonumfang | B2–B12-Baselines plus neue 320-px-Matrix; Gesamtseitenfehler unten getrennt. |
| Firefox DE/EN × vier Breiten | PASS, Rendering/skriptierte Interaktion | Native Firefox-Tastatur/Fokus/Hover damit nicht freigegeben. |
| AJAX/Auto-Save/Filter/Apply/Popup/Submit | PASS, Fixtureverträge | Abgefangenes I/O, echte Templates/Dispatcher; gleiche native Attribute, Targets, Parent/Set, Locks, Fehler/Retry, Loading-Restore. Kein echter Backend-Schreibnachweis. |
| ACL/Mandanten/Parent-Child | PASS, Memory/HTTP | Positive/negative Serverprüfungen; keine neue Daten-/Rechteentscheidung. Reale vollständige Rollenmatrix separat offen. |
| Chromium Tastatur/Fokus/Kontrast | PASS, begrenzt | Native Tab/Shift-Tab/Enter/Space; 2-px-Fokus, Forced Colors, coarse pointer, 200%-CSS-Zoom und AX-Namen. Textkontrast Default 14,68:1 / Primary 8,66:1 / Danger 6,95:1 / Ghost 13,68:1. Kein vollständiges WCAG-Zertifikat. |
| Globale mobile Headerdarstellung bei 320 | **FAIL bestehend** | Korrektur in eigenem Layoutauftrag oder explizite begrenzte Freigabe durch Verantwortliche. |
| Globale EN-Menü-/Reportlokalisierung, mobiler Umsatzreport | **FAIL bestehend** | Katalog-/Layoutauftrag oder ausdrücklich ausgenommener EN-Umfang vor Freigabe. |
| Sales-Notification-PHP-Fixture | **FAIL unabhängig** | Fehlende Service-Abhängigkeit separat im Dokument-/Testkontext beheben; nicht als Button-PASS zählen. |
| Echter Login → ERP-Geschäftsview mit Testmandanten/-rollen | **NOT TESTED** | Keine belegbar isolierte vollständige ERP-Testinstallation nachgewiesen. Nicht durch Kundenkonfiguration/Adminsession ersetzen. |
| Reale SaaS-On/Off-/Tasks-Rollenmatrix | **NOT TESTED** | Fachlichen Entitlementvertrag/Testkonten bereitstellen und serverseitige Ablehnung plus legitimes Sharing abnehmen. |
| Firefox native Tastatur/Fokus/Hover | **NOT TESTED** | Headless-Fokusgrenze reproduziert ohne ERP; mit fokussiertem echten Fenster prüfen. |
| Safari / WebKit | **NOT TESTED** | Safari/macOS nicht vorhanden, kein WebKitWebDriver. Installiertes WebKitGTK 2.52.6 versuchsweise mit isoliertem Broadway gestartet; ungültiges `innerWidth=-30720` trotz 320-px-Allocation, auch mit Anzeigeclient. Keine verlässliche Browsermatrix und kein Safari-Ersatz; keine Pakete nachinstalliert. |
| Screenreader / physische Geräte / native Browserzoom- und Tooltipabnahme | **NOT TESTED** | Kein NVDA/VoiceOver-Lauf, kein Touchgerät; AX-/CSS-Zoomproben ersetzen dies nicht. |
| Fachliche/visuelle Produkt-/Themefreigabe | **NOT TESTED** | Verantwortliche müssen Semantik, Dichte, lange DE/EN-Inhalte und verbleibende Mischdarstellung bestätigen. |
| Uneingeschränkte Deploymentfreigabe | **FAIL / offen** | Offene Gates und bekannte Fehler ausdrücklich entscheiden; kein Deployment im Audit. |

## Konkreter Freigabeplan und nächste Arbeiten

1. **Entwicklung/QA:** B13-Commit als Kandidaten festhalten, reproduzierbare Tests mit obigen Befehlen ausführen; bekannte unabhängige Notification-Fixture separat behandeln. Keine neuen Module aktivieren.
2. **Plattform/QA:** Nachweislich isolierte ERP-Umgebung mit synthetischen Daten, zwei Mandanten und Parent-/Child-Freigabe bereitstellen. Admin, View-only, Add/Edit, ohne Modulrecht und gebuchte/nicht gebuchte Features dokumentieren; Outbound-Mail/API begrenzen. Bestehende Kundensessions nicht übernehmen.
3. **QA:** Echter Login und alle 13 Gruppen: Kopf/Fuß, Index/Select/Edit/View, Filter/Datum/Cookies, Auswahl/Apply, Popuprefresh, Rowtargets gegen fremde Auswahl, Copy-Lock/Fehler/Retry, native Submits und Auto-Save/Opt-out prüfen. Cross-Tenant-/ACL-/Entitlementablehnung serverseitig und erlaubtes Sharing bestätigen. Schreibfälle ausschließlich mit Testdaten.
4. **Design/Produkt/Lokalisierung:** Header- und EN-Probleme gesondert lösen oder deren klar begrenzte Ausnahme schriftlich entscheiden. DE/EN × 320/390/768/1280 mit langen Namen/Filterwerten, aktiven Themes und echten Listen/Reports sichten. Kein pauschales Layout-PASS aus Buttongeometrie.
5. **Accessibility/Browser-QA:** Fokussiertes Firefoxfenster und unterstützte Safari/iOS-/WebKitversionen, reale Tastatur und Touch, NVDA/VoiceOver, Zoom/Textvergrößerung, Tooltipnamen, Busy-/Fehleransagen, Popupfokus und Tabellenwischen abnehmen. Ergebnis je Plattform protokollieren.
6. **Freigabeverantwortliche:** Pro Gate PASS/FAIL/NOT TESTED, verantwortliche Person, Datum, Kandidatenhash und akzeptierte Ausnahmen festhalten. Erst bei erfüllten/ausdrücklich entschiedenen Gates einen separat autorisierten Rollout planen. Das Audit führt keinen Rollout aus.

Nächste technische Priorität bleibt die **separate globale Layout-/Lokalisierungskorrektur und reale Abnahme**, keine weitere Buttonmigration. Danach nur ausdrücklich beauftragte Standard-Delete-Abnahme mit Confirm/Cancel/Kaskaden/Rechten/Tenants/Teilergebnissen; anschließend Positions-/Set-/Pin-/Sortierverträge. Dokument/Mail/Zahlung, normale Submit-/dynamische Factories und Shop/CMS bleiben eigene risikogerechte Blöcke. Keine automatische Fortsetzung mit B14.

## Eigene Änderungen und Abschlusskontrolle

Nur `tests/ui/button-release-audit.py`, dieser Bericht, der optionale Testbatch in `filter-buttons.php` und Fortsetzungsverweise in Inventur/B12. Keine Laufzeitkorrektur erforderlich; die Firefox-/WebKit-Testumgebungsgrenzen werden dokumentiert und nicht durch Änderungen an produktivem Fokus-/Event-/CSS-Verhalten verdeckt.

PHP-Lint, Python-AST, Berichtlinks, `git diff --check`, Status und staged Diff werden vor dem lokalen Commit geprüft. Ausschließlich explizite eigene Pfade stagen; fremde Dateien und den unveränderten B1-Bericht nicht aufnehmen. Keine Datenbankmigration oder manuelle Datenbanknacharbeit. **Kein Push, Merge oder Deployment.** Der lokale B13-Hash samt kurzer englischer Message wird im Abschlussbericht genannt.
