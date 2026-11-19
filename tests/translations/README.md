# DEWAWI – T2: Übersetzungsreparatur und Vervollständigung

Stand: 19. November 2026. Ausgangspunkt: T1 `c6325897`. Technischer Block T2A:
`00a99d88` (`stabilize translation loading and locale contexts`). Maßgeblich ist der lokale
Arbeitsstand. Parallel vorhandene Dashboard-/Button-Arbeiten wurden weder überschrieben
noch in die Übersetzungscommits aufgenommen.

## Ausführen und Artefakte

```bash
cd /var/www/dewawi
python3 tests/translations/audit.py --fail-on-defects > /tmp/translations-report.json
python3 tests/translations/validate.py --stage T2B --browser > /tmp/translations-validation.json
```

Weitere Aufrufe:

```bash
python3 tests/translations/audit.py --summary
python3 tests/translations/audit.py --no-runtime
python3 -B tests/translations/audit-test.py
php tests/translations/runtime.php
python3 tests/translations/browser.py
```

[report.json](report.json) enthält alle Katalogtexte einschließlich Dateipfaden, Zeilen,
SHA-256, Datei-/Schlüsseldifferenzen, Verwendungen, dynamischen Aufrufen, Kollisionen und
Laufzeitkontexten. [validation.json](validation.json) ist der tatsächliche, wiederholbar
mit `validate.py` erzeugte Prüflauf einschließlich T1-/T2A-/T2B-Kennzahlen.
[review-notes.json](review-notes.json) benennt verbleibende fachliche Entscheidungen.
Die ursprünglichen T1-Berichte bleiben über `git show c6325897:tests/translations/report.json`
und die entsprechende README/validation erhalten; der T2A-Stand über `00a99d88`.

Der Audit liest ausschließlich Produktquellen/Kataloge. `inspect.php` prüft PHP-Syntax
und das direkt zurückgegebene String-Array mit PHP-Tokens, ohne Katalogcode auszuführen.
Auch Unicode-Escapes, Kommentare und doppelte Definitionen werden berücksichtigt. Es
wird kein Übersetzungscode per `eval` ausgeführt. Der Audit schreibt JSON auf stdout;
`--no-runtime` führt keine Anwendungsklassen aus. Normale Runtimeprüfungen verwenden
bekannte lokale Kataloge erst nach ihrer Format-/Encodingprüfung.

Die Runtime-/Regressionsprüfungen verwenden isolierte Auth-/Konfigurations-/Session-
Fixtures, In-Memory-Adapter und eigene temporäre Dateien. Kein Produktionsbootstrap,
keine produktive Datenbank, keine echten E-Mail-Empfänger und keine Kundendaten.
Chromium lädt ausschließlich lokale Dateien; externe Hosts sind gesperrt. `--browser`
benötigt ein ausführbares Chromium. Manche Sandboxen blockieren dessen interne Sockets;
der reine PHP-/Python-Audit benötigt keine Browserfreigabe.

Exitcodes von `audit.py`: 0 = Prüfung ausgeführt; 2 = Syntax-/Encoding-/Scan-/Runtimefehler.
`--fail-on-defects` liefert zusätzlich 1 bei fehlender Parität, Duplikaten, widersprüchlichen
geladenen Texten, Platzhalter-/Schreibfehlern oder unaufgelösten Aufrufen/Labelkandidaten.
Die beabsichtigte Markenrückgabe `DEWAWI` bleibt dabei einzeln sichtbar und wird begründet
ausgenommen. `--fail-on-findings` zählt weiterhin auch allgemeine manuelle Kandidaten,
z. B. identische Eigennamen; dessen Exit 1 ist deshalb keine Aussage über einen bestätigten
Produktfehler. Der Validator akzeptiert die beiden T1-Baselinefehler ausschließlich bei
passender Fehlersignatur; andere Fehler derselben Dateien gelten als neue Fehler.

## Architektur und technische Korrekturen

Das bestehende System aus `DEEC_Translate`, Registry, ZF1-Plugin und View-Helper bleibt
bestehen. Keine neue Übersetzungsarchitektur, Datenbankstruktur oder Bibliothek.

