# Kontrollierte Sammelfreigabe historischer Rechnungs-PDFs

`invoice-original-batch.php` ergänzt den bestehenden Recovery-Ablauf um `plan` und `apply` für ZIPs der Form `correct/<Rechnungsnummer>.pdf`. Mandant und Operator sind immer explizit. Die Implementierung unterstützt gemischte Freigaben für wiedergefundene Originaldateien und bestehende, geprüfte Rekonstruktionsdoppel; sie ist nicht auf Mandant 100 oder eine feste Dateianzahl begrenzt. Keine SQL-Migration, keine Neuberechnung, kein PDF-Rendering und keine Änderung von Geschäftsdaten. Auswahl, Freigabe, Archivpfad, Kopie, Registrierung und Auswahlhistorie verwenden die vorhandenen Recovery-/Archivkomponenten.

## Voraussetzungen und Grenzen

- Vorhandener privater Recovery-Prüfbereich mit `review.json`, `.collector.lock` und passendem Sammlerlauf unter `runs/`. Neue Registrierungen müssen bereits eindeutig im angegebenen Sammlerlauf geführt sein. Ein dort nicht aufgeführtes, bereits registriertes Original kann unverändert geprüft und übersprungen werden. Ein bestehender Download-Locator muss auf denselben Prüfbereich zeigen.
- Operator mit Zugang zum expliziten Mandanten sowie Rechnungs-Lese- und Bearbeitungsrechten. Für `plan` reichen SELECT-Datenbankrechte. `apply` benötigt zusätzlich INSERT auf `documentarchive`; dieses Werkzeug ruft für bestehende Registrierungen keinen Import und kein UPDATE auf.
- Lesbare Datenbankkonfiguration im bestehenden INI-Format; das darin verwendete Environment ist standardmäßig `production` (abweichend `--environment=...`). Das bezeichnet den Konfigurationsabschnitt, nicht eine Erlaubnis zur produktiven Ausführung durch Codex.
- ZIP und Erklärung müssen reguläre, private Dateien mit 0600 sein. Prüfbereich, Unterverzeichnisse und temporäre Dateien bleiben außerhalb von Installations-/Webroots mit 0700/0600. Konfiguration und Prüfbereich müssen für den ausführenden Betriebssystembenutzer zugänglich sein. Keine rekursiven Rechteänderungen an Originalen.
- Höchstens 100 PDFs und optional `correct/`; je PDF höchstens 20 MiB, ZIP und gesamte entpackte Daten höchstens 200 MiB, Expansionsverhältnis höchstens 200. Nur STORE/DEFLATE, höchstens 1 MiB Zentralverzeichnis; kein ZIP64/Mehrvolumeformat, keine Verschlüsselung, Symlinks, doppelten Namen oder fremden Dateitypen. Rechnungsnummern sind positive Dezimalzahlen ohne führende Nullen im bestehenden INT-Bereich. Kein rekursives Entpacken. Nicht passende ZIP-Strukturen brechen den gesamten Lauf ab.

Eine SHA-/Größen-/PDF-Strukturprüfung beweist keine inhaltliche Originalgleichheit. Das Werkzeug liest PDF-Beträge nicht automatisch aus und führt keinen OCR- oder Layoutvergleich durch. Es verlangt stattdessen eine ausdrücklich dokumentbezogene Betreibererklärung zur jeweiligen Freigabeart, Vergleich mit dem Kundenarchiv **und** Vergleich von Identität, Positionen, Steuern und Summen mit der im Plan ausgegebenen gespeicherten Geschäftsgrundlage. Ungeklärte Unterschiede bleiben gesperrt; es werden keine Werte angepasst. Bekannte Rekonstruktionen können ausschließlich als geprüfte Rekonstruktionsdoppel freigegeben werden. Diagnose-PDFs bleiben gesperrt. Es findet keine Neuerzeugung vorhandener PDFs statt. Bestehende Diagnosekonflikte desselben Belegs erfordern zuerst fachliche Klärung; dafür gibt es keinen Batch-Override.

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

