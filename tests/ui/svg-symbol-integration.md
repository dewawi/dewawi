# B14 – Verlässliche SVG-Symbolversorgung vollständiger ERP-Seiten

Fortsetzung: [B15 – Dashboard-Standardicons](dashboard-buttons.md) migriert die drei direkten Dashboard-Editgruppen. Die folgenden Zahlen und Aussagen „Dashboard ohne moderne Controls“ beschreiben den historischen B14-Stand; Symbolversorgung und übrige B14-Kontexte bleiben erhalten.

Stand: 19.11.2026. Ausgangs-HEAD `c59fa45d` (B13), Arbeitsverzeichnis zunächst sauber. Grundlage: `AGENTS.md`, B1–B13, insbesondere [B13-Freigabematrix](button-release-audit.md) und [Gesamtinventur](button-system-inventory.md). Kein Push, Merge oder Deployment.

## Befund, Ursache und Nachweisgrenze

Die gemeldete ausgelieferte Kontaktseite enthält laut Benutzer 147 Buttons, 85 SVG-Buttons und 84 `<use>`-Referenzen, aber **keine Symboldefinition**. Diese SVGs haben damit keine zeichnbare Geometrie. Das ist vom absichtlich weiterhin vorhandenen PNG-/Text-Legacybestand zu unterscheiden. Die eine Dashboard-Glocke verwendet eigenständige Inlinegeometrie und benötigt den Buttonkatalog nicht.

Im lokalen Code waren **Ressourcenverfügbarkeit und Migrationsfreigabe gekoppelt**: Beide Vollseitenlayouts gaben `DEEC_Form::renderButtonSymbols()` nur bei einer separaten Liste von `$this->module`, `$this->controller`, `$this->action` aus. Die Formulare entscheiden ihr Opt-in dagegen anhand des Requests und konkreter Klassen. Ein bereits als SVG gerendertes Control konnte deshalb auf ein Layout ohne Definitionen treffen, sobald dessen View-Kontext abwich. JavaScript erzeugt/ergänzt keine Symboldefinitionen; AJAX liefert ausschließlich Referenzen. CSS, currentColor oder Browsercache können fehlende Definitionen nicht ersetzen.

**Wichtig: Die konkrete Deploymentabweichung ist nicht abschließend bewiesen.** Der reguläre lokale MVC-Aufruf `/contacts/contact` lieferte bereits vor B14 14 Definitionen. Ein gezielter vollständiger MVC-Fehlerfall mit fehlendem View-Aktionskontext reproduziert dagegen die fehlende Symbolversorgung und wird durch B14 geschlossen. Daraus wird nicht behauptet, genau dieser Kontextverlust sei auf dem Produktivserver aufgetreten.

Der Benutzer nennt `/var/www/public/dewawi/cloud`. Dieser Pfad existiert in der Arbeitsumgebung nicht. Lesende Prüfung der vorhandenen VHosts ergibt lediglich `ServerName localhost`, `DocumentRoot /var/www/dewawi`, daneben `/var/www/html`; keine lokale Deploymentkonfiguration mit bestätigtem Produktivhost gefunden. Die erwähnten produktiven HTML-Dateien sind hier nicht nachgewiesen. Keine produktive HTTP-/DB-Anfrage, keine Produktionssession, kein Auslesen von Zugangsdaten und kein Eingriff in PHP-Worker/Caches. Eine Bestätigung der produktiven Behebung bleibt daher nach einem separat freigegebenen Rollout erforderlich.

## Kleine zentrale Änderung

`application/layouts/scripts/layout.phtml` und `plain.phtml` rufen nun **genau einmal je vollständigem internem Dokument** die bereits vorhandene `DEEC_Form::renderButtonSymbols()` auf. Die duplizierten Routenbedingungen entfallen. Das öffentliche `site.phtml`, Drittanbieter-Dokumente und AJAXfragmente bleiben unverändert.

