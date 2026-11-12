# Historische Dokumente: vorbereiten, freigeben und unverändert archivieren

Der Ablauf verwendet die vorhandenen DEEC-Komponenten, Version 1 von `documentarchive` und den geschützten Prüfbereich. Er gilt für alle Mandanten, alle Zeiträume und alle acht Typen aus `DEEC_DocumentArchive::documents()`: `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest`, `purchases/purchaseorder`.

Keine Nummernvergabe, Fertigstellung oder Änderung von Geschäftsdaten. Es gibt keine neue Archivarchitektur, keine SQL-Migration und keine erneute Hauptmigration. Vorhandene registrierte Originale werden im Sammelablauf geprüft und unverändert übersprungen. Historische Dateien bleiben erhalten; eine spätere Auslagerung wird nur durch `retention-plan` vorbereitet, niemals ausgeführt.

## Fehlgeschlagene Belege als Diagnose-PDFs

`prepare-diagnostic` bearbeitet ausschließlich zuvor fehlgeschlagene Belege aus privaten `prepare`-Berichten desselben Mandanten und Prüfbereichs. `--retry-failed-from=latest` ermittelt mit dem vorhandenen Berichtsleser den letzten verfügbaren Vorbereitungsstatus pro Beleg und schneidet diese Fehlerliste mit dem angegebenen Sammellauf. Alternativ ist ein konkreter `prepare`-JSON-Bericht unter `runs/` möglich. Ohne diesen Parameter und ohne passende Mandantenbestätigung wird die Aktion abgewiesen. Es gibt keine fest codierte Zahl 62, keinen Datumsfilter und keine pauschale Rekonstruktion des Gesamtbestands.

Für den dokumentierten bestehenden Lauf genügt ein Betreiberbefehl:

```bash
sudo -u www-data php /var/www/dewawi/tests/documents/document-recovery.php \
  --config=/var/www/dewawi/configs/database.ini \
  --review=/var/www/dewawi-recovery-100/client-100 \
  --run=/var/www/dewawi-recovery-100/client-100/runs/20261111T201533Z-f315b7900048/run.json \
  --client=100 --operator-user=100 --action=prepare-diagnostic \
  --retry-failed-from=latest --confirm-client=100
```

Der Befehl wurde nicht produktiv ausgeführt. Die Betreiberangaben zu 31 unvollständigen Positionsbeträgen, 13 ungültigen Preisregeln, sieben Kontaktfällen, fünf fehlenden Nummern/Datierungen/Währungen, vier Kopfsummenfällen, einer fehlenden Rundungsbasis und einem Beleg ohne Positionen sind keine lokal verifizierten Einzeldiagnosen. Der Lauf zeigt die tatsächlich vorhandenen Probleme und Erfolge. Aktive View-/Edit-Rechte und vorhandene Mandantenfreigabe bleiben erforderlich; Datenbankrechte können weiterhin auf SELECT beschränkt bleiben.

Der Diagnosemodus erzeugt einen neutralen Diagnosebeleg innerhalb von `DEEC_Pdf`, ohne die normale Rechnungs-Payloadberechnung zu lockern. Gespeicherter Belegkopf, Positionen, Positionsgruppen und Preisregeln werden unverändert und möglichst vollständig als gespeicherte Werte dargestellt. NULL und leere Werte erhalten sichtbare Hinweise; es gibt keine Ersatzpreise, Null-Steuern, Empfänger, Belegnummern, Datierungen oder Währungsannahmen. Gespeicherte Empfängerdaten bleiben erhalten, eine fehlende/mehrdeutige Kontaktzuordnung wird benannt und nicht durch aktuelle Stammdaten ersetzt.

Arithmetische Positionsreferenzen aus gespeicherten Einzelpreisen und Mengen stehen ausdrücklich als Vergleich **ohne Preisregeln und ohne bestätigte fachliche Grundlage** im Bericht. Eine vollständige Vergleichsrechnung verwendet ausschließlich die bestehende read-only-Methode `Calculate::calculate()`, nachdem ihre maßgeblichen Eingaben, aktiven gespeicherten Regeln und Vererbungsbezüge geprüft wurden. Unzulässige aktive Regeln sperren diese Vergleichsrechnung und werden mit ihren Originalwerten ausgewiesen. Fehlende Positionen erzeugen keine erfundenen Summen. Gespeicherte und berechnete Netto-/Bruttowerte stehen getrennt mit ihrer jeweiligen Differenz; keine Abweichung wird pauschal als Rundung akzeptiert. Die normale Berechnung, Vorlagen und Sales-/Purchases-Payload-Dienste bleiben unverändert.

