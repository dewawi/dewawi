# Migration in das Dokumentarchiv

## Kanonische Ablage

Ausgestellte Originale werden unter

`files/documents/<client-shard>/<module>/<controller>/<intdiv(document-id,1000)>/<document-id>.<format>`

abgelegt. Beispiel: `files/documents/1/0/100/sales/invoice/59/59296.pdf`. Der physische Name verwendet ausschließlich interne, stabile IDs; sichtbare Belegnummer und Downloadname stehen in `documentarchive`. Direkte HTTP-Zugriffe auf diesen Root sind gesperrt, Downloads laufen durch die bestehende Dokumentaktion.

Die acht Belegarten sind `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest` und `purchases/purchaseorder`. Die bisherigen Pfade unter `files/contacts` mit Kundennummer beziehungsweise interner Kontakt-ID und dokumentbezogene Kopien unter `files/attachments` bleiben unverändert als historische Quellen erhalten. Manuelle Kontaktdateien werden gemeldet, aber weder erfasst noch kopiert.

Neue Belege werden beim erstmaligen Abschluss direkt im kanonischen Verzeichnis exklusiv veröffentlicht. Größe, vollständige PDF-/XML-Struktur und SHA-256 werden geprüft; anschließend wird dieselbe Datei in `documentarchive` registriert. Reguläre Abrufe verwenden ausschließlich dieses Archiv. Historische Kontakt- und Attachment-Dateien sind nur Wiederherstellungskandidaten des Operators. Bei fehlenden Metadaten, fehlenden Dateien oder abweichenden Fassungen wird der Zugriff abgebrochen; es findet keine automatische Kopie oder Neugenerierung statt.

## Betreiberwerkzeug

Für die reine Sammlung unterschiedlicher PDF-Fassungen ohne Registrierung oder Ersetzung steht zusätzlich der [geschützte manuelle Prüfbereich](document-review.md) zur Verfügung.

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

### Datenbankkonfiguration

Die explizite Konfiguration darf flache `resources.db.params.*`-Schlüssel oder einen vollständigen Abschnitt `[production]` enthalten. Ein anderer vollständiger Abschnitt wird mit `--environment=audit` gewählt; Zend-Abschnittsvererbung wird vom Betreiberwerkzeug nicht aufgelöst. Host, Benutzername, Passwort und Datenbankname müssen skalare Werte sein. Der optionale Port wird als dezimale Zahl zwischen 1 und 65535 geprüft; ohne Angabe gilt 3306.

Die Installation verwendet einfach zitierte Werte, die Beispieldatei unzitierte Werte. Der RAW-Scanner entfernt einfache Anführungszeichen nicht und würde etwa `'localhost'` als Host einschließlich Apostrophen übergeben. Das Werkzeug normalisiert deshalb vollständige einfach zitierte Literale vor dem RAW-Einlesen. Ein zusätzlicher NORMAL-Durchlauf prüft ausschließlich die INI-Syntax; seine interpretierten Werte werden verworfen. So bleiben unter anderem `off`, `on`, `null`, Konstantennamen, `${...}`, führende Nullen und große Ziffernfolgen unverändert. Ein pauschaler Wechsel auf NORMAL oder TYPED würde diese Eigenschaft verlieren.

Zugangsdaten als einzelne Literale angeben, bei Leerzeichen oder Semikolon zitieren und bei einem enthaltenen Apostroph doppelte Anführungszeichen verwenden. Backslashes bleiben gemäß bisheriger RAW-Semantik wörtlich erhalten; keine Escape-, Interpolations-, Verkettungs- oder Mehrzeilenausdrücke verwenden. Parserwarnungen werden unterdrückt und durch allgemeine Fehlermeldungen ersetzt, damit fehlerhafte INI-Zeilen keine Zugangsdaten ausgeben. Die lokale Regression `php tests/documents/document-archive-migration-config.php` verwendet ausschließlich künstliche Zugangsdaten und öffnet keine Datenbank.

### Vollständigen Dry-Run auswerten

