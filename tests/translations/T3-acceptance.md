# T3 – Translation Integration Acceptance

Stand: 19. November 2026, lokaler Produktstand `88eef4c0` (`complete german and english translations`).
T1/T2-README, report.json und validation.json wurden vor den Prüfungen gelesen.

**Abnahme nicht erteilt.** Die Katalogparität ist vollständig, die Anwendung hat jedoch
bestätigte Integrationsfehler. 217 von 235 gezielten T3-Prüfungen bestehen, 18 scheitern:
HTTP/Browser **137/149**, PDF **66/72**, lokales SMTP/MIME **14/14**. Wiederholungen eines
Fehlers in verschiedenen Sprachen/Belegtypen sind einzelne Prüfungen, keine zusätzlichen
unabhängigen Fehlerursachen. Die bestehenden Suiten sind unten separat aufgeführt;
ihre teilweise kumulativen Assertions werden nicht in diese Summe addiert.

## Artefakte und Wiederholung

```bash
cd /var/www/dewawi
python3 -B tests/translations/acceptance.py > /tmp/t3-http-browser.json
php tests/translations/acceptance-pdf.php > /tmp/t3-pdf.json
python3 -B tests/translations/acceptance-mail.py > /tmp/t3-mail.json
python3 -B tests/translations/acceptance-suites.py > /tmp/t3-suites.json
python3 -B tests/translations/classify-hardcodes.py > /tmp/t3-hardcodes.json
php tests/site/cms-structure.php
php tests/site/public-errors.php
```

`acceptance.py` und `acceptance-pdf.php` liefern wegen der bestätigten Produktbefunde
Exit 1; das ist der tatsächliche rote Abnahmestand. `acceptance-suites.py` liefert wegen
zweier vorhandener Suite-Abbrüche ebenfalls Exit 1. Der reine Klassifikator und der
SMTP-Test liefern Exit 0. Keine Tests wurden passend zum Produktfehler grün umdefiniert.

- [t3-http-browser.json](t3-http-browser.json): jede HTTP-/Browserprüfung, Status,
  geladene Domains, Texthash, Diagnose und synthetische Schreiboperationen.
- [t3-pdf.json](t3-pdf.json): zwölf tatsächlich erzeugte und mit `pdftotext` gelesene PDFs,
  sechs einzelne Assertions je Kombination und extrahierter Text.
- [t3-mail.json](t3-mail.json): zwei lokal aufgefangene MIME-Nachrichten mit Betreff,
  UTF-8-Body und unverändertem PDF-Anhang.
- [t3-suites.json](t3-suites.json): vollständige Ergebnisse der acht bestehenden Aufrufe,
  einschließlich des verschachtelten Validators mit seinen bekannten Baselinefehlern.
- [t3-additional.json](t3-additional.json): CMS-/Error-Suiten, PHP-Lint und Python-Compile.
- [t3-hardcodes.json](t3-hardcodes.json): alle 204 Kandidaten einzeln, Originalfundstelle,
  Klasse, Begründung, Quellkontext und SHA-256; keine Sammelauslassungen.
- [t3-findings.json](t3-findings.json): priorisierte Befunde und konkrete T4-Dateien.

Benötigt: PHP/ZF1, Python, Chromium/ChromeDriver, `pdftotext`; für bestehende DB-Suiten
MariaDB-Werkzeuge und für den Tenant-HTTP-Test Apache. Browser/HTTP/SMTP benötigen lokale
Sockets. `acceptance-suites.py` startet ausschließlich die vorhandenen isolierten
Testdatenbanken. Abnahmeprogramme nie über den produktiven Webserver einbinden.

## Architektur und Ladeverhalten

Unverändert: Registry-Translator aus `Bootstrap::_initLocale`, `Application_Plugin_Translate`,
View-Helper und `DEEC_Translate`. Die Session-Locale hat Vorrang vor der Mandantenkonfiguration,
sonst `de_DE`. Der Loader lädt `default`, alphabetisch installierte `common.php`-Kataloge
und die Details des tatsächlich angeforderten Moduls. Erste Domain gewinnt, innerhalb einer
Domain spätere Datei; kein automatischer Wechsel in eine andere Sprache bei fehlendem Schlüssel.
Locale-Wechsel verwirft Instanzdaten; der Folgekontext wird neu geladen. Gemeinsame
Common-Kataloge sind für Navigation/Komponenten absichtlich modulübergreifend verfügbar.

