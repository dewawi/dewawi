# T5 – Translation Cleanup and Quality Gates

Stand: 19. November 2026. Bezug: T4 `22bc7ab1`; Arbeitsbeginn auf lokalem
`99e760c8`. Die späteren SVG-/Berechtigungsänderungen bleiben erhalten.
T3-/T4-Berichte sind historische Nachweise und werden nicht überschrieben.

## Ergebnis und Bilanz

Die gezielte T3-/T4-Abnahme besteht unverändert mit **235/235** Prüfungen:
149 HTTP-/Browserprüfungen, 72 PDF-Prüfungen, 14 E-Mail-/SMTP-Prüfungen.
Zusätzlich: 12 erweiterte Browserprüfungen, 300 Zahlenformatprüfungen,
86 neue Renderingprüfungen und 33 Offline-Chromium-Prüfungen.
Alle strukturellen Translation-Prüfungen bestehen; 28 Modul-/Sprachkontexte
entsprechen dem statischen Lademodell. Die unabhängigen Suiten sind wegen der
unten genannten bestehenden Fehler ausdrücklich nicht insgesamt bestanden.

| Ursprüngliche 204 Kandidaten | Anzahl | Behandlung |
| --- | ---: | --- |
| Bereits durch T4 erledigt | 9 | IDs 39–44 und 54–56, vorhandene Schlüssel beibehalten |
| Durch T5 korrigiert | 133 | 63 Anwendungs- und 70 Installer-Fundstellen |
| Technisch/intern | 51 | Ursprünglich 49 plus die Kommentare 26 und 157 |
| Dynamisch beziehungsweise bereits übersetzt | 9 | Unverändert, exakte Audit-Ausnahmen |
| Fachlich ungeklärt | 2 | IDs 4/5, bestehende Geschäftsnamen unverändert |
| Verbleibende bestätigte UI-Hardcodes dieser Liste | **0** | Keine pauschalen Ersetzungen |

Die ursprünglichen 144 UI-Kandidaten ergeben damit 9 T4 + 133 T5 + 2
nachweislich unsichtbare Kommentare. ID 26 ist der `title` eines deaktivierten
Videos innerhalb eines HTML-Kommentars; ID 157 eine auskommentierte
`pushMessages`-Zeile. Beide bleiben unverändert. Der bereits technisch
klassifizierte PHPDoc-Kandidat 204 wird ebenfalls nicht als UI geprüft.

[t5-hardcodes.json](t5-hardcodes.json) enthält jeden der 204 ursprünglichen
Datensätze mit ID, ursprünglichem Dateiverweis/Text, Ergebnis, Schlüssel oder
Begründung und aktuellem Quelldateihash. Die Summary prüft diese Zuordnung erneut.
Es wurde keine neue vollständige manuelle Bestandsaufnahme durchgeführt.

**Fachliche Fragen:** `application/modules/admin/models/Service/ClientInitializer.php`
erzeugt die Geschäftsnamen `Vorlage` und `Hauptlager`. Zu entscheiden ist, ob dies
bewusst gespeicherte Mandantennamen sind oder ob neue Mandanten künftig lokalisierte
Startnamen erhalten sollen. Bestehende Namen, Datenbankwerte und Initialisierung
wurden nicht geändert. Keine fachliche Annahme wurde als Übersetzung umgesetzt.

## Änderungen und Sprachkontexte

- Gemeinsame Komponenten: `MainMenu.php`, `Media.php`, `MultiForm.php`, Footer,
  Dashboard, Download-/E-Mail-Partials. Sichtbare Texte und zugängliche Namen
  verwenden vorhandene oder gezielt ergänzte gemeinsame Katalogschlüssel.
  Platzhalter/Attribute bleiben escaped; `data-*`, URL-Generierung und Aktionen
  bleiben erhalten.
- Module: Admin-Redirect-Beispiel, interne Artikelinformation, Datenblatt- und
  Preislistentitel, Verkaufsbeleg-Aktionen. Modultexte bleiben in den jeweiligen
  bestehenden Modul-/Common-Katalogen.
- Kontakte: `InquiryView.php` liefert Schlüssel für Auswahlprüfung und Status.
  Die drei Inquiry-Ansichten unterstützen Formatargumente; technische Reason-Codes
  werden unverändert interpoliert und bei HTML-Ausgabe escaped.
- Shop/CMS: Kategorie-/Artikelpreise, Anfragehinweise, Versandseitenlinks,
  Karussell-Labels, Suchplatzhalter, Bestätigungs-/Fehlertexte und dynamische
  Anfrage-Buttons. Die an denselben Fundstellen angrenzenden Preislabels und
  Fehlerformular-Feldnamen sowie die unmittelbar zur geprüften Anfragewarnung gehörenden
  Anforderungs-/Ergebnis-/Preislabels sind ebenfalls lokalisiert. Produkt-/CMS-Inhalte,
  Einheiten, Formularfeldnamen und Protokollwerte bleiben unverändert.
