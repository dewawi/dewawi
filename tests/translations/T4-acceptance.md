# T4 – Translation Acceptance Fixes

Stand: 19. November 2026. Ausgangspunkt: `87ac98c1`.

**Die gezielte T3-Abnahme besteht mit 235/235 Prüfungen.** Vorher: 217 bestanden,
18 fehlgeschlagen. Kein ursprüngliches Szenario und keine ursprüngliche PDF-/Mail-
Prüfbedingung wurde entfernt. Zusätzlich bestehen zwölf Browserprüfungen und 300
Zahlenformatprüfungen. Das ist keine grüne Erklärung sämtlicher Bestandssuiten:
sechs unabhängige Abbrüche sind unten ausdrücklich ausgewiesen.

## Ursachen und Korrekturen

### Sprachwechsel, Session und Mandant

`js/global.js` band den Handler an `#userinfo #language`, während das Layout bereits
`.dw-userbar__switchers` verwendet. Die Bindung ist jetzt delegiert und mit `dwLanguage`
benannt; erneutes Initialisieren entfernt zuerst ausschließlich denselben Handler. Der
bestehende Endpunkt erhält die Auswahl per POST-Daten. Dokumentenfelder namens
`language` außerhalb der Benutzerleiste lösen keinen UI-Sprachwechsel aus.

Native Chromium-Auswahl DE→EN sowie EN→DE→EN, Reload, weitere Modulrequests und Auto-Save
bestehen. Der zusätzliche Browsernachweis zählt **genau einen Request pro Auswahl**.
Anmeldung und Mandant 7 bleiben erhalten; ungültige Locale bleibt 400, ungültiger
Mandanten-CSRF-Token bleibt 403. `languageAction()`, ACL, Locale-Whitelist, Session-
Priorität und Mandantenwechsel wurden funktional nicht geändert. Der bisherige
Sprach-Endpunkt besitzt keinen eigenen CSRF-Token; die vorhandene CSRF-Prüfung des
Mandantenwechslers bleibt unverändert. Ein neuer Sicherheitsvertrag wurde nicht eingeführt.

### PDF-Zahlenformat ohne globale Locale-Änderung

Texte verwendeten bereits die Belegsprache; Summen-Steuersätze und große Teile des
Payloads verwendeten weiterhin die UI-Locale/-Currency. Die Korrektur umfasst:

- `library/DEEC/Pdf.php`: Instanz-Locale aus der expliziten Belegsprache für Steuersätze
  sowie dieselbe Formatierung in der Prüfung rekonstruierter Payloads.
- Sales-/Purchases-`services/DocumentPayloadService.php`: Locale aus Belegsprache vor UI-
  Fallback; Anzahlungen und Restbeträge erhalten eine eigene Currency-Instanz.
- Sales-/Purchases-`controllers/helpers/Positions.php`: vorhandene Locale wird auch an
  Currency und Calculate weitergegeben, damit Mengen, Positionspreise, Summen und
  Steuergruppen gemeinsam formatiert werden.
- `application/controllers/helpers/Calculate.php` und `Currency.php`: optionale explizite
  Formatierungs-Locale. Bestehende Aufrufe bleiben kompatibel. Bei expliziter Locale wird
  eine eigene Zend_Currency erzeugt; die numerische Berechnung bleibt unverändert.

`DEEC_Display` wurde geprüft: seine vorhandene explizite Locale-Unterstützung bleibt
bestehen; die betroffenen PDF-Pfade verwenden die oben genannten vorhandenen Helpers.
Keine globale Registry-Umschaltung, keine temporären globalen Locale-Wechsel, keine
Änderung der gespeicherten Rechenwerte. Vorhandene Originaldateien werden weiterhin
vor dem Aufbau eines neuen Payloads zurückgegeben. Historische Belege werden durch
T4 nicht neu berechnet oder überschrieben. Bereits vorhandene read-only Prüfberechnungen
in der Rekonstruktionslogik bleiben bestehen; ihre Ausgabe verwendet den Belegkontext.
Die Datumsnotation `d.m.Y` bleibt bei der bisherigen fachlichen Entscheidung.

### eBay und Breadcrumbs

`Ebay_Form_Listing` ist ein altes `Zend_Form`; es übernimmt den DEEC-Translator nicht
wie `DEEC_Form`. Seine Labels werden jetzt mit dem vorhandenen View-Translate-Helper
aufgelöst, wie bei anderen bestehenden Zend-Formularen. Filter, Validatoren, Feldnamen
und Decorators bleiben erhalten. Der Save-Button verwendet den global vorhandenen
`TOOLBAR_SAVE`. Keine eBay-Detailtexte wurden global dupliziert oder zusätzlich geladen.