**39/39 reale HTTP-Vergleiche** (13 Module, DE→EN→DE) stimmen in Domainreihenfolge und
SHA-256 sämtlicher wirksamer Texte mit T2 überein. Kein unerwartetes Laden fremder
Detailkataloge und keine unerwartete Domain-Überschreibung in diesen Requests. Das bestätigt
nicht automatisch die Translator-Anbindung alter Formularklassen oder dynamischer Schlüssel.
Die vorhandenen 157 Runtimeprüfungen bestehen weiterhin, einschließlich Nachladen bei
Forwards, Alias-/Invalid-Locale, fehlenden Schlüsseln, Formatfehlern und Instanzcache.

Bestand unverändert: **2 Sprachen**, **13 Anwendungsmodule**, **17 Katalogbereiche**,
**140 PHP-Katalogdateien** (70 je Sprache), **7.732 Definitionen** und **3.740 eindeutige
Schlüssel je Sprache**. Null fehlende Sprachdateien/Schlüsselgegenstücke, Katalogsyntax-,
UTF-8-, Duplikat- oder Platzhalterbefunde im erneuten statischen Audit. Die Parität findet
beispielsweise `USER`/`REPORTING` nicht, weil diese dynamischen Namen in beiden Sprachen fehlen.

## Ausgeführte Szenarien

Die neue HTTP-Fixture verwendet echte Bootstrap-/Auth-/Locale-Logik, Controller, Modul-Helper,
Views, Layouts und Übersetzungslader. Sie ersetzt ausschließlich Datenakquisition durch
einen In-Memory-Adapter und Sitzungs-/Dateipfade durch eigene temporäre Verzeichnisse.
Die SELECT-Fixture ist kein vollständiger SQL-Emulator: Geschäftslisten sind überwiegend
leer, komplexe Geschäftsabfragen liefern synthetische Leerresultate. Deshalb ist ein
HTTP 200 hier ein Nachweis des gerenderten Ablaufs, keine vollständige CRUD-/Fachabnahme.

| Modul | Tatsächlicher Aufruf | DE/EN HTTP und Ladevergleich | Chromium: Schlüssel/Breadcrumbs |
|---|---|---|---|
| admin | `/admin/index` | bestanden | bestanden; EN-Beschreibungen separat fehlgeschlagen |
| calendar | `/calendar/index` | bestanden | bestanden |
| campaigns | `/campaigns/campaign` | bestanden | bestanden |
| contacts | `/contacts/contact` | bestanden | bestanden |
| ebay | `/ebay/listing` | bestanden | fehlgeschlagen: acht Formularschlüssel, `LISTING` |
| items | `/items/item` | bestanden | bestanden |
| processes | `/processes/process` | bestanden | bestanden |
| purchases | `/purchases/purchaseorder` | bestanden | bestanden |
| sales | `/sales/quote` | bestanden | bestanden |
| shops | `/shops/index` | bestanden | bestanden, externe CDN-Skripte gesperrt |
| statistics | `/statistics/reporting` | bestanden | fehlgeschlagen: `REPORTING` |
| tasks | `/tasks/task` | bestanden | bestanden |
| users | `/users/user/edit` | bestanden | fehlgeschlagen: `USERS`, `USER` |

26 Modul-/Sprachkombinationen im Browser: 20 ohne sichtbare Schlüssel-/Breadcrumbbefunde,
sechs fehlgeschlagen. Das ist eine enge Schlüsselprüfung; weitere Hardcodes sind separat
bewertet. Der Browser prüft tatsächlichen `innerText` und UI-Attribute. Acht bekannte
eBay-Schlüssel bleiben in beiden Sprachen in Legacy-Formularfeldern; vier weitere dynamische
Namen bleiben in Breadcrumbs. `DEWAWI` ist bewusst als Marke ausgenommen. Diagnoseeinträge
wie `Import` und `Top 5 (%)` sind allein kein Beleg eines sichtbaren Übersetzungsfehlers.

Zusätzlich tatsächlich ausgeführt:

- DE-/EN-Loginformular und falsches Passwort über HTTP. EN-Gastpräsentation ist eine
  ausdrücklich markierte Fixture-Injektion; produktiv existiert keine anonyme Sprachauswahl.
  Echte Anmeldung mit synthetischem Konto über HTTP und per Browser-Tastatureingabe/Submit
  bestanden. EN-Anmeldefehler bleibt deutsch, DE-Login deklariert `lang="en"`.
- Direkter Sprach-Endpunkt, DE→EN→DE-Folgerequests, ungültige Locale (400), tatsächliche
  Browser-Auswahl und Reload. Endpunkt/Session/Follow-up bestanden; echte Auswahl bleibt
  wirkungslos, weil der JavaScript-Selektor zum alten Layout gehört.
