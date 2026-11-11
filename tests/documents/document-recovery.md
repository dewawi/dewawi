# Vollständiger historischer Dokumentbestand: Prüfung, Rekonstruktion und Freigabe

Der Ablauf gilt für alle Mandanten und die acht Arten `sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest` und `purchases/purchaseorder`. Er verändert weder Nummern, Belegdatum, Positionen, Beträge noch Status. Es gibt keine automatische Freigabe, keine pauschale Neuerzeugung und keine Bereinigung bestehender Originale.

Die folgenden Befehle sind Betreiberanweisungen, keine bereits ausgeführten Produktivaktionen. Mandant 100 und Benutzer 42 sind Beispiele. Ein konsistenter Snapshot ist für die Bestandsprüfung vorzuziehen. Das Werkzeug wird unter der OS-Identität ausgeführt, mit der DEWAWI seine Dateien lesen kann; kanonische neue Dateien werden mit 0600 erzeugt. Der Prüfbereich bleibt außerhalb des Webroots und darf auch nicht durch einen Webserver-Alias veröffentlicht werden. Seine Verzeichnisse benötigen 0700, seine Dateien 0600, das Dateisystem muss Hardlinks unterstützen.

## 1. Aktuellen Gesamtbestand erfassen

Die gemeldeten 43.325 vollständigen und 1.418 blockierten Dokumente (1.041 fehlende Originale, 367 Versionskonflikte, 4 Kontaktmehrdeutigkeiten, 6 ungültige Kontexte) sind Betreiberangaben. Sie wurden nicht gegen eine Produktionsdatenbank geprüft und sind keine Auswahlgrenzen. Ein neuer Dry-Run erfasst auch ausgestellte Dokumente ohne Dateiname und weist andere Ausschlüsse separat aus:

```sh
umask 077
php tests/documents/document-archive-migration.php \
  --config=/var/www/migration-safety/read-only-database.ini \
  --base=/var/www/dewawi --client=100 --contacts-client=100 \
  > /var/www/migration-safety/reports/client-100-full-dry-run.jsonl \
  2> /var/www/migration-safety/reports/client-100-full-dry-run.stderr
```

Keine Typ-, Kontakt-, Nummern- oder Datumsfilter verwenden. Exitcode 1 kann abgeschlossene Konfliktberichte bedeuten; entscheidend sind stderr und die abschließende `summary`. `missing_filenames`, `excluded_documents`, `excluded_document`, `structured_original` und `unassigned_file` zusätzlich auswerten. Gelöschte ausgestellte Belege und Dateien ohne Ausstellungsmerkmal werden gesondert inventarisiert und nicht stillschweigend wieder aktiviert. Nicht zugeordnete Dateien werden nicht anhand ähnlicher Namen importiert. Eine sichere bekannte Zuordnung über interne Beleg-ID, eindeutigen Kontakt oder verifizierte Attachment-/Nachrichtenreferenz ist erforderlich; andernfalls manuelle Nachweise und `external/` verwenden.

## 2. Alle blockierten Belege sammeln

```sh
php tests/documents/document-review.php \
  --config=/var/www/migration-safety/read-only-database.ini \
  --base=/var/www/dewawi --client=100 --contacts-client=100 \
  --jsonl=/var/www/migration-safety/reports/client-100-full-dry-run.jsonl \
  --select=all-unarchived \
  --output=/var/www/migration-safety/document-review/client-100
```

`all-blocked` ist der neue Standard und benötigt keine TSV; es umfasst exakt die blockierten Fälle aller acht Arten. Für die vollständige Archivierung empfiehlt sich der oben gezeigte Modus `all-unarchived`: Er nimmt zusätzlich `ready`-Fälle auf, bei denen eine eindeutige historische PDF vorhanden, aber noch keine vollständige kanonische Registrierung vorhanden ist. Bereits vollständige Belege bleiben ausgeschlossen. Die bisherigen Modi `audit`, `blocked-invoices` und `both` bleiben verfügbar; die vierteilige TSV ohne Kopfzeile ist unverändert nutzbar und nicht mehr auf 2019–2021 begrenzt. Alle sicheren unterschiedlichen PDF-Fassungen werden nach SHA-256 gesammelt, alle Quellpfade bleiben im Manifest. Auch leere Fälle bekommen ein Prüfverzeichnis. Kontaktmehrdeutigkeiten erlauben keine willkürliche Zuordnung von Kontaktdateien; eindeutig belegbezogene Quellen bleiben nutzbar. Jüngste historische Fassungen können bei allen Arten vorgeschlagen werden, auch bei Rechnungen. Ein Vorschlag ist keine Originalauswahl.