Das ist **keine globale SVG-Buttonaktivierung**: Der passive, dekorative 0×0-Symbolblock macht keine Aktion sichtbar oder ausführbar. Die bestehenden exakten Formular-/Controller-/Aktionsguards, 45 Feldkonfigurationen, 84 Controller-/Feldzuordnungen, 13 Gruppen und der Filterchipweg mit 22 Kontexten bleiben unverändert. Ein Legacy-Dashboard erhält verfügbare Definitionen, aber weiterhin null moderne Buttoncontrols. Jedes unabhängige Popup-/iframe-Dokument besitzt seinen eigenen Block; ein AJAX-Refresh fügt keinen hinzu.

Keine Änderung an `DEEC_Form`, Button-Helper, SVG-Allowlist/-Geometrie, PNGs, CSS, `global.js`, Modulformularen, Controllern, Berechtigungen, Mandanten-/Sharing-/SaaS-Prüfungen oder fachlichen Aktionen. Keine Buttonattribute oder Handler werden ersetzt. Alle bestehenden `id/class/name/value/data-*/rel/href/type/onclick` bleiben bei ihren bisherigen Besitzern.

Tradeoff: ungefähr 2,8 KB unkomprimiertes statisches Markup auch auf internen Legacy-Seiten. Keine zusätzliche Icon-Netzwerkanfrage, kein CDN, Framework oder Initialisierungslauf. Weiterhin 15 semantische Keys / 14 Geometrien aus lokalem Lucide 0.468.0 mit vorhandenen ISC-/Feather-MIT-Nachweisen. Assetpfad unverändert: `images/icons/dewawi.svg` relativ zum Repository der bestehenden DEEC_Form-Implementierung.

## Vollständiges MVC-/HTTP-Rendering statt ausschließlich vorbereiteter Views

Neue Dateien: `tests/ui/button-page-integration.php` und `.py`.

Der PHP-Test startet die vorhandene Zend-Anwendung gezielt mit View-, Layout- und Helperressourcen. **Originalcontroller** `Contacts_ContactController`, `Items_ItemController`, `Tasks_TaskController`, `Sales_QuoteController` und `IndexController`, Originalrouting, `ViewRenderer`, MVC-Layoutplugin, echte Views/Listen/Toolbarhelper sowie ACL-, Translate-, ListResponse- und SecurityHeaders-Plugins führen den Request aus. Modul-/Controller-/Aktionsvariablen werden nicht vorab wie in den bisherigen Buttonfixtures passend in die View geschrieben.

Nur Daten/Identität sind synthetisch: ein ausschließlich lesender Memoryadapter, Testbenutzer 42/Mandant 70, 25 Kontakte, ein Artikel, ein Angebotsentwurf und auf der Aufgabenseite eine Aufgabe. Es wird keine Datenbankkonfiguration geladen. Verbindungsaufbau ist durch den Adapter ersetzt, Transaktionen/Insert/Update/Delete sind gesperrt; Testendpunkte gestatten nur die benötigten Lese-/Suchrouten. Sessions liegen temporär außerhalb der Anwendung. Kalenderpolling liefert ausschließlich eine leere synthetische Testantwort. Der Test ist unter normalem Webserver-SAPI nicht ausführbar. Das ist kein Passwortlogin und keine Abnahme eines realen SaaS-/Kundendatenbestands.

Der optionale `fixture_contextless=1`-Pluginzweig ist eine **ausdrückliche Fehlerinjektion**: Nach dem Originalcontroller wird ausschließlich die View-Aktion geleert, der Request und die bereits erzeugten SVG-Controls bleiben bestehen. Dieser Fehlerfall wurde mit den alten Layouts aus `git show c59fa45d:…` und demselben vollständigen HTTP-Runner rot ausgeführt; anschließend mit den korrigierten Layouts grün. Es handelt sich nicht um eine nachgestellte produktive Konfiguration.

### Vorher/nachher

