# Migration in das Dokumentarchiv

## Kanonische Ablage

Ausgestellte Originale werden unter

`files/attachments/<module>/<controller>/<client-shard>/<document-id-shard>/<filename>`

abgelegt. Diese bereits vorhandene Struktur ist gegenüber den Kontaktordnern stabil, weil sie die interne Beleg-ID und den Belegmandanten verwendet. Sie ist schon durch Dokumentrechte, Mandantenprüfung und Löschschutz abgesichert und wird für E-Mail-Anhänge verwendet. Es wird deshalb kein weiteres Archivverzeichnis eingeführt.

Die acht Belegarten sind `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest` und `purchases/purchaseorder`. Die bisherigen Pfade unter `files/contacts` mit Kundennummer beziehungsweise interner Kontakt-ID bleiben unverändert als historische Quellen erhalten. Manuelle Kontaktdateien werden weder erfasst noch kopiert.

Neue Belege werden beim erstmaligen Abschluss direkt im kanonischen Verzeichnis atomar veröffentlicht. Größe und vollständige PDF-Struktur werden vor dem Commit geprüft; anschließend wird dieselbe Datei in `emailattachment` registriert. Bestehende Belege werden weiterhin zuerst aus dem kanonischen Archiv und ersatzweise aus beiden historischen Kontaktpfaden gelesen. Bei abweichenden Fassungen wird der Zugriff als Konflikt abgebrochen. Es findet keine automatische historische Kopie oder PDF-Neuerzeugung statt.

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
  --document-id=112143
```

Das Werkzeug akzeptiert nur gespeicherte, strukturell vollständige PDFs. Es meldet fehlende Dateien, leere oder beschädigte PDFs, Symlinks, Verzeichniskollisionen, abweichende historische beziehungsweise kanonische Fassungen, falsche Mandanten-/Pfadmetadaten, gelöschte oder mehrdeutige Metadatensätze und fehlende Archivkopien getrennt. Rechnung 112143 wird nur dann als `copy_and_register` geplant, wenn genau eine inhaltlich eindeutige gültige historische Fassung vorhanden ist. Es wird niemals ein PDF erzeugt.

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

Dokumentdownload, Kontaktdateien-Tab, E-Mail-Anhänge und Kundenhistorie verwenden weiterhin die vorhandenen Dokumentrechte. FileManager-Verzeichnisse werden nicht vereinigt. Datenbankreferenzen und Belegnummern bleiben unverändert. Der bestehende GoBD-Export exportiert weiterhin CSV-Daten und Dateinamen, aber keine PDFs; die Archivumstellung ändert deshalb seinen Inhalt nicht und schließt seine bereits dokumentierte PDF-/Manifest-Lücke nicht. Eine spätere GoBD-Paketerweiterung muss ausschließlich kanonische, tatsächlich gespeicherte Originale verwenden und fehlende Dateien als Fehler behandeln.

Die strukturelle PDF-Prüfung und SHA-256 belegen Dateiidentität, nicht die fachliche Richtigkeit des Inhalts oder Schutz gegen privilegierte Betriebssystemzugriffe. Produktive Dry-Runs und Ausführungen erfolgen ausschließlich durch den Betreiber; die lokale Entwicklung öffnet oder verändert keine Produktivdatenbank.
