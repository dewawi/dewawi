# Testbericht: gemischte Freigabe historischer Rechnungs-PDFs

Datum: 12.11.2026. Ausgangsstand: `3edac988`. Ausschließlich isolierte lokale Datenbanken und synthetische PDFs. Der produktive 28-Dateien-Bestand wurde nicht gelesen oder verändert; die tatsächlichen produktiven Zuordnungen bestätigt erst der Betreiberplan.

| Ausgeführter Test | Ergebnis |
| --- | --- |
| `python3 tests/documents/document-recovery-db.py` | 1.533 Backend-Prüfungen bestanden: bisherige 967 Recovery-Prüfungen, 343 Original-Batch-Prüfungen und 223 neue Prüfungen gemischter Batches. Zusätzlich alle zwölf Chromium-Prüfungen für bestehende Prüf-, Diagnose- und Freigabeansichten (DE/EN, 375/1440 px) bestanden. |
| `python3 tests/documents/gobd-export-db.py` | 527 Prüfungen bestanden, einschließlich bestehender Steuer-CSV-Daten, aller acht Belegarten, Größen-/SHA-/Referenzprüfungen und 32-MB-PDF unter 24 MB PHP-Limit. Neue Prüfungen erkennen alte freigegebene Rekonstruktionen ohne Kategorienfeld und verhindern eine bewusst irreführende Originalkennzeichnung vor Paketveröffentlichung. |
| `python3 tests/documents/gobd-export-ui.py` | Alle vier Sprach-/Breitenkombinationen bestanden; Herkunftshinweise übersetzt, bestehende Filter, Auswahl, Rücksetzen und AJAX-Verhalten erhalten. |
| `php tests/documents/document-archive.php` | 125 Prüfungen bestanden, einschließlich Herkunftsklassifikation aller acht Belegarten und unveränderter bestehender Originale. |
| `php tests/documents/document-review-test.php` | 80 Prüfungen bestanden. |
| `php tests/documents/pdf.php` | 183 Prüfungen bestanden; bisheriger PDF-/Originalschutz und Mandantentrennung erhalten. |
| `php tests/documents/gobd-export.php` | Bestehendes steuerliches Profil, Standardexport ohne Belegbilder und Zeitraumvalidierung bestanden. |
| `php -l` für alle geänderten/neuen PHP-Dateien; `git diff --check` | Bestanden. |

## Gemischter Fixture-Bestand

Die bestehende kurzlebige, netzwerklose Recovery-MariaDB unter `/tmp/dewawi-document-recovery-db-*` wird weiterverwendet. Vor dem Batchlauf erzeugt die Fixture mit dem bestehenden Renderer 17 neue synthetische Rekonstruktionskandidaten samt echten Betrags-/Darstellungsnachweisen und veröffentlicht sie über den vorhandenen Recovery-CLI-Ablauf. Dazu kommen neun unabhängige Original-Fixtures und die beiden nicht eindeutig dem eigenen Mandanten zuordenbaren Nummern 109856 und 109954. Der Batch selbst erzeugt keine PDFs neu. Rechnung 109860 ist bereits vorhanden und fehlt im gemischten ZIP.

Geprüfte Ergebnisse:

- Der erste Plan führt exakt 28 Dateien auf, unterscheidet 17 Rekonstruktionen und neun eigenständige Originale und bestätigt keinen fachlichen Vergleich vorab.
- Erst die jeweiligen dokumentbezogenen Erklärungen machen 26 Registrierungen möglich. Beide unbekannten/fremden Nummern bleiben ohne Identität und gesperrt; es gibt keine Ersatzzuordnung oder Mandantensonderregel.
- Rekonstruktionen benötigen `reviewed_reconstruction_duplicate`, eine ausdrückliche historische Inhaltsbestätigung, die konkrete Kundenarchiv-Vergleichsgrundlage sowie Identitäts-, SHA- und Datenfingerprintbindung. Sie behalten leere Originalherkunft und `original_provenance_verified=false`.
- Originaldateien benötigen weiterhin `verified_recovered_original` und die tatsächliche bestätigte Originalherkunft. Keine Freigabeart darf die Herkunft der anderen vortäuschen. Die bisherigen Original-Erklärungen bleiben kompatibel.
- Fehlende beziehungsweise geänderte Nachweise und Veröffentlichungen, unzureichende Nachweise dargestellter Beträge, veraltete Datenfingerprints, Diagnosekonflikte, eine fehlende Recovery-PDF und Rekonstruktionen eines anderen Belegs sperren Plan beziehungsweise Apply. Eine verbleibende Veröffentlichung verhindert, dass ein verschwundener Nachweis zur Umdeklarierung als Original führt.
- Nachweis- und Veröffentlichungs-SHA sind im Plan eingefroren; Approve/Import prüfen aktuelle Kandidaten und Veröffentlichung erneut. Ein ZIP ersetzt keine verschwundene Recovery-PDF.
- Genau 17 Rekonstruktionsdoppel und neun Originaldateien werden über dieselbe bestehende Selection-/Approve-/Importkette registriert. Mit den zwei ungeklärten Nummern im ZIP bleiben diese zwei Fälle `blocked`, der Gesamt-Exitcode ist ausdrücklich 1. Das bezeichnet keinen Verlust der 26 erfolgreichen Registrierungen.
- Zweiter Apply: 26 `already_archived` und zwei unverändert gesperrte Nummern; keine geänderte Registrierung. Alle vorher existierenden Originale einschließlich 109860 bleiben unverändert.
- Rekonstruktionen verwenden ausschließlich ihre bestehenden `candidates/<SHA>.pdf`. Keine zusätzliche externe Original-Prüfkopie für diese Dateien; sämtliche vorhandenen Kandidaten, Nachweise und Veröffentlichungen behalten Bytes, Hash und Änderungszeitpunkt.
- Archivnachweise unterscheiden dauerhaft `origin.kind=reconstructed`/`reviewed_reconstruction_duplicate` von `historical_pdf`/`verified_recovered_original`. Ursprüngliche Rekonstruktionsdaten bleiben gespeichert. Kein erfundener Vergleichszeitpunkt und keine Originaldatei-Herkunftsbehauptung für Rekonstruktionen.
- Der echte GoBD-Export des gemischten Fixture-Bestands enthält alle 17 freigegebenen Doppel mit `pdf_origin=reviewed_reconstruction_duplicate` und neun wiedergefundene Originale mit `pdf_origin=verified_recovered_original`, jeweils mit exakt den bestätigten PDF-Bytes. README und Metadaten stellen klar, dass ein freigegebenes Doppel keine unveränderte historische Originaldatei ist.
- Reguläre Originale bleiben `issued_original`. Legacy-Rekonstruktionsnachweise werden auch ohne neues Kategorienfeld erkannt; ein widersprechendes Original-Kategorienfeld überstimmt die bekannte Rekonstruktionsherkunft nicht. Fehlende/missgebildete Herkunftsdaten behaupten keine Originalherkunft. Eine nicht freigegebene, erkennbare Rekonstruktion wird nicht als Archivbeleg geliefert.
- Geschäfts-, Positions-, Positionsgruppen-, Preisregel-, Kontakt- und E-Mail-Zeilen bleiben vollständig unverändert. Die bisherigen Original-Batch-Tests prüfen weiterhin SHA-Konflikte, geänderte Auswahl, Plan-/Erklärungs-/Sicherungsbindung, ZIP-Angriffe und transaktionalen Wiederanlauf nach Teilfehlern.

Keine offenen Testfehler oder umgebungsbedingt ausgelassenen Prüfungen in diesem Lauf. Die beim vorherigen Auftrag dokumentierte Chromium-Startbeschränkung trat hier nicht auf. Keine SQL-/Hauptmigration, kein Push, kein Deployment und keine Produktivdatenänderung.

Betreiberablauf und genaue Befehle: [invoice-original-batch.md](invoice-original-batch.md). Bestehende Pläne werden nicht nachträglich freigeschaltet; nach den neuen Einzelerklärungen einen neuen Plan erstellen und dessen exakten Hash sowie die aktuelle Sicherung ausdrücklich bestätigen. Das externe GoBD-Lieferverzeichnis enthält jetzt zusätzlich `pdf_origin`; Verbraucher dieses Verzeichnisses müssen die zusätzliche Spalte berücksichtigen. Die steuerlichen Stamm-/Bewegungs-CSV-Dateien und ihre Beziehungen bleiben unverändert.
