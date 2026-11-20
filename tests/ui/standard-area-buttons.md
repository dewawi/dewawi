# B18 – Standardbereiche und systemweite Button-Restinventur

Stand: 20.11.2026. Ausgangs-HEAD `7ceb5e6b` (`release version 1.1.8`), Arbeitsverzeichnis zu Beginn sauber. Grundlage: aktuelle `AGENTS.md`, tatsächliche B1–B17-Implementierung einschließlich `fd4b80df`, [Gesamtinventur](button-system-inventory.md), [B17](delete-apply-buttons.md) und die nach B17 hinzugekommene ERP-Shopbestellintegration. Anwendungsversion bleibt 1.1.8.

## Umsetzung und Grenzen

B18 ergänzt nur explizite Präsentationsmetadaten für drei bestehende Standardbereiche. Vorhandene DEEC-Formulare, Toolbar-/Bottom-Helper, Listenmodelle, lokaler SVG-Katalog und `global.js` bleiben die Grundlage. Keine neue Renderingarchitektur, keine SVG-/CSS-/JavaScriptänderung und keine Änderung von Controllern, Listenmodellen oder fachlichen Aktionen.

| Kontext | Haupttoolbar | Inline | Kontextgrenze |
| --- | --- | --- | --- |
| `processes/process` | add/edit/copy/delete/filter/reset/clear | view/edit/copy/delete | Exakte Klassen; Haupttoolbar Index/Search/Select/Edit/View, Inline nur Index/Search mit Indexkontext. |
| `items/pricerule` | dieselben sieben Felder | edit/copy/delete | Bestehende Items-Klassen gezielt erweitert; bestehendes Item-Select-Apply bleibt auf `items/item` beschränkt. Pricerule-Apply bleibt in Index und Select Legacy. |
| `admin/shop` | dieselben sieben Felder | ausschließlich copy | Bestehende Admin-Toolbarallowlist erweitert; Inline nur eigene Klasse/Shop/Index bzw. Search-Index. Admin-`show`-Callbacks und Controller-ACL unverändert. |

Das sind **29 zusätzliche Controller/Feld-Zuordnungen**: 21 Haupttoolbar- und acht Inlinezuordnungen. **Elf zusätzliche eindeutige Klasse/Feld-Konfigurationen** entstehen in den zwei bisher vollständig alten Processes-Formularen; Items-/Adminfelder werden wiederverwendet. Kopf-/Fußtoolbar und Searchfragmente erhalten dieselbe Darstellung. Textbuttons behalten ihren sichtbaren Text; Inlinebuttons bleiben Icon-only. Verwendet werden nur bestehende SVG-Keys; Clear nutzt den bestehenden Close-Alias.

`Toolbar::usesSvgFilters()` erhält genau die drei zusätzlichen Klassen-/Controllerzuordnungen. Dadurch besitzen die vorhandenen Index-Filterbuttons korrekte `aria-controls`/`aria-expanded`, die Panels eindeutige IDs und aktive Chips den bestehenden SVG-Helperweg. Select hatte bisher keinen Filter-/Resetbutton im Kopf und erhält keinen neuen; hier werden Keyword-Clear, Panel-ID und dynamische Filterchips geprüft. Reset belässt das offene Filterpanel wie bisher offen.

**Explizite Ausnahmen:** Admin-Inline-Delete mit `data-action=media-delete` und zusätzlich von Listen erzeugtem `data-action=delete` bleibt vollständig unverändert. Nicht angefasst: PDF, Pin, Status-/Zuweisungs-/Liefer-/Zahlungssteuerung, Positionen/Sets, Preisregelberechnung/Priorität/Kategorien/Gültigkeit, Shopkonfigurationen/Slugs/Redirects/Seiten/Menüs, Export/Import, öffentliche Shops/CMS und Drittanbieter. Auch die separate neue ERP-Shopbestelltoolbar ist keine B18-Migration.

### Attribute und zugängliche Namen

Alle bisherigen nativen/Event-/Zielattribute bleiben erhalten: `id`, `name`, `value`, `type`, Klassentokens, `data-*`, `rel`, `onclick`, `href` und vorhandene ARIA-Namen. Im vollständigen Browser-DOM werden die Toolbar-/Rowbuttons zusätzlich gegen dieselben sechs Formulardateien aus dem Ausgangscommit verglichen. Zulässige Differenz sind die neuen Präsentationsklassen/SVG-Kinder sowie die ausdrücklich nötigen zwei Disclosureattribute des Filterbuttons.