Der Betreiber führt den Lauf auf einem konsistenten Snapshot von Datenbank und Dateien aus, mit einem ausschließlich leseberechtigten Konto und einem verifizierten Basisverzeichnis. Mandanten-IDs und freigegebene Parent-Child-Kontaktzuordnung vorher prüfen. Die folgenden Werte sind Beispiele; die Entwicklungsumgebung führt diesen Aufruf nicht gegen Produktion aus:

```sh
umask 077
php tests/documents/document-archive-migration.php \
  --config=/secure/read-only-database.ini \
  --base=/srv/dewawi \
  --client=100 \
  --contacts-client=100 \
  > /secure/document-archive-full.jsonl \
  2> /secure/document-archive-full.stderr
audit_exit=$?
printf 'Dry-run exit: %s\n' "$audit_exit"
```

Keine Filter `--type`, `--contact`, `--document-id` oder `--document-number` setzen. Nur dann werden alle acht Belegarten und die nicht zugeordneten Dateien in den vorgesehenen Mandantenverzeichnissen untersucht. Der Kontakt-Dateiscan verwendet weiterhin den Belegmandanten im Pfad; `--contacts-client` steuert den Kontakt-Join, keine zusätzliche Verzeichnisinventur eines Parent-Mandanten. Bei geteilten Kontakten sind dessen manuelle Ablagen gegebenenfalls separat read-only zu inventarisieren.

Exitcode 1 kann einen vollständig abgeschlossenen Lauf mit gesperrten Belegen oder XML-Originalen bedeuten; ohne abschließende Zusammenfassung ist der Lauf abgebrochen. Exitcode 2 meldet ungültige CLI-Argumente. Vor jeder fachlichen Auswertung stderr und die letzte JSONL-Zeile prüfen. Beispiel mit vorhandenem `jq`:

```sh
jq -s -e 'length > 0 and .[-1].kind == "summary" and .[-1].summary.mode == "dry-run" and .[-1].summary.unassigned_scan == "complete"' /secure/document-archive-full.jsonl
jq -c 'select(.kind == "summary") | .summary' /secure/document-archive-full.jsonl
jq -s '[.[] | select(.kind == "document") | .plan] | group_by([.status, .reason]) | map({status: .[0].status, reason: .[0].reason, count: length})' /secure/document-archive-full.jsonl
jq -c 'select(.kind == "document") | .plan | select(.reason == "original_missing" or .reason == "historical_versions_differ" or .reason == "contact_mapping_ambiguous")' /secure/document-archive-full.jsonl
jq -c 'select(.kind == "structured_original" or .kind == "unassigned_file")' /secure/document-archive-full.jsonl
```

`documents = complete + ready + blocked` muss für die PDF-Pläne gelten, `applied = failed = 0` für den Dry-Run. `structured_originals`/`structured_blocked` und `unassigned` separat beurteilen. `unassigned_scan = complete` bestätigt nur den ausgeführten Scanpfad; fehlende oder falsch gemountete Verzeichnisse werden übersprungen. Auch Exitcode 0 ist daher kein Vollständigkeitsnachweis.

Die Belegauswahl umfasst alle nicht gelöschten Datensätze mit mindestens einem Ausstellungsmerkmal (Nummer nicht NULL, `completed`, `cancelled` oder Zustand 105/106), inzwischen ausdrücklich auch ohne `filename`. `missing_filenames` zählt diese Fälle. Vollständige Läufe inventarisieren zusätzlich `excluded_document`: logisch gelöschte ausgestellte Belege (`soft_deleted_issued`) und Dateien an Datensätzen ohne Ausstellungsmerkmal (`file_without_issue_marker`). Diese werden nicht automatisch zur Wiederherstellung ausgewählt. `unassigned_file` bleibt eine separate Dateiinventur ohne automatische Zuordnung. XML wird nur für vorhandene XML-Archivmetadaten geprüft; nicht jedes PDF benötigt eine XML-Fassung.