- JavaScript: Varianten-/Positionsfehler, nicht mehr verfügbare Datensätze,
  Medienlöschbestätigung und Speichern-/Löschen-Fallbacks nutzen `Dewawi.message`.
  `Messages.php` exportiert die drei zusätzlich benötigten Schlüssel; der
  bestehende Security-Test prüft weiterhin die exakte vollständige Exportanzahl
  (79) und zusätzlich die drei Werte. Serverantworten und API-Codes bleiben gleich.
- Fehlerseite: vorhandener Request-Translator oder isolierter englischer
  Default-Translator bei Bootstrap-Ausfall; sichere generische Fehlertexte,
  passende HTML-Sprache, keine Änderung am Registry-Kontext oder an der
  ausschließlich entwicklungsbezogenen Diagnoseausgabe.
- Installer: dieselbe `DEEC_Translate`-Klasse, eigener Bereich
  `languages/{de_DE,en_US}/installer/installer.php`, nur explizit im Installer geladen.
  Keine neue Anwendungsdomäne und kein zusätzliches Laden im ERP/Shop.
  Englisch bleibt der bisherige Standard. Eine CSRF-geschützte Auswahl auf der
  Einführungsseite speichert die Sprache ausschließlich in `installer_locale`.
  Sie löst keinen Installations-/Importschritt aus. Alle sieben Schritte plus
  Header/Footer und zugehörige generische Validierungs-/Fehlermeldungen sind
  lokalisiert; dynamische Verbindungsangaben werden escaped. SQL, Importlogik,
  Lizenztext, Session-/CSRF- und Mandantenkontext bleiben erhalten.

114 neue Schlüssel je Sprache: 39 in vorhandenen Anwendungskatalogen und 75 im
isolierten Installer-Katalog. Kein vorhandener Schlüssel oder Katalogwert wurde
entfernt oder umbenannt. Der automatisch geprüfte T4-Katalogbestand bleibt Teilmenge
von T5. Stand: **142 Dateien, 71 je Sprache, 3.994 Definitionen und 3.868 eindeutige
Schlüssel je Sprache**, 13 Anwendungsmodule plus Default ergeben 28 Sprachkontexte.
Die 18 Katalogbereiche schließen Installer und bestehende zusätzliche Bereiche ein;
sie sind nicht mit der Anzahl der Anwendungsmodule gleichzusetzen.

## Dauerhafte Qualitätsprüfungen

Der vorhandene `audit.py --fail-on-defects` prüft jetzt zusätzlich:

1. Den verpflichtenden DE-/EN-Dateibestand, auch wenn beide Sprachdateien zugleich
   gelöscht werden; fehlende Schlüssel, leere Schlüssel/Werte, doppelte Definitionen,
   Syntax/Struktur/UTF-8, ungültige Formatangaben und sprachübergreifende Argumente.
2. Die feste Domain-Reihenfolge `default`, acht vorhandene Common-Domänen, aktives
   Modul. Statisches Modell und tatsächliche Runtime werden in allen 28 Kontexten
   verglichen; vorhandene Prioritäts-/Fallback-Tests bleiben bestehen.
3. Die Schlüsselverfügbarkeit in der JavaScript-Bridge.
4. Neue nicht geprüfte Texte in den bestehenden UI-Senken: HTML-Text,
   Placeholder/Title/Aria/Alt, Formularlabels/-beschreibungen/-überschriften sowie
   JavaScript-Dialoge, `.text` und `pushMessages`.

`quality-policy.json` fixiert 71 relative Katalogpfade, Common-Domain-Reihenfolge
und **53 einzeln begründete, exakt gezählte Ausnahmen für 59 Rohfundstellen**:
48 technische, neun dynamische und zwei fachlich offene Fundstellen. Die Differenz
zur Bilanz 51 technischer Kandidaten entsteht durch die ausgefilterten Kommentare
26, 157 und 204. HTML-Kommentare, PHP-Kommentare/PHPDoc und JavaScript-Kommentare zählen nicht als UI;
Strings und URLs werden dabei bewahrt. Installer-Aufrufe werden vom bestehenden
PHP-Tokenizer erkannt.

Ausnahmen sind auf Datei, Senke, exakten Text und Vorkommensanzahl begrenzt.
Neue/geänderte Texte, veränderte Anzahl und veraltete Ausnahmen scheitern.
Eine Ausnahme benötigt eine dokumentierte technische/dynamische/fachliche Begründung;
keine Verzeichnisfreigaben und keine pauschale Übernahme neuer UI-Texte. Änderungen
an Policy oder Domain-Prioritäten sind explizit zu reviewen. Der Policy-Hash steht
im Auditbericht. Fehlende oder ungültige Policies scheitern ebenfalls.

