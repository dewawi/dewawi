# Testbericht: kontrollierte Sammelübernahme historischer Rechnungsoriginale

Datum: 12.11.2026. Ausgangsstand: `8f05cf15`. Ausschließlich lokale synthetische Testdaten; keine Ausführung mit produktiver Konfiguration, `correct.zip` des Betreibers oder produktiven Kundendaten. Die tatsächlichen Produktiv-SHAs, Zuordnungen und die erwarteten 26 neuen Registrierungen müssen daher erst der Betreiberplan bestätigen.

## Ergebnis

| Ausgeführter Test | Ergebnis |
| --- | --- |
| `python3 tests/documents/document-recovery-db.py` | 1.310 Assertions bestanden, davon 343 neue Rechnungs-Batch-Prüfungen und die bisherigen 967 Recovery-Prüfungen. Früherer vollständiger Durchlauf: alle 12 Chromium-Prüfungen für Prüf-/Diagnose-/Freigabeansichten auf Deutsch/Englisch bei 375/1440 Pixeln bestanden. Die letzten zwei Wiederholungen scheiterten erst beim Browserstart vor den Ansichtsprüfungen (Details unten); Gesamt-Exitcode dort 1 trotz bestandener Backend-Prüfungen. |
| `python3 tests/documents/gobd-export-db.py` | 525 Prüfungen bestanden; unveränderte Steuer-CSV-Daten, acht Belegarten, Original-/Prüfexport, SHA-/CSV-Verknüpfungen, Rechte, Fehlerbehandlung sowie 32-MB-PDF unter 24 MB PHP-Speicherlimit. |
| `php tests/documents/document-review-test.php` | 80 Prüfungen bestanden. |
| `php tests/documents/document-archive.php` | 69 Prüfungen bestanden. |
| `php tests/documents/pdf.php` | 183 Prüfungen bestanden. |
| `php tests/documents/gobd-export.php` | Bestehendes GoBD-Profil, Standard-README und Zeitraumvalidierung bestanden. |
| `php tests/security/permissions.php` | 415 Prüfungen bestanden. |
| `php -l` für `library/DEEC/DocumentRecovery.php`, `tests/documents/invoice-original-batch.php`, `tests/documents/invoice-original-batch-fixtures.php`, `tests/documents/document-recovery-db.php` | Alle bestanden. |
| `git diff --check` | Bestanden. |

## Neue Batch-Fixtures

Die Tests verwenden die vorhandene kurzlebige, netzwerklose MariaDB unter `/tmp/dewawi-document-recovery-db-*`, ein eigenes SQL-Tempverzeichnis und ausschließlich das bestehende Installationsschema. `plan` läuft mit einem SELECT-only-Konto; der Übernahmelauf benutzt das vorhandene begrenzte Test-Archivkonto. Dateien, Berichte und Prüfkopien bleiben unter dem privaten temporären Testsockel und werden danach entfernt.

Geprüfte Fälle:

- 27 mit TCPDF erzeugte synthetische Rechnungs-PDFs für Mandant 100; 109860 ist bereits registriert. Nach dem vollständigen Ablauf existieren genau ein unverändertes bestehendes und 26 neue Originale.
- `plan` verändert weder Datenbank-/Archivregistrierungen noch vorhandene Archivdateien, Auswahlzeiger oder Nachweise. JSON/CSV enthalten Nummer, interne ID, Datum, SHA/Größe, Datenfingerprint, Geschäftsgrundlage, Archivzustand, Kandidatenstatus und Aktion. Die Erklärungsvorlage bestätigt nichts vorab.
- Alle erforderlichen CLI-Parameter, fehlender/falscher Plan-Hash, fehlende Sicherungsbestätigung/-referenz, fehlende Erklärung, fremder Mandant/Operator sowie fehlende Rechnungs-Bearbeitungsrechte werden abgewiesen.
- Jede neue Registrierung benötigt eine einzeln benannte und SHA-/Identitäts-/Fingerprint-gebundene Erklärung zu Originalherkunft, Kundenarchivvergleich und Übereinstimmung mit gespeicherten Geschäftswerten. Ein nicht aufgeführter Eintrag übernimmt keine Erklärung. Fremde zusätzliche Dateien, geänderte Erklärungen und geänderte ZIP/PDF-Bytes werden nicht übernommen.
- Offene Differenzen sowie ein bloßer Kundenarchivvergleich ohne bestätigten Geschäftsdatenvergleich bleiben gesperrt. Fehlender historischer Vergleichszeitpunkt bleibt `null`; ungültige/unmögliche/zukünftige Zeitpunkte werden abgewiesen, ein tatsächlich angegebener gültiger Zeitpunkt unverändert validiert.
- Exakte positive numerische Rechnungsnummern: unbekannte/fremde Nummern, führende Nullen, falsche Ordner/Dateitypen und mehrdeutige Nummern werden gesperrt. Auch bei einer bereits archivierten Rechnung wird eine erst nach Planerstellung mehrdeutig gewordene Zuordnung erkannt. Ein manipuliertes Plan-Identitätsfeld kann die ZIP-Nummer nicht umgehen.
- 109860 mit passender SHA wird unverändert übersprungen; andere PDF-Bytes führen zu `CONFLICT`. Bestehende Registrierungen einschließlich Metadaten, Pfad, Bytes, SHA und Zeitpunkt bleiben unverändert.
- ZIP-Angriffe: Traversal, absolute/fremde Pfade, falsche Erweiterung, Symlink-Eintrag, Symlink-Quelldatei, Verschlüsselung, zu viele Einträge, doppelte Dateinamen, stark komprimierter Inhalt und übergroße Einzeldatei. Vor libzip wird außerdem das Zentralverzeichnis auf Anzahl, Größe und Grenzen geprüft. Daten werden begrenzt gestreamt und anhand tatsächlicher Größe/CRC sowie PDF-Struktur/MIME und SHA geprüft; temporäre Dateien bleiben privat und werden entfernt.
- Bestehender Diagnosekonflikt sperrt bereits im Plan und erneut beim Apply. Es gibt keine Diagnose-Override-Option.
- Änderungen der Auswahl nach Planerstellung werden nicht überschrieben. Frühere Auswahlfassungen bleiben bei einer autorisierten neuen Auswahl unverändert in `selections/` erhalten.
- Ein gemeinsamer Apply-Fixturelauf simuliert drei getrennte Fehler: veralteter Datenfingerprint, fehlgeschlagenes INSERT und offener Diagnosekonflikt. Ergebnis: eine bestehende Archivierung, 23 erfolgreiche neue Registrierungen, drei separat gemeldete Fehler und Exitcode 1.
- Nach Wiederherstellung des ursprünglichen Fixture-Zustands werden mit demselben bestätigten Plan ausschließlich die drei fehlgeschlagenen Fälle abgeschlossen; die 24 vorhandenen Originale bleiben unverändert. Der nach INSERT-Fehler verbliebene verifizierte kanonische Dateikörper wird anhand des plan-/SHA-gebundenen Versuchs sicher weiterverwendet.
- Dritter Apply-Lauf: ausschließlich 27 `already_archived`, keine Metadaten- oder Dateiänderungen und keine doppelten Registrierungen.
- Nach jeder erfolgreichen neuen Registrierung werden Freigaben, kanonischer Pfad, Größe und SHA erneut kontrolliert. Herkunft, Kundenarchivbestätigung, tatsächlicher bestätigender Benutzer/Zeitpunkt, konkrete Datei-SHA, Plan-/ZIP-/Erklärungs-SHA und Sicherungsbestätigung bleiben dokumentbezogen gespeichert. Keine neu ausgeführte Papierprüfung oder erfundene historische Versandinformation.
- Vollständiger Vorher-/Nachhervergleich der Geschäfts-, Positions-, Positionsgruppen-, Preisregel-, Kontakt- und E-Mail-Zeilen sowie aller zuvor vorhandenen Originalregistrierungen und Dateien.

## Korrekturen während der Verifikation und Grenzen

Der erste Integrationslauf erkannte eine zu strenge Fixture-Erwartung: Die autorisierte Auswahl ändert absichtlich den aktuellen `selection.json`-Zeiger. Der Test prüft nun zusätzlich dessen unverändert erhaltene vorherige Fassung in `selections/`; der Schutz bestehender Originaldateien/-registrierungen wurde nicht abgeschwächt. Zwei abschließende Wiederholungsläufe bestanden sämtliche jeweiligen Datenbank-/Batch-Prüfungen (zunächst 1.309, danach 1.310), brachen anschließend aber beim unveränderten Chromium-Teststarter ab: `POST /session` erreichte innerhalb von 20 Sekunden keine Browser-Sitzung (`TimeoutError`). Ein vorheriger kompletter Durchlauf hatte alle zwölf Browserprüfungen bestanden. Die letzten Ansichtsprüfungen konnten daher umgebungsbedingt nicht erneut ausgeführt werden. Dies bleibt als Testeinschränkung offen; es wurde keine Anwendungscode-Regression festgestellt. Die CLI-/Datenbankprüfungen des endgültigen Standes sind vollständig bestanden.

Ein PDF-Inhaltsvergleich/OCR ist nicht implementiert und wird nicht behauptet. Die fachliche Erklärung muss auf tatsächlich durchgeführten Vergleichen beruhen; offene Diagnosen/Differenzen bleiben gesperrt. Die Sicherungsbestätigung wird protokolliert, die tatsächliche Sicherung bleibt Betreiberaufgabe. Es wurden keine Produktivimporte, Pushes oder Deployments ausgeführt. Keine SQL-Migration erforderlich. Betreiberbefehle und Betriebsgrenzen: [invoice-original-batch.md](invoice-original-batch.md).