Anders als bei früheren Migrationen werden die automatischen Inline-Namen in B18 **nicht nebenbei umbenannt**. Einige bleiben technische Fallbacks wie `EDIT`/`COPY`/`DELETE`/`VIEW`; explizite Namen und Tooltipattribute bleiben ebenfalls unverändert. Das ist eine dokumentierte bestehende A11y-Grenze entsprechend der B18-Erhaltungsanforderung. Eine spätere übersetzte Namensbereinigung braucht eine eigene festgelegte Schnittstelle. Dekorative SVGs bleiben `aria-hidden=true`, `focusable=false`, ohne zusätzlichen Tabstopp.

## Verfolgter Funktions- und Sicherheitsvertrag

Geprüft: `DEEC_Form`/Button-Helper, `DEEC_List::renderActionsCell`, `DEEC_Controller_Action::{buildListView,getToolbarClass,getToolbarInlineClass,copyAction}`, betroffene Controller/Entity-/Listenmodelle, beide Toolbarhelper, vorhandene CSS-Zustände und `DewawiToolbar::{actionConfig,runAction,resolveSelection,getButtonTarget,actions}`, `copyEntities`, `trash`, Requestsperren, Loading und `search`.

- Kopf-/Fußaktionen verwenden bestehende Auswahl; Rowaktionen weiterhin ihre eigene ID. Vorgänge zeigen Edit oder View abhängig vom bisherigen Readonlystatus. Keine geänderten Sichtbarkeits-/Admincallbacks.
- Copy behält JSON-Vertrag und bestehende POST-URL. Copy/Trash-Requests werden im Browser abgefangen und mit kontrollierten Erfolgs-/403-/Teilantworten beantwortet; die tatsächlichen Handler einschließlich Sperren, Fehleranzeige, Retry und Search laufen. Kein fachlicher Datenbankvorgang wird ausgeführt.
- Delete behält übersetztes `deleteConfirm`, Abbruch, JSON-Payload mit Modul/Controller/IDs, Disabled/Busy/Loading, überlappende Requestsperren, Teilergebnis und leere Liste. Search verwendet **echte lokale HTTP-Anfragen** und Originalcontroller/Plugin/Listen-/Layoutausgabe; die geänderten Fixture-Rowmengen sind ausschließlich synthetischer Requestzustand.
- Keine Änderungen an ACL, SaaS-Prüfungen, Moduleigentümern, Mandanten-/Parent-Child-Regeln, Validierung, Transaktionen oder Auto-Save. Items-Pricerule-Rechte werden weiterhin über das bestehende Itemrecht aufgelöst. Die spezifischen Copy-Nebenwirkungen von Shopkonfigurationen oder echten Prozess-/Preisregeldaten sind keine durchgeführten Schreibtests.
- Fremde Mandanten laden den Datensatz im isolierten MVC-Nachweis nicht. Eine konfigurierte Modulowner-Zuordnung allein erlaubt im bestehenden generischen Editpfad keinen fremden `Client.id`-Datensatz: getById verwendet dort den eigenen Mandantenscope. Dieser Befund ist vor/nach B18 gleich; B18 führt keine neue Sharingfreigabe ein. Die vorhandenen positiven Parent-Child-/Bootstrapregressionen bleiben bestehen. Reale Mandanten-/SaaS-Paketkombinationen sind keine abgeschlossene Abnahme.

## Geänderte Dateien

Laufzeit (sieben Dateien):

- `application/modules/{processes,items,admin}/forms/Toolbar.php`
- `application/modules/{processes,items,admin}/forms/ToolbarInline.php`
- `application/views/helpers/Toolbar.php`

Tests/Inventur:

- Neu `tests/ui/standard-area-buttons.php`: Metadaten-/HTML-Attribute-/Namens-/Scope-/Rechteprüfungen und Unterklassennegativtests.
- Neu `tests/ui/standard-area-browser.py`, eingebunden über `button-release-audit.py --standard-areas`: vollständiges MVC/HTTP und historische Formularvergleiche in beiden Engines.
- `button-page-integration.php`: zusätzliche ausdrücklich begrenzte Read-only-Fixturerouten, zwei synthetische Rows, eigene Testrollen/-mandanten und Auswahlzustände. DB-Verbindungen/Schreibvorgänge bleiben gesperrt; Attachmentverzeichnisvorbereitung und GET-Datensatzlocks sind im Fixture deaktiviert. Kein produktiver Bootstrap und keine echten Dateien/Kundendaten. Historische Formularquellen werden nur aus einem temporären Verzeichnis geladen.
- `toolbar-buttons.php` unterstützt die neuen Kontexte und das bereits vorhandene Ordertoolbarformular im Memoryfixture. Der tatsächlich verwendete Benutzer wird an die Listenkontexte übergeben; bestehende Baselines bleiben erhalten.
- `remaining-toolbars.php` erweitert die bisherige positive Adminliste um Shop, `toolbar-buttons.py` ersetzt Shop in der alten Negativliste durch den weiterhin alten Config-Kontext.
- `button-inventory.php` ergänzt die drei B18-Gruppen und die zuvor nicht erfasste vorhandene Ordertoolbar. `button-inventory.py --remaining` verknüpft literale Stellen mit erfassten SVG-Kontexten, ohne Laufzeitvollständigkeit vorzutäuschen.
- Dokumentation: dieser Bericht, aktualisierte Gesamtinventur und [vollständiger Quellenanhang](button-remaining-inventory.md).

## Systemweite Restinventur

Neu ausgeführter identischer Quellscan: **1.083 Dateien**, **246 literale Formstellen in 50 Dateien** (237 Button/9 Submit), **149** lexikalische Buttonanfänge in 81 Dateien, **80** Buttonlinks in 34, **23** alte Helperaufrufe in sieben, **13** ZF1-Submitkonstruktoren. Alle Treffer mit Datei/Zeile und vollständigen erfassten Formnamen stehen im Quellenanhang bzw. reproduzierbaren JSON.

Der Nach-B17-Vorbestand ist inzwischen größer als die historische B17-Dokumentation: `Shops_Form_OrderToolbar` bringt drei unabhängig vorhandene SVG-Felder. Korrigierter aktueller Vorbestand **55 Felder / 99 Controller-Feld-Paare / 14 Gruppen**; B18 danach **66 / 128 / 17**. Der alte Scanner meldete vor B18 noch 52/96/13, weil er Order nicht kannte. Die Zähldifferenz 14 Felder besteht deshalb aus **elf B18-Feldern plus drei nacherfassten vorhandenen Feldern**.

17 SVG-produzierende PHP/PHTML-Dateien: 15 Formulare, ein Helper, eine View. 36 Toolbarformulardateien insgesamt; 22 ohne eigene SVG-Metadaten. 51 Listenmodelle, 44 mit deklarierter Actionszelle; 13 Modelle mit mindestens einer in den untersuchten Kontexten migrierten Standardaktion, 31 außerhalb dieser Zuordnung. Der Filterhelper unterstützt nun 28 logische Kontexte in 16 Gruppen; die 17. Metadatengruppe Order verwendet diesen Helper-Opt-in nicht.

Der neue Quell-/Runtimeabgleich findet 66 Formstellen mit erfasstem SVG-Kontext und **180 ohne solchen Nachweis**; keine SVG-Formquelle bleibt unzugeordnet. Dies ist ausdrücklich keine Behauptung von 180 produktiv sichtbaren Legacybuttons und kein Prozentwert. Dynamische Namen, bedingte Felder, gespeichertes HTML, Themes und Fremdwidgets besitzen andere Grundgesamtheiten. Der Referenz-DOM wächst von neun Seiten/133 Controls auf zwölf Seiten/180 Controls (neu 17 Process, 17 Pricerule, 13 Admin-Shop). Alle mit zwei synthetischen Rows, wiederholtem Kopf/Fuß und ohne aktive Chips.

Die statischen Altkandidaten wurden erneut gesucht: Mobile-Drawer hat weiter nur einen auskommentierten Aufruf (`MainMenu.php:18`) und seine Definition; Ordering hat keinen eigenen Literalaufruf; Rootsprites besitzen keinen bestätigten eigenen Literalverbraucher; `.language-flag` verweist weiter auf den unversorgten Sprite. Daraus folgt kein globaler Löschbeweis für Themes/gespeicherte Inhalte. Keine Assetdatei entfernt. Alle 14 SVG-Symbole und die vorhandenen PNG-Sonderaktionen bleiben erhalten.

