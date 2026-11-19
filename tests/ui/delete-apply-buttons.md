# B17 – Standard-Löschen und Übernehmen

Stand: 19.11.2026. Ausgangsstand `1910ef01` (`complete translation cleanup and quality gates`); während der Arbeit entstand unabhängig `c196e6ad` (`verify translation release readiness`). Maßgeblich: `AGENTS.md`, lokale Implementierung und [Gesamtinventur](button-system-inventory.md), [B13](button-release-audit.md), [B14](svg-symbol-integration.md), [B15](dashboard-buttons.md), [B16](svg-live-diagnosis.md). Fremde Übersetzungs-/Fixtureänderungen wurden weder verändert noch in den B17-Commit aufgenommen.

## Entscheidung und tatsächlicher Umfang

Die Standard-Löschbuttons hatten einen bereits vorhandenen, gemeinsam delegierten Aktionsvertrag, aber noch keinen expliziten SVG-Opt-in. B17 ergänzt ausschließlich Präsentationsmetadaten innerhalb der bestehenden exakten Klassen-/Requestgates. `DEEC_Form`, Button-Helper, Katalog, Layouts, CSS und JavaScript bleiben unverändert. Kein neuer Renderer, kein Iconasset, kein Löschendpunkt und keine Rechteentscheidung in der Präsentationsschicht.

| Feldquelle unter `application/modules/` | Eindeutig aktivierte Zielkontexte | Änderung |
| --- | --- | --- |
| `contacts/forms/Toolbar.php::delete` | `contacts/contact` | Bestehendes `icon=delete`, ausdrücklich `iconFormat=svg`. |
| `items/forms/Toolbar.php::delete` | `items/item` | Ebenso. |
| `tasks/forms/Toolbar.php::delete` | `tasks/task` | Ebenso. |
| `admin/forms/Toolbar.php::delete` | `admin/country`, `uom`, `deliverytime`, `manufacturer`, `warehouse`, `shippingmethod` | Dasselbe Feld in genau sechs vorhandenen Standardkontexten. |
| `contacts/forms/ToolbarInline.php::delete` | Contact Index/Search mit Indexkontext | SVG, `TOOLBAR_DELETE`, Icon-only, sm. |
| `items/forms/ToolbarInline.php::delete` | Item Index/Search mit Indexkontext | Ebenso. |
| `tasks/forms/ToolbarInline.php::delete` | Task Index/Search mit Indexkontext | Ebenso. |

**Sieben Feldquellen, zwölf Controller/Feld-Zuordnungen:** neun Haupttoolbar- und drei Inlinekontexte. Konstruktorgates der Haupttoolbars bleiben Index/Search/Select/Edit/View; der vorhandene Helper zeigt Delete tatsächlich im Indexkopf/-fuß und im Editkopf. Search erneuert die passenden Fragmente. Select und View erhalten keinen neuen sichtbaren Löschweg. Unterklassen, andere Controller und Spezialaktionen bleiben ausgeschlossen.

Toolbar-Delete bleibt **Text plus Icon, Default/md**; „Löschen“/„Delete“ bleibt sichtbar. Inline-Delete war zuvor bereits Icon-only und bleibt dies. Nur sein automatisch erzeugter technischer ARIA-Fallback `DELETE` wird durch „Löschen“/„Delete“ ersetzt. Explizite `aria-label`/`aria-describedby` bleiben erhalten; kein neues `title` oder eigener Tooltipmechanismus. Das SVG ist dekorativ, `aria-hidden=true`, `focusable=false`, Symbol `#dw-icon-delete` aus dem unveränderten lokalen Katalog.

### Übernehmen und bewusst unveränderte Aufrufstellen

