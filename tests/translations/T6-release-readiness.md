# T6 – Translation Release Readiness

Stand: 19. November 2026. Ausgangspunkt `1910ef01`; lokaler Stand maßgeblich.
Untersucht wurden ausschließlich die sechs dokumentierten Abbrüche und die dafür
notwendigen Testkontexte. Keine erneute manuelle Übersetzungsbestandsaufnahme.
T4-/T5-Berichte bleiben historische Nachweise.

## Empfehlung

**GO MIT DOKUMENTIERTEN RESTRISIKEN für den geprüften Translation-Stand.**

Die sechs Abbrüche sind ursächlich als Fixture-/Testvertragsfehler aufgeklärt und
behoben. Kein Produktfix war dafür erforderlich. Die ursprünglichen 235 Prüfungen,
28 Sprachkontexte und der strikte Validator bestehen; die zuvor blockierten
Shop-, Kalender- und historischen PDF-/Browserstrecken werden vollständig erreicht.
Keine Baseline-Ausnahme wandelt mehr einen fehlerhaften Validatorlauf in Erfolg um.

Diese Empfehlung ist keine pauschale Freigabe eines sich noch ändernden
Arbeitsverzeichnisses. Der Benutzer hat parallele Toolbar-Arbeiten bestätigt.
Sie wurden weder geändert noch in T6 übernommen. Für die Produktivfreigabe gelten:

1. Toolbar-Arbeiten separat abschließen, reviewen und versionieren. Der tatsächlich
   auszuliefernde Stand muss dem dokumentierten getesteten Quellenstand entsprechen;
   spätere Änderungen erfordern einen erneuten strikten Validator und die 235er-Abnahme.
2. Externe SMTP-/TLS-Zustellung und installationsspezifische Mailkonfiguration bleiben
   eine Betriebsabnahme mit ausdrücklich freigegebenem internem Testempfänger. T6
   belegt ausschließlich lokale SMTP-/MIME-/ICS-Funktionen, keine Providerzustellung.
3. Kundeneigene PDF-/E-Mail-Templates und Erweiterungen bei Verwendung separat prüfen.
   Explizite Belegsprache ist maßgeblich; insbesondere private PDF-Erweiterungen,
   die ausschließlich `Zend_Locale` setzen, entsprechen nicht dem geprüften
   Dokument-/Translator-Kontext.

Bis zum Abschluss/Abgleich dieser Bedingungen besteht **keine unbedingte
Produktivfreigabe**. Ein erneuter roter Gate-Lauf bedeutet NO-GO für den betroffenen
Auslieferungsstand. T6 führt selbst keinen Push oder Deployment-Schritt aus.

## Tabelle der sechs Abbrüche