- Profil-Auto-Save über HTTP sowie mit Browser-Tastatureingaben, Fokuswechsel und Reload
  in beiden Sprachen bestanden. Ungültige E-Mail und unerlaubtes Profilfeld geprüft.
  **`Form is invalid` ist hier ein API-Vertrag, kein bestätigter sichtbarer Hardcode:**
  `showSaveError()` übersetzt ihn im Browser korrekt zu „Bitte überprüfen Sie Ihre Eingaben.“
  beziehungsweise „Please check your entries.“. Keine pauschale API-String-Ersetzung empfohlen.
- Gemeinsam gerenderte Toolbar, Formulare, Navigation, Meldungen und mobile Navigation
  (390 px) geprüft; zusätzliche vorhandene Dashboard-Suite bei 390/768/1280 px bestanden.
  Englisches Profil enthält weiterhin „Neues Passwort“. Weitere Menü-/Media-Texte sind
  quellenbestätigte Restkandidaten, ihre bedingten Zweige wurden nicht vollständig ausgelöst.
- Öffentliche Startseite, Kontaktbestätigung/-fehler und fehlende CMS-Seite (404) in DE/EN.
  Kontaktbestätigung bestanden; englische Kontaktfehlerseite bleibt deutsch. Anonyme
  Startseiten nach Logout bestanden. Site DE bei UI EN und Rückkehr ins ERP EN bestanden.
- Logout und Gast-AJAX über den echten ACL-Forward: 401 und `session_expired`, ohne
  authentifizierten Mandantenkontext. Sicherheitscodes werden nicht als UI-Texte umbenannt.

## Belege, PDFs und E-Mails

Zwölf PDFs: Angebot, Auftrag und Rechnung × DE/EN-Beleg × DE/EN-UI. Verwendet werden die
reale Dokumentsprachauswahl, der kompakte Header, Summenrenderer und TCPDF mit synthetisch
vorgegebenem Payload. Alle PDFs erzeugt und lesbar, Beleglabels aufgelöst, keine rohen
`DOCUMENTS_*`-Schlüssel, UI-Registry und gespeicherte Testbeträge unverändert.

**Sechs Zahlenformat-Prüfungen scheitern**, wenn UI und Dokumentensprache abweichen:
`totalBox()` liest weiterhin `Zend_Locale` der UI. Beispielsweise enthält ein englischer
Beleg bei deutscher UI „Taxes (19,50%)“ statt „19.50%“. Bei umgekehrter Kombination gilt das
Gegenstück. `Sales_Service_DocumentPayloadService::build()` bezieht ebenfalls die UI-Locale;
Positions-/Währungsformatierung muss T4 deshalb mitprüfen. Das PDF-Datum bleibt `d.m.Y`;
die gewünschte englische Notation ist eine offene fachliche Entscheidung.

Die vorhandenen PDF-Suiten bestätigen zusätzlich Originalschutz, Mandantenisolation,
Archivzugriff und gemischte Steuersätze. Nicht ausgeführt: vollständige Belegerstellung
mit gespeicherten kundeneigenen Templates, realen Positionen und anschließendem Versand.

Der zusätzliche SMTP-Test verwendet echte Shop-Template-Expansion, PHPMailer, UTF-8 und
PDF-Anhänge gegen einen ausschließlich an `127.0.0.1` gebundenen Mailfänger. Zwei DE-/EN-
Nachrichten, jeweils bei entgegengesetzter UI-Sprache: 14/14 Prüfungen bestanden, Betreff
und Body korrekt, Attachmentbytes/Name unverändert, UI-Translator erhalten. Die Templates
werden synthetisch bereitgestellt. Bestehende Sales-Mailtests prüfen Rechte, Empfänger,
Wiederholung und Parent-Child-Kontext mit Transport-Doubles. **Keine externe Mailzustellung.**

## Bestehende Suiten: tatsächliche Ergebnisse