1. `application/Bootstrap.php::_initLocale()` wählt eine geprüfte Session-Locale vor der
   Mandantenkonfiguration, sonst ausdrücklich `de_DE`. Kein `scandir()[2]` und kein
   `file_exists` mit Benutzereingaben mehr. `DEEC_Translate::normalizeLocale()` unterstützt
   `de_DE`, `en_US` sowie die Aliasformen `de`/`de-DE`, `en`/`en-US`. Andere Sprachen
   wurden nicht eingeführt. `UserController::languageAction()` lehnt ungültige und
   nicht skalare Werte mit HTTP 400 ab. Benutzerrechte und Sprachauswahl bleiben getrennt.
2. Der Bootstrap lädt `languages/<locale>/default`. Die Registry-Einträge
   `DEEC_Translate`, `Zend_Locale` und `Zend_Currency` bleiben unverändert benannt.
3. `application/plugins/Translate.php` delegiert die vorhandene Ladepolitik an
   `DEEC_Translate::loadModule()`: `default`, dann alphabetisch die `common.php`-Dateien
   tatsächlich installierter Module, dann ausschließlich die Detaildateien des
   angeforderten Moduls. Das ist keine Komplettladung aller Modulkataloge.
4. Common-Dateien bleiben bewusst modulübergreifend verfügbar: Navigation und gemeinsame
   Komponenten benötigen sie. Es gibt keine Filterung dieser allgemeinen Texte nach
   Feature-Abonnement. Die eigentliche Berechtigungs-/Mandantenprüfung bleibt unabhängig.
   Externe Erweiterungen können explizit über die bestehenden `load`-/`loadDir`-APIs laden.
5. `preDispatch()` lädt nach Forwards den tatsächlich verwendeten Modulkontext nach.
   AJAX-Controller, Partials und geteilte Komponenten verwenden denselben Requesttranslator;
   sie führen keinen konkurrierenden Loader ein. Die drei gemeinsamen Kontaktbezeichner
   `CONTACTS_PHONE`, `CONTACTS_INTERNET`, `CONTACTS_CONTACT_DATA` wurden aus den
   Contacts-Details in `contacts/common.php` beider Sprachen verschoben.

**Prioritäten bleiben:** letzte Definition innerhalb einer Datei; alphabetisch spätere
Datei innerhalb einer Domain; zuerst geladene Domain vor späteren Domains. Explizite
Domainlisten in `t()` behalten ihre Suchreihenfolge. Vor T2B blieben sämtliche zuvor
aufgelösten Texte in allen 28 Kontexten unverändert. T2B korrigiert gezielt Textwerte;
abweichende geladene Definitionen wurden auf den gewünschten gemeinsamen Wert abgestimmt.
Das betrifft insbesondere den englischen Cookiehinweis und den Plural `Purchase orders`.

`DEEC_Translate` fängt Formatfehler einschließlich PHP-8-`ValueError` ab und gibt den rohen
Text zurück. `getIssues()` macht unbekannte Schlüssel, Formatfehler und Ladefehler
beobachtbar, ohne Kundentexte in globale Logs zu schreiben. Fehlende/ungültige Dateien
sperren die Domain nicht mehr; ein späterer gültiger Load bleibt möglich. Verzeichnisse
werden erst nach erfolgreichem Lesen aller enthaltenen Arrays übernommen. Ungültige
Rückgabetypen und nicht-stringförmige Werte werden verworfen und diagnostiziert.

`setLocale()` normalisiert die neue Locale und verwirft bei einem Wechsel Daten,
Loaded-Flags, Dateicache und Diagnosen. Danach muss der gewünschte Kontext erneut geladen
werden, z. B. `loadModule('sales')`. Ein Wechsel darf keine alten Texte behalten.
Gleiche Locale-Aliase löschen gültige Daten nicht. Der Dateicache gilt nur für die
Instanz und verhindert doppelte Auswertung gemeinsamer Dateien in mehreren Domains.
Neue Instanzen sehen Dateiänderungen; es gibt keinen zusätzlichen persistenten Cache
und keine Kopplung an `Zend_Cache`. Langlebige Jobs müssen ihre Locale/Instanz ausdrücklich
festlegen. Die 28 Kontextprüfungen kontrollieren auch die Anzahl gelesener Dateien.

**Fallbackentscheidung:** Kein automatisches Deutsch in englischen Belegen und kein
Englisch in deutschen Belegen. Eine solche Ersatzsprache würde fehlende Übersetzungen
verschleiern. Stattdessen sind beide Katalogbestände vollständig angeglichen. Unbekannte
Schlüssel bleiben als Schlüssel sichtbar, werden diagnostiziert und im Audit weiterhin
ungefiltert als fehlende Definitionen erfasst. Leere gültige Werte bleiben leer.

