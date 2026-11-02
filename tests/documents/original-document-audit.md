# Audit der Originaldokumente

Stand: 02.11.2026. Diese Anleitung ist bewusst read-only. Sie öffnet keine Produktionsdatenbank aus der Entwicklungsumgebung, erzeugt keine PDFs, stellt keine Dateien wieder her und verändert weder Datenbank noch Dateibestand.

## Nachgewiesener lokaler Ist-Stand

Die Kontakt-Historie liest die acht Belegtabellen unabhängig vom Dateisystem. Deshalb belegen 23 Historienzeilen nicht, dass 23 Original-PDFs vorhanden sind. Der FileManager zeigt dagegen den Kontaktordner `files/contacts/<Mandant>/<Kontaktnummer>/`; die E-Mail-Kopie liegt separat unter `files/attachments/<Modul>/<Belegart>/<Mandant>/<interner Beleg-ID>/`. Fünf sichtbare PDFs können daher sowohl echte Verluste als auch nur eine unvollständige Betrachtung der zweiten Ablage bedeuten.

Vor dieser Änderung wurde die Belegtransaktion vor der PDF-Erzeugung committed. Ein Schreib-, Template- oder Prozessfehler konnte damit einen nummerierten, als fertig markierten Beleg ohne Original hinterlassen. Außerdem verweigerten nur Rechnung und Gutschrift eine Rekonstruktion aus aktuellen Daten. FileManager-Schreibaktionen mit ausreichender Edit-Berechtigung und das Löschen der automatisch erzeugten E-Mail-Kopie waren nicht durch den Belegstatus selbst gesperrt.

Der GoBD-Export enthält CSV-Daten, Datenwörterbuch, Beziehungen, Metadaten und README, aber keine Original-PDFs, keine XRechnung-XML, kein Dateimanifest und keine Prüfsummen. Sein Belegprofil umfasst Angebot, Auftrag, Rechnung und Gutschrift samt Positionen; Lieferschein, Mahnung sowie beide Einkaufsbelegarten fehlen ebenfalls. Die XRechnung wird derzeit bei Bedarf aus Belegdaten, aktuellen Mandanten-/Bankdaten und eingegebenen Exportparametern erzeugt und nur zum Download ausgegeben. Diese XML ist daher keine dauerhaft abgelegte historische Fassung.

## Produktionsdiagnose für Kontakt 80560

1. Der Betreiber ermittelt den exakten Belegmandanten für Atalay & Roos und arbeitet auf einem konsistenten read-only Snapshot oder einer isoliert wiederhergestellten Sicherung. Eine geschützte Arbeitskopie von `original-document-production-audit.sql` wird angelegt; darin muss ausschließlich `@clientid := 0` durch diese ID ersetzt werden. Kontakt `80560` und der Fokus ab `2026-05-01` sind bereits gesetzt.
2. Mit einem ausschließlich leseberechtigten Datenbankkonto wird der TSV-Export erstellt. `--raw` verhindert Escape-Umformungen; das SQL selbst startet eine read-only Transaktion.

```sh
mariadb --defaults-extra-file=/secure/read-only.cnf --batch --raw --default-character-set=utf8mb4 dewawi \
  < tests/documents/original-document-production-audit.sql > /secure/contact-80560-documents.tsv
```

3. Der TSV wird gegen den produktiven Dateibaum oder vorzugsweise gegen eine isoliert gemountete Sicherung geprüft. Das Werkzeug schreibt nur nach stdout; die Umleitung ist der gewünschte Diagnosebericht.

```sh
php tests/documents/original-document-audit.php \
  --input=/secure/contact-80560-documents.tsv \
  --base=/path/to/dewawi \
  > /secure/contact-80560-original-audit.csv
```

Der Bericht unterscheidet `ok`, `database_filename_missing`, `both_files_missing`, `original_missing_attachment_recoverable`, `attachment_missing`, `attachment_file_missing`, `attachment_unregistered`, `attachment_metadata_ambiguous`, `copies_differ`, `invalid_or_unreadable_file`, `duplicate_contact_filename` und zusätzliche nicht zugeordnete PDFs im Kontaktordner. Er enthält Größe und SHA-256 beider Kopien. `empty`, `invalid_pdf`, `unreadable` und `symlink` bleiben als konkrete Dateizustände sichtbar. Abweichende Attachment-`location` oder gespeicherte `filesize` werden in `attachment_metadata` markiert.