`audit-test.py` enthält 30 Tests, darunter mutierte Mini-Repositories: fehlende
Dateien auf einer/beiden Sprachseiten, fehlende/leere Schlüssel, Duplikate,
ungültige Strukturen/Syntax, `%Q`, `%d`/`%s`-Abweichung, neue UI-Texte, genaue
Ausnahmebehandlung, falsche Domain-Priorität, Kommentarfilter und Installer-Aufrufe. Ein reaktivierter HTML-Kommentar wird wieder
als ungeprüfter sichtbarer Text erkannt, statt dauerhaft ausgenommen zu bleiben.
Die Regeln werden durch negative Fälle geprüft, nicht nur gegen den grünen Bestand.

`validate.py` führt den strikten Audit, diese Tests, die neuen Renderprüfungen,
Syntaxprüfungen und bestehende Regressionen aus; `--stage T5` ergänzt die vorhandenen
Stufen. Auch der bestehende `acceptance-suites.py` ruft damit den strikten Gate auf.
Keine neue Testplattform, Abhängigkeit oder deaktivierte Assertion.

Die UI-Erkennung bleibt eine begrenzte statische Heuristik. Dynamisch erzeugte
Texte, Datenbankinhalte, externe Erweiterungen, reine CSS-/Canvas-Texte und
unbekannte Senken sind durch diese Prüfung nicht vollständig abgedeckt. Neue
Entwicklungen benötigen zusätzlich Render-/Browserprüfung und fachliches Review.

## Tatsächlich ausgeführte Regressionen

| Prüfung | Ergebnis | Artefakt |
| --- | --- | --- |
| Strikter vollständiger Audit, deterministische Wiederholung | 0 Defekte; 157 Runtime-Prüfungen; 28/28 Modellvergleiche | `t5-report.json`, `t5-validation.json` |
| Syntax und Gate-Negativtests | 210 PHP-Dateien im regulären Validator; zusätzlich alle 66 geänderten/neuen PHP-/PHTML-Dateien; 30 Parser-/Gate-Tests bestanden | `t5-validation.json`, `t5-syntax.json` |
| T3/T4-Vertrag | 149 HTTP/Browser + 72 PDF + 14 Mail = **235/235** | `t5-acceptance.json`, `t5-http-browser.json`, `t5-pdf.json`, `t5-mail.json` |
| Erweiterte echte Sprachwechsel-Events, CSRF und Browserregression | 12/12 | `t5-http-browser.json` |
| Neue tatsächliche Renderer und Formulare | 86/86 DE/EN: Medien leer/befüllt, XSS-Werte, mobile/Desktop-Navigation, MultiForm, mehrstufige Anfrage, Checkoutfehler mit/ohne Daten, Warenkorb/CMS-Versandlink, Suche, Kontaktprüfstatus, Fehlerseiten, alle Installer-Templates in Erfolgs-/Fehlerzweigen | `t5-cleanup.json` |
| Offline-Chromium, echte jQuery-Logik | 33/33: Anfrageauswahl, Variantenzustand, AJAX-Fallbacks und Entsperrung | `t5-browser.json` |
| PDF-Mengen/Preise/Summen/Steuern/Prozente, UI/Beleg DE/EN | 180 Sales + 120 Purchases = 300/300 | `t5-sales-format.json`, `t5-purchases-format.json` |
| Lokaler SMTP-Mailfänger | 2 Nachrichten, 14/14; Betreff, UTF-8-Body, PDF-Anhanghash und UI-Kontext | `t5-mail.json` |
| Session-/Mandanten-/Fehler-HTTP | 25/25, 48/48, 152/152; letzterer inklusive Installer-Wechsel, Persistenz, ungültiger Locale und CSRF | `t5-suites.json` |
| Checkout mit temporärer DB | 17/17 | `t5-suites.json` |
| ERP-Dashboard in DE/EN, 320/390/768/1280 px | 812/812 | `t5-suites.json` |
| Bestehende reguläre Sicherheits-/Formular-/Toolbar-/PDF-Tests | Bestanden außer zwei unten genannten Baseline-Abbrüchen; vollständige Einzelresultate und Syntaxanzahl im Validator | `t5-validation.json` |
| Belegberechnung Sales/Purchases/Processes und E-Rechnung | 736/736, 712/712, 706/706, 371/371 | `t5-document-regressions.json` |
| CMS-Struktur und öffentliche Fehlerantworten | 200/200, 10/10 | `t5-document-regressions.json` |

