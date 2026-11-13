# Registrierte Archive – lokaler Entwicklungs- und Testbericht

Ausschließlich synthetische lokale Daten und isolierte Testdatenbanken. Keine produktiven Archive, Firmen-/Kundenidentitäten, konkreten Prüfungsdaten oder Betreiberergebnisse wurden verarbeitet oder im Repository gespeichert.

## Umsetzung

Die bisherige Ausstellerkontrolle bleibt ohne Nachweis gesperrt. Neu sind ein direkter lesender Inventarlauf über registrierte PDFs, eine private Textvorprüfung mit wiederverwendbarem Cache, eine priorisierte Liste und die Vorbereitung einer zunächst unbestätigten Erklärung für einen exakt benannten Dokument-/SHA-Umfang. Dafür ist kein vollständiger ZIP-Zwischenexport erforderlich.

Ein positiver Sammelnachweis benötigt eine tatsächliche ausdrückliche Erklärung mit unabhängigen Ausstellerbelegen, tatsächlichem Prüfer, Zeitpunkt und Grundlage für den gesamten benannten Umfang. Ohne diese Grundlage bleibt die Freigabe offen. `scope_attested` ist keine behauptete Einzelvollprüfung. Bestätigte Fremdaussteller haben Vorrang vor Scope-Nachweisen; eine fehlende Konfiguration wird nicht als positive Bestätigung gewertet.

Die Übergabe des tatsächlichen ZIP erhält eine gesonderte Freigabe mit Paket- und Preflight-SHA. Die Nachprüfung berücksichtigt die beim Export verwendete Erklärung und den aktuellen Entscheidungsstand. Eine neue Negativentscheidung sperrt eine zuvor positiv geprüfte Lieferung. Es werden keine Pakete, Archivregistrierungen oder Ausstellerstatus nachträglich umgeschrieben.

## Geänderte Dateien

- `library/DEEC/Export/Gobd.php`: transparente Scope-Kennzeichnung und erforderliche separate Paketfreigabe; fachliches CSV-Profil und bisherige Strukturinformationen bleiben erhalten.
- `library/DEEC/Export/GobdIssuer.php`: explizite SHA-/kontextgebundene Scope-Erklärungen, individuelle Entscheidungen weiter unterstützt, Vorrang bestätigter Fremdaussteller.
- `tests/documents/gobd-archive-inventory.php`: authentifizierter SELECT-only-Archivsammler über bestehende DEEC-Komponenten, acht Belegarten, kanonische Datei-/Mandantenprüfung.
- `tests/documents/gobd-issuer-preflight.py`: private Profile, lesende Vorprüfung, Textcache, Prioritäten, unbestätigte Scope-Vorbereitung und eigenständige Paketfreigabeprüfung.
- `tests/documents/gobd-package.py`: gemeinsame Erklärungsvalidierung, Wiederverwendung der begrenzten Texterkennung, Unterstützung expliziter Scope-Nachweise; bestehender Paketvergleich bleibt erhalten.
- `tests/documents/gobd-export-db.php`, `gobd-issuer-test.php`, `gobd-package-test.py`: gezielte synthetische Regressionen.
- `tests/documents/gobd-archive-screening.md`, `gobd-package.md`, `gobd-export.md`, `gobd-software-description.md`: generische Betreiberbefehle, Nachweismethoden und Grenzen.

## Tatsächlich ausgeführte Tests

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax auf allen fünf geänderten/neuen PHP-Dateien | bestanden |
| Syntaxprüfung der geänderten/neuen Python-Dateien ohne Bytecode-Artefakte | bestanden |
| `php tests/documents/gobd-issuer-test.php` | 55 Prüfungen bestanden |
| `python3 tests/documents/gobd-package-test.py` | 109 Prüfungen bestanden |
| `python3 tests/documents/gobd-export-db.py` | 622 Prüfungen bestanden |
| `python3 tests/documents/document-recovery-db.py` | 343 Originalbatch-, 223 Mischbatch- und 1.529 Recovery-Prüfungen bestanden; zwölf Browserprüfungen bestanden |
| `python3 tests/documents/document-review-db.py` | 38 Prüfungen bestanden |
| `python3 tests/documents/gobd-export-ui.py` | reale Exportmaske, Filter und getrennte Exporte DE/EN bei schmalem/breitem Viewport bestanden |
| `python3 tests/security/attachment-http.py` | geschützte Dateien, Downloads, Rechte, Mandanten und Pfade bestanden |
| `php tests/documents/gobd-export.php` | fachliches Profil, README, freie Datumswahl und CSV-Werterhalt bestanden |
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

Neu geprüft werden insbesondere fehlende Konfiguration ohne positive Bestätigung, vollständige read-only Inventare, acht interne/sechs externe Arten, Fremdmandanten, echte synthetische Text-/Scan-PDFs, mehrere Ausstellersignale ohne juristische Bestätigung, priorisierte Gegenanzeigen, Wiederverwendung bestätigter Entscheidungen, Cachetreffer, unbestätigte Vorlagen, Scope-SHA und Kontext, gesperrte unklare Scope-Einträge, unveränderte CSV-Werte und Archive, separate Paketfreigabe, geänderte Paket-SHA und inzwischen neue Fremdausstellerentscheidungen.

Eine Integrationsfixture musste nach ihrem bewussten Negativtest ohne Konfiguration die Testkonfiguration vor dem positiven Scope-Fall wiederherstellen. Der abschließende Datenbanklauf bestand vollständig. Es ist keine Abschlussprüfung fehlgeschlagen oder mangels Umgebung ausgelassen worden. Erwartete Negativfälle protokollieren absichtliche Fixture-Fehler und gesperrte Nachweise.

## Verbleibende Grenzen und Betrieb

- Es wurde kein realer historischer Bestand vermessen. Laufzeit-/Cachevorteile sind mit synthetischen Daten und Wiederholung geprüft; eine konkrete produktive Vollständigkeit oder juristische Richtigkeit wird nicht behauptet.
- Texte und Fußregionen können unvollständig oder mehrdeutig sein. Scans, historische Namenswechsel und unzureichende Kennzeichen benötigen manuelle Klärung bzw. begründete weitere Profile. Ein unauffälliger Textbefund ist kein positiver manueller Nachweis.
- Nur geeignete neue Verkaufskandidaten werden für die Scope-Vorlage ausgewählt. Fehlen unabhängige Belege für den ganzen Umfang, darf die Erklärung nicht bestätigt werden; bestehende Einzelprüfverfahren bleiben verfügbar.
- Dateien, Quellen, Scope-Dateien, Erklärungsstände und Paketfreigaben bleiben unverändert privat aufzubewahren. Geänderte SHA oder Mandantenkontexte werden nicht stillschweigend übernommen.
- Der Cache ist eine private Werkzeugdatei, keine DEWAWI-Tabelle und keine Freigabedatenbank. Entscheidungen und Profile werden bei jedem Lauf neu geprüft.
- Die bestehende private PHP-Exportkonfiguration bleibt erforderlich; die neuen CLI-Inventare können ohne vorherigen PDF-ZIP-Export vorbereitet werden. Die vollständigen Befehle und Exitcodes stehen in [gobd-archive-screening.md](gobd-archive-screening.md).

Keine SQL-Migration, keine Änderung produktiver Geschäfts- oder Archivdaten, kein Push und kein Deployment.