| Testfall | Ursache und zeitliche Einordnung | Ergebnis nach T6 | Freigaberelevanz | Folgeaufgabe |
| --- | --- | --- | --- | --- |
| Notifications | Seit vor T1: isolierter Child-Prozess lädt den realen Revisionsservice nicht und besitzt keinen passenden Mandanten-/DB-Kontext. `Finish.php` ruft seit `e79ab895` die Revisionsrelation auf. | 382 Hauptprüfungen, 64 Sales- und 24 Purchases-Helperprüfungen bestanden. Echter Service, synthetische DB-Grenze, Mandant/Belegtyp geprüft. | Reiner Fixture-Fehler; kein nachgewiesener Produkt-Autoloadfehler. Positive Sichtprüfung wieder wirksam. | Keine offene T6-Korrektur. |
| History | Seit vor T1: Fixture liefert leere Rechte und keinen sichtbaren Kontakt. Sicherheitsguards in `Contacts_Model_Get::history()` liefern deshalb korrekt `[]`; der Test greift danach trotzdem auf `states` zu. | 49 Prüfungen: Statuslabels DE/EN, nicht editierbar, finale Zustände nicht auswählbar, SQL-Scope und negative Zugriffe bestanden. | Sicherheitsnaher Fixture-Fehler. Autorisierungs-/Mandantengrenzen wurden nicht gelockert. | Keine offene T6-Korrektur. |
| Shop-Anfrage | Vor T1 bestanden; seit T2B `88eef4c0`: übersetzte Templates benötigen den DEEC-View-Helper und den Shopkatalog, die in Standalone-/HTTP-Fixtures fehlen. | Voller DB-/HTTP-/AJAX-/Chromiumlauf bestanden: 770 Haupt-, 57 Katalog- und 95 Variantenprüfungen sowie beide Produktfamilien bei 390/1280 px. | Veralteter Fixture-Bootstrap; im regulären Modulbootstrap ist der Katalog vorgesehen. Kein Produktfehler nachgewiesen. | Keine offene T6-Korrektur; externe Kundenshop-Templates außerhalb dieses Nachweises. |
| Kalender | Seit vor T1: `explode(';', ...)` zerlegt einen SQL-Kommentar des später angehängten Revisionsblocks. Zusätzlich reicht die Kalender-Fixture unbeschränkt bis in fremde Migrationsabschnitte. | Vollständiger Lauf einschließlich Migration in Wegwerf-DB, Zeitumstellung, Privacy, Rechte, Erinnerungen, Teilnehmer, lokaler SMTP und Browser bestanden. | Testparser-/Scope-Fehler; keine fehlerhafte produktive SQL-Anweisung nachgewiesen. Offizieller Installer nutzt bereits den vorhandenen SQL-Parser. | Keine Schema-/Migrationsänderung und keine manuelle DB-Nacharbeit. |
| Historische PDF-Markierungsprüfung 1 (`document-labels.php`) | Vor T1 bestanden; seit T2A `00a99d88`: Fixture setzt nur die UI-`Zend_Locale`. Markierungen folgen inzwischen dem Dokument-/Translator-Kontext. | 139 Prüfungen: beide Markierungen, alle vier UI-/Dokumentsprachkombinationen, drei Seiten, neutrale Farben, exakter Text und unveränderter Body. | Veralteter privater Testaufruf. Die produktive Dokumentensprachlogik bleibt erhalten. | Private Erweiterungen mit ausschließlich UI-Locale separat prüfen. |
| Historische PDF-Markierungsprüfung 2 (`document-recovery-db.py` / `document-viewing-fixtures.php`) | Vor T1 bestanden; seit T2A `00a99d88`: Fixture erwartet bei englischer UI `COPY`/`SAMPLE` für ausdrücklich deutsche Dokumente. Korrekt sind `KOPIE`/`MUSTER`. | Vollständige Recovery-Suite: 2.632 Prüfungen, darunter 708 historische Ansichts-/Entwurfs- und 395 Verfügbarkeitsprüfungen; DE/EN-Browser ebenfalls bestanden. Separater englischer Testbeleg liefert `COPY` bei beiden UI-Sprachen. | Falsche Sprachannahme im Test, kein Verlust von Markierungen oder Originalen. Alle Byte-/Metadaten-/Datenvergleichsprüfungen bleiben aktiv. | Keine offene T6-Korrektur an Produkt oder Archiv. |

Die in bisherigen Berichten verwendete Bezeichnung „unabhängig“ bedeutet somit
**nicht**, dass alle sechs Abbrüche bereits vor T1 vorhanden waren. Notifications,
History und Kalender scheitern im tatsächlich ausgeführten Vor-T1-Snapshot;
Shop und beide PDF-Prüfungen bestehen dort. Die späteren Änderungen T2A/T2B
machten deren bisher unvollständigen bzw. veralteten Testkontext sichtbar.

## Reproduzierbare Herkunftsnachweise

Ein frisches `git archive d68f7a8c` (unmittelbarer Elternstand von T1 `c6325897`)
wurde ausschließlich unter `/tmp/dewawi-t6-pre-t1-*` entpackt. Alle sechs damaligen
Runner wurden unverändert dort ausgeführt. Vergleich dazu: dieselben sechs Runner
vor der T6-Korrektur im aktuellen Repository. Es wurde weder der lokale Branch
gewechselt noch eine Datei aus der Arbeitskopie zurückgesetzt.