Jede Seite trägt sichtbar **REKONSTRUKTION ZUR PRÜFUNG – KEIN BESTÄTIGTES ORIGINAL**. Der Status lautet `historical_diagnostic_reconstruction`, niemals der Status einer validierten Rekonstruktion. Pro Beleg werden die PDF unter `diagnostic-candidates/<SHA>.pdf` und ein privater Nachweis unter `diagnostics/<SHA>.json` erstellt. Dieser enthält Erzeuger, echten UTC-Erzeugungszeitpunkt, Identität, vollständigen aktuellen Geschäftsdatenfingerprint, Größe/SHA, tatsächliche Diagnose und Herkunftsfehler samt Quellbericht-SHA. `accounting_basis_verified=false`, `archive_approval_allowed=false` und `database_values_verified=false` bleiben ausdrücklich erhalten. Es wird keine neue Betreiberbestätigung einer Geschäftsdaten- oder Papierprüfung behauptet.

Dieselbe Aktion veröffentlicht erfolgreiche Diagnose-PDFs atomar unter `published-diagnostics/<Modul>-<Controller>-<ID>/<SHA>.json` für den vorhandenen internen Download. PDFs/Nachweise bleiben außerhalb des Webroots mit 0600/0700. Größe, PDF-Struktur, SHA, Nachweis und Datenfingerprint werden bei Wiederverwendung und Download erneut geprüft. Abbruch nach der Vorbereitung und vor Veröffentlichung kann mit demselben Befehl fortgesetzt werden. Gültige vorhandene Diagnose-PDFs werden wiederverwendet; veraltete private Nachweise bleiben erhalten. Bestehende Archivregistrierungen und bereits gültige normale Prüf-PDFs werden unverändert übersprungen, auch wenn ein älterer Fehlerbericht sie noch enthält. Auch eine noch unregistrierte kanonische Datei bleibt unverändert; die Diagnose erhält ihren eigenen externen Speicherort und ersetzt diese Datei nicht. Die bisherigen 1.356 Prüf-PDFs und Archivoriginale werden nicht ersetzt.

Die Belegansicht zeigt Diagnose-PDFs als eigene Gruppe; der Download liefert den Diagnosestatus und einen entsprechenden Dateinamen. Der vorhandene interne [Prüfexport](gobd-export.md) übernimmt sie getrennt unter `diagnostics/` mit Diagnosestatus und tatsächlichen Problemen im Manifest. Veröffentlichte Diagnosen ohne gespeichertes Belegdatum werden zusätzlich in jedem internen Prüfexport aufgenommen und ausdrücklich mit `date_scope=undated_diagnostic_not_assigned_to_period` ausgewiesen. Es wird kein Zeitraum oder historisches Datum erfunden; `undated_diagnostic_documents` zählt diese Fälle separat. Normale Originale und datierte PDFs behalten ihre bisherige Zeitraumsauswahl.

Diagnose-PDFs werden nicht als Original registriert, nicht in den GoBD-Originalexport aufgenommen und nicht automatisch als Kundenanhang angeboten. Die vorhandene Auswahl/Freigabe weist einen bekannten Diagnose-SHA auch dann ab, wenn seine Bytes in eine externe Vergleichsdatei kopiert wurden. Eine Diagnose ist keine freigabefähige Ersatzrechnung; eine spätere fachliche Klärung muss separat über die bestehenden validierten Abläufe erfolgen.

Der ausgegebene JSON-/CSV-Bericht unterscheidet `diagnostic_created`, `diagnostic_incomplete` und `diagnostic_unavailable` sowie unverändert übersprungene Fälle. JSON enthält die tatsächlichen Probleme pro Beleg; die verknüpften Nachweise enthalten gespeicherte/berechnete Werte und Differenzen. `publication_summary.available_candidates` zählt die verfügbaren Diagnosefassungen. Exitcode 1 kann einzelne nicht sinnvoll darstellbare beziehungsweise nicht zugängliche Belege melden; andere erfolgreich veröffentlichte Diagnosen bleiben verfügbar. Eine vollständige Auflösung aller 62 Fälle wird nicht vorausgesetzt oder vor der produktiven Prüfung behauptet.

## Vorbereitete PDFs gesammelt intern zugänglich machen