Der Sammler meldet den Pfad zu `runs/<lauf-id>/run.json`. Dieser konkrete, vollständig abgeschlossene Lauf ist die Eingabe der nächsten Schritte. Alte Manifeste, externe Dateien und manuelle Auswahlen bleiben erhalten.

## 3. Fehlende PDF-Kandidaten rekonstruieren und vergleichen

Für reine Vorbereitung genügt eine SELECT-Konfiguration. Der angegebene aktive DEWAWI-Benutzer muss den Mandanten nach den bestehenden Wechselregeln erreichen können sowie View- und Edit-Rechte auf die konkrete Belegart haben. Ein Administratorstatus ersetzt keine Dokumentrechte. Parent-Child-Kontakte werden aus der tatsächlichen Mandantenbeziehung abgeleitet und müssen zum Prüfbereich passen. Die Prüfung benutzt die bestehenden ERP-Berechtigungen; das Repository enthält keine zusätzliche ERP-Abonnement-/Feature-Entitlement-Schnittstelle. Die eigenständigen Shop-Features werden weder verwendet noch freigeschaltet; `shops/*` ist kein zulässiger Wiederherstellungstyp.

Für die folgenden Beispiele einmal die tatsächlichen Pfade und Benutzer-ID setzen:

```sh
review_root=/var/www/migration-safety/document-review/client-100
review_run="$review_root/runs/REPLACE_WITH_COMPLETED_RUN/run.json"
read_config=/var/www/migration-safety/read-only-database.ini
write_config=/var/www/migration-safety/archive-registration-database.ini
reviewer_id=42
```

```sh
php tests/documents/document-recovery.php \
  --config="$read_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=prepare

php tests/documents/document-recovery.php \
  --config="$read_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=inspect
```

`--only=sales/invoice/59296` begrenzt jede Aktion auf einen bekannten internen Belegschlüssel. Ohne diesen Filter wird der gesamte gewählte Lauf verarbeitet. Die Vorbereitung verwendet getrennte PHP-Prozesse, weil die alten Sales-/Purchases-Positionshelfer denselben PHP-Klassennamen besitzen. Rendererfehler erhalten private Protokolle; ein problematischer Beleg blockiert die anderen nicht. Es wird weder eine Nummer vergeben noch eine Fertigstellung aufgerufen. Der normale PDF-Schutz wird nicht abgeschaltet.

Die Rekonstruktion prüft gespeicherte Nummer, Datum, Währung, Positionen und Vorlage. Die vorhandene reine Berechnung prüft den Kopf anhand gespeicherter Einzelpreise, Mengen, Steuersätze und anwendbarer gespeicherter Positionspreisregeln. Es werden keine heutigen Artikel-/Kontaktpreisregeln ergänzt. Ungültige Regeln, fehlende Hauptpositionen, widersprüchliche Vererbung und nicht reproduzierbare Kopfwerte sperren die Erzeugung. Fehlende oder mehrdeutige Kontakte und fehlende Vorlagen führen zu einem kontrollierten Fehler. Die gespeicherte Vorlagen-ID wird ausdrücklich verwendet; ein stiller Wechsel auf die heutige Standardvorlage ist ausgeschlossen.

