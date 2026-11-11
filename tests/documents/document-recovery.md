# Historische Dokumente: vorbereiten, freigeben und unverändert archivieren

Der Ablauf verwendet die vorhandenen DEEC-Komponenten, Version 1 von `documentarchive` und den geschützten Prüfbereich. Er gilt für alle Mandanten, alle Zeiträume und alle acht Typen aus `DEEC_DocumentArchive::documents()`: `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest`, `purchases/purchaseorder`.

Keine Nummernvergabe, Fertigstellung oder Änderung von Geschäftsdaten. Es gibt keine neue Archivarchitektur, keine SQL-Migration und keine erneute Hauptmigration. Vorhandene registrierte Originale werden im Sammelablauf geprüft und unverändert übersprungen. Historische Dateien bleiben erhalten; eine spätere Auslagerung wird nur durch `retention-plan` vorbereitet, niemals ausgeführt.

## Bestehender Bestand und lokale Nachweisgrenze

Betreiberangaben vom 12.11.2026: 44.745 Dokumente, davon 43.326 vollständig archiviert und 1.419 offen; 1.318 vorbereitete PDFs, 101 fehlgeschlagene Rekonstruktionen und 1.208 Kandidaten mit `original_comparison_required`. Diese Zahlen sind keine Auswahlgrenzen. Die produktiven Prüfverzeichnisse, die Baseline und Fehlerlogs waren im Entwicklungsworkspace nicht vorhanden. Es wurden keine Produktivdatenbanken für Analyse oder Tests geöffnet. Eine tatsächliche Lösungszahl für die 101 Belege lässt sich lokal deshalb nicht angeben.

Der vorhandene Lauf und seine Kandidaten werden weiterverwendet. Ein passender Datenfingerprint, gültige Betragsprüfung und unveränderte PDF genügen zur technischen Wiederverwendung; eine reine Änderung der Renderer-Dateiprüfsumme erzwingt keine Neuerzeugung. Fehlende oder nachweislich veraltete Nachweise bleiben gesperrt oder können gezielt vorbereitet werden. Alte Dateien und Nachweise werden nicht überschrieben.

## Vier Betreiberaufrufe für den vorhandenen Lauf

Die folgenden Befehle wurden **nicht produktiv ausgeführt**. Sie setzen einen bereits unter der DEWAWI-Dateiidentität zugänglichen Prüfbereich voraus. Die Datenbankkonfiguration muss zum bestätigten Mandanten passen. Für `release-plan` genügt SELECT; `release` benötigt zusätzlich INSERT in `documentarchive`. Der ältere direkte Import benötigt bei einer ausdrücklichen Bestätigung vorhandener Registrierungen gegebenenfalls UPDATE nur auf `documentarchive.parameters`. Schreibrechte auf Beleg-/Positionstabellen oder DELETE sind nicht erforderlich.

Vor jedem `release` muss der Betreiber eine konsistente Datenbank- **und** Dateisicherung verifizieren. `--backup-verified` bestätigt beides ausdrücklich; Benutzer, Bestätigungszeitpunkt, Lauf-/Umfangsprüfsumme und dokumentbezogene Übernahme werden dauerhaft protokolliert. Es werden keine unbekannten Sicherungsnamen oder Sicherungszeitpunkte ergänzt.

Einmal in Bash die gemeinsamen Argumente setzen:

```bash
cd /var/www/dewawi
recovery=(sudo -u www-data php /var/www/dewawi/tests/documents/document-recovery.php
  --config=/var/www/dewawi/configs/database.ini
  --review=/var/www/dewawi-recovery-100/client-100
  --run=/var/www/dewawi-recovery-100/client-100/runs/20261111T201533Z-f315b7900048/run.json
  --client=100 --operator-user=100)
```

1. **Rechnung 110395 prüfen und in einem Aufruf übernehmen.** Dieser Aufruf bestätigt ausdrücklich die bereits zuvor vom Betreiber festgestellte vollständige Übereinstimmung genau dieser PDF mit Papierrechnung 110395:

```bash
"${recovery[@]}" --action=release --only=sales/invoice/55893 \
  --attest-previous-paper-match=fd4e3af8fcf0fd560d7fc7519ec3990a5c8092ea6cba3ac5923f0b157450b394 \
  --confirm-client=100 --backup-verified
```

Der Aufruf benutzt die vorhandenen Bytes und erzeugt keine neue PDF. Er durchläuft Auswahl, Freigabe, Import und Originalprüfung mit den bestehenden Funktionen. Die Bestätigung ist nur zusammen mit genau einem `--only` erlaubt. Falsche SHA-256 oder ein fremder/veralteter Nachweis sperren. Ein späterer Wiederholungsaufruf überspringt das gültige Original ohne Metadatenänderung.