`application/views/helpers/Breadcrumbs.php` ordnet nur die bestätigten Routen gezielt zu:
`users`/`users/user` zu zwei neuen Users-Labels, `ebay/listing` zum vorhandenen
`EBAY_LISTINGS` und `statistics/reporting` zu `STATISTICS_REPORTING`. Andere historische
Bezeichnungen und die gesamte URL-Erzeugung bleiben unverändert.

### Login, Admin, Passwort und öffentlicher Kontaktfehler

- `Users_UserController::loginAction()` meldet für fehlgeschlagene Anmeldungen einen
  gemeinsamen übersetzten Text. Gelöschte/deaktivierte Konten werden nicht offengelegt.
  Die Login-View setzt ihre HTML-Sprache aus der geprüften Request-Locale.
- Die fünf Admin-Beschreibungen verwenden stabile Schlüssel im Admin-Katalog.
- Profil-/Passwort-Views verwenden vorhandene bzw. gezielt ergänzte Users-Labels für
  Trigger, Hinweise und Button. Im geprüften Passwortflow gaben drei alte `getElement()`-
  Aufrufe Arrays statt Felder aus; sie verwenden jetzt das bestehende `renderElement()`.
  Der Meldungsfallback in `pushMessages()` adressiert `#content` und unterstützt damit
  das aktuelle `main#content` des Plain-Layouts. Der echte Dialog zeigt bei falschem
  Passwort die lokalisierte Fehlermeldung; es wurde kein Testpasswort erfolgreich geändert.
- Die öffentliche Kontaktfehlerseite verwendet Shop-Labels einschließlich Pflichtfeld-
  Hinweis, Formulardaten-Bezeichnern und Rücklink. Escaping der Formulardaten bleibt erhalten.
  Es werden keine Exception-, SMTP- oder internen Systemdetails eingeblendet.

14 neue Schlüssel je Sprache, ausschließlich in `users/user.php`, `admin/admin.php`
und `shops/shops.php`. Alle 3.740 bisherigen Schlüssel und ihre Werte sind erhalten.
Aktuell: 3.754 eindeutige Schlüssel je Sprache, 140 Dateien, 7.760 Definitionen.
Die Domain-Prioritäten und modulabhängigen Detaildateien bleiben unverändert.
Von den ursprünglichen 204 Hardcode-Kandidaten entfallen genau neun Fundstellen auf die
bearbeiteten Passwort-/Kontaktfehlertexte. Die übrigen Kandidaten wurden nicht pauschal
ersetzt. T3-Einzelklassifikation und T3-Artefakte bleiben als historische Belege unverändert;
der Klassifikator verweigert bei geänderten Quellhashes bewusst eine ungeprüfte Neuzuordnung.

## Nachweis jedes ursprünglichen Fehlers

[t4-acceptance.json](t4-acceptance.json) enthält genau 18 Vorher-/Nachher-Einträge mit
Szenario, Dateien und Ergebnisartefakt. `summarize-t4.py` verifiziert zusätzlich die
unveränderte Menge sämtlicher ursprünglicher Szenarien und Prüfbedingungen.

| Ursprünglich fehlgeschlagene Prüfung | Vorher | Nachher |
|---|---:|---:|
| Login-HTML-Sprache DE | fehlgeschlagen | bestanden |
| Anmeldefehler EN | fehlgeschlagen | bestanden |
| eBay DE: Formularschlüssel/Breadcrumb | fehlgeschlagen | bestanden |
| Statistik DE: Breadcrumb | fehlgeschlagen | bestanden |
| Benutzerprofil DE: Breadcrumb | fehlgeschlagen | bestanden |
| Admin-Beschreibungen EN | fehlgeschlagen | bestanden |
| eBay EN: Formularschlüssel/Breadcrumb | fehlgeschlagen | bestanden |
| Statistik EN: Breadcrumb | fehlgeschlagen | bestanden |
| Benutzerprofil EN: Breadcrumb | fehlgeschlagen | bestanden |
| Passwort-Trigger EN | fehlgeschlagen | bestanden |
| Öffentlicher Kontaktfehler EN | fehlgeschlagen | bestanden |
| Nativer Browser-Sprachwechsel mit Reload | fehlgeschlagen | bestanden |
| UI DE / Angebot EN: Steuersatzformat | fehlgeschlagen | bestanden |
| UI DE / Auftrag EN: Steuersatzformat | fehlgeschlagen | bestanden |
| UI DE / Rechnung EN: Steuersatzformat | fehlgeschlagen | bestanden |
| UI EN / Angebot DE: Steuersatzformat | fehlgeschlagen | bestanden |
| UI EN / Auftrag DE: Steuersatzformat | fehlgeschlagen | bestanden |
| UI EN / Rechnung DE: Steuersatzformat | fehlgeschlagen | bestanden |