## Getrennte Sprachkontexte und Kompatibilität

- UI/AJAX: Session-/Mandanten-Locale bleibt maßgeblich. Form-/List-/View-APIs und die
  bisherigen jQuery-/`data-*`-Verträge bleiben erhalten. `Form::setLocale()` ist weiterhin
  ein Formatierungskontext; Übersetzer werden ausdrücklich injiziert oder aus der Registry
  bezogen. Bestehende Komponenten behalten ihr einmal bezogenes Translatorobjekt.
- PDF: `DEEC_Pdf::buildDocumentPayload()` wählt für unterstützte Dokumentensprachen einen
  eigenen Translator vor dem Rendern. Korrekturdokumente und Revisionsbezeichnungen
  verwenden dieselbe Locale-Normalisierung. Die UI-Registry wird nicht umgestellt.
  Neue Sales-/Purchases-Standardbelegtitel folgen ihrer primären Dokumentensprache.
  Gespeicherte Titel, Beträge, Sprachen und Originaldateien werden nicht verändert.
- Historische Dokumente: vorhandene Originale werden vor neuem Rendering unverändert
  zurückgegeben. Die PDF-Tests prüfen Originalschutz, Mandantenisolation, gemischte
  Steuersätze und gespeicherte Summen. Keine Neuübersetzung archivierter Originale.
- Öffentlicher Shop/CMS: Erst die geroutete `shops`-Anfrage verwendet die Site-Locale
  einschließlich `Zend_Locale`/`Zend_Currency`. ERP-Routen behalten die UI-Auswahl.
  HTML-Sprache, Shoptexte und JavaScript-Währungsdarstellung stimmen dadurch zusammen.
- E-Mail/Jobs: Shop-Mailmarker und Betreffmarker verwenden einen eigenen Translator nach
  der konfigurierten Shop-Sprache, auch ohne HTTP-Plugin. Feedback normalisiert die
  angeforderte Locale; Kalender-Einladungen behalten ihren eigenen geprüften Translator.
  Explizit gespeicherte Sales-/Purchases-Mailtemplates werden nicht automatisch umgeschrieben.
- CLI: bestehende explizite Registrierungen funktionieren weiter. `loadModule()` kann den
  notwendigen Kontext ohne Bootstrap und ohne Datenbankzugriff herstellen.

## Inhaltliche Bereinigung

Alle 943 ursprünglich fehlenden englischen Schlüssel wurden bearbeitet. Dafür wurden
509 unterschiedliche deutsche Ausgangstexte geprüft; eindeutige vorhandene englische
Zuordnungen wurden wiederverwendet und darin erkannte Fehler korrigiert. Keine externe
Übersetzungs-API, keine deutschen Ersatzkopien als Englisch. Sämtliche 22 fehlenden
englischen Dateigegenstücke und die vier umgekehrten Gegenstücke sind vorhanden,
einschließlich beibehaltener Legacy-/Erweiterungskataloge.

Die 16 überschriebenen Wiederholungsdefinitionen wurden entfernt, wobei jeweils ihr
bisheriger letzter, tatsächlich wirksamer Wert erhalten blieb. Kein Katalogschlüssel
wurde gelöscht. Ein zusätzlicher Test vergleicht den gesamten ursprünglichen
Schlüsselbestand aus T1 mit beiden aktuellen Sprachen, auch ohne Verwendungsnachweis.

Korrigiert wurden die 17 bestätigten Schreibfehler, neun deutsche PDF-Referenztexte im
englischen Katalog, Sortier-/Ländernamen, E-Mail-Texte und englische Versandbeschreibungen.
Historische API-Schlüssel wie `*_PAYED`/`*_SUCCESFULLY_*` bleiben kompatibel erhalten.
Fachliche Labels berücksichtigen Kontakte, Lager, Einkauf/Verkauf, Belege, Kalender,
Prozesse, Aufgaben, Statistik, Shop/CMS und Formulare. Mehrdeutige fachliche Entscheidungen
werden in `review-notes.json` ausdrücklich offen gehalten.

