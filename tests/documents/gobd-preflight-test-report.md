# GoBD-Preflight – lokaler Entwicklungs- und Testbericht

Ausschließlich synthetische Fixtures und isolierte lokale Testdatenbanken. Keine konkreten Betreiberidentitäten, produktiven Eingabepakete, Vergleichsergebnisse oder Prüfzeiträume wurden verarbeitet oder in das Repository übernommen.

## Gefundene allgemeine Probleme und Änderungen

- Archivregistrierung und Mandantenzuordnung belegten bisher keine eigenständige Prüfung des tatsächlichen PDF-Ausstellers. Der externe PDF-Export benötigt jetzt eine private, exakt an Mandant, interne Dokumentidentität, sichtbare Nummer, PDF-SHA und aktuellen Mandantenkontext gebundene manuelle Ausstellerentscheidung. Ungeklärte oder als fremd bestätigte Aussteller verhindern die PDF-Aufnahme, nicht den Export der Geschäftsdaten. Ein Vorlagengenerator erzeugt ausschließlich unbestätigte Einträge.
- Das kompatible Feld `issued` beschrieb technischen Schreibschutz, wurde aber missverständlich als Ausstellung interpretiert. Metadaten, README und Überschriften präzisieren dies. Gespeicherte Workflowfelder bleiben unverändert und stehen auch im Belegverzeichnis; daraus wird kein Versand abgeleitet.
- Die bisherige CSV-Backslash-Maskierung konnte Textwerte mit Anführungszeichen oder abschließendem Backslash mehrdeutig machen. Der Export verwendet eindeutige doppelte Anführungszeichen und wörtliche Backslashes; Regressionen prüfen das unveränderte Zurücklesen der Werte.
- PDF-/CSV-Verweise und PDF-Pfade werden zusätzlich gegen Belegart und geschützte interne Dokumentidentität geprüft. Zwei ältere Zählermanipulationstests verwendeten falsche Konstruktorargumente und scheiterten dadurch bereits an der Berechtigung; sie prüfen jetzt die tatsächlich beabsichtigten Fehler.
- Neue sensible Stagingdateien und dauerhafte technische Exportnachweise liegen in einem konfigurierten privaten Verzeichnis außerhalb von Installation und Git-Worktree. Externe Metadaten enthalten benötigte Schemaattribute statt interner Datenbanknamen; private Prüfpersonen und Ausstellerbelege bleiben intern.
- Ein rein lesender Paketprüfer kontrolliert ZIP-Struktur, Integrität, Metadaten, CSVs, Beziehungen, Dokumentidentitäten, PDF-Inventare, Ausstellerentscheidungen und Vollständigkeitszähler. Der Versionsvergleich trennt Struktur, zusätzliche Datensätze, Exportmitgliedschaft, Geschäftswerte, Beträge, Status und PDF-Inventare. Temporäre SQLite-Indizes liegen ausschließlich im privaten Arbeitsbereich und verändern keine DEWAWI-Datenbank.
- Ein leeres strukturiertes Anzahlungsfeld wird ausdrücklich nicht als Beweis fehlender Anzahlungen dargestellt. Die vorhandene Versionsmigration belegt das Anlegen des Feldes, kein konkretes Kalenderdatum einer Installation. Kein Betrag wird ergänzt oder neu berechnet.

## Relevante Dateien

| Bereich | Dateien |
| --- | --- |
| Export und Ausstellerbindung | `library/DEEC/Export/Gobd.php`, `library/DEEC/Export/GobdIssuer.php` |
| Gemeinsamer privater Pfadschutz und Statusbeschreibung | `library/DEEC/DocumentReview.php`, `library/DEEC/Permission.php` |
| Exporthinweise | `languages/de_DE/admin/admin.php`, `languages/en_US/admin/admin.php` |
| Rein lesender Preflight und Vergleich | `tests/documents/gobd-package.py` |
| Gezielte Regressionen | `gobd-export.php`, `gobd-export-db.php`, `gobd-issuer-test.php`, `gobd-package-test.py`, `gobd-issuer-fixture.php` im selben Testverzeichnis |
| Bestehende Integrationsfixtures | `document-recovery-db.php`, `document-diagnostic-fixtures.php`, `document-original-review-fixtures.php`, `invoice-mixed-batch-fixtures.php` im selben Testverzeichnis |
| Generische Anleitungen | `gobd-export.md`, `gobd-package.md`, `gobd-software-description.md` im selben Testverzeichnis |

## Tatsächlich ausgeführte Prüfungen