`publish-reviewable` macht vorhandene Rekonstruktionen im Register **Dokument** der acht bestehenden Belegansichten sichtbar. Jede unterschiedliche SHA-256 erhält eigene Links **Prüf-PDF ansehen** und **Prüf-PDF herunterladen**. Es wird keine Fassung ausgewählt und keine PDF erzeugt, kopiert oder als Original registriert. Status: `historical_reconstruction_pending_original_comparison` / **Historische Rekonstruktion – Papierprüfung ausstehend**. Die Betreiberbestätigung bezieht sich ausschließlich auf die geprüften Datenbankwerte; sie behauptet keinen Papiervergleich.

Aktuelle Betreiberangaben: 43.327 registrierte Originale, 1.418 offene Dokumente, davon 1.356 vorbereitete und noch nicht archivierte PDFs sowie 62 fehlgeschlagene Rekonstruktionen. Diese Zahlen werden nicht fest codiert oder lokal als produktiv verifiziert ausgegeben. Der Lauf ermittelt seine tatsächlichen Ergebnisse anhand des bestehenden Sammellaufs und aller darin enthaltenen Belege ohne Datumsbegrenzung.

Für den bereits dokumentierten Prüfbereich, Sammellauf und Betreiberbenutzer 100 genügt dieser eine Aufruf für die gesamte Menge; kein `--only` und keine Neuerzeugung:

```bash
sudo -u www-data php /var/www/dewawi/tests/documents/document-recovery.php \
  --config=/var/www/dewawi/configs/database.ini \
  --review=/var/www/dewawi-recovery-100/client-100 \
  --run=/var/www/dewawi-recovery-100/client-100/runs/20261111T201533Z-f315b7900048/run.json \
  --client=100 --operator-user=100 --action=publish-reviewable \
  --confirm-client=100 --database-values-verified --backup-verified
```

Dieser Befehl ist zur Ausführung durch den Betreiber bestimmt und wurde nicht produktiv ausgeführt. Die Option `--database-values-verified` protokolliert die ausdrücklich bestätigte Geschäftsdatenprüfung, `--backup-verified` die bereits bestätigte Datenbank-/Dateisicherung. Benutzer 100 muss ein aktives, für Mandant 100 und die betroffenen Belege berechtigtes Betreiberkonto sein. Andere Mandanten verwenden ihre eigenen vorhandenen Prüfbereiche, Läufe und Benutzer. Der CLI-Benutzer benötigt die bestehenden View-/Edit-Rechte; interne Leser benötigen ausschließlich die normalen View-Rechte des jeweiligen Belegs. Administratorstatus ersetzt keine Belegrechte. Berechtigte Parent-Kontakte bleiben nutzbar, Parent-/Child-Belege werden nicht gegenseitig freigegeben.

Der Lauf arbeitet mit SELECT-only-Datenbankrechten. Er prüft pro Kandidat Identität und Mandant, Rekonstruktionsnachweis mit tatsächlich dargestellten Beträgen, aktuellen vollständigen Geschäftsdatenfingerprint, PDF-Struktur, Größe und SHA-256. Die gespeicherten Betragsnachweise werden geprüft und unverändert gebunden; es erfolgt keine erneute Belegberechnung. Veraltete Nachweise und ungültige Dateien werden einzeln abgewiesen; weitere gültige Fassungen und Belege werden trotzdem verfügbar gemacht. Dokumente mit bestehender PDF-Archivregistrierung werden unabhängig von deren Integritätszustand unverändert übersprungen; eine fehlerhafte Registrierung bleibt Aufgabe der vorhandenen Archivprüfung.

Prüf-PDFs, Rekonstruktionsnachweise und Veröffentlichungsbelege bleiben außerhalb des Webroots, mit 0700 für Verzeichnisse und 0600 für Dateien. Unter `files/documents/.review-roots/<Mandant>.json` entsteht ausschließlich ein privater Locator auf genau diesen Prüfbereich. Die bestehenden Apache-Sperren für `files/documents` und versteckte Pfade schützen ihn; andere Webserver müssen dieselben bereits erforderlichen Sperren durchsetzen. Keine öffentlichen Aliase auf den Prüfbereich. Der Webserver muss unter derselben Dateiidentität wie der Betreiberlauf lesen können. Es sind keine manuellen Konfigurations- oder SQL-Änderungen nötig.

