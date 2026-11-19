# B16 – Fehlender Live-Symbolkatalog: nachgewiesene Rechteursache und Abschluss

Stand: 19.11.2026. Ursprüngliche Diagnose bei HEAD `87ac98c1`; dokumentarischer Abschluss nach dem unabhängigen Commit `22bc7ab1`. B14 `d68f7a8c`, B15 `c345af08`. Grundlage: `AGENTS.md`, [B14](svg-symbol-integration.md), [B15](dashboard-buttons.md). Parallel entstandene Übersetzungs-/PDF-/Shopänderungen und deren Commits sind kein Bestandteil von B16.

**Status: B16 abgeschlossen; Produktivfehler laut direktem Server- und Firefox-Nachweis des Benutzers behoben.** Auf `cloud.dewawi.com` gehörte `images/icons` dem Benutzer/der Gruppe `root:root` und hatte Modus `700`; `dewawi.svg` hatte zunächst Modus `600`. PHP als `www-data` konnte den Katalog deshalb nicht lesen. `file_get_contents()` meldete Permission denied; die Symbolausgabe blieb leer. Der Benutzer korrigierte ausschließlich das öffentliche Assetverzeichnis auf `755` und die Katalogdatei auf `644`. Keine PHP-/JavaScript-/CSS-Änderung war erforderlich.

Der anschließende PHP-Lesetest als `www-data` lieferte **2.836 Bytes und 14 Symbole**. `document.querySelectorAll('svg.dw-icon-symbols symbol').length` ergibt im vollständigen Firefox-DOM wieder **14 statt 0**; die migrierten Icons in Kontaktverwaltung und Dashboard sind sichtbar. Der produktive Git-Status ist laut Benutzer sauber. Diese Produktionsmaßnahmen und die Live-Abnahme wurden vom Benutzer durchgeführt, nicht von Codex. Seit dieser Abschlussmeldung kein weiterer Produktivzugriff durch Codex.

## 1. Vollständiges Dokument gegenüber kopiertem Ausschnitt

Beide internen Layouts geben `(new DEEC_Form())->renderButtonSymbols()` unmittelbar nach dem öffnenden `body` aus:

- `application/layouts/scripts/layout.phtml:66`, vor `.dw-app-shell` und `.dw-app-shell__inner`.
- `application/layouts/scripts/plain.phtml:55`, vor `.dw-popup-shell`.

Damit liegt der Katalog **außerhalb** der bei `.dw-app-shell__inner` beginnenden Ausschnitte. Null Definitionen allein in diesen Ausschnitten wären korrekt und kein Ausfallnachweis. Die spätere Messung im vollständigen Live-DOM ist dagegen ein bestätigter Fehler. Ein Verschieben des Katalogs in den inneren Content oder seine Wiederholung je Button wäre daraus nicht abzuleiten.

| Kontext | Vollständige HTTP-Ausgabe | Vollständiger Browser-DOM | Innerer Inhalt |
| --- | --- | --- | --- |
| Kontakte lokal, Originalcontroller/Layouts, synthetische Daten | 14 Definitionen vor dem Shellinhalt, 84 Referenzen | Firefox/Chromium: 14 Definitionen, ein Bankcontainer, null ungelöste Referenzen | Null Definitionen, wie konstruktiv vorgesehen. |
| Dashboard lokal, 25 synthetische Kontakte/ein Artikel | 14 Definitionen, 26 Buttonreferenzen | 14 Definitionen, sichtbare Editgrafiken | Null Definitionen. Die gemeldeten 15 Livereferenzen gehören zu einem anderen Datenbestand. |
| Item-Select/Plain lokal | 14 Definitionen vor dem Popup-Shellinhalt | 14 eigene Definitionen, sichtbares Apply-Icon | Null Definitionen innerhalb des Popup-Shells. |
| Livekontakte laut Benutzer, vor/nach Rechtekorrektur | Vorher 84 Referenzen im Ausschnitt; nachher erfolgreicher PHP-Lesetest mit 14 Symbolen | **0 → 14 Definitionen**; migrierte Icons nachher in Firefox sichtbar | Null Definitionen im inneren Ausschnitt allein kein zusätzlicher Befund. |
| Livedashboard laut Benutzer, vor/nach Rechtekorrektur | Vorher Ausschnitt mit 15 Referenzen; keine Codeänderung durch die Rechtekorrektur | Vorher leere Grafiken, nachher migrierte Edit-Icons in Firefox sichtbar | Definitionen liegen bei korrekter Vollseite außerhalb des Ausschnitts. |