| Vollständige Ausgabe | Vor B14 | B14 / Nachweis |
| --- | --- | --- |
| Reguläres Kontakte-MVC, 25 synthetische Rows | 14 Definitionen vorhanden; gemeldeter produktiver Nullbefund so lokal nicht reproduziert | 145 HTML-Buttons, 85 SVG-Buttons inklusive Glocke, 84 moderne Controls/Referenzen, 14 Definitionen, null ungelöste Referenzen. Abweichende Gesamtbuttonzahl zur Meldung ist kein Produktionsvergleich identischer Daten. |
| Derselbe MVC mit gezieltem View-Kontextfehler | 135 Buttons, 78 Referenzen, **0 Definitionen**, 78 leere SVGs; **0** gezeichnete Vordergrundpixel im geprüften Eye-Icon | Dieselben Buttons/78 Referenzen, **14 Definitionen**, null leere SVGs, **68** Vordergrundpixel im 20-px-Eye-Icon bei 320 px. Alter Layoutlauf bricht mit Exit 1 ab. |
| Original-Artikelindex mit einer Row | Bestehende gemischte SVG-/PNG-/Textdarstellung | 18 Buttons, elf moderne Referenzen, 14 Definitionen; alle Referenzen auflösbar. |
| Original-Aufgabenindex mit einer Row | Bestehende gemischte SVG-/PNG-/Textdarstellung | 18 Buttons, zwölf moderne Referenzen, 14 Definitionen; alle Referenzen auflösbar. |
| Original-Angebotsindex mit einem Entwurf | Dokumentfamilie weiterhin Legacy | 16 Buttons, null moderne Controls/Referenzen, 14 passive Definitionen; keine Dokumentmigration. |
| Original-Dashboard mit Testbestand | Keine Button-SVG-Definitionen, eigene Glocke | 28 Buttons, null moderne Controls, eine SVG-Glocke, null Button-`use`; 14 passive Definitionen. Edit/PDF weiterhin PNG. |
| Kontakt-/Artikel-Select, Plain | Bestehende SVG-Apply-Controls | Je ein eigener vollständiger Symbolblock, auch im echten gleichursprünglichen iframe; keine Abhängigkeit von Definitionen des Elternfensters. |
| Echte `/search`-Antworten | Layout deaktiviert, JSON `content/toolbar` | Unverändert keine Definitionen im Fragment; dynamische Zeilen und Clear-Filterchips zeichnen gegen den bestehenden Dokumentblock. |

Der Browsertest prüft die HTML-ID-Auflösung und **echte Screenshotpixel innerhalb der SVG-Elemente**, nicht ausschließlich `getBBox()`. Vor-/Nachherbilder: `/tmp/dewawi-b14/{before,after}/de_DE-0-320.png` (gesichtet). Geprüft werden CSS-Sichtbarkeit, Größe, `stroke=currentColor`, `fill=none`, fehlender PNG-Doppelhintergrund und nach tatsächlichem HTTP-Search gezeichnete Chips/Rows. Definierte Symbole bleiben absichtlich unsichtbar; sichtbare Instanzen dürfen weder Größe null noch `display:none`/`visibility:hidden` besitzen.

## Erhaltene Funktion und Regressionstests

Der neue Runner deckt DE/EN × 320/390/768/1280 px ab: regulärer Kontakt-/Artikel-/Aufgaben-/Angebotsindex, Dashboard, beide Select-Popups, Haupt-/Plain-Kontextfehler und Unterverzeichnis `/erp`. Zusätzlich echte Select-iframes bei 390/1280 px. `--compare-layouts` vergleicht **jedes vollständige Buttonmarkup einschließlich aller Attribute und Labels bytegenau** mit den alten Layouts in denselben Original-MVC-Kontexten.

Zusätzlich zur Darstellung werden tatsächliche bestehende JavaScript-Handler ausgeführt: native Tastatur/Fokus, SVG-Kindklicks bei Disabled, Loading mit identischen restaurierten SVG-Knoten, Edit mit eigener Zeilen-ID trotz fremder Auswahl, Copy als POST, gemeinsame Row-/Footer-Requestsperre, Fehler/Retry mit Enter/Space, Erfolg und dynamischer Search-Refresh. Tags-/Export-Dialogöffner werden tatsächlich angeklickt und mit Escape samt Fokusrückgabe geschlossen; keine Tagoperation oder Exportausführung. Edit-Navigation und Copy-Transport werden abgefangen; echte Suchrequests laufen durch die Originalcontroller. Keine echte Kopie/Datenmutation. Die unveränderten B9-/B10-/B13-Baselines ergänzen View, Apply/Parent/Set, Auto-Save-/Submit-/Bestätigungs- und negative Rechte-/Legacyverträge.