- `t6-pre-t1-unit.json`, `t6-pre-t1-shop.json`, `t6-pre-t1-calendar.json`,
  `t6-pre-t1-recovery.json`: tatsächlich ausgeführte Vor-T1-Gegenproben.
- `t6-before-unit.json`, `t6-before-shop.json`, `t6-before-calendar.json`,
  `t6-before-recovery.json`: tatsächliche Wiederholung der sechs T5-Abbrüche.
- `t6-history.json`: Git-Blob-Identitäten von Vor-T1, T1, T2A, T2B, T3, T4 und T5
  sowie die ursächlichen Änderungen. Die Zwischenstände sind ein Quellenvergleich;
  es wird nicht behauptet, jeden historischen Commit vollständig getestet zu haben.
- `t6-after-unit.json`, `t6-after-shop.json`, `t6-after-calendar.json`,
  `t6-after-recovery.json`: erfolgreiche Nachtests.

Im ersten erweiterten Recovery-Nachlauf war nach der korrigierten `COPY`-Annahme
noch die gleiche falsche `SAMPLE`-Annahme für deutsche Entwürfe erreichbar.
`t6-intermediate-recovery.json` bewahrt diesen roten Zwischenlauf. Danach wurde
auch diese Erwartung exakt auf die explizite Dokumentensprache korrigiert. Es gibt
keine alternative Assertion, die beliebige DE-/EN-Markierungen akzeptiert.

## Konkrete Fixture-Korrekturen und Sicherheit

- `tests/security/notifications.php`: realer
  `Sales_Service_DocumentRevisionService`, synthetische
  `Application_Model_DbTable_Documentrevision`, expliziter Mandant 7 und geladene
  Common-Texte. Die DB-Grenze prüft ID 100, Mandant und erlaubte Belegtypen.
  Der Produktions-Autoloader registriert `Service` bereits in
  `library/Zend/Application/Module/Autoloader.php`; dieser Loader war im isolierten
  Testprozess nicht aktiv.
- `tests/contacts/history-states.php`: tatsächliche minimale Leserechte, synthetisch
  sichtbarer Kontakt und prüfbare integerbasierte SQL-Filter. Ein echter DB-Zugriff
  wirft weiterhin eine Exception. Fehlende Rechte, fehlender/fremder/gelöschter
  Kontakt und fehlender Benutzer bleiben gesperrt. Produktguards unverändert.
- `tests/security/shop-inquiry-quote.php`, `inquiry-catalog.php`,
  `inquiry-variants.php`, `fixtures/inquiry-pilot-http.php`: vorhandenen
  `loadModule('shops')`-/View-Helper-Bootstrap vervollständigt. Keine Änderungen an
  Waren-/Varianten-/Quote-Services, CSRF, Formularfeldnamen oder Mandantenscope.
- `tests/calendar/integration.php`: vorhandenes `PMA_splitSqlFile` aus
  `install/helper.php` statt naivem Semikolon-Split; Testbereich endet vor dem
  fachlich unabhängigen Revisionsabschnitt. Alle Frischinstallations-/Upgrade-,
  Altbestands-, Privacy- und Indexvergleiche bleiben bestehen. Kein SQL editiert.
- `tests/documents/document-labels.php`: wie der echte Renderer explizite
  Dokumentensprache initialisieren, zusätzlich entgegengesetzte UI-Sprache und
  unveränderte Registry prüfen. Exakte Labels, Body, Pagination und Farbe erhalten.
- `tests/documents/document-viewing-fixtures.php`: `KOPIE`/`MUSTER` für deutsche
  Dokumente auch bei englischer UI; eigenständiger synthetischer englischer Beleg
  mit unveränderten Beträgen. Alle bestehenden Original-/Hash-/Mtime-/Geschäftsdaten-
  Vergleiche, Mandanten-/Rechte-/Corruption-Negativfälle und GoBD-Abgrenzungen bleiben.