- `release_type`: `verified_recovered_original` für tatsächlich wiedergefundene Originaldateien oder `reviewed_reconstruction_duplicate` für vorhandene Rekonstruktionen; der Wert muss zur technisch ermittelten Herkunft passen.
- `provenance`: nur bei wiedergefundenen Originaldateien deren tatsächliche Herkunft; bei Rekonstruktionen zwingend leer (`""`), ohne Originaldatei-Herkunftsbehauptung;
- `comparison_reference`: konkrete Referenz des tatsächlich durchgeführten Kundenarchiv-/Geschäftsdatenvergleichs;
- `identity_verified`, `customer_archive_compared`, `business_values_compared`, `positions_taxes_totals_match`: nur tatsächlich bestätigte Prüfungen auf `true` setzen. Bei Originaldateien zusätzlich `original_provenance_verified=true`. Bei Rekonstruktionen muss dieses Feld `false` bleiben; stattdessen ist `historical_content_match=true` als separate ausdrückliche Bestätigung des historischen Inhaltsvergleichs erforderlich;
- `unresolved_differences=false` und `differences=[]` ausschließlich bei geklärten Übereinstimmungen; sonst die offenen Unterschiede dokumentieren und den Eintrag gesperrt lassen;
- `comparison_performed_at=null`, falls kein tatsächlicher Vergleichszeitpunkt bekannt ist. Ein bekannter Zeitpunkt kann als ISO-Zeitstempel mit Zeitzone eingetragen werden. Keine erfundenen Zeitpunkte, Papierablagen oder Versandnachweise ergänzen.

Für eine Rekonstruktion werden neben Identität, Datei-SHA und Datenfingerprint etwa diese **fachlich tatsächlich bestätigten** Angaben benötigt:

```json
{
  "release_type": "reviewed_reconstruction_duplicate",
  "provenance": "",
  "original_provenance_verified": false,
  "historical_content_match": true,
  "comparison_reference": "konkrete tatsächlich verwendete Vergleichsgrundlage im Kundenarchiv"
}
```

Dies ist nur ein Ausschnitt der Erklärung, kein ausführbarer vollständiger Freigabenachweis. Die übrigen Bestätigungen und Bindungsfelder der erzeugten Vorlage bleiben erforderlich. Bestehende Original-Erklärungen ohne `release_type` bleiben für eigenständige Originaldateien kompatibel; damit kann keine bekannte Rekonstruktion freigegeben werden.

Rekonstruktionen müssen als bestehende `candidates/<SHA>.pdf` mit vollständigem aktuellem Rekonstruktions-/Betragsnachweis und gültiger Veröffentlichung im gewählten Recovery-Bereich vorhanden sein. Plan und Apply prüfen dieselben bestehenden `DEEC_DocumentReview::candidate`-/`receipt`-Regeln; der Plan bindet außerdem SHA-256 des Nachweises und der Veröffentlichung. Fehlende/veraltete/manipulierte Nachweise, geänderte Geschäftsgrundlagen, entfernte Veröffentlichungen, fremde Dokumentkontexte und fehlende Recovery-PDFs sperren die Freigabe. Auch Approve/Import prüfen die an die Erklärung gebundene Veröffentlichung erneut. Das ZIP dient der Identifikation; archiviert wird bei Rekonstruktionen die unveränderte **bereits vorhandene Recovery-PDF**. Kein Rückgriff auf ZIP-Bytes als Ersatz für eine verschwundene Recovery-PDF.

Die Erklärung benennt außerdem den tatsächlichen `operator_user`, `clientid` und ZIP-SHA. Eine Sammelerklärung gilt nur für die darin enthaltenen benannten Dateien mit ihren exakten SHAs und Datenfingerprints. Fehlende Erklärungen sperren die betreffenden neuen Registrierungen; zusätzliche, doppelte oder abweichende Dateien werden abgewiesen. Bestehende Originale erhalten durch das reine Überspringen keine neue Prüfbestätigung.

```bash
DECLARATION="$REVIEW/runs/tatsaechlich-bestaetigte-originalerklaerung.json"
"${batch[@]}" --action=plan --declaration="$DECLARATION"
```

Den **neuen** JSON-/CSV-Plan und die PDFs mit der ausgewiesenen Geschäftsgrundlage prüfen. Für die nun beschriebenen 28 Dateien werden 17 gültige Rekonstruktionsdoppel und neun eigenständige Originaldateien unterschieden. Nach den tatsächlich ausgefüllten Erklärungen sind höchstens 26 neue Registrierungen zu erwarten; die Rechnungsnummern 109856 und 109954 bleiben ohne eindeutige eigene Datenbankzuordnung gesperrt. Diese Zahlen werden nicht erzwungen oder als Produktivergebnis vorweggenommen. Rechnung 109860 ist nicht mehr im ZIP und wird daher nicht Teil dieses neuen Plans. Allgemein werden bereits registrierte Dateien ausschließlich bei exakt passender SHA, Größe, kanonischem Pfad und gültiger Einzelregistrierung als `already_archived` übersprungen. Abweichungen bleiben `CONFLICT`, keine Ersetzungsaufforderung.

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