| Tatsächlich ausgeführte Prüfung | Ergebnis |
| --- | --- |
| Neuer HTTP-/Pixelrunner mit `--layouts=/tmp/dewawi-b14/legacy-layouts` | **Erwartet FAIL**: fehlende Definitionen und null Vordergrundpixel im gezielten Kontextfehler. Kein pauschales altes Kontaktseiten-FAIL behauptet. |
| Neuer HTTP-/Pixelrunner mit aktuellen Layouts und `--compare-layouts=…` | **PASS: 850 Assertions**, 88 Seiten-/Breitenprofile plus vier echte iframes, 22 vollständige Vorher/Nachher-Buttonmarkupvergleiche, acht Edit-/Copy-Profile und vier Tags-/Export-Dialogöffnungen. Artefakte: `/tmp/dewawi-b14/page-after.log` und `after/page-results.json`. |
| PHP B2–B12: buttons / button-icons / inquiry / toolbar / tasks / remaining / apply / list / mobile / filter | PASS: 165 / 651 / 697 / 2.259 / 1.428 / 27.105 / 1.459 / 7.374 / 256 / 3.070 plus eingebundene Baselines, nicht als einzigartige zusätzliche Fälle addiert. |
| Chromium B2–B12 | **PASS:** buttons 169; SVG/audit 204; Inquiry 796; gemeinsame Toolbar 1.000; Tasks 524; übrige Toolbars 3.247; Apply 302; Listen 1.470; mobile Listen 822; Filter 1.564; Notifications je 1.985 bei 390/1280 px. Alle jeweiligen Kindprozesse Exit 0, Logs in `/tmp/dewawi-b14/browser-results.json`. |
| B13 Chromium / Firefox | **PASS: 628 / 2.152**, 88 ergänzende Chromium-/352 Firefoxprofile. Firefox prüft Rendering/skriptierte Interaktion; native Tastatur/Fokus bleibt wegen der dokumentierten Headless-Grenze NOT TESTED. Keine neue Safari-/WebKit-/Screenreaderfreigabe. |
| PHP permissions / admin-access / tenant-access / bootstrap-auth | PASS: 415 / 68 / 269 / 29. |
| Session-/Tenant-HTTP | PASS: 25 / 48, isolierte Sessions/Apache/Memoryadapter. |
| PHP contact-inquiries / form-input-validation / inquiry-dynamic-form / quote-monitoring / summary | PASS: 46 / 20 / 31 / 293 bzw. zentrale Summenprüfung. |
| PHP purchases notifications / contact-deletion | PASS: 24 / 16; simulierter Löschfehler erwartbar, keine Delete-Migration. |
| PHP security/notifications.php | **Bekannter unabhängiger FAIL**: 370 Checks erfolgreich, dann fehlende `Sales_Service_DocumentRevisionService` in Sales-Finish:12; Fixtureabbruch notifications.php:211, Exit 255. Nicht durch B14 korrigiert. |
| Inventur | Vollständige Source-/Runtime-JSONs wertgleich zu B13. Keine zusätzlichen SVG-Feldkonfigurationen oder modernisierten Controls. |

Die bisherigen negativen Prüfungen für Fremdcontroller werden **nicht entfernt**: `inquiry-buttons.php` und `toolbar-buttons.py` verlangen jetzt einen verfügbaren Definitionenblock und weiterhin **null moderne Controls** bzw. das ursprüngliche Copy-PNG. Die beabsichtigte Ressourcenänderung wird nicht mit einer Modulmigration verwechselt. Alle übrigen Assertions bleiben bestehen.

## Präziser Legacybestand aus vollständigem HTML

Diese Mengen gelten für die beschriebenen synthetischen Daten/Originalviews, nicht für alle Produktionsdatensätze. Die vom Benutzer genannten 147/40 Buttons werden nicht mit den 145/28 des Testbestands gleichgesetzt. „Kein Kind-SVG“ bedeutet bei CSS-PNG-Buttons **nicht automatisch unsichtbar**.