## Tests und tatsächliche Ergebnisse

Die beiden abschließenden neuen Browserläufe bestehen jeweils mit **1.606 Assertions und 72 Profilen**, zusätzlich zu den tatsächlich gerenderten historischen Vergleichsdokumenten. Chromium besteht die 24 nativen Index-Tastatur-/Fokusprüfungen; Firefox markiert diese 24 Prüfungen ausdrücklich NOT TESTED. Sichtbare Pixel werden in allen 72 Profilen beider Engines nachgewiesen (mindestens vier gemessene Strichfarbpixel, keine bloßen 16×16-Flächen). Rohbelege liegen unter `/tmp/dewawi-b18/`; keine temporären HTML-/Screenshotdaten im Commit.

| Prüfung | Ergebnis |
| --- | --- |
| Vorherbaseline PHP B2–B17/Sicherheit | Alle 18 Befehle Exit 0; Logs `php-before.json`. Kein fortbestehender Sales-Notification-Fixturefehler. |
| Neue PHP-Suite | **PASS 8.108 Assertions**; DE/EN, neun Aktionen × zwei Kontextparameter, exakte Klassen, native/Event-/ARIA-Attribute, sichtbare Texte, unveränderte Spezialfelder, Rollen und Unterklassen. Roter Vorlauf ohne Opt-ins ist nachgewiesen. |
| B18 vollständiges MVC/HTTP in Chromium und Firefox | **PASS jeweils 1.606 Assertions / 72 DE/EN-Profile bei 320/390/768/1280 px:** Index/Edit/Select, native Attribute/Text-/ARIA-Vergleich gegen die Originalformulare, Symbole/Pixels, Filterchips, Suche/Reset/Clear, Navigation trotz fremder Checkboxauswahl, Copy-/Delete-Locks, Cancel/403/Retry, Teilergebnis und echte Search-Refreshes, leere Liste, Editredirect, ACL/Fremdmandant/Sharing-Vergleich. |
| Nachher PHP-Baselines | **PASS:** buttons 165, icons 651, Inquiry 697, toolbar 2.451, Tasks 1.488, remaining 28.835, Apply 1.459, Listen 7.586, Mobile 256, Filter 3.070, Dashboard 3.620, B17 1.738. |
| Nachher Sicherheit | **PASS:** permissions 450, tenant-access 269, admin-access 68, bootstrap-auth 29, contact-deletion 16; Notifications 382 + 64 + 24. |
| Vorhandene Chromiumbaselines | **PASS:** SVG/Audit 204, Inquiry 796, gemeinsame Toolbar 1.108, Admin/Statistik 3.571, Mobile 918, Apply/Modaliframe einschließlich 320 px 400, Filter 1.564, vollständiges Original-MVC B14/B15 852. |
| B16-Symbol-/Pixelbaseline | **PASS:** Chromium 420 / Firefox 388. Vollständige Dokumente, jeweils 14 Definitionen/ein Bankcontainer, Popup/iframe/AJAX, sichtbare Pixel und bestehende Aktionen. |
| B17-Funktionsbaseline | **PASS 1.184 Chromium-Assertions / 72 Profile** im separaten Abschlusslauf; keine neue Migration in B17-Kontexten. Der erste Lauf hatte einen einzelnen späten Lade-Timeout (`DewawiToolbar`/Fixture-Setup nicht verfügbar); unveränderte Wiederholung erfolgreich, ohne gelockerte Anforderungen oder Laufzeitfix. Ursache dieses einzelnen Timeouts nicht nachgewiesen. |
| Sicherheits-HTTP | **PASS 60 Apache-Assertions**, tatsächliche Objekt-/ACL-/AJAX-/Trashnegativfälle, Root-/Unterpfadinstallation, ausschließlich Memorydaten. |
| Syntax/Scanner/Diff | **PASS:** zwölf geänderte/neue PHP-Dateien gelintet, vier Pythondateien per AST geprüft, beide Scanner ausgeführt, Links und Diff geprüft; staged Umfang vor Commit. |

### Bestehende Befunde und NOT TESTED

