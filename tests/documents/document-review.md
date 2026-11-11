# Historische PDF-Fassungen zur manuellen Prüfung sammeln

`document-review.php` kopiert vorhandene Kandidaten in einen geschützten Prüfbereich. Es registriert keine Originale, ersetzt keine Archivdateien und erzeugt keine PDFs. Die Datenbank wird mit einem SELECT-Konto in einer read-only Transaktion gelesen. Es gibt keine Apply- oder Importoption und keine SQL-Migration.

Der vollständige Ablauf mit separater Rekonstruktion, manueller Freigabe und Archivübernahme ist in [document-recovery.md](document-recovery.md) dokumentiert. Er gilt für alle Mandanten, alle Daten und alle acht Belegarten. Der Sammler ist nicht auf einen bestimmten Prüfzeitraum oder eine feste Fallzahl begrenzt.

## Eingaben und Auswahl

Die JSONL-Datei muss ein vollständiger Dry-Run des vorhandenen Migrationswerkzeugs sein, einschließlich abschließender Zusammenfassung mit `mode=dry-run`, `unassigned_scan=complete`, `clientid`, `contacts_clientid` und passender Dokumentanzahl. Doppelte Dokumentzeilen, fremde Belegmandanten, gefilterte oder abgebrochene Berichte werden abgewiesen. Dateipfade und Hashwerte aus dem alten Bericht werden nicht als Kopieranweisung verwendet.

Die bestehende Audit-TSV aus `mysql -N -B` wird **ohne Kopfzeile** gelesen. Jede Zeile hat genau vier tabulatorgetrennte Spalten:

```text
sales/invoice	59296	112143	2019-05-03
```

Die Spalten sind `type` (Modul/Controller), interne `id`, sichtbare `document_number` und `document_date` im Format YYYY-MM-DD. Keine zusätzliche Kopfzeile und keine Mandantenspalte hinzufügen. Der Belegmandant kommt ausschließlich aus `--client` und muss mit der JSONL übereinstimmen. Das Werkzeug lädt den ausgewählten Beleg anschließend erneut mit interner ID **und** Mandant aus der Datenbank. Die bisherige TSV aus 2019–2021 bleibt unverändert nutzbar; andere Zeiträume und alle acht Belegarten sind ebenfalls zulässig. Falsche Datumswerte oder widersprüchliche Nummern werden abgewiesen. Es werden keine beliebigen Kontaktdateien anhand von Dateinamen gesucht.

`--select` unterstützt:

| Wert | Auswahl |
| --- | --- |
| `all-blocked` (Standard) | Alle blockierten PDF-Belege aller acht Arten aus dem vollständigen JSONL-Bericht, ohne Datumsfilter und ohne TSV. |
| `all-unarchived` | Alle nicht vollständigen PDF-Belege, also zusätzlich eindeutige historische Fassungen mit `status=ready`, denen noch Dateiablage oder Registrierung fehlt. |
| `audit` | Alle eindeutigen Dokumente aus der TSV; JSONL bleibt zur Mandanten- und Konfliktprüfung erforderlich. |
| `blocked-invoices` | Jede PDF-Rechnung `sales/invoice` mit `status=blocked` in der vollständigen JSONL, unabhängig vom Konfliktgrund und Jahr. TSV wird nicht benötigt. |
| `both` | Vereinigungsmenge der TSV-Auswahl und der blockierten Rechnungen; ein Beleg wird nur einmal bearbeitet. |

Die früheren 49 Auditfälle und 151 blockierten Rechnungen waren eine Teilauswahl. Der Betreiber nennt inzwischen 43.325 vollständige und 1.418 blockierte Dokumente für Mandant 100. Diese Zahlen sind keine fest programmierten Grenzen und wurden lokal nicht gegen Produktion geprüft. Vor dem neuen Gesamtlauf den Dry-Run erneut erstellen, damit bislang fehlende Dateinamen und aktuelle Zustände enthalten sind. Eine neue technische Freigabe im aktuellen Bestand entfernt einen zuvor ausgewählten Beleg nicht stillschweigend aus der Sammlung: berichteter und aktueller Grund bleiben im Manifest getrennt.

## Betreiberaufruf