## Tatsächlich ausgeführte Prüfungen

| Prüfung | Ergebnis |
|---|---|
| Vollständiges T3 HTTP/Chromium, 13 Module × DE/EN, inkl. DE→EN→DE-Folgerequests | **149/149** |
| Unabhängige HTTP-Domain-/Texthashvergleiche innerhalb dieses Laufs | **39/39**, in 149 enthalten |
| T3 PDF: 3 Belegtypen × 2 UI-Sprachen × 2 Dokumentensprachen | **72/72** |
| T3 lokales SMTP/MIME: DE/EN, UTF-8 und unveränderte PDF-Anhänge | **14/14** |
| **Ursprüngliche gezielte T3-Abnahme insgesamt** | **235/235** |
| Zusätzlicher Browser: Rückwechsel, einzelne Requests, Auth/Mandant, CSRF, Passwortfelder und realer Passwortfehler | **12/12** |
| Zusätzliche Sales-Payload-/Zahlen-/PDF-Prüfungen | **180/180**, zwölf PDFs |
| Zusätzliche Purchases-Payload-/Zahlen-/PDF-Prüfungen | **120/120**, acht PDFs |
| Strenger Audit, Originalschlüsselerhalt, Parität und Runtime-Modell | **28/28** Kontexte, alle `defect_counts` null |
| Runtime / Auditparser / Offline-JS-Chromium | **157 / 19 / 13** bestanden |
| Finaler bestehender Validator | Exit 0; **169** Syntaxprüfungen, Baselinefehler ausdrücklich separat |
| Direkter Lint geänderter PHP-/PHTML-Dateien einschließlich neuer Fixture | **22/22**, Python-Compile ebenfalls bestanden |
| Quote-Mail / Shop-Mailcontent / Contact-Data / Form-Input / User-Profile | **364 / 75 / 39 / 20 / 126** bestanden |
| Buttons / Toolbar / Quote-Monitoring | **165 / 2.259 / 293** bestanden |
| PDF-Archiv / PDF-Totals | **208 / 244** bestanden, zweite Suite enthält erste |
| Session-HTTP / Tenant-ACL-Apache / Error-HTTP / Checkout-DB | **25 / 48 / 140 / 17** bestanden |
| Responsive Dashboard-MVC/Chromium | **812** bestanden, 390/768/1280 px |
| Reale Berechnung: Sales / Purchases / Processes | **736 / 712 / 706** bestanden |
| E-Invoice-Vorbereitung | **371** bestanden |
| CMS-Struktur / Public-Errors | **200 / 10** bestanden |

Die Zusatz-PDFs verwenden die echten Payloadservices, Positions-/Calculate-/Currency-
Helpers und TCPDF; nur Datenakquisition ist synthetisch. Goldene Zahlen prüfen Mengen mit
null/zwei/vier Nachkommastellen, vierstellige Preispräzision, Millionen-/Tausendertrennzeichen,
Summen, gemischte Steuern 19,5/7/0 %, Anzahlungen und Restbeträge. Beide UI-/Dokumentsprachen
werden in allen Kombinationen verwendet. Die UI-Currency ist absichtlich USD, der Beleg EUR:
Objektidentität und sämtliche UI-Currency-Optionen bleiben erhalten. Rohdaten, Rechenresultate
und Eingabesnapshots bleiben identisch. Die zusätzlichen Assertions werden nicht in die
ursprünglichen 235 hineingezählt. Viele bestehende Suites enthalten gemeinsame Vorprüfungen;
ihre Assertionzahlen dürfen nicht pauschal zu einer Gesamtzahl addiert werden.

## Unabhängige, weiterhin rote Bestandssuiten

Diese Fehler sind **nicht** als bestanden gewertet und wurden nicht pauschal repariert:

| Suite | Tatsächlicher Abbruch / Gegenprobe |
|---|---|
| `security/notifications.php` | 370 Hauptprüfungen bestehen; Kind-Fixture findet `Sales_Service_DocumentRevisionService` nicht. T1/T3-Signatur unverändert. |
| `contacts/history-states.php` | Fehlendes `history['states']`, danach `Missing history status: 100`; T1/T3-Signatur unverändert. |
| `security/shop-inquiry-quote.py` | `inquiry-catalog.php:158`, Standalone-View ohne vollständigen Shop-Translationkontext; T3-Signatur unverändert, HTTP-/Browserfolge nicht erreicht. |
| `calendar/run.py` | `integration.php:23`, Semikolon-Split zerlegt SQL-Kommentar; T3-Signatur unverändert, Kalender-Browser-/SMTPfolge nicht erreicht. |
| zusätzlich `documents/document-labels.php` | `:26`, „Exact label or body missing“; dieselbe Signatur auch mit unverändertem `DEEC_Pdf` aus `87ac98c1`. Fixture setzt nur Zend_Locale, keinen Request-/Belegtranslator. |
| zusätzlich `documents/document-recovery-db.py` | Englische UI erwartet `COPY`, der dokumentensprachlich deutsche Beleg trägt `KOPIE` (`document-viewing-fixtures.php:137`). Dieselbe Signatur auch mit `DEEC_Pdf` aus `87ac98c1` in separater temporärer MariaDB. |