Der Nachweis heißt `previous_paper_match` / `retrospective_operator_attestation`. Er bindet Mandant, Typ, interne ID, Nummer, PDF-SHA und Benutzer. `confirmed_at` ist der echte Zeitpunkt dieses CLI-Aufrufs. `comparison_performed_at` und `paper_location` bleiben ausdrücklich `null`. Als Referenz wird ausschließlich der bekannte Papierbeleg mit seiner Nummer benannt. Es wird kein neuer Papiervergleich behauptet und die Bestätigung wird auf keinen weiteren Beleg übertragen.

2. **Vollständigen Sammelplan erzeugen:**

```bash
"${recovery[@]}" --action=release-plan \
  --baseline=/var/www/migration-safety/reports/client-100-recovery-baseline-20261111-205426.jsonl
```

Der Plan verwendet eine read-only DB-Transaktion und verändert weder Archiv-/Geschäftsdaten noch Auswahl- und Freigabepointer. Er schreibt ausschließlich geschützte Ergebnisberichte. Ohne explizite Baseline werden die im Sammellauf aufgeführten JSONL-Quellen samt ihrer Prüfsummen verwendet. Externe TSV-Auswahllisten bleiben davon getrennt.

3. **Die geeignete Teilmenge einmalig ausdrücklich autorisieren und importieren:**

```bash
"${recovery[@]}" --action=release --authorize-batch=verified-evidence-v1 \
  --confirm-client=100 --backup-verified
```

Diese Policy autorisiert ausschließlich belegte Fälle. Sie ist keine pauschale Originalvergleichsbestätigung. Unklare Fälle bleiben im Nacharbeitsbericht; andere Dokumente werden weiterbearbeitet. Jede Registrierung wird über den bestehenden Importer ausgeführt und unmittelbar auf Originalpfad, Größe und SHA-256 geprüft.

4. **Gesamtbestand abschließend read-only auf Integrität prüfen:**

```bash
sudo -u www-data sh -c 'umask 077; set -C; php /var/www/dewawi/tests/documents/document-archive-migration.php --config=/var/www/dewawi/configs/database.ini --base=/var/www/dewawi --client=100 --contacts-client=100 > "/var/www/migration-safety/reports/client-100-recovery-final-$(date -u +%Y%m%dT%H%M%SZ).jsonl"'
```

Dies ist ausschließlich der bestehende Gesamt-Dry-Run ohne `--apply`. Dokumentzeilen, `historical_conflicts`, `excluded_document`, `unassigned_file` und abschließende `summary` auswerten. Exitcode 1 kann offene Konflikte melden und ist kein Grund, sie automatisch freizugeben. Bei berechtigten Child-Mandanten muss `--contacts-client` der tatsächlichen Parent-Zuordnung entsprechen.

## Auswahl- und Freigabepolicy

- Ein gültiges bereits registriertes Original wird zuerst geprüft und unverändert übersprungen. Andere Versionen, abweichende Metadaten oder eine belegte kanonische Datei mit anderen Bytes werden niemals ersetzt.
- Bestehende gültige Auswahlen, Freigaben und Originalvergleiche desselben aktuell berechtigten Prüfers werden wiederverwendet. Ungültige oder veraltete Auswahlen werden nicht stillschweigend durch eine automatische Auswahl ersetzt.
- Eine einzige historische Datei allein beweist keine Originalherkunft. Automatisch vorgeschlagen werden unter dieser Policy nur eindeutig zugeordnete Versandoriginale: genau eine historische Fassung, aktuelle mandanten-/belegbezogene Nachrichten- und Attachmentreferenzen, ursprünglicher Belegdateiname, akzeptierter/zugestellter Versand mit Zeitnachweis sowie unveränderte gespeicherte Größe/SHA und unveränderte Versanddatei. SMTP-Annahme beweist keine Zustellung; der Nachweis wird als Versandherkunft protokolliert. Die DB- und Dateinachweise werden vor Freigabe und Import erneut geprüft.
- Mehrere unterschiedliche historische Fassungen benötigen eine individuelle Entscheidung. Zeitstempel, Dateinamenähnlichkeit oder eine vermeintlich neueste Fassung lösen den Konflikt nicht.
- Rekonstruktionen brauchen im Sammelablauf stets einen vorhandenen Originalvergleich oder die ausdrücklich dokumentbezogene Papierbestätigung. Technische Betragskonsistenz allein genügt nicht, auch wenn `original_comparison_required=false` ist. Warnungen werden niemals aufgehoben.
- Die Archivparameter halten Freigabemethode (`batch_policy`, `operator_attestation` oder vorhandene individuelle Auswahl), Grund, Benutzer, Zeit, Kandidaten-SHA, Datenfingerprint, Sicherungsbestätigung und Nachweisgrundlage fest. Batch-Policy und nachträgliche Betreiberbestätigung werden nicht als neu durchgeführter individueller Originalvergleich dargestellt. Eine Wiederherstellung ist keine steuerliche Belegberichtigung.