Neu angebunden wurden fehlende Formular-/Toolbar-/Statusbezeichner sowie Shop-Erfolgs-
und Fehlermeldungen, Anfrage-/Katalog-/Variantenhinweise, Warenkorb-/Checkout-Bezeichner,
Suchplatzhalter und leere Historien. Shop-Auftragsviews verwenden die eigenen `SHOPS_*`
statt ungeladener `EBAY_*`-Bezeichner. JavaScript erhält seine übersetzten Anfragehinweise
über Attribute des tatsächlichen Formulars; das funktioniert auch nach AJAX-Ersetzung.
Der bestehende globale Message-Export bleibt erhalten. Node war nicht vorhanden;
Syntaxprüfung aller drei geänderten JS-Dateien und reale jQuery-Interaktionen erfolgten
im Offline-Chromium-Test für Deutsch und Englisch.

## Offene Prüfungen und fachliche Grenzen

`review-notes.json` beschreibt verbleibende Entscheidungen: Auftragsbestätigung gegenüber
Proformarechnung, erhaltene/geleistete Anzahlungen im Einkauf, Reihenfolge gegenüber
Mengenbezeichnung und unklare historische Versandproduktnamen. Hier wurden keine
Geschäftsregeln oder numerischen Werte geraten.

Verbleibende identische Texte und Hardcode-Kandidaten sind vollständige Prüflisten im
Bericht, keine pauschal bestätigten Fehler oder Löschfreigaben. Sie umfassen Eigennamen,
Abkürzungen, Ländernamen, technische Werte und weitere Legacy-Oberflächen. Die klar
abgegrenzten genannten UI-Flows wurden übersetzt; eine vollständige redaktionelle
Abnahme jeder Oberfläche ist nicht behauptet. Weitere alte Preisfragmente behalten
ihre bisherige Zahlenformatierung; geändert wurden Texte, nicht Preisberechnungen.

Keine produktive Datenbank oder Kundendaten wurden gelesen/geändert. Gespeicherte
Mail-/CMS-Texte, Produktbeschreibungen, Feldnamen und bisher versandte E-Mails bleiben
unverändert. Vollständige authentifizierte Browserflows und echte SMTP-Zustellung wurden
nicht ausgeführt. DB-Integrations-/Migrationstests waren ohne Schemaänderung nicht nötig.
Keine neue Migration oder manuelle Datenbanknacharbeit erforderlich.

## Verifikation

Der vollständige Validator läuft mit `--stage T2B --browser`. Er prüft Syntax aller
140 Kataloge sowie der betroffenen PHP-/PHTML-Dateien, Auditparser, Locale-/Format-/Retry-
Logik, alle 28 Modul-/Sprachkontexte einschließlich SHA-256 sämtlicher effektiver Texte,
Forward-/Mandantenkontext, echte Sprachwechsel-Endpunkte, isolierte E-Mail-/PDF-/Shop-
Partialkontexte und den Erhalt aller ursprünglichen Schlüssel.

Die beiden unveränderten T1-Baselinefehler bleiben gesondert ausgewiesen:

- `tests/security/notifications.php`: nach 370 erfolgreichen Hauptprüfungen fehlt im
  Dokumenthelper-Subprozess `Sales_Service_DocumentRevisionService` (`Finish.php:12`).
- `tests/contacts/history-states.php`: fehlendes `history['states']` (`:57`), danach
  `Missing history status: 100` (`:69`).

Andere Fehler gelten als neue Fehler. Diese beiden Gesamttests werden nicht als bestanden
gezählt. Die folgenden Tabellen geben die aktuellen exakten Zahlen und Ergebnisse wieder.

## Vorher / nach T2A / nach T2B

| Kennzahl | T1 | T2A | T2B |
| --- | ---: | ---: | ---: |
| Sprachen | 2 | 2 | 2 |
| Anwendungsmodule | 13 | 13 | 13 |
| Katalogdateien | 114 | 115 | 140 |
| Definitionen inkl. dateiübergreifender Wiederholungen | 6206 | 6206 | 7732 |
| Verschiedene Schlüssel (Vereinigung) | 3607 | 3607 | 3740 |
| Fehlende englische Schlüssel | 943 | 943 | 0 |
| Fehlende deutsche Schlüssel | 94 | 94 | 0 |
| Fehlende englische Dateien | 22 | 21 | 0 |
| Fehlende deutsche Dateien | 4 | 4 | 0 |
| Doppelte Definitionen in einer Datei | 16 | 16 | 0 |
| Bestätigte Schreibfehler | 17 | 17 | 0 |
| Platzhalterabweichungen | 0 | 0 | 0 |
| Hardcode-Kandidaten (manuell prüfen) | 256 | 256 | 204 |

