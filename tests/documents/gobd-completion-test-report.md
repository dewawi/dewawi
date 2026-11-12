# GoBD-Abschluss – lokaler Testbericht

Stand: 12.11.2026. Ausgangscommit: `e4db8842`. Ausschließlich synthetische lokale Fixtures; keine produktive Datenbank, kein produktiver Export und keine produktive Dateisuche oder Rekonstruktion.

## Ergebnis und Umfang

Der externe PDF-Export ist auf die sechs Verkaufsbelegarten begrenzt und nennt die beiden Einkaufsarten ausdrücklich als außerhalb des angeforderten Umfangs. Interner Prüfexport, Archivaudit, Berechtigungen und Archive bleiben vollständig erhalten. Steuerliche Datensätze, Rechnungspositionen, Gutschriften, Anzahlungen und bestehende Stamm-/Referenzdatensätze bleiben erhalten. Die PDF-Option ändert keine bestehenden fachlichen CSV-Werte.

README und Metadaten beschreiben tatsächliche CSV-Zeilenzahlen, Dateistruktur, Schemaangaben und belegartbezogene Liefer-/Fehlstellenzähler. Externe Fehlstellen erhalten einen Grundcode ohne private Pfade/Recovery-Kommentare. Primärschlüssel und Betragspräzision werden aus dem tatsächlichen Testschema geprüft. Die bestehenden PDF-/CSV-Verknüpfungen werden weiter geprüft. Falsche CSV-Zeilenzahlen oder gelöschte-Ausgestellte-Fehlstellenzähler verhindern die Veröffentlichung.

Gezielte neue Regressionen: sechs externe gegenüber acht internen Arten; ausdrücklich ausgewiesene ausgeschlossene Einkaufsarten; reiner Einkaufsfehlbestand intern unvollständig, extern leerer angeforderter Umfang vollständig; gelöschte ausgestellte Rechnung ohne Dateiname/PDF am Enddatum; geliefertes gelöschtes Original nicht als fehlend gezählt; alle gelieferten CSVs einschließlich technischer Dateien mit tatsächlichen Datenzeilenzahlen; mehrzeiliges CSV-Feld; Wörterbuchtypen/Präzision/Schlüssel; vorhandene Beziehungsdateien und Zielspalten; erhaltene Anzahlungs-/Positions-/Gutschriften-/Stammdatenfelder; Herkunft und Nachweisgrenzen; Manipulation der Zähler; sichtbarer Ausgangsbelegumfang in der realen Exportmaske.

## Tatsächlich ausgeführte Tests

| Befehl | Ergebnis |
| --- | --- |
| `php -l library/DEEC/Export/Gobd.php` | bestanden |
| `php -l tests/documents/gobd-export-db.php` | bestanden |
| `php -l languages/de_DE/admin/admin.php` | bestanden |
| `php -l languages/en_US/admin/admin.php` | bestanden |
| `php tests/documents/gobd-export.php` | altes fachliches Profil und freie Datumswahl bestanden |
| `python3 tests/documents/gobd-export-db.py` | 608 Prüfungen bestanden; SELECT-only, acht intern/sechs extern, ZIP-/SHA-/CSV-/Mandanten-/Rechteprüfungen, Controller, unveränderte Geschäfts-/Archivzeilen, 32-MB-PDF unter 24 MB PHP-Limit |
| `python3 tests/documents/gobd-export-ui.py` | reale Maske DE/EN bei 375/1440 Pixeln, Filterpersistenz, getrennte Exporte und Umfangshinweis bestanden |
| `python3 tests/documents/document-recovery-db.py` | 343 Originalbatch-, 223 Mischbatch- und 1.529 Recovery-Prüfungen bestanden; zwölf DE/EN-Prüfpanel-Prüfungen bei 375/1440 Pixeln bestanden |
| `python3 tests/documents/document-review-db.py` | 38 isolierte MariaDB-/CLI-Prüfungen bestanden |
| `php tests/documents/document-archive.php` | 125 Prüfungen bestanden |
| `php tests/documents/document-review-test.php` | 80 Prüfungen bestanden |
| `php tests/documents/document-archive-migration-config.php` | 79 Prüfungen bestanden |
| `php tests/documents/original-document-audit-test.php` | read-only Klassifikationen bestanden |
| `php tests/security/permissions.php` | 415 Prüfungen bestanden |
| `php tests/security/tenant-access.php` | 269 Prüfungen bestanden |
| `php tests/security/document-edits.php` | 7.424 Prüfungen bestanden |
| `php tests/security/file-access.php` | 430 Prüfungen bestanden |
| `python3 tests/security/attachment-http.py` | isolierte Apache-/HTTP-Prüfungen für Upload/Download, Mandant, Objekt, Pfade, Symlinks, Cache und geschützte Dateien bestanden |
| `php tests/documents/pdf.php` | 183 Prüfungen bestanden |
| `php tests/documents/pdf-totals.php` | 219 Prüfungen bestanden |
| `php tests/documents/lifecycle.php` | 879 Prüfungen bestanden |
| `git diff --check` | bestanden |

