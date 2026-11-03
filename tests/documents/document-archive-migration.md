# Migration in das Dokumentarchiv

## Kanonische Ablage

Ausgestellte Originale werden unter

`files/documents/<client-shard>/<module>/<controller>/<intdiv(document-id,1000)>/<document-id>.<format>`

abgelegt. Beispiel: `files/documents/1/0/100/sales/invoice/59/59296.pdf`. Der physische Name verwendet ausschließlich interne, stabile IDs; sichtbare Belegnummer und Downloadname stehen in `documentarchive`. Direkte HTTP-Zugriffe auf diesen Root sind gesperrt, Downloads laufen durch die bestehende Dokumentaktion.

Die acht Belegarten sind `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest` und `purchases/purchaseorder`. Die bisherigen Pfade unter `files/contacts` mit Kundennummer beziehungsweise interner Kontakt-ID und dokumentbezogene Kopien unter `files/attachments` bleiben unverändert als historische Quellen erhalten. Manuelle Kontaktdateien werden gemeldet, aber weder erfasst noch kopiert.

Neue Belege werden beim erstmaligen Abschluss direkt im kanonischen Verzeichnis exklusiv veröffentlicht. Größe, vollständige PDF-/XML-Struktur und SHA-256 werden geprüft; anschließend wird dieselbe Datei in `documentarchive` registriert. Reguläre Abrufe verwenden ausschließlich dieses Archiv. Historische Kontakt- und Attachment-Dateien sind nur Wiederherstellungskandidaten des Operators. Bei fehlenden Metadaten, fehlenden Dateien oder abweichenden Fassungen wird der Zugriff abgebrochen; es findet keine automatische Kopie oder Neugenerierung statt.

## Betreiberwerkzeug

`document-archive-migration.php` verbindet sich ausschließlich mit der explizit angegebenen Datenbankkonfiguration und arbeitet standardmäßig in einer read-only Transaktion. Der Bericht besteht aus JSON-Lines: eine Zeile pro Beleg und eine Zusammenfassung. Zugangsdaten und Dateiinhalte werden nicht ausgegeben.

Beispiel für die vollständige Bestandsaufnahme eines Mandanten:

```sh
php tests/documents/document-archive-migration.php \
  --config=/secure/read-only-database.ini \
  --base=/srv/dewawi \
  --client=100 \
  --contacts-client=100 \
  > /secure/document-archive-plan.jsonl
```

Für Kontakt 80560/Kundennummer 58101 und die fehlende Attachment-Kopie der Rechnung 112143:

```sh
php tests/documents/document-archive-migration.php \
  --config=/secure/read-only-database.ini \
  --base=/srv/dewawi \
  --client=100 \
  --contacts-client=100 \
  --contact=58101 \
  --type=sales/invoice \
  --document-number=112143
```

`--document-id` bezeichnet immer die interne Datenbank-ID (beispielsweise `59296`). Die sichtbare Rechnungsnummer wird mit `--document-number` gefiltert.
Der globale Scan nach nicht zugeordneten Dateien läuft nur ohne Beleg-, Kontakt- oder Typfilter; gefilterte Läufe prüfen ausschließlich die ausgewählten Belege und deren XML-Originale.

Das Werkzeug akzeptiert nur gespeicherte, strukturell vollständige PDFs beziehungsweise XML-Dateien. Es meldet fehlende Dateien, leere oder beschädigte Originale, Symlinks, Verzeichniskollisionen, abweichende historische beziehungsweise kanonische Fassungen, falsche Mandanten-/Pfadmetadaten, mehrdeutige Metadatensätze und nicht zugeordnete Dateien getrennt. Rechnung 112143 wird nur dann als `copy_and_register` geplant, wenn genau eine inhaltlich eindeutige gültige historische Fassung vorhanden ist. Es wird niemals ein Dokument erzeugt.

## Kontrollierte Ausführung

Vor einer Ausführung müssen der Dry-Run vollständig geprüft, eine konsistente Sicherung von Datenbank und Dateibestand verifiziert und ein Wartungsfenster freigegeben sein. Die explizite Ausführung benötigt alle vier Angaben `--apply`, `--confirm-client`, `--backup-verified` und `--operator-user` sowie eine schreibberechtigte, auf den Zielbestand begrenzte Konfiguration:

```sh
php tests/documents/document-archive-migration.php \
  --config=/secure/migration-database.ini \
  --base=/srv/dewawi \
  --client=100 \
  --contacts-client=100 \
  --apply \
  --confirm-client=100 \
  --backup-verified \
  --operator-user=42 \
  > /secure/document-archive-result.jsonl
```

Die Quelle wird exklusiv in eine noch nicht vorhandene Zieldatei kopiert, anschließend per Größe und SHA-256 geprüft und danach innerhalb einer Datenbanktransaktion registriert. Ein Wiederanlauf erkennt eine bereits verifizierte Datei und ergänzt höchstens noch fehlende Metadaten. Bei einem Metadatenfehler bleibt die geprüfte Zieldatei absichtlich erhalten und wird im Bericht als Fehler ausgewiesen. Es gibt keine Lösch- oder Bereinigungsoption; historische Quellen dürfen erst in einem getrennten, fachlich freigegebenen Vorgang nach erneutem Backup- und Hashnachweis behandelt werden.

Bei Parent-Child-Mandanten bezeichnet `--client` immer den Eigentümer der Belege und des kanonischen Archivs; `--contacts-client` bezeichnet den freigegebenen Kontaktmandanten. Damit wird die Kontakt-ID nur zur Altpfadprüfung verwendet und niemals als Archivmandant übernommen.

## Auswirkungen und Grenzen

Dokumentdownload, Kontaktdateien-Tab, E-Mail-Anhänge und Kundenhistorie verwenden weiterhin die vorhandenen Dokumentrechte. FileManager-Verzeichnisse werden nicht vereinigt. Versandkopien werden nachrichtenbezogen unter `files/attachments/emailmessage` abgelegt und sind keine Originale. Datenbankreferenzen und Belegnummern bleiben unverändert. Der bestehende GoBD-Export exportiert weiterhin CSV-Daten und Dateinamen, aber keine PDFs; die Archivumstellung ändert deshalb seinen Inhalt nicht. Eine spätere GoBD-Paketerweiterung kann PDF-/XML-Originale und SHA-256 aus `documentarchive` datensatzbezogen beziehen und muss fehlende Dateien als Fehler behandeln.

Die strukturelle PDF-Prüfung und SHA-256 belegen Dateiidentität, nicht die fachliche Richtigkeit des Inhalts oder Schutz gegen privilegierte Betriebssystemzugriffe. Produktive Dry-Runs und Ausführungen erfolgen ausschließlich durch den Betreiber; die lokale Entwicklung öffnet oder verändert keine Produktivdatenbank.