Aktuell enthält jede Sprache **70 Dateien, 3.866 Definitionen und 3.740 verschiedene Schlüssel**.
Alle 3.607 ursprünglichen Schlüssel sind in beiden Sprachen erhalten. 133 zusätzliche
Schlüssel beheben vorher fehlende Labels bzw. binden sichtbare Texte an. Sprachparität:
3.740 / 3.740 = 100 %. Dies bedeutet keine automatische redaktionelle Zertifizierung
sämtlicher Produkt-, Datenbank- oder Drittbibliothekstexte. Alle `defect_counts` sind null.

### Modul-/Sprachmatrix

Spalten: Dateien / Definitionen / verschiedene Schlüssel. Bereiche ohne aktuelles
Anwendungsmodul bleiben als Legacy-/Erweiterungsbestand erhalten und werden nicht global geladen.

| Bereich | Anwendung | de_DE | en_US |
| --- | --- | ---: | ---: |
| admin | ja | 4 / 267 / 267 | 4 / 267 / 267 |
| calendar | ja | 1 / 147 / 147 | 1 / 147 / 147 |
| campaigns | ja | 1 / 148 / 148 | 1 / 148 / 148 |
| contacts | ja | 9 / 458 / 454 | 9 / 458 / 454 |
| default | ja | 19 / 993 / 984 | 19 / 993 / 984 |
| ebay | ja | 1 / 138 / 138 | 1 / 138 / 138 |
| ebiztrader | Legacy/Erweiterung | 1 / 43 / 43 | 1 / 43 / 43 |
| items | ja | 12 / 336 / 330 | 12 / 336 / 330 |
| magento | Legacy/Erweiterung | 1 / 38 / 38 | 1 / 38 / 38 |
| portals | Legacy/Erweiterung | 1 / 28 / 28 | 1 / 28 / 28 |
| processes | ja | 2 / 117 / 113 | 2 / 117 / 113 |
| purchases | ja | 4 / 159 / 148 | 4 / 159 / 148 |
| sales | ja | 8 / 571 / 536 | 8 / 571 / 536 |
| shops | ja | 1 / 243 / 243 | 1 / 243 / 243 |
| statistics | ja | 2 / 56 / 53 | 2 / 56 / 53 |
| tasks | ja | 1 / 114 / 114 | 1 / 114 / 114 |
| users | ja | 2 / 10 / 10 | 2 / 10 / 10 |

### Vollständiges aktuelles Dateiinventar

Alle Anwendungskataloge sind UTF-8 ohne BOM und geben ein String-Array zurück. Leere
Arrays in `admin/client.php` und `users/common.php` sind zulässige Stubkataloge, keine leeren
Übersetzungswerte. Die Schlüsselmenge ist auch in jeder korrespondierenden Datei identisch.