| Seite / Gruppe | Sichtbare Erzeugung / Anzahl im Test-HTML | Mechanismus / B14-Entscheidung |
| --- | --- | --- |
| Kontakt-Index Delete | 27: 25 Rows + Kopf/Fuß | Bestehende PNG-Klasse; Confirm/Rechte/Kaskaden unverändert, keine Migration. |
| Kontakt-Index Apply | 1 | Bestehendes Apply-PNG. B9-SVG gilt nur im Auswahlkontext; kein automatisches Index-Opt-in. |
| Kontakt Tags / Export | je 1 | Beschriftete Textbuttons, keine PNG-Zuordnung. Historischer pauschaler Iconabstand bleibt separate Legacybaustelle. |
| Kontakt-Pin | 25 | Text „Anheften“, Inline-`onclick=pin(id)`, keine SVG-/PNG-Aktionszuordnung; bewusst ausgenommen. |
| Kontakt-Tagdialog | zwei Operationsbuttons | Text, `data-contact-tag-operation=add/remove`; eigener Ablauf. |
| Kontakt-Exportdialog | drei Profilbuttons | Text, `data-contact-export-profile=combined/companies/contactpersons`; eigener Exportablauf. |
| Artikel-Index Delete / Apply / Export / Pin | 3 / 1 / 1 / 1 | Delete/Apply PNG, Export/Pin Text; Sonderverträge unverändert. |
| Aufgaben-Index Delete / Export / Pin | 3 / 1 / 1 | Delete PNG, Export/Pin Text; die zwölf bereits migrierten Standardcontrols bleiben SVG. |
| Angebots-Index Add / Edit / Copy / Cancel / Filter / Reset / Clear / PDF | 2 / 3 / 3 / 1 / 1 / 1 / 1 / 1 | Alle PNG; kein fehlgeschlagenes SVG-Opt-in. Zusätzlich Export/Pin je einmal Text. Testrolle mit View/Add/Edit, ohne Delete; keine Löschcontrols. Ein Entwurf ist kein repräsentativer Bestand aller Dokumentzustände/-typen. |
| Dashboard Edit / PDF | 26 / 1 | Direkte Edit-Buttons für 25 Kontakte/einen Artikel; PDF aus `Application_Form_Toolbar`. Vorhandene PNGs, keine Button-SVG-Migration. |
| Dashboard Glocke | 1 | Eigenständiges Inline-SVG mit `<path>`, kein fehlender Buttonkatalog. |
| Kontakt-/Artikel-Select | 28 / 4 moderne Controls; 25 / 1 Pins | Bestehende Apply-/Clear-SVGs bleiben; Pins bleiben Text. Keine allgemeine Tabellen-/Positionsmigration. |
| Dynamische Dialog-Schließencontrols | bedarfsabhängig | Bestehender Modalmanager, eigenes ×/Close-Markup; nicht als serverseitige SVG-Feldkonfiguration gezählt. |

Die Browserprüfungen laden die tatsächlich berechneten Legacy-PNG-URLs als Bilder und verlangen `naturalWidth>0`, unter anderem Dashboard-PDF. Alle bestehenden 23 PNG-Aktionsdateien, die SVG-Geometrie und Styles bleiben unangetastet. Auf dem Produktivserver unsichtbare PNG-Buttons sind gesondert anhand CSS-/Assetstatus zu prüfen; ihre leeren HTML-Inhalte allein beweisen keinen Fehler. Weitere vollständige Legacy-/Ausschlusslisten stehen in der Gesamtinventur; kein Asset entfernt.

## Prioritäten für spätere Icon-Vereinheitlichung