Für die kontrollierte Behandlung aller blockierten Belegarten steht der [vollständige Wiederherstellungsablauf](document-recovery.md) zur Verfügung. Eine verifizierte, manuell freigegebene kanonische Version 1 gilt im Audit als vollständig, selbst wenn historische Kopien abweichen; die Abweichungen bleiben separat unter `historical_conflicts` dokumentiert. Ohne passende Freigabe werden Hashkonflikte weiterhin gesperrt. Das alte `--apply` ist kein Freigabeersatz und überschreibt keine kanonischen Dateien.

| Grund | Vorhandene JSONL-Nachweise | Erforderliche Zusatzprüfung |
| --- | --- | --- |
| `original_missing` | Belegmandant, Art, interne ID, sichtbare Nummer, Dateiname; erwartete Pfade und Dateistatus, Metadatenanzahlen | Alle vorgesehenen Dateien fehlen. Sicherungen, Versandkopien und zusätzliche Speicherorte mit Belegzuordnung prüfen; keine Neugenerierung. |
| `historical_versions_differ` | Alle vier Dateiprüfungen mit Pfad, Status, Größe und SHA-256 | Mindestens zwei gültige historische Quellen haben verschiedene Hashwerte. PDF-Inhalt, Ausgabekontext, Versandkopien und Sicherungszeitpunkte vergleichen. Hashabweichung allein belegt keine fachliche Rechnungsänderung. Archivmetadaten bleiben wegen des frühen Abbruchs im Plan leer. |
| `contact_mapping_ambiguous` | Belegmandant, Art, interne ID, sichtbare Nummer und Dateiname | Früher Abbruch vor Pfad-/Hashprüfung; Kontaktzahl und Kontakt-IDs fehlen im Bericht. Aktive Kontaktzuordnungen im ausdrücklich freigegebenen Kontaktmandanten read-only nachladen. |

Die Berichte reichen zur technischen Gruppierung und für den Hashvergleich unterschiedlicher Versionen. Für eine abschließende fachliche Klassifizierung fehlen insbesondere Kontakt-IDs, Belegdatum/-zustand, Versandbelege und bei frühen Abbrüchen Archivmetadaten. `legacy_attachment_metadata` enthält nur Anzahlen und aktive IDs, keine vollständigen historischen Datensätze.

Beispiel für ergänzende Betreiberabfragen auf demselben read-only Snapshot, hier für Rechnungen mit Belegmandant und Kontaktmandant 100. Interne IDs aus dem JSONL-Bericht einsetzen; die fehlenden Dateinamen entsprechend auch für `quote`, `salesorder`, `deliveryorder`, `creditnote`, `reminder`, `quoterequest` und `purchaseorder` mit ihrem jeweiligen Nummernfeld prüfen:

```sql
START TRANSACTION READ ONLY;
SELECT id, invoiceid, contactid, invoicedate, state, completed, cancelled, filename,
       completeddate, completedby, cancelleddate, cancelledby
FROM invoice
WHERE clientid = 100 AND deleted = 0 AND id IN (59296);

SELECT c.id, c.contactid, c.clientid
FROM contact c
WHERE c.clientid = 100 AND c.deleted = 0
  AND c.contactid IN (SELECT contactid FROM invoice WHERE clientid = 100 AND deleted = 0 AND id IN (59296))
ORDER BY c.contactid, c.id;

SELECT id, invoiceid, contactid, state, completed, cancelled, filename
FROM invoice
WHERE clientid = 100 AND deleted = 0 AND (filename IS NULL OR filename = '')
  AND (invoiceid IS NOT NULL OR completed <> 0 OR cancelled <> 0 OR state IN (105, 106));

SELECT id, documentid, format, version, correctionarchiveid, filename, path, filesize, sha256, archived, archivedby
FROM documentarchive
WHERE clientid = 100 AND module = 'sales' AND controller = 'invoice' AND documentid IN (59296)
ORDER BY documentid, format, version;

SELECT m.id, m.documentid, m.messagesent, m.messagesentby, m.deliverystatus,
       m.providermessageid, m.deliverydate, a.documentarchiveid, a.filename, a.path, a.filesize, a.sha256
FROM emailmessage m LEFT JOIN emailmessageattachment a
  ON a.emailmessageid = m.id AND a.clientid = m.clientid
WHERE m.clientid = 100 AND m.module = 'sales' AND m.controller = 'invoice'
  AND m.documentid IN (59296)
ORDER BY m.id, a.ordering;
ROLLBACK;
```

