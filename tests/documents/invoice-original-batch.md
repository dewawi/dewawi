# Kontrollierte Sammelübernahme historischer Rechnungsoriginale

`invoice-original-batch.php` ergänzt den bestehenden Recovery-Ablauf um `plan` und `apply` für ZIPs der Form `correct/<Rechnungsnummer>.pdf`. Mandant und Operator sind immer explizit. Die Implementierung ist nicht auf Mandant 100 oder 27 Dateien festgelegt. Keine SQL-Migration, keine Neuberechnung, kein PDF-Rendering und keine Änderung von Geschäftsdaten. Auswahl, Freigabe, Archivpfad, Kopie, Registrierung und Auswahlhistorie verwenden die vorhandenen Recovery-/Archivkomponenten.

## Voraussetzungen und Grenzen

- Vorhandener privater Recovery-Prüfbereich mit `review.json`, `.collector.lock` und passendem Sammlerlauf unter `runs/`. Neue Registrierungen müssen bereits eindeutig im angegebenen Sammlerlauf geführt sein. Ein dort nicht aufgeführtes, bereits registriertes Original kann unverändert geprüft und übersprungen werden. Ein bestehender Download-Locator muss auf denselben Prüfbereich zeigen.
- Operator mit Zugang zum expliziten Mandanten sowie Rechnungs-Lese- und Bearbeitungsrechten. Für `plan` reichen SELECT-Datenbankrechte. `apply` benötigt zusätzlich INSERT auf `documentarchive`; dieses Werkzeug ruft für bestehende Registrierungen keinen Import und kein UPDATE auf.
- Lesbare Datenbankkonfiguration im bestehenden INI-Format; das darin verwendete Environment ist standardmäßig `production` (abweichend `--environment=...`). Das bezeichnet den Konfigurationsabschnitt, nicht eine Erlaubnis zur produktiven Ausführung durch Codex.
- ZIP und Erklärung müssen reguläre, private Dateien mit 0600 sein. Prüfbereich, Unterverzeichnisse und temporäre Dateien bleiben außerhalb von Installations-/Webroots mit 0700/0600. Konfiguration und Prüfbereich müssen für den ausführenden Betriebssystembenutzer zugänglich sein. Keine rekursiven Rechteänderungen an Originalen.
- Höchstens 100 PDFs und optional `correct/`; je PDF höchstens 20 MiB, ZIP und gesamte entpackte Daten höchstens 200 MiB, Expansionsverhältnis höchstens 200. Nur STORE/DEFLATE, höchstens 1 MiB Zentralverzeichnis; kein ZIP64/Mehrvolumeformat, keine Verschlüsselung, Symlinks, doppelten Namen oder fremden Dateitypen. Rechnungsnummern sind positive Dezimalzahlen ohne führende Nullen im bestehenden INT-Bereich. Kein rekursives Entpacken. Nicht passende ZIP-Strukturen brechen den gesamten Lauf ab.

Eine SHA-/Größen-/PDF-Strukturprüfung beweist keine inhaltliche Originalgleichheit. Das Werkzeug liest PDF-Beträge nicht automatisch aus und führt keinen OCR- oder Layoutvergleich durch. Es verlangt stattdessen eine ausdrücklich dokumentbezogene Betreibererklärung zum tatsächlichen Originalursprung, Vergleich mit dem Kundenarchiv **und** Vergleich von Identität, Positionen, Steuern und Summen mit der im Plan ausgegebenen gespeicherten Geschäftsgrundlage. Ungeklärte Unterschiede bleiben gesperrt; es werden keine Werte angepasst. Bekannte Rekonstruktionen/Diagnosen können nicht als historische Originaldateien umetikettiert werden. Bestehende Diagnosekonflikte desselben Belegs erfordern zuerst fachliche Klärung; dafür gibt es keinen Batch-Override.

## Betreiberbefehle für Mandant 100

Die folgenden Befehle sind eine Betriebsanleitung und wurden nicht gegen den Produktivbestand ausgeführt. Die tatsächlichen Pfade, die interne Benutzer-ID und die real verifizierte Sicherungsreferenz einsetzen. Kein automatischer `apply`-Aufruf direkt nach `plan`.