Keine verbundene Codex-Dokumentsitzung ist verfügbar. Die Live-DOM-Werte stammen vom Benutzer; hier wurde keine authentifizierte Produktivseite geöffnet oder eine Live-Firefox-Sitzung übernommen. Lokale vollständige HTML-Dateien und Pixelartefakte: `/tmp/dewawi-b16/{firefox,chromium}/`.

## 2. Tatsächlich verglichener Code- und Assetstand

Die lokalen beiden Layouts, `DEEC_Form`, `images/icons/dewawi.svg` und `css/ui.css` sind bytegleich zu B14 und B15. Die Dashboard-View ist bytegleich zu B15. `configs/application.ini:12` konfiguriert weiterhin `APPLICATION_PATH "/layouts/scripts/"`; `index.php` lädt diese Konfiguration. Der Benutzer bestätigt den Produktionsstand **`88eef4c0` – `complete german and english translations`**, das Verzeichnis `/var/www/public/dewawi/cloud` und den tatsächlich verwendeten Formpfad `/var/www/public/dewawi/cloud/library/DEEC/Form.php`.

Der Produktivpfad ist weiterhin nicht Teil dieser lokalen Arbeitsumgebung. Der Serverabgleich unten verwendet die vom Benutzer übermittelten Nachweise und die lokal berechneten Hashes; er ist kein behaupteter eigener SSH-/FPM-Zugriff.

Vor der gemeldeten Rechtekorrektur wurden öffentliche statische Pfade von `https://cloud.dewawi.com` lesend geprüft, ohne Anmeldung/ERP-Aktion. Historische Diagnosewerte, keine erneute Produktivprüfung nach Abschluss:

| Pfad | Tatsächlicher Befund am 19.11.2026 |
| --- | --- |
| `/css/ui.css` | **HTTP 200**, 56.173 Bytes, SHA-256 `b1e52e159b1656311a2b652e0e943ffe063760995cabb30338533d30b2b63ef1`, bytegleich zum lokalen B14/B15/HEAD. `Cache-Control: public, max-age=0, must-revalidate`. |
| `/js/global.js` | **HTTP 200**, 147.221 Bytes, SHA-256 `fb5f6e455a135f7986d1fb70fa653e36fe36b857a3109e6522b1ff2c07237c82`, bytegleich zum Ausgangs-HEAD `87ac98c1`, neuer als B15. Fremde laufende Working-Tree-Änderungen an dieser Datei wurden nicht übernommen. |
| `/images/icons/dewawi.svg` | **GET und HEAD: HTTP 403**, `Server: Apache/2.4.67 (Debian)`, HTML-Content-Type; keine CSP-/Cache-/Last-Modified-Header. Das ist ein belegter öffentlicher Auslieferungsbefund, kein Beweis der PHP-Dateilesbarkeit oder eines bestimmten Dateirechtsfehlers. |

Wichtig: Der Inlinekatalog wird durch PHP vom Dateisystem gelesen. Die Button-`use`-Referenzen lösen sich innerhalb desselben Dokuments auf; sie laden den öffentlichen Spritepfad **nicht** per HTTP. Der damalige HTTP-403-Befund allein bewies die PHP-Lesbarkeit nicht. Der nun vorliegende direkte Servernachweis und der erfolgreiche Lesetest als `www-data` belegen die tatsächliche Rechteursache und deren Behebung.

Vom Benutzer übermittelte Produktionshashes; alle vier stimmen mit den lokalen Dateien und den entsprechenden B14-/B15-Dateien überein:

| Datei | SHA-256 |
| --- | --- |
| `application/layouts/scripts/layout.phtml` | `2a67d411f4d09ea2ec4ceef6b32b56e3fc3d766b5cc922512d6e5cefcc179e3d` |
| `application/layouts/scripts/plain.phtml` | `a8969d8c5e4f776e6e1ae3ca85ea23693aa7d388672bebacbf3e3ea20da96973` |
| `library/DEEC/Form.php` | `8c9e8e930decc18e0a17a1c19b609b5d11d1a518c1299b8f7ef7356414b456af` |
| `images/icons/dewawi.svg` | `fe8a9dcc70cc6325887c3695f9970565b52a27ab79a6cffd76b38f5dacd6b130` |

Der unveränderte Dateiinhaltsstand und die alleinige Rechtekorrektur erklären den sauberen produktiven Git-Status: Git versioniert weder Eigentümer/Gruppe noch Verzeichnisrechte oder sämtliche Unix-Lesebits. Ein korrekter Commit allein garantiert deshalb keine Lesbarkeit durch den Webserver.

## 3. Datei-/Cacheprüfung und belegte Fehlergrenzen

`DEEC_Form::renderButtonSymbols()` liest ausschließlich `dirname(__DIR__, 2) . '/images/icons/dewawi.svg'`. Der bestätigte Produktionspfad ergibt den richtigen Katalogpfad innerhalb `/var/www/public/dewawi/cloud`. Ein Bibliotheks-/Layoutpfadfehler ist für diesen Ausfall nicht nachgewiesen; die Rechtekorrektur genügte. Es gibt keinen nachträglichen JS-Symbollader. Keine Pfadänderung oder zusätzliche Fallbacklogik erforderlich.

Ein isolierter Test mit einer temporären Kopie der unveränderten Formklasse und absichtlich fehlendem Katalog ergab **null Ausgabebytes und eine PHP-Warnung**. Das `false`-Ergebnis von `file_get_contents()` wird im bestehenden schwach typisierten Rückgabekontext zum leeren String. Der direkte Produktionsbefund bestätigt jetzt dieselbe leere Ausgabe bei Permission denied. `index.php` unterdrückt in Production die Fehleranzeige und aktiviert Logging. Kein Asset im Repository wurde für die Diagnose verändert/entfernt; kein Failover-/Fehlerbehandlungscode ergänzt.

Ein aktiver anwendungseigener View-/HTML-Cachepfad für diese Layoutausgabe wurde im Repository nicht gefunden. Produktions-OPcache/FPM-/Proxyzustände wurden von Codex nicht geprüft; sie sind keine nachgewiesene Ursache dieses behobenen Ausfalls. Der lokale CLI-Wert `opcache.enable_cli=0` erlaubt keinen Rückschluss auf den Produktivpool. Für die bestätigte Behebung war keine Cache-/Dienstmaßnahme nötig; Codex hat keinen Cache zurückgesetzt, keinen Dienst neu gestartet und keinen Diagnose-Webendpoint installiert.

CSS kann vorhandene Symbole unsichtbar machen, entfernt aber keine DOM-Symboldefinitionen. Der damalige Livewert null Definitionen erforderte deshalb die Prüfung der fehlenden Ausgabe; deren Rechteursache ist nun belegt. Die lokale Firefoxprobe zeichnet mit unveränderten 0×0-Spritecontainerregeln, `stroke=currentColor`, `fill=none` und bestehenden `href`-Referenzen korrekt. Kein Anlass für zusätzliche `xlink:href`-Attribute, ein neues Iconformat oder einen Browserfallback.

## 4. Neue Regression: Vollseite, tatsächliche Pixel und Funktionen