- `tests/translations/validate.py`: aufgelöste Notifications-/History-Ausnahmen
  entfernt; jeder Fehler ist wieder ein harter Validatorfehler. Markierungsprüfung 1
  ist zusätzlich regulär eingebunden. Vorhandene Runner unterstützen `--stage T6`.
  `summarize-t4.py --stage T6` prüft den unveränderten 235er-Vertrag, Katalogbestand,
  Domain-Priorität und die T5-Hardcodebilanz.

## Tatsächlich ausgeführte Release-Prüfungen

| Prüfung | Ergebnis / Nachweis |
| --- | --- |
| Ursprünglicher Translation-Vertrag | **235/235** = HTTP/Chromium 149, echte TCPDF-Prüfungen 72, lokale SMTP-/MIME-Prüfungen 14; `t6-acceptance.json` |
| Zusätzlicher Sprachwechsel-/CSRF-/Passwort-Browser | **12/12**, `t6-http-browser.json` |
| Audit und Runtime | **0 strukturelle Defekte**, **28/28** Modul-/Sprachkontexte, **157** Runtime-Prüfungen, unveränderte Kataloge und Domain-Reihenfolge; `t6-report.json` |
| Strikter Validator | Exit 0, keine Baseline-Ausnahmen; 210 Syntaxdateien, 30 Audit-Negativtests, 86 Cleanup-Renderprüfungen und 33 Offline-Chromium-Prüfungen; `t6-validation.json` |
| Geänderte T6-PHP-Fixtures | Alle neun Dateien bestehen `php -l`; `t6-syntax.json` |
| PDF-Zahlenkontexte | **180 Sales + 120 Purchases**, alle UI-/Dokumentsprachkombinationen; `t6-sales-format.json`, `t6-purchases-format.json` |
| Lokale SMTP-Translation-Prüfung | Zwei synthetische Nachrichten, **14/14**, UTF-8 und unveränderte PDF-Anhänge; `t6-mail.json` |
| Breiter bestehender Suite-Runner | Alle acht Aufrufe bestanden; Validator, Session 25, Tenant/ACL 48, Error-HTTP 152, Shop-Anfrage, Checkout 17, Kalender und Dashboard 812; `t6-suites.json` |
| Kalender-Daten/Services | **100 / 98 / 158 / 36** Prüfungen für Basis, Reminder, Teilnehmer, Usability; `t6-after-calendar.json` |
| Kalender-HTTP und Chromium | **43 / 35 / 90 / 38** HTTP-Prüfungen; je Basis/Reminder/Teilnehmer/Usability echte Browserflows bei 390/1280 px. Englische Reminderansicht und englische explizite Teilnehmer-Mail sind enthalten. |
| Shop-HTTP und Chromium | Vollständige mehrstufige AJAX-/Zurück-/Replay-Strecke, fremde Mandanten-/Shop-IDs, kanonische Artikel/Varianten, manuelle Prüfung, zwei Familien und 390/1280 px; `t6-after-shop.json` |
| Historische PDFs | **139** Markierungsprüfungen plus **2.632** Recovery-Prüfungen; geschützte echte HTTP-Routen und 20 DE/EN-Browserpanels bei 375/1440 px; `t6-after-unit.json`, `t6-after-recovery.json` |
| Bestehende Berechnungs-/E-Rechnungs-/CMS-Tests | Sales 736, Purchases 712, Processes 706, E-Invoice 371, CMS-Struktur 200, öffentliche Fehler 10; `t6-document-security.json` |

Die Assertionzahlen bestehender Suiten überschneiden sich; sie werden nicht zu
einer künstlichen Gesamtzahl addiert. Absichtliche ACL-Ablehnungen und simulierte
SMTP-Fehler erscheinen in manchen Logs auf stderr, während ihre Negativprüfungen
bestehen. Keine externe Mailzustellung wird daraus abgeleitet.

## Paralleler Arbeitskontext und Grenzen