- **Contact-/Item-Select und Search-Select:** bereits B9, sowohl Kopf/Fuß als auch Inline-Apply. Bestehende vier Feld-Opt-ins und `#dw-icon-apply` unverändert, keine doppelte Migration. `applyContact` verwendet den ersten Kontakt; Item-Apply verarbeitet ausgewählte IDs über den bestehenden Parent-/Set-/Zielvertrag.
- **Contact-/Item-Index-Apply:** weiterhin Legacy. Der Index liefert keinen gesicherten sinnvollen Parentvertrag: Kontakt-Apply setzt `window.parent.applyContact` voraus, Item-Apply benötigt Parent-/Positionskontext. Optische Aktivierung ohne diese Klärung wäre irreführend; keine neue Funktion ergänzt. Negative Tests vergleichen das bestehende HTML.
- **Admin-Inline-Delete:** ausdrücklich Legacy. Die Feldkonfiguration trägt `data-action=media-delete`; die bestehende Listenausgabe injiziert daneben `data-action=delete`. Die doppelten Attribute bzw. ihre Parserreihenfolge sind kein eindeutig freigegebener Standardvertrag. Weder Medienaktion noch Attributreparatur mitgenommen.
- Application-/Dashboard-Legacy, Inquiry-Löschen außerhalb des Piloten, weitere Admincontroller, alle Toolbarunterklassen, Positions-/Set-/Sortier-/Pinaktionen, komplexe Massenaktionen, Tags/Export, Dokumente/PDF/Mail/Zahlung, Shop/CMS und dynamische Factories unverändert. Insbesondere keine globale Aktivierung in `Application_Form_Toolbar` oder `DEEC_Form`.

## Vollständig verfolgter Verhaltensvertrag

Vor den Änderungen geprüft: Toolbar-/Bottom-Helper und `getCurrentTarget`, DEEC-Listen-/Inlineausgabe, `global.js::DewawiToolbar.actionConfig`, `bindActions`, `runAction`, `resolveSelection`, `getButtonTarget`, `actions.delete`, `actions.apply`, `trash`, `beginActionRequest`, `setButtonLoading`, `getDeletedIds`, `search` und die bestehenden Apply-/Parentfunktionen.

- `type=button`, `name=delete`, vorhandene Klassen und `data-action=delete` bleiben; kein Submit/Reset/Linkwechsel. Alle vorhandenen IDs, Values, `rel`, `href`, `onclick`, sonstigen `data-*` und expliziten ARIA-Attribute bleiben. Bestehende `nolabel`-Normalisierung wurde nicht geändert.
- Datensatzbuttons verwenden ihre eigene ID und ihr Modul/Controllerziel, unabhängig von fremder Checkboxauswahl. Kopf/Fuß verwenden die bestehende Mehrfachauswahl. Leere Auswahl bleibt ohne Anfrage.
- Bestätigung über den bestehenden übersetzten `deleteConfirm`; Abbruch erzeugt keine Anfrage. Derselbe JSON-POST an `/trash/add/` mit `module`, `controller`, `id`. Überlappende Anfragen bleiben über die vorhandenen Datensatzsperren geschützt.
- Loading/Disabled und Busy-Attribute werden vom bestehenden Code gesetzt und wiederhergestellt. Fehler lassen Auswahl, SVG-Knoten und Retryfähigkeit bestehen. Erfolgreiche bzw. teilweise erfolgreiche Antworten verwenden weiterhin `getDeletedIds` und den vorhandenen Search-Refresh; Edit führt weiterhin zur bestehenden Listen-URL. Ungültige Antworten lösen keinen Refresh aus.
- Serverseitig unverändert: `TrashController::addAction`, Modul-/Controllerprüfung, ACL/Delete-Recht, Adminprüfung, Modulowner/Mandantenscope, `getByIdForUpdate`, Objekt-/Parent-Child-Prüfung, Transaktionen und fachliche `canDelete`-/Kaskadenprüfungen. Keine Änderung an SaaS-Featureprüfungen, Aufgabenstatus/-zuweisung oder Auto-Save.
- Die dokumentierte bestehende Besonderheit fehlender regulärer Tasks-Modulkonfiguration in `DEEC_Permission` wird nicht durch einen Darstellungsauftrag geändert.

## Vorher/Nachher und Zählmethodik

Die vor den Laufzeitänderungen gespeicherten tatsächlichen Fixture-Dokumente wurden mit den später gerenderten Dokumenten verglichen: **1.966 Buttonpaare**, darunter genau **272 Präsentationsänderungen** in den wiederholten Seiten-/AJAXprofilen. Nicht unterschiedliche fachliche Controls zählen: Index/Edit/View/Readonly/Rechteprofile und Search/Rest-/Leerlisten wiederholen dieselben Feldquellen. Buttonanzahl, Reihenfolge, sichtbarer Text und sämtliche Verhaltensattribute bleiben gleich; neue SVGs nur auf den dokumentierten Deletecontrols, zusätzliche Klassen ausschließlich aus der bestehenden Präsentationsroutine. Einzige beabsichtigte Namenskorrektur: der beschriebene automatische Inline-ARIA-Fallback.