| Relativ unter languages/&lt;locale&gt;/ | de Definitionen | en Definitionen | fehlende Schlüssel je Datei |
| --- | ---: | ---: | ---: |
| admin/admin.php | 256 | 256 | 0 |
| admin/client.php | 0 | 0 | 0 |
| admin/menu.php | 9 | 9 | 0 |
| admin/page.php | 2 | 2 | 0 |
| calendar/common.php | 147 | 147 | 0 |
| campaigns/campaigns.php | 148 | 148 | 0 |
| contacts/bankaccount.php | 4 | 4 | 0 |
| contacts/comment.php | 2 | 2 | 0 |
| contacts/common.php | 24 | 24 | 0 |
| contacts/contact.php | 131 | 131 | 0 |
| contacts/contactperson.php | 23 | 23 | 0 |
| contacts/download.php | 6 | 6 | 0 |
| contacts/inquiry.php | 244 | 244 | 0 |
| contacts/map.php | 22 | 22 | 0 |
| contacts/pricerule.php | 2 | 2 | 0 |
| default/category.php | 16 | 16 | 0 |
| default/comment.php | 2 | 2 | 0 |
| default/common.php | 99 | 99 | 0 |
| default/controlpanel.php | 23 | 23 | 0 |
| default/countries.php | 202 | 202 | 0 |
| default/document.php | 220 | 220 | 0 |
| default/feedback.php | 30 | 30 | 0 |
| default/menu.php | 31 | 31 | 0 |
| default/messages.php | 141 | 141 | 0 |
| default/months.php | 12 | 12 | 0 |
| default/order.php | 24 | 24 | 0 |
| default/pagination.php | 5 | 5 | 0 |
| default/position.php | 30 | 30 | 0 |
| default/settings.php | 28 | 28 | 0 |
| default/state.php | 11 | 11 | 0 |
| default/tabs.php | 34 | 34 | 0 |
| default/toolbar.php | 71 | 71 | 0 |
| default/user.php | 6 | 6 | 0 |
| default/validation.php | 8 | 8 | 0 |
| ebay/ebay.php | 138 | 138 | 0 |
| ebiztrader/ebiztrader.php | 43 | 43 | 0 |
| items/attribute.php | 8 | 8 | 0 |
| items/common.php | 17 | 17 | 0 |
| items/import.php | 10 | 10 | 0 |
| items/inventory.php | 14 | 14 | 0 |
| items/item.php | 75 | 75 | 0 |
| items/itemlist.php | 21 | 21 | 0 |
| items/ledger.php | 49 | 49 | 0 |
| items/option.php | 8 | 8 | 0 |
| items/pricerule.php | 32 | 32 | 0 |
| items/stock.php | 24 | 24 | 0 |
| items/stockplanning.php | 69 | 69 | 0 |
| items/variant.php | 9 | 9 | 0 |
| magento/magento.php | 38 | 38 | 0 |
| portals/portal.php | 28 | 28 | 0 |
| processes/common.php | 4 | 4 | 0 |
| processes/processes.php | 113 | 113 | 0 |
| purchases/common.php | 11 | 11 | 0 |
| purchases/purchaseorder.php | 74 | 74 | 0 |
| purchases/purchases.php | 2 | 2 | 0 |
| purchases/quoterequest.php | 72 | 72 | 0 |
| sales/common.php | 38 | 38 | 0 |
| sales/creditnote.php | 76 | 76 | 0 |
| sales/deliveryorder.php | 73 | 73 | 0 |
| sales/invoice.php | 124 | 124 | 0 |
| sales/quote.php | 111 | 111 | 0 |
| sales/reminder.php | 75 | 75 | 0 |
| sales/sales.php | 2 | 2 | 0 |
| sales/salesorder.php | 72 | 72 | 0 |
| shops/shops.php | 243 | 243 | 0 |
| statistics/common.php | 3 | 3 | 0 |
| statistics/statistics.php | 53 | 53 | 0 |
| tasks/tasks.php | 114 | 114 | 0 |
| users/common.php | 0 | 0 | 0 |
| users/user.php | 10 | 10 | 0 |

### Tatsächliche Ladeabdeckung

Spalten: tatsächlich ausgewertete Dateien / logische Dateieingänge der Domains /
effektive Schlüssel / unaufgelöste direkte Modulaufrufe. Gemeinsam geladene Dateien
werden trotz ihrer Verwendung in zwei Domains nur einmal ausgewertet. Die vollständige
sortierte Ladefolge, Quellbytes, Gewinner und SHA-256 der Texte stehen im JSON.

| Aktives Modul | de_DE | en_US |
| --- | ---: | ---: |
| default | 27 / 27 / 1227 / 0 | 27 / 27 / 1227 / 0 |
| admin | 31 / 31 / 1494 / 0 | 31 / 31 / 1494 / 0 |
| calendar | 27 / 28 / 1227 / 0 | 27 / 28 / 1227 / 0 |
| campaigns | 28 / 28 / 1375 / 0 | 28 / 28 / 1375 / 0 |
| contacts | 35 / 36 / 1655 / 0 | 35 / 36 / 1655 / 0 |
| ebay | 28 / 28 / 1364 / 0 | 28 / 28 / 1364 / 0 |
| items | 38 / 39 / 1537 / 0 | 38 / 39 / 1537 / 0 |
| processes | 28 / 29 / 1335 / 0 | 28 / 29 / 1335 / 0 |
| purchases | 30 / 31 / 1361 / 0 | 30 / 31 / 1361 / 0 |
| sales | 34 / 35 / 1725 / 0 | 34 / 35 / 1725 / 0 |
| shops | 28 / 28 / 1433 / 0 | 28 / 28 / 1433 / 0 |
| statistics | 28 / 29 / 1276 / 0 | 28 / 29 / 1276 / 0 |
| tasks | 28 / 28 / 1341 / 0 | 28 / 28 / 1341 / 0 |
| users | 28 / 29 / 1236 / 0 | 28 / 29 / 1236 / 0 |