Der folgende Aufruf ist für den Betreiber vorgesehen und wurde nicht gegen produktive Daten ausgeführt. Ein konsistenter, isoliert bereitgestellter Datenbank-/Dateisnapshot ist vorzuziehen. Das Konto in der Konfiguration benötigt nur SELECT auf den betroffenen Tabellen. Die INI-Auswertung wird aus dem [Migrationswerkzeug](document-archive-migration.md) wiederverwendet. `--contacts-client` muss dem freigegebenen Kontaktmandanten und der JSONL-Zusammenfassung entsprechen; die Beleg- und Dateieigentümerschaft bleibt bei `--client`.

```sh
umask 077
php tests/documents/document-review.php \
  --config=/var/www/migration-safety/read-only-database.ini \
  --base=/var/www/dewawi \
  --client=100 \
  --contacts-client=100 \
  --jsonl=/var/www/migration-safety/reports/client-100-full-dry-run.jsonl \
  --select=all-blocked \
  --output=/var/www/migration-safety/document-review/client-100
```

Für getrennte Sammlungen `--select=audit` oder `--select=blocked-invoices` setzen; beide dürfen denselben Prüfbereich verwenden. Eine andere vollständig konfigurierte INI-Umgebung kann mit `--environment=audit` gewählt werden. Den Prüfbereich nicht über Webserver-Aliase veröffentlichen. Das Werkzeug verweigert Ziele innerhalb beziehungsweise oberhalb des Quellbaums oder dieses Repository-Webroots; zusätzliche Webserver-Aliase kann es nicht erkennen. Die neuen Prüfverzeichnisse erhalten 0700, neue Dateien 0600. Bestehende Prüfverzeichnisse oder Werkzeugdateien mit abweichenden Modi werden abgewiesen und nicht automatisch umgestellt. Bereits existierende übergeordnete Betreiberverzeichnisse werden nicht umkonfiguriert.

Exitcode 0 bedeutet eine abgeschlossene Sammlung ohne zusätzliche Quellen-/Kopierfehler, nicht die Bestätigung eines Originals. Auch `original_missing` mit leerem Kandidatenbestand ist ein regulär erfasstes Prüfergebnis. Exitcode 1 kann eine abgeschlossene Sammlung mit `source_issues`/`errors` oder einen Abbruch bedeuten. Maßgeblich sind die Ausgabe mit `run_directory`, die zugehörige vollständige `run.json` und stderr. Ein Laufverzeichnis ohne `run.json` ist unvollständig. Nach Fehlerbehebung erneut ausführen; vorhandene Dateien werden dabei nicht überschrieben.

## Quellen und erneute Prüfung

Die aktuellen Belegdaten, Kontaktzuordnungen, dokumentbezogenen Attachment-Metadaten, Archivmetadaten und zugehörigen E-Mail-Kopien werden erneut gelesen. Fehlender, gelöschter oder fremder Elternbeleg sowie geänderte sichtbare Nummer oder abweichendes TSV-Belegdatum führen zu einem Manifest ohne neue Kandidaten und mit `document_unavailable` beziehungsweise `document_identity_changed`.

Das Werkzeug verwendet die tatsächlichen Pfade aus `DEEC_DocumentArchive`:

- kanonischer PDF-Pfad aus Belegmandant, Belegart und interner ID;
- historische dokumentbezogene Attachment-Datei;
- Kontaktablage nach Kundennummer und historischer interner Kontakt-ID, sofern die Zuordnung eindeutig ist;
- weitere historisch registrierte PDF-Dateinamen aus `emailattachment`, einschließlich gelöschter Metadatensätze, nur bei exakt passender dokumentbezogener Ablage;
- gespeicherte E-Mail-PDF-Kopien mit passender Nachrichten-/Belegmandantenzuordnung, nachrichtenbezogenem Pfad und einer verifizierten Archiv- oder Attachmentreferenz zum selben Beleg und Dateinamen.

Nachrichtenbezogene Kopien sind Kandidaten; ihr Vorhandensein belegt weder erfolgreichen Versand noch allein die Originaleigenschaft. Unreferenzierte manuelle Mailanhänge werden nicht willkürlich zugeordnet. Später verfügbare DATEV-/E-Mail-Originale legt der Betreiber manuell in `external/` ab; dieser Ordner wird vom Werkzeug nicht eingelesen oder verändert.