| Befehl / Prüfung | Ergebnis |
| --- | --- |
| `php -l` auf allen 14 geänderten/neuen PHP-Dateien | bestanden |
| Python-Syntaxprüfung der neuen Skripte ohne Bytecode-Artefakte | bestanden |
| `php tests/documents/gobd-export.php` | Profil, freie Datumswahl, README und CSV-Werterhalt bestanden |
| `php tests/documents/gobd-issuer-test.php` | 48 Prüfungen bestanden, einschließlich aller acht Arten, exakter Nummern, SHA, Kontext, manueller Erklärung, Konflikte und privater Pfade |
| `python3 tests/documents/gobd-package-test.py` | 72 Prüfungen bestanden, einschließlich realer synthetischer PDFs, Scans, Fremdaussteller, interner/externer Struktur, Vergleich, doppelter Nummern, Statusnormalisierung, Werteformatierung, Betragsänderung, Integrität, Fehlstellen und unveränderter Eingaben |
| `python3 tests/documents/gobd-export-db.py` | 616 Prüfungen bestanden; sechs externe/acht interne Arten, Ausstellerlücken, unveränderte Geschäfts-/Archivzeilen, vertauschte PDF-Zuordnung, Rechte, Mandanten, private Nachweise, Zähler und große PDF unter begrenztem PHP-Speicher |
| `python3 tests/documents/document-recovery-db.py` | 343 Originalbatch-, 223 Mischbatch- und 1.529 Recovery-Prüfungen bestanden; zusätzlich zwölf Browserprüfungen der Prüfoberflächen |
| `python3 tests/documents/document-review-db.py` | 38 isolierte MariaDB-/CLI-Prüfungen bestanden |
| `python3 tests/documents/gobd-export-ui.py` | reale Maske DE/EN in schmalem und breitem Viewport, Filterpersistenz und getrennte Exporte bestanden |
| `python3 tests/security/attachment-http.py` | isolierte HTTP-Prüfungen für geschützte Dateien, Rechte, Mandanten, Pfade, Uploads und Downloads bestanden |
| `php tests/documents/document-archive.php` | 125 Prüfungen bestanden |
| `php tests/documents/document-review-test.php` | 80 Prüfungen bestanden |
| `php tests/documents/document-archive-migration-config.php` | 79 Prüfungen bestanden |
| `php tests/documents/original-document-audit-test.php` | read-only Klassifikationen bestanden |
| `php tests/security/permissions.php` | 415 Prüfungen bestanden |
| `php tests/security/tenant-access.php` | 269 Prüfungen bestanden |
| `php tests/security/document-edits.php` | 7.424 Prüfungen bestanden |
| `php tests/security/file-access.php` | 430 Prüfungen bestanden |
| `php tests/documents/pdf.php` | 183 Prüfungen bestanden |
| `php tests/documents/pdf-totals.php` | 219 Prüfungen bestanden |
| `php tests/documents/lifecycle.php` | 879 Prüfungen bestanden |
| `git diff --check` | bestanden |

Ein paralleler Wiederholungsversuch des Recovery-Tests brach bei der Initialisierung der kurzlebigen MariaDB mit einer fehlenden temporären Systemtabelle ab. Die abschließende sequenzielle Ausführung von GoBD und Recovery bestand vollständig. Der neue PDF-Zuordnungsnegativtest musste außerdem zwei gleich klassifizierte synthetische Archivfassungen auswählen; diese Fixture-Voraussetzung wurde korrigiert. Es bleibt keine fehlgeschlagene Abschlussprüfung offen. Erwartete Negativtests protokollieren verweigerte Zugriffe, beschädigte/veraltete Nachweise und absichtliche Fixture-Fehler.

## Fachlich offene Entscheidungen und Betrieb

Die Software beurteilt juristische Aussteller nicht automatisch anhand einer Textsuche. Vollständiger Dokumentvergleich, tatsächlicher Aussteller, belastbare Vergleichsgrundlage, Prüfperson und tatsächlicher Bestätigungszeitpunkt bleiben Betreiberentscheidungen. Die Ausstellererklärung ersetzt keine bestehende Original-/Inhaltsvergleichs- oder Recovery-Freigabe. Nicht vorhandene Stammdatenhistorie und Versandinformationen werden nicht erfunden. Konkrete produktive Fälle und ein Vergleich konkreter Betreiberpakete wurden nicht geprüft.

Vor PDF-Paketläufen im tatsächlichen PHP-Webworker `DEWAWI_GOBD_REPORT_DIR` und `DEWAWI_GOBD_ISSUER_REVIEW_DIR` auf die dokumentierten privaten Verzeichnisse konfigurieren. Das Berichtsverzeichnis benötigt für die bestehende Hardlink-Veröffentlichung dasselbe Dateisystem wie das Exportziel. Ohne konkrete Ausstellerentscheidung bleibt die betreffende PDF eine ausgewiesene Nichtlieferung; fachliche CSVs werden deswegen nicht entfernt. Neue Belegverzeichnisspalten und die eindeutige CSV-Maskierung sind bei strikt festgelegten technischen CSV-Verbrauchern zu berücksichtigen. Mehrdeutige alte CSV-Dateien werden nicht automatisch repariert.

Der vollständige generische Betreiberablauf mit Vorlagen, Ausstellerentscheidung, Export, finalem Preflight, optionalem ZIP-Vergleich und Exitcodes steht in [gobd-package.md](gobd-package.md). Ergebnisse und vollständige Einzelabweichungen verbleiben in privaten JSON-/JSONL-Dateien außerhalb des Repositorys. Technischer Erfolg ist keine rechtliche Konformitäts- oder Versandbestätigung.

Keine SQL-Schemaänderung, keine produktive Datenbank-/Archivänderung, keine Git-History-Änderung, kein Push und kein Deployment.