`position.total` ist kein verlässlicher historischer Nachweis für die dargestellte Positionssumme: `Calculate::direct()` speichert heute gerundetes Brutto; der Vorgänger vor `f68824e0` speicherte ungerundetes Brutto, während frühe Berechnungspfade (`a665fea6`) nur den Kopf aktualisierten. Die PDF-Positionshelfer berechneten schon in `d4794d6e` Netto aus Preis und Menge. Auch heute setzen Sales und Purchases `position.total` ausschließlich im Speicher auf die berechnete formatierte Nettosumme für den Renderer. Ein gespeicherter Wert kann numerisch Netto entsprechen oder veraltet sein; seine konkrete Herkunft lässt sich daraus allein nicht beweisen. Netto wird deshalb ausdrücklich mit Netto und Brutto mit Brutto verglichen.

Bei konsistentem Kopf dürfen abweichende alte Positions-Zwischenwerte einen **Prüfkandidaten** erhalten. `validation.positions` dokumentiert je Position gespeicherte Preise/Mengen/Steuersätze/Total, berechnetes Netto und Brutto, beide Differenzen, angewendete Regeln und Klassifizierung. `matches_net` ist keine Bestätigung eines Bruttowerts. `historical_position_total_conflict` ist ausdrücklich kein Rundungsfall, auch wenn die Abweichung klein ist. `historical_rounding` verlangt eine reproduzierbare Variante (etwa gerundeter Einzelpreis oder alter ungerundeter Nettobetrag/Bruttobetrag) und höchstens einschließlich 0,10 Differenz zur passenden Basis. Größere Unterschiede werden niemals als Rundung bezeichnet.

Kopfabweichungen werden nur bis einschließlich 0,10 und bei einer nachvollziehbaren Berechnungsvariante zugelassen: heutige Berechnung, alte ungerundete Positions-/Steuersummen oder einzeln gerundete Positionssteuern. Eine beliebige Differenz von 0,01 reicht nicht. Netto plus Steuer muss den gespeicherten Gesamtbetrag reproduzieren. Der PDF-Kopf bleibt der gespeicherte Kopf, auf zwei Stellen formatiert. Vor dem Rendern werden alle tatsächlich übergebenen Kopfwerte, Positionspreise, Mengen, Nettosummen und die Hierarchie gegen die freigegebene Berechnungsgrundlage geprüft; Abweichungen sperren. `validation.displayed` hält diese Ausgabewerte fest. Nummern, Status, Positionswerte und Preisregeln werden niemals gespeichert oder korrigiert.

Die vorhandenen Payload-Dienste und TCPDF rendern ausschließlich in den privaten Prüfbereich. Jeder Kandidat erhält SHA-256, Größe, Strukturprüfung sowie `reconstructions/<sha256>.json` mit tatsächlichem Erzeugungszeitpunkt/-benutzer, Datenfingerprint und Abhängigkeiten. Ein vorhandener, zum selben Datenstand gehörender Rekonstruktionskandidat wird wiederverwendet. Jede vorhandene kanonische Datei oder PDF-Archivregistrierung verhindert eine erneute Erzeugung.

Gespeicherte Vorlagen können seit Ausstellung verändert worden sein. Bilder, Artikel-/Optionsdaten, Fonts und Übersetzungen entsprechen dem heute verfügbaren Bestand; ihre historische Gleichheit ist nicht garantiert. Vorlageninhalt, Payload-/Renderer-Prüfsummen und diese Einschränkung werden protokolliert. Die Freigabe erfordert deshalb Sichtvergleich mit vorhandenen Fassungen, DATEV-/E-Mail-Nachweisen und erforderlichenfalls historischen Papierbelegen. Nicht ausreichend rekonstruierbare Fälle bleiben offen. PDF-Header/EOF und SHA-256 allein beweisen keine inhaltliche Originalgleichheit.

`inspect` erzeugt neben JSON eine geschützte HTML-Datei unter `runs/` mit PDF-Links und einer Gegenüberstellung der ersten zwei Kandidaten. Weitere Fassungen können über die Links verglichen werden. Die Datei lokal mit demselben berechtigten OS-Zugriff öffnen; keinen öffentlichen Prüf-Webserver einrichten. Das HTML enthält keine Auswahl- oder Freigabeautomatik.