Bei mehreren aktiven Kontakten zu derselben Kundennummer werden **beide Kontaktpfade ausgelassen**, auch wenn dort PDFs liegen. Eindeutig belegbezogene Archiv-, Attachment- und referenzierte Versandkopien können weiterhin gesammelt werden. Fehlende Kontaktzuordnungen werden ebenso separat ausgewiesen. Zusätzlich wird für Kontaktdateien geprüft, ob derselbe physische Kontaktpfad/Dateiname zu anderen Belegen aller acht Belegarten passen könnte; solche Zuordnungen erhalten `contact_filename_ambiguous` und werden nicht kopiert. Ein expliziter Parent-Kontaktmandant ändert dabei nicht den bisherigen Mandantenanteil des Dateipfads.

Jede Quelle wird mit den vorhandenen PDF-/Pfadprüfungen neu inspiziert. Symlinks einschließlich Verzeichnisvorfahren, Pfadmanipulationen, ungültige Dateien oder nicht passende Metadatenpfade werden nicht kopiert. Vor und nach der Kopie werden Größe, SHA-256 und Dateiidentität geprüft. Neue Kandidaten werden über eine private temporäre Datei und eine exklusive Veröffentlichung angelegt. Bestehende Kandidaten werden nur bei identischen Bytes und sicheren Dateirechten wiederverwendet; manipulierte Prüfkopien werden als Fehler gemeldet und niemals repariert oder ersetzt. Das Zieldateisystem muss lokale Hardlinks für diese exklusive Veröffentlichung unterstützen.

Historische Metadaten sind ebenfalls nur Nachweise: Abweichungen ihrer aufgezeichneten Größe/Prüfsumme von den aktuell stabil gelesenen PDF-Bytes werden als `metadata_integrity=differs` beziehungsweise `recorded_integrity_differs` ausgewiesen. Eine strukturell gültige und eindeutig zugeordnete physische Fassung bleibt trotzdem als Kandidat erhalten, damit der Prüfer gerade diese Abweichung untersuchen kann. Die PDF-Prüfung bestätigt Header/EOF und Dateiidentität, keine vollständige semantische PDF-Validität oder rechtliche Originaleigenschaft. Änderungen durch privilegierte Betriebssystemzugriffe kann der Prüfbereich nicht ausschließen.

## Ablage und Wiederholbarkeit

Beispielhafte Struktur:

```text
client-100/
  review.json
  .collector.lock
  sales-invoice-59296-112143/
    candidates/<sha256>.pdf
    external/
    manifest.json
    manifest.<weiterer-lauf>.json
    selection.json
  runs/<lauf-id>/
    run.json
    overview.csv
    by-type.csv
    by-year.csv
    by-reason.csv
```

Die sichtbare Nummer wird für den Verzeichnisnamen URL-kodiert und in der Länge begrenzt; die interne ID und Belegart machen die Zuordnung eindeutig. Bei einer nummernlosen JSONL-Rechnung steht im Verzeichnisnamen `unnumbered`, ohne eine Belegnummer zu erfinden. `review.json` bindet den Bereich an Belegmandant, Kontaktmandant und Quellbasis. Ein paralleler zweiter Sammler wird durch eine exklusive Sperre abgewiesen.

`manifest.json` bleibt die erste Beobachtung. Jeder weitere Lauf legt eine neue Manifestdatei an, auch bei inzwischen geänderten Quellen oder einer anderen Auswahlkombination. `run.json` verweist auf die für diesen Lauf maßgeblichen Manifestdateien und enthält Anzahl, Eingabeberichtspfade/-Hashwerte, Quellbasis, Zeitpunkt und Fehlerzähler. Bereits vorhandene Manifeste, Kandidaten, `selection.json`, externe Dateien und frühere Übersichten werden nicht überschrieben. Temporäre Dateien werden nur vom gerade laufenden Schreibvorgang bereinigt; nach einem Prozessabbruch zurückgebliebene temporäre Dateien werden nicht als Kandidaten übernommen.