Gelöschte Versandhistorie wird in dieser Nachweisabfrage bewusst nicht ausgeblendet. Ein fehlender Versanddatensatz beweist dennoch nicht, dass der Beleg nie extern ausgegeben wurde. Die Ergebnisse sowie Snapshot-/Backup-Zeitpunkt, Codeversion, Basisverzeichnis, Mandantenfreigabe und Prozess-Exitcode dem geschützten Auditpaket beilegen. Im lokalen Workspace liegen keine produktiven JSONL-Berichte vor; aus den Fixturetests werden keine Bestandszahlen abgeleitet.

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

## Prüfung der Archivierungslogik am 11.11.2026

Die folgende Bestandsanalyse beschreibt den Ausgangszustand vor dem ergänzten Wiederherstellungsablauf. Für ausdrücklich freigegebene kanonische Fassungen gilt inzwischen die in [document-recovery.md](document-recovery.md) beschriebene Ausnahme: Sie bleiben trotz intern dokumentierter historischer Konflikte vollständig. Ein fehlender alter Belegdateiname kann im Download durch den verifizierten Archivdateinamen ersetzt werden. Ungeprüfte Konflikte bleiben gesperrt; eine produktive Migration wurde nicht ausgeführt.

1. `DEEC_DocumentArchive::plan()` prüft zunächst die Kontaktmehrdeutigkeit, anschließend die kanonische Datei und drei historische Kandidaten (`attachment`, Kundennummer `current`, interne Kontakt-ID `legacy`). Nur strukturell als gültig erkannte historische Dateien fließen in den Hashvergleich ein. Eine vorhandene ungültige historische Quelle sperrt vorher mit `invalid_historical_source`; mehr als ein verschiedener SHA-256 unter den gültigen Quellen sperrt mit `historical_versions_differ`, auch wenn bereits eine kanonische Datei existiert. Eine alleinige Abweichung zwischen kanonischer Datei und inhaltlich einheitlichen historischen Quellen heißt `canonical_version_differs`. Identische Kopien oder identische aktuelle/historische Kontaktpfade sind kein Versionskonflikt. Die PDF-Strukturprüfung prüft Header und EOF-Marker, keine vollständige semantische PDF-Validität.
2. `historical_versions_differ` setzt ausschließlich `status=blocked`, `action=none` und den technischen Grund im Plan. Das CLI zählt und berichtet ihn; nur `ready` kann im expliziten Apply-Zweig verarbeitet werden. Es gibt keinen Aufruf zur Änderung von Rechnungsdaten, Nummer, Zustand oder steuerlicher Kennzeichnung. Im Dry-Run werden weder Kopie noch Registrierung aufgerufen; die DB-Transaktion ist read-only und wird zurückgerollt. Vorhandene Dateien werden nicht ersetzt, PDFs nicht erzeugt. Der Dateikopierer veröffentlicht exklusiv und prüft Größe/Hash; seine Fehlerbereinigung betrifft nur die gerade selbst angelegte neue Zieldatei.
3. `Application_Model_DbTable_Documentarchive` verwendet im regulären Originalabruf Version 1 pro Mandant/Modul/Belegart/Beleg/Format. Dateiname (PDF), kanonischer Pfad, Größe und SHA-256 werden geprüft. Eine passende Registrierung wird wiederverwendet, abweichende Daten werden abgelehnt; neue Originale erhalten Version 1 und `correctionarchiveid=NULL`. Das Feld hat einen Index, aber keinen implementierten Korrektur-/Versionsworkflow. `getArchive(id)` kann einen vorhandenen Datensatz ausdrücklich nach ID lesen, prüft dabei Elternbelegrechte und Eigentümer. Die Migration erzeugt keine zusätzlichen Versionen: mehrere gefundene Metadatensätze sperren als mehrdeutig. Für PDFs prüft ihr Plan das Feld `version` derzeit nicht explizit; die XML-Nachprüfung verlangt Version 1. Nichtstandardversionen müssen deshalb in der ergänzenden Metadatenabfrage bewertet werden.
4. `DEEC_Permission::isDocumentReadonly()` sperrt Rechnungsinhalte bei vorhandener sichtbarer `invoiceid`, gesetztem `completed`, gesetztem `cancelled` oder Zustand 105/106. `InvoiceController::beforeEdit`, State-Plugin, Dokumentcontroller und Positionsschutz verwenden diese Regel. Ein fehlendes Archiv hebt sie nicht auf. Interne `notes` bleiben mit Edit-Recht und gültiger Benutzersperre separat änderbar. `locked`/`lockedtime` sind zeitweilige Bearbeitungssperren, kein eigenständiger steuerlicher Festschreibungsnachweis; `completeddate/by` und `cancelleddate/by` dokumentieren bestehende Zustandswechsel.
5. E-Mail-Nachweise bestehen aus `emailmessage` mit Belegbezug, `messagesent/by`, SMTP-Annahme (`accepted`) beziehungsweise Fehler und gegebenenfalls Provider-/Zustellstatus. `emailmessageattachment` speichert nachrichtenbezogene Dateikopien mit Archiv-/Attachmentreferenz, Größe, SHA-256 und Erstellungsdaten. Eine Kopie allein beweist keinen erfolgreichen Versand, SMTP-Annahme allein keine Zustellung. PDF-/XML-Response und Archivzeitpunkt belegen keine tatsächliche Entgegennahme oder Ausdruck. Im geprüften Dokumentfluss gibt es kein vollständiges belegbezogenes Download-, Druck-, externes Ausgabe- oder steuerliches Festschreibungsjournal. `downloadtracking` bezieht sich auf Downloadsets/Downloads/Artikel; `usertracking` auf Anmeldung/Weiterleitungsziel, nicht auf jede Rechnungsausgabe. Externe Ausgabe lässt sich deshalb nicht allein aus fehlenden E-Mail-Zeilen ausschließen.
6. Der GoBD-Export filtert tabellarische Daten nach Mandant und bei Belegen/Positionen nach Belegdatum, nicht nach Nummer oder Abschlussstatus. Entwürfe mit Datum im gewählten Zeitraum können daher neben fertigen und stornierten Belegen enthalten sein; `state`, `completed`, `cancelled` und `filename` sind Kennzeichen in CSV, keine getrennten Pakete. Auch logisch gelöschte Daten werden nicht pauschal ausgeschlossen. Das Profil umfasst Angebot, Auftrag, Rechnung und Gutschrift samt Positionen; keine der anderen vier Archivbelegarten. PDF-/XML-Originale, `documentarchive`, Manifest und Prüfsummen fehlen. Damit besteht keine vollständige Unterscheidung/Integritätssicherung von Originaldateien im Export. Dies wurde ausschließlich analysiert; die Exportlogik bleibt unverändert. Der aktuelle InvoiceController archiviert dagegen die erste heruntergeladene XRechnung-XML und verwendet sie bei späteren Downloads wieder; ältere Audittexte zur ausschließlich flüchtigen XML-Erzeugung beschreiben einen früheren Stand.

Nächster Schritt ist der vollständige Bestandsaudit samt zusätzlicher Prüfung fehlender Dateinamen und manueller Klassifizierung aller unterschiedlichen PDF-Versionen anhand Inhalt, Hash, Versandkopien und Sicherungen. Kein Konflikt darf allein anhand Zeitstempel, Pfadpriorität oder aktueller Belegdaten automatisch aufgelöst werden. Eine Pilot- oder Hauptmigration ist ein gesonderter Auftrag; diese Vorbereitung verändert keine Archivoriginale, Fachlogik oder SQL-Schemata.