| Zähleinheit | Vor B17 | Nach B17 |
| --- | ---: | ---: |
| Eindeutige SVG-Formfelder (`Klasse::Feld`) | 45 | 52 |
| Controller/Feld-Paare | 84 | 96 |
| Primäre Controllergruppen | 13 | 13 |
| SVG-produzierende PHP/PHTML-Dateien | 14 | 14 |
| Moderne Controls in neun Indexfixtures mit je zwei Rows (ein DE-Durchlauf) | 109 | 133 |
| Davon Contacts / Items / Tasks | 15 / 13 / 15 | 19 / 17 / 19 |
| Je einfache Adminliste (sechs Listen) | 11 | 13 |
| SVG-Symbole im vollständigen Dokument | 14 | 14 |

Quellscanner unverändert: 1.074 Dateien; 243 literale Formstellen (234 Button/9 Submit), 145 lexikalische HTML-Buttonanfänge in 79 Dateien, 79 Buttonlinks, 25 alte Helperaufrufe, 13 ZF1-Konstruktoren. Der aktuelle Roh-HTML-Wert ist nicht der historische B11-Wert 144. Kein Laufzeit-/Produktionsgesamtzähler und keine aus verschiedenen Grundgesamtheiten berechnete Prozentzahl.

`button-inventory.php` liefert zusätzlich `standard_action_fields` (sieben Delete-, vier bestehende Applyfelder) und `legacy_standard_action_fields` samt konkreten nicht aktivierten Konstruktorkontexten. Felder können in beiden Gruppen vorkommen; zum Beispiel bleibt Index-Apply Legacy, während Select-Apply SVG ist. Die bestehende Python-Quellinventur wurde ebenfalls tatsächlich ausgeführt.

### Responsive Messungen

24 gepaarte Chromium-Messungen: drei Listen × DE/EN × 320/390/768/1280. Dokumentbreite vor/nach jeweils identisch; bewusstes Tabellenscrolling bleibt. Der bekannte deutsche Headerüberlauf bei 320 px bleibt mit 334 px Dokumentbreite unverändert (EN hier 316 px). Kontakt-/Aufgabenrows mobil weiterhin **115 px**, kein Rückfall auf 207 px. Itemrows mobil bereits vorher **171,75 px**, nachher unverändert; diese bestehende nicht optimierte Gruppe wurde nicht durch neue CSS-Regeln erweitert. Desktop überwiegend **95 → 91 px**, weil das letzte Legacy-36-px-Control jetzt dieselbe sm-Höhe wie die anderen Controls hat. Bei Contact/768 verbleiben rund 115,22 px wegen bestehendem Inhalt/Umbruch. Inline-Delete mobil **36×36 → 44×44 px**, Desktop **36×36 → 32×32 px**.

Firefox bestätigt für die aktuellen Kontakt-/Taskrows mobil 115 px und für Itemrows rund 171,8 px. SVG-Pixelproben: Chromium 58–74, Firefox 58–83 Pixel in der berechneten Strichfarbe im Ausschnitt des Delete-SVG. Damit wird tatsächliches Zeichnen geprüft, nicht nur eine Layoutfläche oder `getBBox`.

## Geänderte Dateien

- Laufzeit: ausschließlich die oben genannten vier `Toolbar.php` und drei `ToolbarInline.php`.
- Neue Tests: `tests/ui/delete-apply-buttons.php`, `delete-apply-browser.py`.
- Bestehende Tests/Scanner: `tests/ui/{toolbar-buttons,tasks-toolbar,remaining-toolbars,apply-buttons,list-buttons}.php`, `button-inventory.php`; Browserrunner `tests/ui/{toolbar-buttons,apply-buttons,list-buttons,button-page-integration,button-release-audit}.py`; `tests/security/tenant-access-http.py`.
- Dokumentation: dieser Bericht und `tests/ui/button-system-inventory.md`. Bestehender B1-Analysebericht, T6-Dateien und andere fremde Arbeit unverändert.

## Tests und Ergebnisse