Pro Manifest werden Berichtsauswahl/-ursachen, aktuelle Dokument-/Kontaktzuordnung, aktueller technischer Grund, alle geprüften Quellen mit ursprünglichem Pfad, Quellenart, Nachweisen, Dateistatus, Größe, SHA-256, informativem `mtime` (Unix-Sekunden) und Kandidatenpfad protokolliert. Bytegleiche Dateien teilen pro Beleg einen Kandidaten; alle Quellen bleiben sichtbar. Auch ein Beleg ohne eine einzige gefundene Datei erhält ein Manifest und leere `candidates/`- und `external/`-Ordner.

`overview.csv` enthält eine Zeile je ausgewähltem Beleg. Die drei weiteren CSV-Dateien aggregieren nach Belegart, Jahr sowie berichtetem/aktuellem Grund und Quellenproblemen. Ein Beleg kann in mehreren Ursachengruppen stehen; deren Summen sind deshalb keine eindeutige Beleganzahl. Für CSV-Werte werden mögliche Tabellenformeln entschärft. Alle Übersichten liegen im jeweiligen unveränderlichen Laufverzeichnis.

## Format für die spätere manuelle Originalauswahl

Alle Belegarten, auch Rechnungen, können eine unverbindliche `suggestion` nach dem jüngsten Quell-`mtime` erhalten. Bei Gleichstand verschiedener Fassungen bleibt sie leer. Alle Dokumente bleiben ausdrücklich manuell auswählpflichtig. Der Zeitstempel ist kein Nachweis der tatsächlich ausgegebenen Fassung; der Freigabe- und Importprozess übernimmt Vorschläge niemals automatisch.

`selection.json` wird einmalig als leere Entscheidungsvorlage erstellt. Beispiel einer später manuell ausgefüllten Auswahl; Platzhalter für Prüfsumme und Begründung sind durch echte Nachweise zu ersetzen:

```json
{
  "schema_version": 1,
  "document": {"module": "sales", "controller": "invoice", "id": 59296, "clientid": 100, "document_number": "112143"},
  "decision": "selected",
  "candidate_path": "external/datev-original.pdf",
  "sha256": "<64 hex characters of the selected bytes>",
  "origin": {"kind": "datev", "source_path": "<original export location>", "evidence": "<export reference and date>"},
  "reason": "<documented reason for choosing these bytes>",
  "reviewed_by": 42,
  "reviewed_at": "2026-11-11T12:00:00+01:00"
}
```

Vorgesehene Entscheidungen sind `pending`, `selected`, `unavailable` oder `deferred`. Bei `selected` sind relativer Kandidatenpfad unter `candidates/` oder `external/`, SHA-256, Herkunft (`historical_pdf`, `datev` oder `email`, mit ursprünglichem Pfad und Nachweis), nicht leere Begründung, positive Benutzer-ID und ISO-8601-Prüfzeitpunkt erforderlich. Bei den anderen Entscheidungen bleiben Kandidatenpfad/Prüfsumme leer; Begründung und Prüfer dokumentieren eine abgeschlossene Nichtauswahl. Externe Dateien separat mit 0600 ablegen. Symlinks und nachträglich veränderte Bytes wären für eine spätere Übernahme unzulässig.

Der Sammler liest oder verarbeitet keine manuelle Entscheidung. Das separate [Wiederherstellungswerkzeug](document-recovery.md) unterstützt inzwischen die kontrollierte Auswahl, ausdrückliche Freigabe und Übernahme. Es prüft Berechtigung, Herkunft, Begründung, Benutzer, Dateiidentität und unveränderte Belegzuordnung erneut; vorhandene Originale bleiben geschützt. `origin.kind` darf zusätzlich `reconstructed` sein, wenn ein passender Rekonstruktionsnachweis vorliegt.

## Lokale Regressionen

```sh
php tests/documents/document-review-test.php
python3 tests/documents/document-review-db.py
```

Der erste Test verwendet ausschließlich synthetische PDFs und temporäre Berichte. Der zweite startet eine kurzlebige MariaDB unter `/tmp/dewawi-document-review-db-*` mit `--skip-networking`, richtet künstliche Datensätze und ein SELECT-Konto ein und führt das echte CLI zweimal aus. Produktive Berichte, Dateien und Datenbanken werden nicht benötigt oder angesprochen. Die reale Produktions-TSV/-JSONL lag der lokalen Umsetzung nicht vor; reale Fallzahlen und Dateizustände müssen beim Betreiberaufruf geprüft werden.