`tests/ui/button-release-audit.py` erhält ausschließlich den Testmodus `--document-icons`. Dieser verwendet die vorhandenen Chromium-/Firefox-Treiber und ruft `tests/ui/svg-document-context.py` auf. Das Modul startet die vorhandene vollständige Originalcontroller-/MVC-Fixture `button-page-integration.php` mit synthetischen Daten und gesperrten Schreibmethoden. Kein Produktions-DB-Setup, keine echte Kopie oder Dokumentaktion.

```sh
python3 tests/ui/button-release-audit.py --engine firefox --document-icons --output=/tmp/dewawi-b16/firefox
python3 tests/ui/button-release-audit.py --engine chromium --document-icons --output=/tmp/dewawi-b16/chromium
python3 tests/ui/button-page-integration.py --output=/tmp/dewawi-b16/full-pages
```

Je Engine: Kontakte, Dashboard, Plain-Select × DE/EN × 320/390/768/1280 px = **24 vollständige Dokumentprofile**, insgesamt 40 gezielte Grafikproben. Der Test prüft HTTP-/Dokumentkontext, genau einen vollständigen Symbolblock vor dem Shellinhalt, DOM-ID-Auflösung, nicht leere Definitionen und **gezeichnete Vordergrundpixel** in Screenshotausschnitten der SVG-Elemente.

Für jede Grafik wird der Bankcontainer ausschließlich im synthetischen Test-DOM entfernt. Die SVG-Fläche bleibt dabei exakt 16×16 bzw. 20×20 px groß und alle Buttonattribute/inneren Buttonknoten bleiben unverändert. Die Grafiken müssen dann **null** Vordergrundpixel zeichnen. Das Wiedereinsetzen desselben Bankknotens muss die Pixel ohne Button-/CSS-/Handleränderung zurückbringen. Dies ist ein negativer Regressionstest, **kein produktiver JS-Fallback**.

Beispiel Firefox 160.0.1, Kontakte/DE/1280 px:

| Icon | Vollständiger Katalog | Katalog entfernt | Derselbe Katalog restauriert |
| --- | --- | --- | --- |
| View, 16×16 px | 36 Vordergrundpixel | 0, Fläche weiter 16×16 | 36 |
| Edit, 16×16 px | 31 | 0, Fläche weiter 16×16 | 31 |
| Add, 16×16 px | 4 | 0, Fläche weiter 16×16 | 4 |

Die Bildprüfung erfasst die tatsächliche Rasterausgabe, nicht nur `getBoundingClientRect` oder `getBBox`. Bilder und `document-results.json` liegen in den jeweiligen Ausgabeverzeichnissen.

Die folgenden lokalen Testläufe wurden bereits während der B16-Diagnose ausgeführt. Beim dokumentarischen Abschluss werden sie nicht als neue Produktionsprüfung oder als erneuter Testlauf gegen fremde Folgeänderungen ausgegeben.

| Tatsächlich ausgeführte Prüfung | Ergebnis |
| --- | --- |
| Neuer vollständiger Firefox-Dokumenttest | **PASS: 388 Assertions**, 24 Profile/40 Grafikproben, echte native Pointerklicks auf Kind-SVGs, richtige Editziele, Disabled/Loading/Node-Restore, echte HTTP-Search-/Clearfilterrequests, dynamisch gezeichnete Chips/Rows und öffnender/schließender Tagsdialog. |
| Derselbe Test in Chromium | **PASS: 420 Assertions**; zusätzlich native Tab/Shift-Tab, Enter/Space, sichtbarer Fokus und Escape im Dialog. |
| Firefox native Tastatur/Fokus | **NOT TESTED:** Headless-Firefox meldet `document.hasFocus=false`; keine physische Tastatur-/Screenreaderfreigabe behauptet. Native Pointer und Pixel wurden tatsächlich geprüft. |
| Bestehende vollständige B14/B15-HTTP-/Chromium-Baseline | **PASS: 852 Assertions**, 88 Breiten-/Seitenprofile und vier Select-iframes. Copy-POST-Transport ist abgefangen; bestehende Requestsperre, Fehler 403/Retry, Auswahl, erfolgreicher echter Search-Refresh, Tags-/Exportdialoge sowie Popup-/AJAX-Symbolerhaltung geprüft. |
| PHP buttons/icons/inquiry/toolbar/tasks/apply/list/filter/dashboard | **PASS:** 165 / 651 / 697 / 2.259 / 1.428 / 1.459 / 7.374 / 3.070 / 3.620 Assertions. |
| PHP permissions/tenant/admin/bootstrap-auth | **PASS:** 415 / 269 / 68 / 29. Memoryfixtures; keine produktive Rollen-/Mandantenabnahme. |
| PHP security/notifications | **Bekannter unabhängiger FAIL:** 370 Checks erfolgreich, dann `Sales_Service_DocumentRevisionService` fehlt in Sales-Finish:12; Fixtureabbruch notifications.php:211, Exit 255. Nicht durch B16 verändert. |
| Produktiver Lesetest und Live-Firefox nach Rechtekorrektur | **PASS laut Benutzer:** PHP als `www-data` liest 2.836 Bytes/14 Symbole; Firefox-DOM 14 statt 0 Definitionen; sichtbare Icons in Kontaktverwaltung und Dashboard. Keine eigene erneute Produktionsprüfung durch Codex. |