| Befehl/Teilprüfung | Ergebnis |
|---|---|
| `validate.py --stage T2B --browser` | Exit 0; 166 PHP/PHTML-Lints, 19 Audit-Parsertests, 157 Runtimeprüfungen, 28 Katalogkontexte und Audit-Wiederholung bestanden; zwei bekannte Baselinefehler separat |
| `security/quote-email.php` | 364 bestanden |
| `security/shop-email-content.php` | 75 bestanden |
| `security/contact-data.php` | 39 bestanden |
| `security/form-input-validation.php` | 20 bestanden |
| `security/user-profile.php` | 126 bestanden |
| `ui/buttons.php` / `ui/toolbar-buttons.php` | 165 / 2.259 bestanden; gemeinsame Vorprüfungen teilweise kumulativ |
| `sales/quote-monitoring.php` | 293 bestanden |
| `documents/pdf.php` / `documents/pdf-totals.php` | 208 / 244 bestanden; zweite Suite enthält erste |
| `translations/browser.py` | 13 Offline-Chromium-Prüfungen bestanden |
| `security/notifications.php` | **Baselinefehler**: 370 Hauptassertions bestanden, Kind-Fixture findet `Sales_Service_DocumentRevisionService` nicht |
| `contacts/history-states.php` | **Baselinefehler**: `history['states']` fehlt; „Missing history status: 100“ |
| `security/session-http.py` | 25 HTTP-Sessionfälle bestanden |
| `security/tenant-access-http.py` | 48 Apache-/ACL-/AJAX-/Tenantfälle bestanden, Root und Unterverzeichnis |
| `security/error-handling-http.py` | 140 bestanden, u. a. HTML/JSON/CLI-Cron und Development/Production-Modi in isolierter Fixture |
| `security/shop-checkout-db.py` | 17 Transaktions-/Replay-/Concurrency-/Isolationsfälle bestanden |
| `security/shop-inquiry-quote.py` | **fehlgeschlagen** vor HTTP/Browser in `inquiry-catalog.php:158`; Standalone-View hat keinen vollständigen Shop-Übersetzungskontext |
| `calendar/run.py` | **fehlgeschlagen** in `integration.php:23`; Semikolon-Split zerlegt Kommentar der offiziellen Migration, bevor Browser/SMTP erreicht werden |
| `ui/button-page-integration.py --dashboard` | 812 MVC-/Browser-/Responsive-Assertions bestanden |
| `site/cms-structure.php` / `site/public-errors.php` | 200 / 10 bestanden |

Die Shop-Anfrage-Fixture erwartet „Katalogvorschläge“; der übersetzte Text
„Unverbindliche Katalogvorschläge“ würde diese Teilstringprüfung erfüllen. Der isolierten
View fehlt jedoch die echte Translation-Helper-Anbindung und dem Registry-Translator der
Shop-Detailkatalog. Die spätere HTTP-Fixture lädt außerdem noch den nicht vorhandenen
separaten `languages/de_DE/country`-Pfad. Das sind konkrete Testintegrationslücken, kein
Nachweis einer fehlenden englischen Produktdatei. Die tatsächlichen Katalog-/Varianten-
Partials bestehen in der korrekt initialisierten T2-Runtime in beiden Sprachen.

Der Kalenderabbruch entsteht durch `explode(';', $sql)`: Der Kommentar in
`update-1.1.7-1.1.8.sql:391` enthält ein Semikolon. Das Restfragment wird als SQL ausgeführt.
Es wurde ausschließlich die temporäre Testdatenbank betroffen; die Migration blieb unverändert.

## Vollständige Klassifikation der 204 Kandidaten

| Klasse | Fundstellen | Bedeutung |
|---|---:|---|
| tatsächlich sichtbarer, übersetzbarer Text | 144 | 74 außerhalb Installer, 70 Installer; bedingte Sichtbarkeit berücksichtigt |
| technischer oder interner String | 49 | u. a. 36 GoBD-Dictionary-Beschreibungen, SQL/JS-Fehlalarme, Marken und Development-Diagnose |
| dynamisch beziehungsweise bereits übersetzt | 9 | Kontakt-/Anzeige-/Validierungsinterpolation, Sprachendonyme und passende neutrale E-Mail-Labels |
| fachlich unklar | 2 | gespeicherte Initialnamen „Vorlage“ und „Hauptlager“ |
| **gesamt** | **204** | jede Originalfundstelle genau einer Klasse zugewiesen |

Diese Liste ist keine vollständige Menge aller Restfehler: Login-Flashtexte, indirekte
Admin-Beschreibungen, Breadcrumbnamen und „Neues Passwort“ wurden zusätzlich gefunden.
Die 144 Fundstellen sind weder 144 eindeutige Schlüssel noch 144 im Browser beobachtete
Fehler. Klassifikation und Quellbeleg sind sicher, manche Laufzeitbedingungen bleiben offen.
Keine produktiven Texte, Schlüssel oder gespeicherten Initialnamen wurden geändert/gelöscht.

## Prioritäten und T4

1. **P1: echten Sprachwechsel reparieren** – `js/global.js:113` bindet an
   `#userinfo #language`; aktuelles `layout.phtml:83` verwendet `.dw-userbar`. Test zeigt
   gewähltes `en_US`, HTML weiter `de-DE`, kein `#userinfo`. Anschließend Wechsel in beide
   Richtungen mit Folge-AJAX und Modulwechsel abnehmen.