- **Vorgänge, 768 px:** Tabelle bereits vor B18 breiter als ihr verborgenes Wrapperfenster: 1.057 px Scrollbreite bei 661 px Wrapperbreite in Chromium. Keine B18-Verschlechterung; Inhalte können dort abgeschnitten sein. Neue Tests vergleichen dieselbe tatsächliche Geometrie gegen die Originalformulare statt den bestehenden Fehler zu einem PASS für gutes Layout umzudeuten.
- Hohe Shopzeilen mobil bleiben bestehen; in den vollständigen synthetischen Daten etwa 548,63 / 247,13 px bei 320 px. Auch breite Tabellen, Zeilenhöhen und globaler deutscher Headerüberlauf werden vor/nach getrennt gemessen. Keine globale Layoutkorrektur in B18.
- **Bestehender FAIL des vollständigen Process-Viewfixtures:** Vor und nach B18 `count(null)` in `application/modules/processes/views/scripts/process/view.phtml:98`, weil das Positions-Viewmodel fehlt; dazu fehlende Datenfeldwerte. Das ist keine nachgewiesene B18-Regression und wurde nicht durch Änderungen am fachlichen View-/Positionspfad kaschiert. Constructor-/Helper-Viewverträge sind geprüft; keine Behauptung einer bestandenen vollständigen Process-Viewseite. Preisregel-/Shop-View haben keine eigene vorhandene Viewvorlage; deren volle Viewseite wird nicht als bestanden gemeldet.
- Headless-Firefox-Tastatur/Fokus wird nur dann als PASS bezeichnet, wenn tatsächlich nachgewiesen; false-Werte bleiben **NOT TESTED**. Chromium verwendet native Tab/Shift-Tab/Enter und sichtbare Fokusprüfung. Safari/WebKit, Screenreader, native Betriebssystem-Bestätigungsdialoge, reale SaaS-/Testrollenlogins und Drittanbieterinnere **NOT TESTED**.
- Bestätigen/Abbrechen läuft im Browsertest über kontrolliertes `window.confirm`; der bestehende übersetzte Parameter und die realen Handler werden geprüft. Keine Aussage zur nativen Dialogoberfläche oder zu tatsächlichen serverseitigen Copy-/Löschkaskaden auf Kundendaten.
- B16-Dateirechteanforderung unverändert: Der Webserver muss den lokalen Symbolkatalog lesen können. Kein neuer Produktionslesetest oder Produktiveingriff.

## Reproduzierbare Prüfung und Freigabe

```sh
php tests/ui/standard-area-buttons.php
php tests/ui/button-inventory.php
python3 tests/ui/button-inventory.py --remaining --json
python3 tests/ui/button-release-audit.py --standard-areas --engine chromium --output /tmp/dewawi-b18/final-chromium
python3 tests/ui/button-release-audit.py --standard-areas --engine firefox --output /tmp/dewawi-b18/final-firefox
```

Der neue Browserlauf benötigt den vorhandenen Ausgangscommit `7ceb5e6b` für die historischen Formularvergleiche. Er führt Index/Edit/Select × drei Bereiche × DE/EN × vier Breiten aus, jeweils mit vollständigem altem/neuem Dokumentkontext. Echte Search-HTTP-Antworten, dynamische Chips/Footers, Pixel, Ziele, Auswahl, Cancel, Loading, Fehler/Retry und Rechte werden getrennt geprüft. Rohprotokolle: `php-{before,after}.json`, `browser-{a,b}.json`, `inventory-after.json`, `layout-before-after.json`, `view-{before,after}.log` sowie die beiden abschließenden Browserverzeichnisse.

Technische Migration im abgegrenzten Scope; keine uneingeschränkte Deploymentfreigabe. Manuelle Folgeabnahme: vollständige reale Prozess-/Preisregel-/Shop-Workflows mit zulässigen Rollen/Mandanten/Paketen, readonly Process-View, Mobil-Tabellen, Browser-/Screenreaderbedienung und fachliche Copy-/Löschfolgen. Die Restinventur empfiehlt danach getrennte normale Formular-/Modalgruppen sowie eigenständige Vertragsklärung für Index-Apply/Admin-media-delete/Dashboard-Altaktionen; Dokumente, Zahlung, Shop/CMS und Positionen brauchen eigene Abnahme.

Keine Datenbankmigration, keine gespeicherten Inhaltsänderungen und keine manuelle Datenkorrektur. Kein Push, Merge, Tag, Deployment oder Produktiveingriff.