Erwartete Negativtests protokollieren verweigerte Berechtigungen, absichtliche Fixture-Lesefehler, veraltete/manipulierte Prüf-PDFs, SMTP-Ablehnungen und gesperrte Belegänderungen. Die Tests enden erfolgreich. Alle Datenbankprüfungen nutzen kurzlebige netzwerklose MariaDB-Instanzen unter `/tmp`, HTTP-/Browserprüfungen isolierte lokale Fixtures. Es gibt keine offen fehlgeschlagene Abschlussprüfung.

## Geprüfte Historienquellen und fachlich offene Nachweise

Geprüft wurden Installationsschema und aktive Migration, Kontakt-/Artikelmodelle, Kontakthistorie, Benutzertracking sowie eBay-/Magento-Historien. Kontakt-/Artikeländerungen überschreiben Werte; vorhandene Änderungszeitpunkte enthalten keine Feldänderungsfolge. Die Kontakthistorie lädt Belege. `usertracking` protokolliert Zugriffe, `ebayhistory`/`magentohistory` Importzuordnungen. Keine dieser geprüften Quellen liefert eine vollständige verlässliche Stammdatenhistorie mit Alt-/Neuwerten. Es werden keine fiktiven Historienzeilen erzeugt. Gespeicherte Belegadressen und Positionstexte werden als Beleg-Snapshots bezeichnet, ohne daraus Unveränderlichkeit abzuleiten.

Nicht lokal nachgewiesen: tatsächliche produktive Vollständigkeit 2019–2021, konkrete Herkunft/offene Originalvergleiche, undatierte historische Zuordnung sowie gegebenenfalls externe Auditlogs oder historische Sicherungen. Dafür sind Betreiber-/Buchhaltungsnachweise erforderlich. Die bestehende gezielte Such-/Review-/Recovery-Reihenfolge ist in der Betreiberanleitung beschrieben; keine Hauptmigration und keine pauschale PDF-Erzeugung.

## Kompatibilität und Betrieb

Neue Felder sind ergänzend: `document_files.missing_reason`, zusätzliche Wörterbuchspalten und Metadaten für CSVs, Schema, Herkunftsgrenzen und Belegartenzähler. Verbraucher mit starrer technischer Kopfzeile müssen diese Ergänzungen berücksichtigen. Fachliche CSV-Kopfspalten und Geschäftswerte bleiben erhalten. Die bestehenden Leserechte aller acht Arten bleiben auch für Paketdownloads erforderlich. Bestehende Pakete bleiben unverändert; für den geänderten Umfang nach Bereitstellung des Codes ein neues Paket erstellen.

Keine SQL-Schemaänderung und keine Datenbanknacharbeit. Keine produktiven Datenänderungen, kein Push und kein Deployment.