Nach einem Codewechsel beziehungsweise einer geänderten Erklärung zuerst einen **neuen Plan** erzeugen, prüfen und dessen neuen Hash bestätigen. Alte gesperrte Pläne werden nicht automatisch freigeschaltet.

Vor jeder Übernahme werden Dateiintegrität, exakte numerische Zuordnung, Mandant, Berechtigungen, aktueller Geschäftsdatenfingerprint, Recovery-Manifest-/Auswahlzustand, Diagnosekonflikte und Archivregistrierungen erneut geprüft. Der gemeinsame Sammler-Lock verhindert konkurrierende Recovery-Schreibläufe. Pro Rechnung wird eine eigene Datenbanktransaktion verwendet. Die unveränderte Original-Prüfkopie beziehungsweise die bestehende Recovery-Rekonstruktion gelangt ausschließlich über `recoverySelect`, `recoveryApprove` und `recoveryImport` in das Archiv. Anschließend werden Registrierung, kanonischer Pfad, Größe und SHA erneut geprüft.

Die Freigabe enthält die konkrete Datei-SHA, Erklärung, Plan- und ZIP-SHA, Datenfingerprint sowie tatsächliche Freigabedaten. Die vorherige `selection.json` bleibt in `selections/` erhalten. Nur wiedergefundene Originaldateien erhalten bestätigte Originalherkunft (`origin.kind=historical_pdf`, `category=verified_recovered_original`). Rekonstruktionsdoppel behalten `origin.kind=reconstructed`, `category=reviewed_reconstruction_duplicate`, den vollständigen ursprünglichen Rekonstruktionsnachweis und `original_provenance_verified=false`. Ihr rückblickend bestätigter Kundenarchiv-Inhaltsvergleich bleibt als solcher erkennbar; es wird keine neu ausgeführte Einzel-/Papierprüfung behauptet.

Der externe GoBD-PDF-Export kennzeichnet diese Archivfassungen in `document_files.csv` über `pdf_origin=reviewed_reconstruction_duplicate`. Wiedergefundene Originaldateien tragen `verified_recovered_original`, regulär ausgestellte Originale `issued_original`; ungeklärte historische Herkunft wird ausdrücklich als solche ausgewiesen. Auch ältere, bereits freigegebene Rekonstruktionen ohne neues Kategorienfeld werden über ihre vorhandenen Rekonstruktionsnachweise erkannt. `pdf_supplied=1` bezeichnet eine gelieferte verifizierte Archivfassung und behauptet keine unveränderte historische Originaldatei. Die Steuer-CSV-Daten und Beziehungen bleiben unverändert.

Erfolgreiche Registrierungen bleiben bei Fehlern anderer Rechnungen bestehen. Der Ergebnisbericht führt `registered`, `already_archived`, `blocked`, `CONFLICT` und `failed` getrennt. Verbleiben 109856 und 109954 im ZIP, kann der Lauf 26 erfolgreiche Freigaben und zwei gesperrte Nummern melden. Auch dann ist der Exitcode 1; die erfolgreichen Freigaben bleiben erhalten. Bei gesperrten/fehlgeschlagenen Fällen ist der Exitcode 1; ein erfolgreich gespeicherter Bericht bedeutet nicht, dass alle Belege übernommen wurden. Unsichere globale Eingaben brechen vor der Übernahme ab und werden auf STDERR gemeldet.

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

Die Fixture `invoice-original-batch-fixtures.php` läuft innerhalb der vorhandenen kurzlebigen, netzwerklosen Recovery-Testdatenbank. Sie bildet Mandant 100 mit 27 synthetischen Rechnungs-PDFs ab, einschließlich bereits registrierter 109860 und 26 neuer Registrierungen. Produktivkonfiguration und Kundendaten werden nicht verwendet. Der vollständige Ergebnisbericht dieses Entwicklungsauftrags steht in `invoice-original-batch-test-report.md`.

`invoice-mixed-batch-fixtures.php` ergänzt 28 Dateien mit 17 zuvor real erzeugten/veröffentlichten Recovery-Fixtures, neun wiedergefundenen Original-Fixtures und zwei gesperrten Nummern. Der Batch selbst rendert keine PDFs. Sie prüft beide Erklärungsarten, Herkunftsverwechslungen, fehlende/geänderte Nachweise und Veröffentlichungen, unveränderte Recovery-Dateien, Diagnose-/Fingerprint-Sperren, Wiederholung und die korrekte externe GoBD-Herkunftskennzeichnung.

Der aktuelle Testbericht zur gemischten Freigabe steht in [invoice-mixed-batch-test-report.md](invoice-mixed-batch-test-report.md); der vorherige Original-Batch-Testbericht bleibt als historischer Nachweis erhalten.