## 4. Auswahl dokumentieren und ausdrücklich freigeben

Die vorhandene `selection.json` bleibt das Auswahlformat. Pro Beleg sind Identität, Kandidatenpfad, SHA-256, Herkunft und eine konkrete Begründung erforderlich. `origin.kind` unterstützt `historical_pdf`, `datev`, `email` und `reconstructed`. Rekonstruktionen benötigen den passenden erzeugten Nachweis. DATEV-/Maildateien mit 0600 unverändert in `external/` ablegen; Symlinks werden abgewiesen.

Für größere Mengen eine JSONL-Datei mit einer **bewusst geprüften Entscheidung pro Zeile** erstellen, beispielsweise:

```json
{"document":{"module":"sales","controller":"invoice","id":59296,"clientid":100,"document_number":"112143"},"decision":"selected","candidate_path":"external/datev-original.pdf","sha256":"REPLACE_WITH_64_HEX_CHARACTERS","origin":{"kind":"datev","source_path":"DATEV export reference","evidence":"export date and paper comparison"},"reason":"Specific reason for choosing these exact bytes"}
```

Für einen rekonstruierten Kandidaten `candidate_path=candidates/<sha256>.pdf`, `origin.kind=reconstructed` und einen Bezug zum Datenbank-/Papiernachweis angeben. Die reale Prüfsumme verwenden; keine Vorschläge ungeprüft in Entscheidungen umwandeln. Die Entscheidungsdatei mit 0600 schützen. Der Sammelbefehl ersetzt die Auswahlpointer nur ausdrücklich und journalisiert die bisherigen Auswahlen unter `selections/`:

```sh
php tests/documents/document-recovery.php \
  --config="$read_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=select \
  --decisions=/var/www/migration-safety/reports/client-100-reviewed-decisions.jsonl

php tests/documents/document-recovery.php \
  --config="$read_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=approve
```

`select` trägt den tatsächlichen handelnden Benutzer und Prüfzeitpunkt ein. Alternativ dürfen vollständige manuelle `selection.json`-Dateien genutzt werden; `reviewed_by` muss dann dem Freigebenden entsprechen. `approve` erteilt nur bei expliziter Auswahl eine Freigabe und schreibt ein unveränderliches `approvals/<selection-sha256>.json`. Rechnungen und steuerlich relevante Konflikte erhalten niemals eine automatische Freigabe. Vorschläge und Zeitstempel ersetzen weder Entscheidung noch Begründung.

Die Freigabe bindet genau diese PDF, Auswahlbytes und den Datenstand des Kopfes, aller Positionen, Positionsgruppen und Preisregeln. Nach Änderung von Auswahl oder Belegdaten ist eine neue bewusste Auswahl/Freigabe erforderlich. Auch geänderte interne Notizen können den konservativen Datenfingerprint verändern. Eine Freigabe allein verändert keine Archiv- oder Geschäftsdaten.

Bei Rekonstruktionswarnungen ist zusätzlich `original_comparison` in der Entscheidung beziehungsweise `selection.json` zwingend. Ein allgemeiner Auswahlgrund oder ein Vergleich nur mit heutigen DB-Werten genügt nicht. Beispiel für einen **tatsächlich durchgeführten** Papiervergleich (Referenz, Benutzer und Zeitpunkt ersetzen):

```json
"original_comparison": {
  "kind": "paper_document",
  "reference": "Paper archive, binder and page, original document number",
  "compared_by": 42,
  "compared_at": "2026-11-11T14:00:00+01:00",
  "amounts_match": true
}
```

`compared_by` muss der handelnde Prüfer sein. `amounts_match=true` bestätigt ausdrücklich, dass die dargestellten Beträge mit dem ursprünglichen Nachweis verglichen wurden und übereinstimmen; bei Abweichung darf nicht freigegeben werden. Für `kind=original_document` oder `kind=verified_dispatch_copy` zusätzlich `evidence_path=external/<datei>.pdf` und `evidence_sha256=<echter-sha256>` angeben. Die unveränderte Vergleichsdatei muss privat mit 0600 vorliegen und darf nicht die Rekonstruktion selbst sein. Die fachliche Prüfung der Original-/Versandherkunft bleibt beim Prüfer; das Werkzeug prüft Referenz, Prüfer, Zeitpunkt und Dateiintegrität. Ein Papierbeleg wird über seine konkrete physische Fundstelle und die ausdrückliche Bestätigung nachgewiesen.