2. **P1: Beleg-Formatierung isolieren** – `DEEC/Pdf.php:1982`,
   `sales/services/DocumentPayloadService.php:7`, `controllers/helpers/Currency.php:14`.
   UI-Registry, historische Originale und gespeicherte Werte erhalten.
3. **P2: Legacy-Zend-Form und dynamische Breadcrumbs anbinden** –
   `ebay/forms/Listing.php:3`, `ebay/controllers/ListingController.php:41`,
   `application/views/helpers/Breadcrumbs.php:14/20`. Acht vorhandene eBay-Schlüssel und
   vier bislang undefinierte dynamische Namen separat behandeln; keine Komplettladung
   fremder Moduldetails als Reparatur.
4. **P2: sichtbare Resttexte gezielt anbinden** – Login-Controller/-View,
   `admin/views/scripts/index/index.phtml:20`, Shop-Kontakt-/Checkout-Fehlerseiten,
   `users/views/scripts/user/edit.phtml:24`, `user/password.phtml:9`; danach Media,
   Navigation und JS-Fehlerzweige nach Einzelklassifikation. API-Codes erhalten.
5. **P2: blockierte Tests vervollständigen** – `tests/security/inquiry-catalog.php:155`,
   `fixtures/inquiry-pilot-http.php:38`, `tests/calendar/integration.php:23`; zusätzlich
   bekannte Notification-/History-Fixtures beheben. Vorher keine grüne Gesamtabnahme behaupten.
6. **P3/fachlich:** Installer-Locale vor App-Bootstrap, Initialnamen, englische Datumsnotation,
   Exportmetadaten und die 301 bestehenden identischen Katalogwerte bei Bedarf separat prüfen.

## Offene Integrationsprüfungen und Grenzen

- Vollständige CRUD-/Dokumentworkflows mit isoliert befüllten Daten je Modul; aktuell primär
  Listen-/Form-/Layoutkontexte. Keine Behauptung einer vollständigen 13-Modul-Fachabnahme.
- Kalendertermin anlegen/ändern, Erinnerungen, Teilnehmer, Einladungen/Absagen inklusive
  DE-/EN-SMTP; vorhandener Runner bricht vorher ab. CLI-Cron-Fehlerbehandlung besteht,
  vollständige sprachliche Abnahme echter Hintergrundjobs bleibt offen.
- Shop-Katalog-/Varianten-/Anfrage→Angebot→Mail/PDF-Kette nach Reparatur der Fixture.
- Öffentliches interaktives Shop-/CMS-Verhalten mit den produktiv gepinnten CDN-Assets:
  externe Browserhosts waren gesperrt, Shop-jQuery daher nicht geladen. Serverrendering,
  Sprachkontext, Bestätigung/Fehler und 404 wurden geprüft; Bootstrap-Dialoge, öffentliche
  Live-AJAX-Suche und responsive Shop-Interaktionen sind nicht damit abgenommen.
- Kundeneigene CMS-Inhalte, Datenbank-Mailtemplates und externe Erweiterungen sind keine
  Repository-Kataloge. Kein Zugriff auf Kundendaten; diese Texte wurden nicht bewertet.
- TLS/Authentifizierung externer SMTP-Provider, Mailclientdarstellung, Bounce-Verarbeitung
  und Zustellung wurden nicht geprüft. Lokaler MIME-/Transportnachweis ersetzt diese nicht.
- Längere/multipage PDFs, Produktdatenblätter/Preislisten, Templatevarianten und sämtliche
  Belegpositionen mit abweichender Dokumentensprache weiter prüfen; hier kompakter Header
  und Summen mit synthetischem Payload sowie vorhandene Archiv-/Totals-Suiten.

## Sicherheit, Änderungen und Git

Nur Tests und Dokumentation unter `tests/translations/` ergänzt. Keine produktiven
Übersetzungen, Architektur, Rechte, Mandantenlogik oder Datenbankmigration geändert.
Synthetische IDs 42/7, temporäre Sessions; Produktionskonfiguration wird nicht eingelesen.
Der HTTP-Adapter schreibt nur synthetische Benutzer-/Trackingdaten. Bestehende Datenbank-
Runner verwenden eigene temporäre MariaDB-Datadirs ohne Netzwerkzugriff. Mail geht nur an
lokale Sink-Software; `example.invalid`-Adressen sind reine Fixturewerte.

Keine Kundendaten geändert, keine echten Kunden-E-Mails, keine Deployment-Aktion,
kein manueller DB-Follow-up erforderlich. **Kein Push durchgeführt.**