Die erfolgreichen regulären Einzeltests umfassen Quote-E-Mail 364,
Shop-E-Mail-Inhalt 75, Kontaktdaten 39, Formularvalidierung 20, Benutzerprofil 126,
Buttons 165, Toolbar 2.259 gemeinsame Prüfungen (zusätzlich 165 Button-Prüfungen),
Quote-Monitoring 293, PDF-Archiv/Isolation 208 und PDF-Totals 244.
Das sind die tatsächlich ausgegebenen Prüfzahlen der bestehenden Runner;
Überlappungen werden nicht zu einer künstlichen Gesamtzahl addiert.

Bei den geprüften englischen Szenarien verbleibt kein bestätigter unbeabsichtigter
deutscher Systemtext aus der bearbeiteten Liste. Native Sprachnamen, synthetische
Geschäftsdaten, Produkt-/CMS-Inhalte und technische Codes sind keine Systemübersetzung.
Die Aussage ist auf diese tatsächlich geprüften Szenarien begrenzt.

## Bestehende Abbrüche und offene Integrationen

Unverändert rot, ohne Abschaltung oder gelockerte Assertions:

- `tests/security/notifications.php`: 382 erfolgreiche Prüfungen, dann fehlende
  `Sales_Service_DocumentRevisionService` im Child-Fixture. Die exakte Browser-
  Exportassertion wurde für die drei nötigen Meldungen gezielt erweitert.
- `tests/contacts/history-states.php`: fehlender Fixture-Eintrag `states`,
  `Missing history status: 100`.
- `tests/security/shop-inquiry-quote.py`: nach vorherigen Archiv-/Rollbacktests
  Abbruch in `inquiry-catalog.php:158`, `Generic catalog view depends on legacy
  calculation fields`; wie T3/T4. Die vollständige folgende HTTP-/Browserstrecke
  dieses Runners wird dadurch nicht erreicht.
- `tests/calendar/run.py`: SQL-Kommentar wird am Semikolon zerlegt,
  `integration.php:23`; folgende Browser-/SMTP-Prüfungen nicht erreicht.
- `tests/documents/document-labels.php:26`: `Exact label or body missing`,
  dieselbe bereits in T4 gegengeprüfte fehlende Locale-/Translator-Fixture.

Signaturvergleich: `t5-baseline-failures.json`. Der in T4 dokumentierte unabhängige
COPY-/KOPIE-Abbruch in `document-recovery-db.py` wurde in T5 nicht erneut ausgeführt;
keine Änderung an PDF-Rechen-/Archivlogik, Belegwerten oder dessen Assertions.
Externe SMTP-/TLS-Zustellung, produktive Kundentemplates/Erweiterungen und öffentliche
CDN-Interaktionen sind nicht abgenommen. T5 liefert keinen allgemeinen grünen Status
für wegen Fixturefehlern abgebrochene Suiten.

## Reproduzieren

```bash
cd /var/www/dewawi
python3 -B tests/translations/audit.py --fail-on-defects > tests/translations/t5-report.json
python3 -B tests/translations/validate.py --stage T5 --browser > tests/translations/t5-validation.json
php tests/translations/cleanup.php > tests/translations/t5-cleanup.json
python3 -B tests/translations/browser.py > tests/translations/t5-browser.json
python3 -B tests/translations/acceptance.py --report tests/translations/t5-report.json --extended > tests/translations/t5-http-browser.json
php tests/translations/acceptance-pdf.php > tests/translations/t5-pdf.json
python3 -B tests/translations/acceptance-mail.py > tests/translations/t5-mail.json
php tests/translations/document-format.php sales > tests/translations/t5-sales-format.json
php tests/translations/document-format.php purchases > tests/translations/t5-purchases-format.json
python3 -B tests/translations/summarize-t4.py --stage T5 > tests/translations/t5-acceptance.json
python3 -B tests/translations/acceptance-suites.py > tests/translations/t5-suites.json
```

Der breite Suite-Runner bleibt wegen unabhängiger Fehler Exit 1. Der Validator
meldet seine zwei bekannten Fehler als `baseline_failure`, niemals als `passed`.
Für PHP-/Python-/Chromium-/MariaDB-/SMTP-Fixtures werden nur temporäre Verzeichnisse,
synthetische Daten und lokale Testdienste verwendet. Der echte Installer wurde
nicht auf dem Repository oder gegen eine produktive Datenbank ausgeführt.

Keine neue Sprache, Migration, Rechenlogik, Änderung historischer Dokumente oder
produktiver Daten. Keine Kunden-E-Mails. Keine manuellen Datenbankschritte nötig.
Kein Push, Merge, Deployment oder History-Rewrite.