Die unveränderlichen Belege unter `<Prüfbereich>/published/<Modul>-<Controller>-<ID>/<SHA>.json` binden Identität, PDF-Größe/SHA, Geschäftsdatenfingerprint, vollständigen Rekonstruktionsnachweis per SHA und die Betreiberbestätigung mit echtem UTC-Zeitpunkt, Benutzer, Laufprüfsumme und Umfangsprüfsumme. Der geschützte JSON-Laufbericht enthält den vollständigen bestätigten Umfang. Wiederholung behält bestehende Bestätigungen unverändert bei und ergänzt fehlende Veröffentlichungen. Ein bereits mit einem anderen Prüfbereich belegter Mandanten-Locator wird nicht ersetzt.

Die gewohnten JSON-/CSV-Ergebnisberichte stehen unter `runs/recovery-<Zeit>-<Zufall>.*`; der Pfad wird ausgegeben. `publication_summary.available_candidates` zählt die verfügbaren Fassungen, `invalid_candidates` die abgewiesenen Kandidaten. `reconstruction_failed` führt Belege ohne vorbereiteten Nachweis mit dem letzten Vorbereitungsfehler separat auf, also auch die vom Betreiber genannten 62 Fälle, soweit sie weiterhin ohne Kandidaten im Sammellauf stehen. Eine feste Erfolgszahl wird nicht vorausgesetzt. Exitcode 1 meldet technische oder Kandidatenfehler; bereits erfolgreich veröffentlichte Prüf-PDFs bleiben nutzbar. Fehlende Rekonstruktionen werden ausdrücklich gemeldet und niemals neu erzeugt.

Download und Ansicht prüfen erneut Dokument-/Mandantenrechte, Geschäftsdatenfingerprint, Nachweis-SHA und PDF-Integrität. Der Download nimmt ausschließlich Belegidentität und SHA entgegen, niemals einen Dateipfad. Er streamt die geprüften Bytes mit `private, no-store`, `nosniff`, einem Prüfdateinamen und dem Statusheader `X-DEWAWI-Document-Status`; HEAD liefert keine PDF-Bytes. Normale Originaldownloads und E-Mail-Anhänge erhalten keinen Fallback auf Prüf-PDFs. Ohne reguläre Originalregistrierung gelangen diese Kandidaten auch nicht in den Originalbeleg-Export. Die gesonderte GoBD-Erweiterung wird durch diese Änderung nicht angepasst.

Nach dem Vergleich durch die Buchhaltung die vorhandene dokumentbezogene `selection.json` samt tatsächlichem `original_comparison` und die Aktionen `select`/`approve`/`import` beziehungsweise `release` verwenden. Für veröffentlichte Prüf-PDFs ist der Originalvergleich vor Archivierung immer Pflicht, auch bei warnungsfreien Betragsnachweisen. Eine Datenbankprüfung ersetzt ihn nicht. Nach endgültiger Archivierung blendet die Ansicht die Prüfdownloads aus; interne Nachweise bleiben erhalten.

## Früherer Bestand und lokale Nachweisgrenze

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

Dieselbe Regression prüft `publish-reviewable` und echte lokale HTTP-Downloads aller acht Arten unter SELECT-only-Rechten, GET/HEAD/Inline, Status- und Cacheheader, fehlende Berechtigungen, Fremd-/Parent-Child-Zugriffe, geänderte Nachweise und Datenfingerprints, falsche Größe/SHA/PDF-Struktur, Symlinks, mehrere Fassungen, unveränderte Bestätigungen bei Wiederholung, Teilwiederanlauf und getrennte Rekonstruktionsfehler. Chromium prüft den real gerenderten Prüfbereich mit zwei Fassungen auf Deutsch/Englisch bei 375/1440 Pixeln. Der HTTP-Router `document-recovery-download-fixture.php` funktioniert ausschließlich im lokalen PHP-Testserver mit dem temporären Testsockel; keine produktive Authentifizierung wird ersetzt.


## Originalfreigabe im Register Dokument

Voraussetzung ist der bereits über den Recovery-Betreiberlauf gebundene geschützte Prüfbereich dieses Mandanten. Ohne gültige Bindung erscheinen keine Freigabeformulare. Der PHP-Benutzer benötigt dieselben privaten Dateirechte wie beim bestehenden Prüfdownload sowie INSERT-Rechte für `documentarchive`. Keine SQL-Migration erforderlich.