Sämtliche 28 Kontexte stimmen mit dem echten Translator-/Plugin-Ergebnis überein.
Keine ungelöste direkte Modulverwendung und keine unterschiedlich belegte geladene
Definition bleibt zurück. Der allgemeine Markenfallback `DEWAWI` ist explizit dokumentiert.

### Verwendungen und konservative Unbenutztanalyse

Untersucht: 1085 Produktquelldateien; 1525 literale und 197 dynamische Aufrufe.
Methoden-/Formular-/Listenmetadaten, Länder aus der DB, zusammengesetzte PDF-/Fehlercodes,
SQL-Menüs und E-Mail-Marker bleiben indirekte Verbraucher. Testfixtures zählen nicht als
Produktverwendung. Die Klassifizierung aller Katalogschlüssel lautet:

| Klasse | Schlüssel |
| --- | ---: |
| unresolved | 912 |
| literal_call_found | 906 |
| indirect_literal_candidate | 1819 |
| possibly_dynamic | 103 |
| sicher unbenutzt / Löschfreigabe | **0** |

301 identische DE/EN-Definitionen und 204 verbleibende Hardcode-Kandidaten werden mit
Datei-/Zeilenbeleg zur manuellen Prüfung ausgegeben. Keine dieser Listen wird als
automatischer Löschbeweis oder pauschale Fehlerzahl verwendet.

### Drittbibliotheken

Unverändert separat inventarisiert: 602 Dateien in Sprachverzeichnissen von jQuery, CKEditor,
TCPDF, FileManager, MediaManager, elFinder und TinyMCE sowie 726 ZF1-CLDR-XML-Dateien.
README-/Statusdateien und Formatierungsmetadaten zählen nicht als zusätzliche ERP-Sprachen.
Die vollständigen Pfade stehen unter `scan`; deren sprachliche Qualität wird nicht als
abgenommen behauptet. Keine Fremdbibliothek wurde geändert.

### Tatsächlich ausgeführte Tests

| Prüfung | Ergebnis |
| --- | --- |
| `php -l (catalogues, affected PHP and audit fixtures)` | 165 Syntaxprüfungen bestanden |
| `python3 -B tests/translations/audit-test.py` | 19 Prüfungen bestanden |
| `php tests/translations/runtime.php` | 157 Prüfungen bestanden |
| `php tests/security/quote-email.php` | PASS: 364 quote email permission, recipient, resend and tenant-sharing checks |
| `php tests/security/shop-email-content.php` | OK (75 checks) |
| `php tests/security/contact-data.php` | PASS: 39 tenant-safe contact data cases |
| `php tests/security/form-input-validation.php` | PASS: 20 full-form, AJAX, decimal, integer and date validation cases |
| `php tests/security/user-profile.php` | PASS: 126 user profile and password cases |
| `php tests/ui/buttons.php` | PASS: 165 button presentation, pilot, legacy, translation and permission checks |
| `php tests/ui/toolbar-buttons.php` | PASS: 165 button presentation, pilot, legacy, translation and permission checks; PASS: 2259 shared toolbar, layout, SVG, translation, permissions, readonly and exclusion checks |
| `php tests/sales/quote-monitoring.php` | PASS: 293 quote monitoring, defaults, customer actions, due dates and tenant cases |
| `php tests/documents/pdf.php` | PASS: 208 PDF checks; canonical archive and historical originals preserved, tenant isolation retained |
| `php tests/documents/pdf-totals.php` | PASS: 244 PDF checks including mixed-rate total boxes, exemption and balance |
| `php tests/security/notifications.php` | Bekannter T1-Fehler mit bestätigter Fehlersignatur; nicht bestanden |
| `php tests/contacts/history-states.php` | Bekannter T1-Fehler mit bestätigter Fehlersignatur; nicht bestanden |
| `python3 -B tests/translations/browser.py` | 13 Offline-Chromium-Prüfungen bestanden, beide Sprachen |
| `python3 tests/translations/audit.py --fail-on-defects` | 28/28 Modelle stimmen überein; strenger Defektcheck bestanden |
| `repeat complete audit and compare JSON bytes` | Zwei vollständige Berichte byteidentisch |