Die vorhandenen Aktionen `prepare`, `inspect`, `select`, `approve`, `import` und das Format `selection.json` bleiben verfügbar; siehe auch [document-review.md](document-review.md). `inspect` erzeugt einen privaten HTML-Vergleich. Individuelle Vergleiche verwenden `original_comparison` mit `kind=paper_document|original_document|verified_dispatch_copy`, konkreter `reference`, tatsächlichem `compared_at`, `compared_by` gleich dem Prüfer und `amounts_match=true`. Digitale Vergleichsdateien benötigen zusätzlich `evidence_path=external/<datei>.pdf` und `evidence_sha256`; sie dürfen nicht die Rekonstruktion selbst sein. Der vollständige Rekonstruktionsnachweis wird bei Freigabe per SHA gebunden. Änderungen von Auswahl, Daten, Warnungen oder Vergleichsdatei sperren den Import.

## Fehlergruppen und gezielte Nacharbeit

| Gemeldete Fehlergruppe | Anzahl laut Betreiber | Lokal nachvollzogene Behandlung |
| --- | ---: | --- |
| Fehlende/mehrdeutige Kontakte | 40 | Vollständige im Beleg gespeicherte Billing-Anschrift darf ohne Ersatzkontakt gerendert werden. Herkunft und ungeklärte Zuordnung werden dokumentiert; Originalvergleich bleibt Pflicht. Unvollständige Anschriften bleiben gesperrt. |
| Unvollständige Positionsbeträge | 31 | Fehlendes abgeleitetes `position.total` darf bei vorhandenen Preisen/Mengen/Steuern und reproduzierbarem Kopf als `null` erhalten bleiben. Fehlende maßgebliche Eingaben werden nicht durch Null ersetzt. |
| Ungültige Positionspreisregel | 13 | Eine gespeicherte numerische Nullregel ohne Aktion ist nachweislich wirkungslos und wird ausdrücklich als Warnung erfasst. Fehlende Beträge, unbekannte wirksame Aktionen und widersprüchliche Vererbung bleiben gesperrt. |
| SQL-Syntax bei `contactid = ?` | 6 | Mit NULL in der isolierten MariaDB reproduziert: ZF1 ersetzt einen Platzhalter bei `where(..., null)` nicht. Historischer Kontaktzugriff bindet nur validierte positive IDs; NULL verwendet ausschließlich den gespeicherten Empfängersnapshot oder meldet kontrolliert unvollständige Daten. |
| Nummer/Datum/Währung fehlen | 5 | Bleibt gesperrt; keine Nummern-/Datumsvergabe oder Währungsannahme. |
| Kopfbeträge nicht reproduzierbar | 4 | Bleibt gesperrt; keine Korrektur gespeicherter Beträge. |
| Historische Rundungsbasis fehlt | 1 | Nur belegbare bestehende Berechnungsvarianten bis einschließlich 0,10 zulässig; sonst gesperrt. |
| Gespeicherte Positionen fehlen | 1 | Bleibt gesperrt; keine erfundenen Positionen. |

Vier technische Muster sind durch lokale Fixtures nachgewiesen: NULL-Kontaktbindung, nutzbarer gespeicherter Empfängersnapshot, fehlender abgeleiteter Positionszwischenwert und nachweislich wirkungslose Nullregel. Diese Muster bedeuten nicht, dass alle Fälle ihrer jeweiligen Betreibergruppe gelöst seien. Ohne die realen Logs und Daten bleibt die konkrete Trefferzahl unbekannt.

`position.total` war historisch kein verlässlicher Ausgabewert: `Calculate::direct()` speichert heute gerundetes Brutto; vor `f68824e0` ungerundetes Brutto, während frühe Berechnungspfade (`a665fea6`) nur den Kopf aktualisierten. Die PDF-Positionshelfer berechneten schon in `d4794d6e` Netto aus Preis und Menge. Beide aktuellen PDF-Helfer setzen die formatierte Nettosumme ausschließlich im Speicher. Der Rekonstruktionsnachweis trennt gespeichertes Total, Netto, Brutto, Differenzen, Regeln und tatsächlich dargestellte Beträge. Größere Widersprüche sind keine Rundung. Es gibt keine Toleranzerhöhung. Kopf und PDF-Ausgabe werden weiterhin unabhängig geprüft. Historische Vorlagen-, Artikel-, Bild-, Font- und Übersetzungsstände sind nicht generell garantiert.