Zu Beginn war `git status` sauber. Während der Ausführung änderten parallele,
vom Benutzer bestätigte Toolbar-Arbeiten sieben produktive Formular-Dateien sowie
UI-Testdateien. Ein Zwischenlauf scheiterte deshalb an
`tests/ui/toolbar-buttons.php` („Toolbar migration escaped its bounded scope“).
Diese Prüfung wurde von T6 weder abgeschaltet noch angepasst oder als Baseline
zugelassen. Nach der parallelen Aktualisierung bestehen die Toolbar-Prüfungen
mit 2.451 gemeinsamen Assertions; der finale Validator und Suite-Runner wurden erneut
ausgeführt. Die roten Zwischenartefakte bleiben als
`t6-intermediate-validation.json` und `t6-intermediate-suites.json` erhalten.

`t6-worktree-context.json` benennt fremde Pfade und Fingerprints; `t6-report.json`
kennzeichnet den getesteten Produktquellenstand. T6 verändert ausschließlich Tests,
Test-Fixtures und Dokumentation und übernimmt keine dieser fremden Änderungen.
Eine Aussage „der gesamte Produktquellenbaum ist seit T5 unverändert“ wäre wegen
dieser parallelen Arbeiten falsch. Der Übersetzungskatalogbestand bleibt erhalten.

Restgrenzen: externe SMTP-/TLS-/Providerintegration, kundeneigene Templates und
Erweiterungen sowie öffentliche CDN-Interaktionen. Die Shop-Integrations-Browserstrecke
verwendet die vorhandenen deutschen synthetischen Formularinhalte; die getrennte
Translation-Abnahme prüft DE/EN in allen 28 Kontexten. Die zwei aus T5 unverändert
fachlich offenen Geschäftsnamen sind kein neu entdeckter T6-Produktfehler.

Keine Produktions-/Kundendaten gelesen oder verändert. Keine bestehenden Datenbanken,
Schema-/Migrationsdateien, historischen Dokumentwerte oder Kundenarchive geändert.
Datenbanktests erzeugen ausschließlich eigene temporäre MariaDB-Instanzen ohne
Netzwerkzugang und synthetische Daten. Gezielt korrumpierte Originale/Races existieren
nur als Test-Fixtures; Byte-/Mtime-/DB-Snapshots sichern die Originalerhaltung ab.
E-Mails ausschließlich an lokale Testfänger bzw. in Fixtures gesperrt.
Keine manuelle Datenbanknacharbeit. **Kein Push, Merge oder Deployment.**

## Reproduzieren

```bash
cd /var/www/dewawi
php tests/security/notifications.php
php tests/contacts/history-states.php
python3 -B tests/security/shop-inquiry-quote.py
python3 -B tests/calendar/run.py
php tests/documents/document-labels.php
python3 -B tests/documents/document-recovery-db.py
python3 -B tests/translations/audit.py --fail-on-defects > tests/translations/t6-report.json
python3 -B tests/translations/validate.py --stage T6 --browser > tests/translations/t6-validation.json
php tests/translations/cleanup.php > tests/translations/t6-cleanup.json
python3 -B tests/translations/browser.py > tests/translations/t6-browser.json
python3 -B tests/translations/acceptance.py --report tests/translations/t6-report.json --extended > tests/translations/t6-http-browser.json
php tests/translations/acceptance-pdf.php > tests/translations/t6-pdf.json
python3 -B tests/translations/acceptance-mail.py > tests/translations/t6-mail.json
php tests/translations/document-format.php sales > tests/translations/t6-sales-format.json
php tests/translations/document-format.php purchases > tests/translations/t6-purchases-format.json
python3 -B tests/translations/summarize-t4.py --stage T6 > tests/translations/t6-acceptance.json
python3 -B tests/translations/acceptance-suites.py > tests/translations/t6-suites.json
```

Für historische Gegenproben den jeweiligen Commit mit `git archive` in ein neues
`/tmp`-Verzeichnis entpacken und dort dieselben Runner ausführen; niemals die aktive
Arbeitskopie zurücksetzen oder Fixtures gegen eine bestehende Datenbank starten.