Die Resultate, die der Betreiber zurückgeben muss, sind: vollständige CSV, exakter Mandant, geprüfter Snapshot-/Backup-Zeitpunkt, Basisverzeichnis und eine Liste vorhandener Sicherungen seit spätestens April 2026. Dateiinhalte oder Kundendaten außerhalb dieses Kontakts sind nicht erforderlich. Keine gefundene Datei darf verschoben, überschrieben oder gelöscht werden. Wiederherstellungskandidaten werden zunächst in ein isoliertes Verzeichnis kopiert und per SHA-256/PDF-Struktur sowie fachlichem Sichtvergleich bestätigt.

Scheitert nach erfolgreicher Dateipublikation ausnahmsweise der DB-Commit, bleibt die nicht zugeordnete Datei absichtlich bestehen. Ein erneuter Abschluss übernimmt einen bereits belegten Zielpfad nicht automatisch. Der Auditbericht und die Transaktions-/Fehlerprotokolle müssen dann Identität und weitere Behandlung klären; automatisches Löschen oder Überschreiben wäre in diesem Randfall riskanter.

## Begrenzter Exportvorschlag, nicht implementiert

Eine rückwärtskompatible Erweiterung sollte den bestehenden GoBD-ZIP-Inhalt unverändert lassen und ergänzend `documents/`, `einvoices/` und `manifest.json` aufnehmen. Das Manifest sollte pro Beleg Mandant, Belegart, interne ID, sichtbare Nummer, Datum, Originalname, relative Archivposition, Bytezahl, SHA-256, MIME-Typ und Status enthalten. Für jedes ausgewählte ausgestellte Dokument muss ein Eintrag existieren; fehlende oder ungültige Originale brechen den Export mit einer expliziten Fehlerliste ab und werden nie ausgelassen oder regeneriert. Die vorhandenen CSV-Zeilen bilden die maschinenlesbaren Daten.

Eine XRechnung darf nur aufgenommen werden, wenn eine tatsächlich gespeicherte unveränderliche XML-Fassung samt Profil/Version, ursprünglichen Exportparametern, Erzeugungszeitpunkt und SHA-256 existiert. Die aktuelle On-demand-XML erfüllt diese Voraussetzung nicht. Schema, fachlicher Freigabeprozess, Aufbewahrungszeitpunkt und Umgang mit bereits versendeten XML-Fassungen müssen vor einer Implementierung entschieden werden.

## Korrektur-Sonderfall, nicht implementiert

Für eine fertiggestellte, nachweislich noch nicht versendete Rechnung sollte keine direkte DB-Freischaltung existieren. Ein berechtigter Sonderworkflow benötigt Vier-Augen-Freigabe und Begründung, friert die bestehende PDF/XML-Fassung samt Hash als historische Version ein, erzeugt aus einer Kopie einen neuen Entwurf und dokumentiert die Beziehung. Ob die ursprüngliche Nummer storniert, als ungültig gekennzeichnet oder durch Korrekturbeleg berichtigt wird, ist eine fachlich-steuerliche Entscheidung. Bereits versendete oder exportierte Fassungen dürfen diesen Weg nicht nutzen.

## Grenzen und Wiederherstellung

Die strukturelle Prüfung `%PDF-…%%EOF`, Größe und SHA-256 beweist Übertragungsidentität zwischen vorhandenen Kopien, aber nicht allein die fachliche Richtigkeit oder langfristige Unveränderbarkeit gegen privilegierte Betriebssystemzugriffe. Dafür braucht es fachlich freizugebende, extern oder append-only verwahrte Hash-/Manifestdaten sowie Betriebs- und Aufbewahrungsregeln.

Der vorhandene Backup-/Restore-Test nimmt `files`, `media` und `templates` einschließlich Kontakt- und Attachment-Dateien auf, prüft Symlinks, Kollisionen und Byte-/Modusgleichheit und stellt die Datenbank isoliert wieder her. Er belegt das lokale Verfahren, nicht das Vorhandensein oder die Vollständigkeit konkreter produktiver Sicherungen. Für Kontakt 80560 sind deshalb Sicherungen vor und nach Mai 2026 als mögliche Quelle separat zu inventarisieren.