Nur die fehlgeschlagene Teilmenge eines bestehenden geschützten `prepare`-JSON-Berichts erneut verarbeiten: zur gemeinsamen CLI `--action=prepare --retry-failed-from=<vollständiger-pfad-zum-prepare-bericht>` ergänzen. Der Bericht muss zum selben Prüfbereich und Mandanten gehören. `--only=module/controller/id` erlaubt gezielte Nacharbeit. Auch beim Wiederanlauf werden bereits gültige PDFs beibehalten. Der Sammler und das TSV-Format ohne Kopfzeile bleiben unverändert nutzbar. Keine Hauptmigration oder pauschale Vorbereitung aller 44.745 Belege ausführen.

## Ergebnisberichte, Rechte und Wiederanlauf

Jede Aktion schreibt `runs/recovery-<zeit>-<zufall>.json` und eine gleichnamige CSV mit 0600. JSON enthält Einzelentscheidungen, Nachweisdetails und Zusammenfassungen nach Typ, Jahr und Ursache. Die CSV enthält Mandant, Typ, ID, Nummer, Jahr, Status, Grund, Kandidaten-SHA, nächste Handlung und gegebenenfalls Quellpfad. Kategorien: `archived`, `already_archived`, `candidate_evidence_missing`, `original_comparison_required`, `historical_conflict`, `contact_unresolved`, `technical_failed`, `business_data_incomplete`, `excluded_issued` und `unassigned_file`; weitere ausgeschlossene Entwürfe bleiben separat erkennbar. Die letzten verfügbaren Vorbereitungsfehler werden aus den geschützten Laufberichten zugeordnet. Die Baseline liefert die separat ausgeschlossenen Dokumente und nicht zugeordneten Dateien. Fehlende, veränderte oder fremde Baselines werden nicht als gültige Bestandsnachweise verwendet.

Pro Dokument wird einzeln transaktional gearbeitet. Die vorhandenen Tenant-/Parent-Child- und View-/Edit-Prüfungen gelten weiterhin; Administratorstatus ersetzt keine Belegrechte. Archivregistrierung prüft Datenfingerprint, Größe und SHA vor/nach exklusiver Veröffentlichung am kanonischen DEEC-Pfad. Scheitert die Registrierung nach Dateipublikation, bleibt die geprüfte Datei erhalten und wird beim Wiederanlauf nur bei identischen Bytes wiederverwendet. Keine doppelten Registrierungen und keine Ersetzung bestehender Versionen. Exitcode 1 meldet einzelne technische Fehler; andere Belege können erfolgreich abgeschlossen sein. `skipped` ist keine erfolgreiche Archivierung.

Ausführung standardmäßig als `www-data`. Prüfbereich außerhalb des Webroots und ohne öffentlichen Alias; Verzeichnisse 0700, Dateien 0600, Hardlinks müssen unterstützt werden. Keine pauschalen rekursiven Rechteänderungen. Git-Updates unter `umask 022`; Recovery setzt intern `umask 077`. Berichte, Auswahlen und Sicherungen bleiben privat. Ältere Kontakte, Dateien, E-Mail-Verweise und Versandkopien werden nicht verändert oder bereinigt.

## Lokale Tests

`python3 tests/documents/document-recovery-db.py` startet eine kurzlebige, netzwerklose MariaDB ausschließlich unter `/tmp/dewawi-document-recovery-db-*` mit bestehendem Installationsschema. Die Regression benutzt echte PDFs aller acht Arten und `pdftotext`, prüft Beträge und Warnungen, NULL-Kontakte, gespeicherte Empfänger, Parent-Child-Rechte, nachträgliche Papierbestätigung, falsche SHA, Sammelpolicy, fehlende Nachweise, widersprüchliche Fassungen, private JSON/CSV-Berichte, unveränderte Kandidaten/Archive, Auswahl-/Freigabewiederverwendung und Wiederanlauf nach Insertfehler. Geschäfts-, Positionsgruppen-, Preisregel-, Kontakt- und E-Mail-Zeilen werden vor/nach dem Ablauf verglichen. Die bestehenden Archiv-, Review-, Migrations-Dry-Run-, Download-, Berechtigungs- und Berechnungstests ergänzen diese Prüfung.