Browser: ChromeDriver/Chromium 157.0.8103.78, Firefox 160.0.1. Alle Schreib-/Löschtests verwenden ausschließlich Memoryadapter oder kontrollierte synthetische AJAX-Antworten. Keine Datenbankverbindung, produktive Anfrage oder gespeicherte Inhaltsänderung. Vollseitenfixtures verwenden die vorhandenen echten Form-, Listen-, Helper- und Layoutkomponenten; B14/B16 ergänzen Originalcontroller über isoliertes HTTP. Dies ersetzt keine angemeldete ERP-Abnahme mit echten Testrollen.

| Prüfung | Tatsächlicher Befund |
| --- | --- |
| Neue PHP-Prüfung `php tests/ui/delete-apply-buttons.php` | **PASS: 1.738 eigene Assertions**, zusätzlich eingebundene vorhandene Baselines. Metadata nur Präsentation, native/Event-/ARIA-Attribute, Klassen, DE/EN, sichtbare Labels, Kopf/Fuß, Inline, Index-Apply unverändert. |
| Neuer Browsermodus `button-release-audit.py --standard-actions --engine chromium` | **PASS: 1.184 Assertions**, 72 DE/EN-Breitenprofile, zusätzlich Detail-/Readonly-/Einzelaktionsfälle. Sichtbare Delete-Pixel, 14 Symbole/ein Block/keine ungelöste Referenz, Hover, native Enter-/Tab-Fokusprüfung, Auswahl, Cancel, Loading, überlappende Anfragen, 403/Retry, Teilergebnis, erneuerte Inlineaktion, leere Liste, Editredirect, ungültige Antwort und blockierter Datensatz. |
| Derselbe Modus mit `--engine firefox` | **PASS: 1.256 technische/Pointer-Assertions**, dieselben 72 Profile, Hover und sichtbare Pixel. **NOT TESTED:** native Tastaturaktivierung/Fokus in allen 72 Headlessprofilen; die Sitzung gibt die Tasten nicht zuverlässig an den Dokumentinhalt. Separate Rohwerte `nativeEnter=false`, `nativeFocusVisible=false`, kein behaupteter Tastatur-PASS. |
| Sicherheits-HTTP `tests/security/tenant-access-http.py` | **PASS: 60 Assertions** statt 48; zusätzlich zwölf Negativfälle für leere, ungültige, unerlaubte und unbekannte Modul-/Controllerziele, Status 200/403/404 entsprechend bestehendem Vertrag, keine veränderten Memorydaten. Root- und Unterpfadinstallation. |
| PHP B2–B15 | **PASS:** buttons 165, icons 651, inquiry 697, toolbar 2.451, tasks 1.488, remaining 27.681, apply 1.459, list 7.586, mobile 256, filter 3.070, dashboard 3.620. |
| PHP Sicherheit | **PASS:** permissions 415, tenant-access 269, admin-access 68, bootstrap-auth 29, contact-deletion 16. |
| Bestehende Chromium-Browserbaselines (Abschlussläufe) | **PASS:** B2 169; B3 mit `--svg --audit` 204; Inquiry 796; Toolbar 1.108; Tasks 578; Admin/Statistik 3.571; Apply einschließlich 320 px 400; Listen 1.578; Mobile 918; Filter 1.564; B13-Audit 628. Keine Summierung überlappender Assertions zu einzigartigen Fällen. |
| Vollständiges Original-MVC/HTTP, B14/B15 | **PASS: 852 Assertions**, 88 DE/EN-Seiten-/Breitenprofile und vier Select-iframes; echte lokale Searchrequests, Grafikpixel, Copy-Locks/403/Retry, Auswahl, Tags-/Exportdialoge und negative Dokument-/Index-Apply-Kontexte. Dashboard weiterhin 26 migrierte Editbuttons; kein zusätzlicher Dashboard-Opt-in. |
| B16 vollständiger Dokument-/DOM-/Pixeltest | **PASS Chromium: 420 / Firefox: 388 Assertions**. Kontakte, Dashboard und Plain-Select; sichtbare Pixel, Symbolauflösung, AJAX, Filterchips, Disabled/Loading, Dialoge; Firefox-Tastaturgrenze unverändert. |
| Syntax/Inventur/Diff | **PASS:** PHP-Lint für alle 14 geänderten/neuen PHP-Dateien, Python-AST, beide Inventurscanner, Vorher/Nachher-HTML- und Layoutvergleich, `git diff --check`. |
| Notifications-Fixture | Ausgangslauf: bekannter unabhängiger Fehler nach 370 Checks (`Sales_Service_DocumentRevisionService` fehlt). Nach fremder Fixturekorrektur in `c196e6ad`: **PASS 382 + 64 + 24 Checks**. Keine B17-Reparatur und kein Übernehmen fremder Änderungen. |