`approve` und `import` prüfen diese Angaben unabhängig. Die Freigabe bindet zusätzlich den vollständigen Rekonstruktionsnachweis per SHA-256; eine nachträgliche Änderung von Warnungen oder Vergleichsdateien sperrt den Import. Alte Rekonstruktionsnachweise ohne die neue Betragsprüfung dürfen nicht freigegeben/importiert werden: erneut `prepare`, neue Fassung prüfen, neu auswählen und freigeben. Alte Kandidaten und Nachweise bleiben erhalten. Aktuelle passende Vorbereitungen werden einschließlich ihrer Warnungen wiederverwendet. `inspect` zeigt die Warnungen samt Betragsgrundlage auch im privaten HTML-Vergleich an.

## 5. Ausschließlich freigegebene Bytes registrieren

Die Zielkonfiguration braucht SELECT auf den benötigten Tabellen, INSERT in `documentarchive` und gezielt UPDATE nur auf dessen Spalte `parameters` (etwa `GRANT UPDATE (parameters) ON <datenbank>.documentarchive ...`). UPDATE auf andere Archivspalten oder auf Beleg-/Positionstabellen und DELETE sind nicht erforderlich. Derselbe aktuell berechtigte Freigebende führt die Übernahme aus. Die bestehende Migration darf nicht als Ersatz für diese Freigabe benutzt werden.

```sh
php tests/documents/document-recovery.php \
  --config="$write_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=import \
  --confirm-client=100 --backup-verified
```

Pro Beleg wird separat transaktional gearbeitet. Vor der Übernahme werden Identität, Rechte, Freigabe, Datenfingerprint und Quelldatei erneut geprüft; Kopf, Positionen und Preisregeln werden für die Registrierung gesperrt. Die Datei wird exklusiv unter dem kanonischen Pfad aus `DEEC_DocumentArchive` veröffentlicht, ihre Größe/Prüfsumme anschließend erneut geprüft und genau eine Version 1 registriert. `archived`/`archivedby` beschreiben die tatsächliche heutige Registrierung in der Mandanten-Zeitzone (ohne Konfiguration UTC). Erzeugungs- und Freigabeinformationen, historische Quellen und Konflikte verbleiben dauerhaft in `documentarchive.parameters`; es wird keine steuerliche Änderungskennzeichnung gesetzt.

Ein vorhandenes Original wird nie überschrieben. Eine vorhandene Registrierung darf nur mit exakt ihren gespeicherten Bytes wiederhergestellt werden; Identität, Dateiname, Pfad, Größe, Prüfsumme, Version und ursprüngliche Archivierungszeit bleiben erhalten. Bei einer ausdrücklich geprüften bestehenden kanonischen Fassung wird nur `parameters` um den passenden Freigabenachweis ergänzt. Vorhandene zusätzliche Parameter und frühere Freigaben bleiben erhalten. Damit kann auch ein bereits registriertes Original trotz abweichender historischer Kopien fachlich bestätigt werden, ohne seine Datei oder Registrierung neu anzulegen. Eine belegte kanonische Datei mit anderem Inhalt wird nicht ersetzt, auch wenn sie beschädigt ist. Solche Fälle werden kontrolliert gesperrt und benötigen eine getrennte fachlich freigegebene Behandlung oder passende Originalbytes. Eine neue Rekonstruktion rechtfertigt keine Änderung einer vorhandenen Prüfsumme.

Scheitert nach der geprüften Dateipublikation die DB-Registrierung oder der Commit, bleibt die Datei erhalten. Beim Wiederanlauf wird sie nur bei identischer Größe und SHA-256 wiederverwendet. Existiert die passende Registrierung bereits, werden weder Datei noch Datensatz erneut angelegt. Mehrere Archivversionen und widersprüchliche Metadaten werden abgewiesen. Die vorhandenen vollständigen Originale werden nicht pauschal angefasst.