Der Browser prüft die Syntax von `global.js`, `shops.js`, `settings.js` und die echten
jQuery-Funktionen für Auswahl-/Leer-/Variantenhinweise. Die PHP-Fixtures rendern außerdem
die realen Katalog-/Variantenpartials einschließlich Preiszweig und HTML-Escapierung in
beiden Sprachen. Das sind keine vollständigen authentifizierten HTTP-Tests.

### Relevante geänderte Dateien

- Technik: `library/DEEC/Translate.php`, `Pdf.php`, `Feedback.php`; `application/Bootstrap.php`,
  `application/plugins/Translate.php`, `application/modules/users/controllers/UserController.php`;
  Sales-/Purchases-`services/CreateDataFactory.php`, Sales-`DocumentPayloadService.php` und
  `application/controllers/helpers/Email.php`.
- Kataloge: die in der Inventartabelle aufgeführten Bereiche unter `languages/de_DE` und
  `languages/en_US`; alle neuen englischen/deutschen Gegenstücke sowie vorhandene korrigierte Dateien.
- UI: `application/layouts/scripts/site.phtml`, `application/views/scripts/partials/history.phtml`,
  Shop-Views `cart/index`, `checkout/index`, `checkout/success`, `contact/success`,
  `inquiry/index`, `inquiry/success`, `inquiry/catalog`, `inquiry/variants`, `order/index`;
  `js/global.js`, `js/shops.js`, `js/settings.js`.
- Verifikation/Dokumentation: `tests/translations/*` und der um echte Übersetzungen/englische
  Ausgaben erweiterte Bestandstest `tests/security/shop-email-content.php`.

Quellfingerabdruck: `e703c2380fb7993bbd809fdf9dcb810a23ba17b3db3961db9d8c176eac120819`.
Der Fingerabdruck enthält den maßgeblichen lokalen Produktstand, einschließlich separat
bearbeiteter UI-Dateien; deren Änderungen wurden nicht in diesen Commit übernommen.

## T3 – Integrationsabnahme

Die Abnahme auf Produktstand `88eef4c0` ist in [T3-acceptance.md](T3-acceptance.md)
dokumentiert. **Nicht abgenommen:** 217/235 gezielte Prüfungen bestanden, 18 fehlgeschlagen.
Alle 13 Module wurden in DE/EN über isolierte HTTP-/Chromium-Flows geöffnet; 39/39
Lade-/Texthashvergleiche stimmen. Kritisch bleiben der JavaScript-Sprachumschalter und
das PDF-Zahlenformat bei abweichender Beleg-/UI-Sprache. Weitere sichtbare Schlüssel,
Sprachmischungen und blockierte Bestandssuiten sind mit konkreten T4-Dateien dokumentiert.

Die vollständige Einzelklassifikation der 204 Hardcode-Kandidaten steht in
[t3-hardcodes.json](t3-hardcodes.json): 144 übersetzbare, 49 technische/interne,
neun dynamische/bereits passende und zwei fachlich offene Fundstellen.
T1-/T2-Artefakte bleiben unverändert. Die neuen Abnahmeprogramme dürfen bei bestätigten
Restfehlern bewusst Exit 1 liefern; genaue Aufrufe und tatsächliche Ergebnisse siehe T3-Bericht.

## T4 – Acceptance Fixes

Aktueller Abnahmestand: **235/235 gezielte T3-Prüfungen bestanden**, vorher 217/235.
Zusätzlich zwölf Browser- und 300 Zahlenformatprüfungen bestanden. Ursachen, Änderungen,
alle 18 Korrekturnachweise, unabhängige Bestandssuite-Abbrüche und genaue Aufrufe stehen in
[T4-acceptance.md](T4-acceptance.md). Maschinenlesbare Zusammenfassung:
[t4-acceptance.json](t4-acceptance.json), aktueller Audit:
[t4-report.json](t4-report.json), letzter Validator:
[t4-validation.json](t4-validation.json).

Die aktuelle HTTP-Abnahme verwendet ausdrücklich `--report tests/translations/t4-report.json`.
T2-/T3-Berichte bleiben als Vorher-Nachweise unverändert. 14 gezielte neue Schlüssel je Sprache;
alle bisherigen Schlüssel/Werte und Domainreihenfolgen erhalten. Keine pauschale Bearbeitung
der übrigen Hardcodes, keine Migration und keine produktiven Daten-/Mailänderungen.