Navigation im Test wird an `setLocation` aufgezeichnet, nicht zu einem echten Editor ausgeführt. AJAX-Reads laufen durch Originalcontroller; keine Kundendaten verändert. Der Modus ergänzt bestehende Architektur statt einen zweiten Button-/Iconrenderer zu bauen. Sämtliche Anwendungsbuttons, HTML-Attribute, JS-Hooks, URLs, Eventdelegation, ACL, SaaS-/Tenant-/Sharingentscheidungen, Auto-Save und gespeicherte Inhalte bleiben durch B16 unverändert.

## 5. Abschluss und separate Folgeaufgabe

B16 benötigt keinen Anwendungsfix und keine weitere Migration. Die nachgewiesene Rechteursache wurde produktiv durch den Benutzer korrigiert und visuell/technisch abgenommen. Noch nicht auf SVG migrierte Buttons bleiben eine **separate Migrationsaufgabe**, kein fortbestehender Fehler der Symbolauflösung. Die lokalen Firefox-/Chromium-Regressionsprüfungen bleiben als Nachweis für vollständigen Dokumentkontext und tatsächliche Pixel erhalten.

Als eigene spätere Aufgabe den Git-/Deploymentprozess hinsichtlich `umask`, Erzeugung neuer Verzeichnisse/Dateien, Eigentümer/Gruppe, geerbten ACLs und effektiven Webserver-/FPM-Rechten untersuchen. Herkunft der Modi `700`/`600` ist damit noch nicht geklärt. Öffentliche Assets müssen mitsamt durchquerbaren Elternverzeichnissen für den tatsächlichen Webserver lesbar sein; sensible Konfigurationen und private Kundendaten müssen ihre Schutzgrenzen behalten. Keine pauschale rekursive Rechteänderung des Repositories. Die hier angewendeten `755`/`644` sind keine allgemeine Vorgabe für private Dateien.

Geeignete Abnahmekriterien der Folgeaufgabe: reproduzierbare Rechte nach einer Testbereitstellung, Prüfung neu angelegter Unterverzeichnisse, Lesetest unter der tatsächlichen Webserveridentität, negative Zugriffsprüfungen für private Dateien sowie vollständiger DOM-/Pixeltest des ausgelieferten Katalogs. Buttonfunktionen, JavaScript-Handler, `data-*`, AJAX, Auto-Save und Berechtigungsprüfungen erhalten. **Diese Folgeaufgabe wird im Rahmen von B16 nicht begonnen.**

**Git/Scope:** Ausschließlich Abschlussdokumentation und bereits vorbereitete/geprüfte Regressionstests; kein PHP-/JS-/CSS-Laufzeitfix. Fremde Änderungen und fremde Commits bleiben getrennt. **Kein Push, Merge, Deployment, Datenbankeingriff oder produktiver Cache-/Dienstneustart durch Codex.**