Freigegebene kanonische Originale sind im normalen Download und in den bisherigen Kontakt-/E-Mail-Abläufen maßgeblich. Ein fehlender alter Belegdateiname wird aus den verifizierten Archivmetadaten für die Ausgabe ersetzt, ohne das Belegfeld zu ändern. Eine Wiederherstellung erscheint nicht als unbegründete Rechnungsänderung. Der Audit führt Abweichungen historischer Kopien weiterhin intern unter `historical_conflicts`; ohne passende Freigabe bleiben diese Konflikte gesperrt.

Exitcode 0 bedeutet Erfolg aller bearbeiteten Fälle. Exitcode 1 kann einzelne Fehler enthalten, während andere Belege erfolgreich abgeschlossen wurden. Immer den ausgegebenen geschützten JSON-Bericht pro Beleg prüfen. `pending`- oder noch nicht freigegebene Fälle werden nicht importiert; `--only` oder eine kleinere bewusst gewählte Sammlerauswahl erlaubt kontrollierte Teilmengen. Es gibt keine pauschale Genehmigungsoption.

## 6. Spätere Auslagerung nur vorbereiten

```sh
php tests/documents/document-recovery.php \
  --config="$read_config" --review="$review_root" --run="$review_run" \
  --client=100 --operator-user="$reviewer_id" --action=retention-plan
```

Der Bericht prüft vorhandene alte Quellen erneut gegen das registrierte kanonische Original und nennt Attachment-/Nachrichtenreferenzen. Nachrichtenbezogene Versandkopien bleiben besonders gekennzeichnet. Externe Links, historische E-Mail-Inhalte, Sicherung und Wiederherstellbarkeit müssen zusätzlich manuell geprüft werden; `relocation_authorized` bleibt immer false. Es existiert keine Verschiebe-, Lösch- oder Bereinigungsfunktion. Alte Dateien unter `files/contacts/` und `files/attachments/` bleiben erhalten.

## Lokale Verifikation und Grenzen

`python3 tests/documents/document-recovery-db.py` startet ausschließlich eine kurzlebige MariaDB unter `/tmp/dewawi-document-recovery-db-*` ohne Netzwerk. Die Regression verwendet das bestehende Installationsschema und echte PDF-Erzeugung für alle acht Arten, einschließlich fehlendem Dateinamen, Netto-/Brutto-Zwischenwerten, veralteten Summen, negativen Positionen, Rundung 0,01/0,10/0,11, fehlenden/ungültigen Preisregeln, geschütztem Warnungsnachweis und Originalvergleich. Das synthetische Rechnungsbeispiel mit 3631,4100 Netto und 4321,3779 Brutto wird zusätzlich mit `pdftotext` auf seine tatsächlich ausgegebenen Beträge geprüft. Die Regression benötigt deshalb auch dieses lokale Werkzeug. Manuelle Auswahl/Freigabe, verweigerte Rechte, fehlende/mehrdeutige Kontakte, manipulierte Prüfsummen, Symlinks, geschützte Originale und Wiederanlauf nach einem Insertfehler bleiben abgedeckt. Geschäftszeilen einschließlich Positionsgruppen und Preisregeln werden vor und nach dem gesamten Ablauf verglichen. Die bestehenden PDF-, Archiv-, Download-, Kontakt-, E-Mail- und Mandantentests ergänzen diese Prüfung.

Es wurden keine produktiven Berichte oder Datenbanken geöffnet und keine produktive Wiederherstellung ausgeführt. Vor dem Betreiberlauf bleiben reale Vollständigkeit, vorhandene Sicherungen, historischer Layoutbestand und die fachliche Auswahl zu prüfen. Neue Tabellen oder versionierte SQL-Änderungen sind für diesen Ablauf nicht notwendig; das vorhandene `parameters`-Feld und die Version-1-Struktur werden benutzt.