1. Ausgestellten Beleg öffnen und das Register **Dokument** wählen. Leserechte zeigen die verfügbaren PDF-Fassungen; zur Freigabe werden zusätzlich Bearbeitungsrechte benötigt. Original-Uploads benötigen außerdem das vorhandene Hinzufügen-/Uploadrecht.
2. Vorhandene Rekonstruktion oder historischen Kandidaten ansehen. Alternativ eine historische Original-PDF bis 10 MB mit genauer Herkunftsreferenz hochladen. Der Upload erzeugt ausschließlich einen **ungeprüften Kandidaten**, keine Archivregistrierung. Bekannte Diagnose-PDFs und bekannte Rekonstruktionen können nicht als Original-Upload umetikettiert werden.
3. Die konkrete Fassung ausdrücklich auswählen; es gibt keine vorausgewählte Fassung. Bei mehreren Fassungen die Entscheidung begründen. Kopfwerte werden unverändert aus den Geschäftsdaten angezeigt. Nummer, Datum, Empfänger, Positionen, Steuer und Summen tatsächlich prüfen.
4. Tatsächliche Prüfgrundlage wählen: Papierbeleg, digitales Original, verifizierte Versandkopie oder verifizierte historische Originalherkunft einer wiedergefundenen Originaldatei. Rekonstruktionen benötigen einen tatsächlichen Originalvergleich. Für digitale Vergleichsgrundlagen eine andere ungeprüfte PDF als Beweiskopie auswählen; dieselbe Datei ist kein unabhängiger Vergleich. Herkunft/Referenz/Ablageort und Auswahlgrund erfassen, beide ausdrücklichen Identitäts-/Inhaltsbestätigungen setzen.
5. **Ausgewählte PDF freigeben und archivieren** übernimmt unveränderte Bytes nach den vorhandenen `selection.json`-/Approval-/Importprüfungen. Archivregistrierung Version 1, tatsächlicher Benutzer und Zeitpunkt, Quelle, Auswahlgrund und Vergleichsnachweis bleiben intern nachvollziehbar. Geprüfte Rekonstruktionsdoppel erhalten `reviewed_reconstruction_duplicate`, verifiziert wiedergefundene Originale `verified_recovered_original`. Eine Herkunftsprüfung behauptet keinen Papiervergleich und keinen historischen Versandzeitpunkt.

Diagnosebelege bleiben nicht freigabefähig. Solange ein aktueller Diagnosenachweis offene fachliche Fehler ausweist, ist auch die Freigabe anderer Kandidaten dieses Belegs gesperrt; zunächst ist der Sachverhalt zu klären. Ein Original kann zur Klärung weiterhin als ungeprüfter Kandidat hochgeladen werden. Geschäftsdatensätze, Preise, Status und Originaldateien werden nicht geändert. Bereits registrierte Originale bieten keine Ersetzung an. Eine identische wiederholte Freigabe ändert keine Registrierung. Nach einem Fehler kann neu geladen und dieselbe bewusste Auswahl wiederholt werden; nach einem Datenfingerprintwechsel ist eine neue Prüfung erforderlich. Eine konkurrierende CLI-Sammlung sperrt Webänderungen. Mehrere getrennte Prüfverzeichnisse desselben Belegs werden nicht automatisch zusammengeführt; diese muss der Betreiber zuerst eindeutig abgleichen.

Formulare verwenden POST, dokument-/mandanten-/benutzergebundene Session-CSRF-Tokens und den aktuellen Datenfingerprint. Sie sind ausdrücklich von der bestehenden Feld-Autospeicherung ausgenommen. Dateien und Nachweise verbleiben außerhalb des Webroots, mit 0700/0600. Ungeprüfte Uploads und Sammelkandidaten sind nur über den vorhandenen geschützten Prüfdownload und den internen Prüfexport verfügbar; der externe GoBD-Originalexport und der normale Kundenversand übernehmen sie nicht.

`document-original-review-fixtures.php` erweitert die isolierte Recovery-Regression um echte HTTP-Multipart-Uploads/Freigaben aller acht Arten, CSRF-/Identitäts-/Fingerprintfehler, fehlende Rechte, Mandanten-/Parent-Child-Negativfälle, Auswahl-/Vergleichsbelege, Fehlerwiederanlauf, wiederholte Freigaben und unveränderte Geschäftsdaten/Originale. Chromium prüft die deutsch-/englischsprachigen Formulare bei 375/1440 Pixeln ohne automatische Auswahl oder Bestätigung.