```bash
CONFIG=/tatsaechlicher/pfad/database.ini
REVIEW=/tatsaechlicher/privater/recovery-pruefbereich
RUN="$REVIEW/runs/tatsaechlicher-sammlerlauf/run.json"
ZIP=/tatsaechlicher/privater/eingang/correct.zip
OPERATOR_USER=interne_benutzer_id

batch=(sudo -u www-data php /var/www/dewawi/tests/documents/invoice-original-batch.php
  --config="$CONFIG" --client=100 --operator-user="$OPERATOR_USER"
  --zip="$ZIP" --review="$REVIEW" --run="$RUN")

"${batch[@]}" --action=plan
```

Der erste Lauf schreibt ausschließlich private JSON-/CSV-Berichte und eine Erklärungsvorlage; temporäre PDF-Prüfkopien werden entfernt. Keine Datenbank-, Auswahl-, Freigabe- oder Archivänderungen. Die JSON-Ausgabe nennt `report`, `report_sha256`, `declaration_template` und Aktionszähler. Der Plan enthält je Datei Nummer, interne ID, Datum, SHA, Größe, vollständige gespeicherte Geschäftsgrundlage, Fingerprint, aktuellen Archivzustand, Prüfstatus und vorgeschlagene Aktion.

Die erzeugte Erklärungsvorlage unter einem eigenen privaten Dateinamen übernehmen und fachlich vervollständigen. Identitäten, ZIP-Einträge, Datei-SHAs und `snapshot_sha256` unverändert übernehmen. Jeder Eintrag hat:

- `provenance`: tatsächliche Herkunft genau dieser historischen Originaldatei;
- `comparison_reference`: konkrete Referenz des tatsächlich durchgeführten Kundenarchiv-/Geschäftsdatenvergleichs;
- `original_provenance_verified`, `identity_verified`, `customer_archive_compared`, `business_values_compared`, `positions_taxes_totals_match`: nur tatsächlich bestätigte Prüfungen auf `true` setzen;
- `unresolved_differences=false` und `differences=[]` ausschließlich bei geklärten Übereinstimmungen; sonst die offenen Unterschiede dokumentieren und den Eintrag gesperrt lassen;
- `comparison_performed_at=null`, falls kein tatsächlicher Vergleichszeitpunkt bekannt ist. Ein bekannter Zeitpunkt kann als ISO-Zeitstempel mit Zeitzone eingetragen werden. Keine erfundenen Zeitpunkte, Papierablagen oder Versandnachweise ergänzen.

Die Erklärung benennt außerdem den tatsächlichen `operator_user`, `clientid` und ZIP-SHA. Eine Sammelerklärung gilt nur für die darin enthaltenen benannten Dateien mit ihren exakten SHAs und Datenfingerprints. Fehlende Erklärungen sperren die betreffenden neuen Registrierungen; zusätzliche, doppelte oder abweichende Dateien werden abgewiesen. Bestehende Originale erhalten durch das reine Überspringen keine neue Prüfbestätigung.

```bash
DECLARATION="$REVIEW/runs/tatsaechlich-bestaetigte-originalerklaerung.json"
"${batch[@]}" --action=plan --declaration="$DECLARATION"
```

Den **neuen** JSON-/CSV-Plan und die PDFs mit der ausgewiesenen Geschäftsgrundlage prüfen. Erwartbar sind bei den beschriebenen 27 Dateien `already_archived=1` und `register=26`; diese Anzahl wird nicht erzwungen und nicht als Ergebnis vorweggenommen. Rechnung 109860 ist nur bei exakt passender SHA, Größe, kanonischem Pfad und gültiger Einzelregistrierung `already_archived`. Abweichungen sind `CONFLICT`, keine Ersetzungsaufforderung.

Den exakten Hash des geprüften Plans anschließend gesondert bestätigen. Die folgenden Variablen bewusst aus dem tatsächlich geprüften Plan/Sicherungsnachweis setzen; keine automatische Pipeline von Planerstellung zu Freigabe verwenden:

```bash
PLAN="$REVIEW/runs/tatsaechlich-gepruefter-invoice-original-batch-plan.json"
sudo -u www-data sha256sum "$PLAN"

PLAN_SHA=exakter_64stelliger_sha256_des_geprueften_plans
BACKUP_REFERENCE='tatsaechliche Referenz der aktuell verifizierten Datenbank- und Dateisicherung'

"${batch[@]}" --action=apply --declaration="$DECLARATION" \
  --plan="$PLAN" --confirm-plan="$PLAN_SHA" \
  --backup-verified --backup-reference="$BACKUP_REFERENCE"
```

`--backup-verified` ist die ausdrückliche Betreiberbestätigung der aktuellen Datenbank-/Dateisicherung; das Werkzeug erzeugt oder verifiziert diese Sicherung nicht selbst. Die Bestätigung wird mit tatsächlichem Benutzer, aktuellem Bestätigungszeitpunkt und Referenz protokolliert. Der aktuelle Zeitpunkt ist der Zeitpunkt der Erklärung/Freigabe, **kein nachträglich angenommener historischer Vergleichs- oder Versandzeitpunkt**.

## Apply, Teilfehler und Wiederholung

Vor jeder Übernahme werden Dateiintegrität, exakte numerische Zuordnung, Mandant, Berechtigungen, aktueller Geschäftsdatenfingerprint, Recovery-Manifest-/Auswahlzustand, Diagnosekonflikte und Archivregistrierungen erneut geprüft. Der gemeinsame Sammler-Lock verhindert konkurrierende Recovery-Schreibläufe. Pro Rechnung wird eine eigene Datenbanktransaktion verwendet. Die unveränderte externe Prüfkopie gelangt ausschließlich über `recoverySelect`, `recoveryApprove` und `recoveryImport` in das Archiv. Anschließend werden Registrierung, kanonischer Pfad, Größe und SHA erneut geprüft.

Die Freigabe enthält die konkrete Datei-SHA, Erklärung, Plan- und ZIP-SHA, Datenfingerprint sowie tatsächliche Freigabedaten. Die vorherige `selection.json` bleibt in `selections/` erhalten. Bestätigte Originalherkunft und rückblickend bestätigter Kundenarchivvergleich werden ausdrücklich von einer neu ausgeführten Einzel-/Papierprüfung unterschieden.

Erfolgreiche Registrierungen bleiben bei Fehlern anderer Rechnungen bestehen. Der Ergebnisbericht führt `registered`, `already_archived`, `blocked`, `CONFLICT` und `failed` getrennt. Bei gesperrten/fehlgeschlagenen Fällen ist der Exitcode 1; ein erfolgreich gespeicherter Bericht bedeutet nicht, dass alle Belege übernommen wurden. Unsichere globale Eingaben brechen vor der Übernahme ab und werden auf STDERR gemeldet.

Nach Abbruch denselben bestätigten Plan mit unveränderten Eingaben und erneuter aktueller Sicherungsbestätigung wiederholen. Bereits vorhandene passende Originale werden nur geprüft; ihre Metadaten bleiben unverändert. Eine nach einem fehlgeschlagenen INSERT verbliebene kanonische Datei wird nur bei passender SHA/Größe und dokumentiertem Importversuch desselben Plans weiterverwendet. Fremde oder abweichende kanonische Dateien bleiben gesperrt. Bei geändertem Datenfingerprint, Manifest, ZIP, Erklärung oder anderer Auswahl einen neuen Plan erstellen und ausdrücklich prüfen/bestätigen. Keine Originaldateien, Nachweise oder Konflikte zur Umgehung einer Sperre löschen.

## Lokale Verifikation

```bash
php -l tests/documents/invoice-original-batch.php
php -l library/DEEC/DocumentRecovery.php
python3 tests/documents/document-recovery-db.py
python3 tests/documents/gobd-export-db.py
php tests/documents/document-review-test.php
php tests/documents/document-archive.php
php tests/documents/pdf.php
php tests/security/permissions.php
```

Die neue Fixture `invoice-original-batch-fixtures.php` läuft innerhalb der vorhandenen kurzlebigen, netzwerklosen Recovery-Testdatenbank. Sie bildet Mandant 100 mit 27 synthetischen Rechnungs-PDFs ab, einschließlich bereits registrierter 109860 und 26 neuer Registrierungen. Produktivkonfiguration und Kundendaten werden nicht verwendet. Der vollständige Ergebnisbericht dieses Entwicklungsauftrags steht in `invoice-original-batch-test-report.md`.