| Reihenfolge | Konkrete verbleibende Gruppe | Erforderlicher Nachweis vor einer Migration |
| --- | --- | --- |
| 1 | Direkte Dashboard-Editcontrols aus den vorhandenen Übersichtslisten | Original-IDs, Links und Handler, Rechte sowie gemischte Dashboardlisten; PDF ausdrücklich ausnehmen. |
| 2 | Wiederkehrende Index-Apply- und einfache Modal-Closecontrols | Index-Massenaktion von B9-Auswahlbestätigung unterscheiden; Auswahl-/Parent-/Set-Vertrag, Fokusrestauration, Dialogstatus und AJAX-Fehler prüfen. Keine Aktivierung allein über `name=apply`. |
| 3 | Standard-Delete in bereits teilweise migrierten Kontakt-/Artikel-/Tasks-Renderern | Bestehende Confirm/Cancel-, Rechte-/Tenant-, Kaskaden-, Teilergebnis- und Requestlocktests ergänzen. Der vorhandene SVG-Key ist keine fachliche Freigabe. |
| 4 | Textaktionen Tags/Export/Pin und deren Dialogcontrols | Eigene semantische Zuordnung/Icons begründen; Pin-`onclick`, Tagoperationen, Profilwahl und bestehende Übersetzungen erhalten. Keine automatische Klassenzuordnung. |
| 5 | Vollständige Legacyfamilien Sales/Purchases/Processes/Campaigns/eBay/Shops und normale Form-/Positionscontrols | Je gemeinsamen Renderweg separat mit vorhandenen exakten Kontextguards planen. Dokument/PDF/Mail/Zahlung, SaaS-/Bestands-/Positionsverträge und Shop/CMS benötigen eigene fachliche Abnahme. |

Keine dieser Gruppen wird in B14 migriert. Der große Legacyanteil auf dem Dashboard und in Dokumentlisten ist kein fehlgeschlagenes B4–B12-SVG-Opt-in. Die SVG-Symbolkorrektur allein kann absichtlich weiterhin PNG-/Text-basierte Aktionen nicht vereinheitlichen.

## Auslieferungs- und Abnahmeplan

1. In einem konsistenten, separat autorisierten Release beide Layouts, vorhandene DEEC_Form-Version und **alle versionierten `images/icons/`-Dateien samt Lizenzen** zusammen ausliefern. Keine Datenbankmigration erforderlich.
2. Auf dem tatsächlich betroffenen Server zunächst nur Code-/Assetidentität und Lesbarkeit prüfen: Releasehash, Hashes von Layout/Plain/DEEC_Form/Sprite, Symlink-/Assetpfad und vollständige Dateiübertragung. Aus der hiesigen lokalen VHostkonfiguration keinen Produktivhost ableiten.
3. Nach genehmigtem Rollout eine frische vollständige Kontaktseite/Select-Seite sichern. Lokale sichere Prüfung: `python3 tests/ui/button-page-integration.py --inspect-html=/pfad/zur/bereitgestellten.html`. Der Befehl liest ausschließlich diese ausdrücklich angegebene Datei, meldet Mengen/fehlende Symbol-IDs und Exit 1 bei ungelösten Referenzen; keine Kundentexte/Datensatz-IDs als Inventar ausgeben. Aktuell gibt es keinen vorgesehenen nachträglichen JS-Symbolloader.
4. Bei weiterhin null Definitionen trotz neuem Layout den wirklich verwendeten Layoutpfad/Release, PHP-Worker-/OPcachestand und Sprite-Lesbarkeit kontrollieren. Ein CSS-/Browsercache-Refresh kann fehlendes serverseitiges Markup nicht reparieren. Änderungen an produktiven Workern/Caches nur nach gesonderter Freigabe; hier nicht vorgenommen.
5. Im freigegebenen Testmandanten echte Navigation, Copy/Apply/Filter/Submit/Auto-Save samt Rechten/Sharing/Entitlements abnehmen. Physische Touchgeräte, fokussiertes Firefoxfenster, Safari/WebKit und Screenreader bleiben offen. Die bekannten globalen Header-/EN-Reportprobleme aus B13 sind nicht Bestandteil dieser Korrektur.

Keine neue breite Migration und kein automatischer Folgeauftrag. Fremde Dokument-/Archivänderungen wurden während B14 unabhängig als `c527418e` committed; sie sind kein Bestandteil dieser Korrektur. Das neu erschienene unversionierte `tests/translations/`, der historische Recovery-Code und B1 bleiben geschützt. Eigene Dateien ausschließlich explizit stagen, Status und staged Diff vor dem lokalen Commit prüfen. **Kein Push, Merge oder Deployment.**