Vor dem letzten Recovery-Abbruch bestehen u. a. 343 Invoice-Original-Batch- und 223
Mixed-Invoice-Batch-Prüfungen. Die gesamte Recovery-Suite wird trotzdem nicht grün genannt.
Gegenproben ersetzten `DEEC_Pdf` ausschließlich beim Laden in temporären Testprozessen;
Produktdateien wurden dafür nicht zurückgesetzt. Belege und Erwartungen dieser fremden
Suiten wurden nicht geändert. Ergebnisse: [t4-document-regressions.json](t4-document-regressions.json)
und [t4-recovery-baseline.json](t4-recovery-baseline.json).

## Reproduzieren und Artefakte

```bash
cd /var/www/dewawi
python3 -B tests/translations/audit.py --fail-on-defects > tests/translations/t4-report.json
python3 -B tests/translations/acceptance.py --report tests/translations/t4-report.json --extended > tests/translations/t4-http-browser.json
php tests/translations/acceptance-pdf.php > tests/translations/t4-pdf.json
python3 -B tests/translations/acceptance-mail.py > tests/translations/t4-mail.json
php tests/translations/document-format.php sales > tests/translations/t4-sales-format.json
php tests/translations/document-format.php purchases > tests/translations/t4-purchases-format.json
python3 -B tests/translations/validate.py --stage T2B --browser > tests/translations/t4-validation.json
python3 -B tests/translations/summarize-t4.py > tests/translations/t4-acceptance.json
```

`--stage T2B` ist die vorhandene Validatorstufe, keine Behauptung eines alten Produktstands.
Der aktuelle Audit wird ausdrücklich als Erwartungsmodell an die bestehende HTTP-Abnahme
übergeben; T2/T3-Snapshots werden nicht mit neuen Texthashes überschrieben. Der Summarycheck
verifiziert zusätzlich, dass alle bisherigen Katalogwerte und sämtliche Domainreihenfolgen
unverändert geblieben sind. Jede neu ausgegebene Übersetzung wird also gegen das tatsächliche
aktuelle Modell geprüft; die 18 roten Prüfbedingungen selbst bleiben erhalten.

Zusätzlich ausgeführt: `acceptance-suites.py` (acht bestehende Aufrufe, mit den oben genannten
Abbrüchen), `calculation.php sales|purchases|processes`, `e-invoice.php`, `document-labels.php`,
`document-recovery-db.py`, `cms-structure.php` und `public-errors.php`. Syntaxnachweis:
[t4-syntax.json](t4-syntax.json); letzter kompletter Validator:
[t4-validation.json](t4-validation.json); übrige Suite-Ausgaben:
[t4-suites.json](t4-suites.json). Der finale Validator und die gezielte Browserabnahme wurden
nach den letzten Produktänderungen erneut ausgeführt.

## Grenzen, Datenintegrität und Git

Keine neue Architektur, Sprache oder Migration. Keine Änderungen an Berechtigungen,
Mandantenregeln, Rechenformeln, Kundentexten oder produktiven Datenbanken. Keine vorhandenen
Katalogschlüssel gelöscht. Keine produktiven Original-PDFs oder gespeicherten Zahlenwerte
geändert. Keine echten Kunden-E-Mails: SMTP ausschließlich an den lokalen Mailfänger,
Datenbanken ausschließlich temporäre Runner-Instanzen ohne Netzwerkzugriff.

Offen bleiben die unabhängigen Suite-Abbrüche, echte externe SMTP-/TLS-Zustellung,
öffentliche CDN-Interaktionen und kundeneigene Templates/Erweiterungen. Die übrigen
Hardcode-Fundstellen gehören weiterhin in einen gesonderten Auftrag; die Datumsnotation
bleibt eine fachliche Folgefrage. Keine manuelle Datenbanknacharbeit erforderlich.
Parallel aufgetauchte UI-Testarbeiten außerhalb `tests/translations/` wurden weder geändert
noch gestagt. Vor dem lokalen Commit wurden Status und staged Diff geprüft.
**Kein Push, Merge, Deployment oder History-Rewrite.**