Die vorhandenen Tests behalten ihre bisherigen Verhaltensassertions. Nur die begrenzten Delete-Scope-/SVG-Zähler und die absichtlich übersetzten Inline-Namen werden aktualisiert. B9-Apply erhält zusätzlich 320 px. Der Firefox-Testhelfer unterstützt nun auch erfolgreich ausgeführte JavaScript-Anweisungen ohne Rückgabewert (`undefined`), wie der Chromiumhelfer. Die frühere Statistikbaseline setzte zwingend einen noch vorhandenen alten Übersetzungsüberlauf voraus; nach der unabhängigen Übersetzungsbereinigung wird derselbe Legacyvergleich erhalten, ohne das Fortbestehen eines alten Fehlers zu verlangen. Keine Anwendungs-/Layoutkorrektur damit verbunden. Während der Testerstellung gefundene Testselektor-/Timingfehler wurden korrigiert; sie waren keine Anwendungsregressionen.

Reproduzierbare Hauptprüfungen:

```sh
php tests/ui/delete-apply-buttons.php
php tests/ui/button-inventory.php
python3 tests/ui/button-inventory.py --json
python3 tests/ui/button-release-audit.py --standard-actions --engine chromium --output /tmp/dewawi-b17/chromium
python3 tests/ui/button-release-audit.py --standard-actions --engine firefox --output /tmp/dewawi-b17/firefox
python3 tests/ui/apply-buttons.py
python3 tests/security/tenant-access-http.py
python3 tests/ui/button-page-integration.py --output /tmp/dewawi-b17/full-pages
python3 tests/ui/button-release-audit.py --document-icons --engine chromium --output /tmp/dewawi-b17/document-chromium
python3 tests/ui/button-release-audit.py --document-icons --engine firefox --output /tmp/dewawi-b17/document-firefox
```

Lokale Rohbelege: `/tmp/dewawi-b17/`, insbesondere `php-before.json`, `php-after.json`, `security-after.json`, `page-comparison.json`, `layout-comparison.json`, `runtime-{before,after}.json`, `source-{before,after}.json`, `chromium/standard-actions.json`, `firefox/standard-actions.json` und die jeweiligen Browserlogs. Temporäre Artefakte gehören nicht zum Commit.

## Freigabegrenzen und nächste Schritte

- Technischer B17-Nachweis für den begrenzten Scope; keine automatische Deploymentfreigabe. Manuell zu prüfen: echte Testrollen/Mandanten/Parent-Child-Sharing/SaaS-Pakete, vollständige fachliche Löschkaskaden/eBay-Anbindung, native Betriebssystem-Bestätigungsdialoge und mobile Touchbedienung mit langen realistischen Daten.
- Bestätigung/Abbruch werden im neuen Browsertest über kontrolliertes `window.confirm` und den unveränderten Handler geprüft; keine Behauptung einer Prüfung der nativen Browserdialogoberfläche.
- Chromium prüft Apply tatsächlich im Modaliframe einschließlich Parentkontaktübernahme, sequenziellen Items, Loading, Fehler/Retry, Auswahl und Fokus. Der vollständige Apply-Parentworkflow in Firefox bleibt **NOT TESTED**; Firefox-Popup-/SVG-Dokumentprüfung ist davon getrennt. Safari/WebKit und Screenreader nicht geprüft.
- B16-Dateirechteanforderung bleibt: Webserver muss den lokalen Katalog lesen können. Kein erneuter Produktiveingriff oder Cache-/Fallbackumbau.
- Nächster fachlich getrennter Block: Index-Apply-/Parentvertrag und Admin-media-delete-Doppelattribute klären; Dashboard-Legacy ohne Targets separat entscheiden. Danach normale Formular-/Modalcontrols mit belegtem Vertrag priorisieren. Dokument-/Zahlungs-/Positions-/Shopmigrationen brauchen eigene Abnahme; keine Freigabe allein aufgrund eines vorhandenen Iconkeys.

Keine Datenbankmigration oder manuelle Datenkorrektur erforderlich. Keine produktiven Daten geändert. Kein Push, Merge oder Deployment.
