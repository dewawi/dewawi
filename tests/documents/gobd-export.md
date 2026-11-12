# GoBD-Originalexport und interner Dokument-Prüfexport

Die vorhandene Maske unter `/admin/export` bietet zusätzlich die standardmäßig ausgeschaltete Option **PDF-Belegbilder mit exportieren**. Der Export bleibt für alle Mandanten und frei gewählte Zeiträume nutzbar. Es gibt keine Schemaänderung und keine Bindung an einen bestimmten Prüfzeitraum.

## Bedienung

- **Ohne PDFs:** Mandanten auswählen, `/admin/export` öffnen, Von und Bis setzen und die Checkbox ausgeschaltet lassen. Der bisherige CSV-Export einschließlich Datenwörterbuch, Beziehungen, Metadaten und Dateinamen bleibt erhalten.
- **Mit PDFs:** Dieselben Schritte ausführen und die Checkbox einschalten. Erforderlich sind Administratorzugriff im ausgewählten Mandanten und aktuelle Leserechte auf alle acht Belegarten. Der neue Dateiname beginnt mit `gobd-documents-`.
- **01.01.2019 bis 31.12.2021 mit Belegbildern:** Von `2019-01-01`, Bis `2021-12-31`, Checkbox einschalten, GoBD-Export starten. Beide Datumsgrenzen sind eingeschlossen. Andere Zeiträume funktionieren identisch.
- **Alle verfügbaren PDFs zur internen Prüfung:** Dieselben Datumsfelder setzen und **Dokumente zur internen Prüfung exportieren** anklicken. Dieser eigene Button erstellt unabhängig vom Zustand der PDF-Checkbox ein separates `document-review-...zip` mit Archivoriginalen und weiterhin gültigen veröffentlichten Prüf-PDFs. Die gespeicherten Datumsfilter gelten für beide Buttons. Administratorzugriff und Leserechte auf alle acht Belegarten sind auch hier erforderlich.

Die Maske merkt sich Von, Bis, die PDF-Option und die Listenexport-Auswahl im `sessionStorage` des jeweiligen Browser-Tabs, getrennt nach Installation, Mandant und Benutzer. Die Auswahl bleibt nach erfolgreichen und fehlgeschlagenen Exporten sowie beim erneuten Öffnen im selben Tab erhalten. **Filter zurücksetzen** stellt für das jeweilige Formular die Standardwerte wieder her: aktuelles Kalenderjahr ohne PDFs beziehungsweise Kontakte als Listenexport. Ohne verfügbaren Browserspeicher funktionieren die Exporte weiterhin, die Wiederherstellung entfällt. Es werden keine neuen Cookies oder Datenbankfelder verwendet; alle Export- und Belegrechte werden weiterhin serverseitig geprüft.

Die Anfrage verwendet weiterhin `/admin/export/export?type=gobd&from=YYYY-MM-DD&to=YYYY-MM-DD`, optional mit `include_documents=1`. Fehlende, ungültige oder vertauschte Datumswerte werden serverseitig abgewiesen. Bestehende CSV-, SQL- und Listenexporte erhalten keine PDF-Option.

Der separate Prüfexport verwendet dieselbe Route und Datierung mit `review_export=1`. Ohne diesen Parameter bleibt der bisherige GoBD-Ablauf erhalten. Der Parameter wird nicht als Filter gespeichert. Die bestehende PHP-API bleibt kompatibel; intern wird `export($path, $from, $to, $includeDocuments = false, $reviewExport = false)` verwendet. Es gibt kein neues Exportsystem und keine SQL-Migration.

Das Ergebnis erscheint erst nach vollständiger Erstellung und Prüfung in der bisherigen Exportliste. Über deren Downloadlink herunterladen; bestehende Links unter `files/export/<Mandant-Shards>/...` bleiben nutzbar und werden durch die vorhandene geschützte Dateiroute bedient. Rechte werden beim Download erneut geprüft. Auch umbenannte ZIPs mit Dokumentmetadaten benötigen die Belegrechte.

## Inhalt und Referenzen

Das bisherige steuerliche CSV-Profil bleibt unverändert: Angebote, Aufträge, Rechnungen und Gutschriften samt Positionen sowie bestehende Stamm-/Referenzdaten. Die optionale Erweiterung ergänzt alle acht Belegarten:

`sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest`, `purchases/purchaseorder`.

| ZIP-Inhalt | Bedeutung |
| --- | --- |
| Vorhandene CSVs, `data_dictionary.csv`, `relationships.csv` | Bisherige steuerliche Stamm-/Bewegungsdaten und Beziehungen, einschließlich vorhandener Lösch-/Stornokennzeichen; durch die PDF-Option unverändert. |
| `document_files.csv` | Kompakte maschinell lesbare Übersicht aller datierten Belege: Identität, Nummer, Datum, historischer Dateiname, Lösch-/Ausstellungsmerkmal, CSV-Verknüpfung, `pdf_path`, `pdf_supplied`, `pdf_origin` und `delivery_note`. |
| `documents/<Modul>/<Belegart>/<Belegnummer>.pdf` | Unveränderte Bytes verifizierter registrierter Archivfassungen, einschließlich ausdrücklich gekennzeichneter freigegebener Rekonstruktionsdoppel; bei Namenskollisionen eindeutige Zusätze. Keine Mandanten-ID-Zwischenordner. |
| `metadata.json`, `readme.txt` | Exportparameter, Übersichtsspalten, Umfang und ausdrückliche Kennzeichnung fehlender angeforderter Belegbilder. |

`pdf_supplied=1` und `delivery_note=supplied` bedeuten eine gelieferte verifizierte Archiv-PDF. Die Spalte `pdf_origin` unterscheidet `issued_original`, `verified_recovered_original` und `reviewed_reconstruction_duplicate`. Ein freigegebenes Rekonstruktionsdoppel ist keine unveränderte historische Originaldatei. Auch bereits früher freigegebene Rekonstruktionen werden anhand bestehender Archivnachweise erkannt. Nicht ausreichend dokumentierte Herkunft bleibt `registered_archive_origin_unconfirmed`; eine nicht freigegebene, als solche erkennbare Rekonstruktion wird nicht geliefert. Bei fehlender PDF gilt `pdf_origin=not_supplied`. `requested_pdf_not_supplied` weist fehlende angeforderte Originale aus; `original_reference_not_supplied_issue_status_unconfirmed` ungeklärte Ausstellungs-/Originalbezüge. Beides macht die Lieferung **INCOMPLETE**. `draft_no_original_requested` bezeichnet einen Entwurf ohne Originalanforderung. Leerer PDF-Pfad bedeutet stets, dass keine PDF geliefert wurde. Keine Lieferlücke wird durch Entfernen ihrer Belegzeile verborgen.

Die bisherigen technischen CSVs `document_index.csv`, `document_manifest.csv`, `missing_documents.csv`, `excluded_documents.csv` und `checksums.sha256` gehören ausschließlich in die geschützte interne Qualitätssicherung bzw. den internen Prüfexport. Archivparameter, Recovery-Kommentare und Konfliktprotokolle erscheinen nicht als zusätzliche Spalten im externen Paket.

Bei den vier vorhandenen steuerlichen Dokumenttabellen verweisen `csv_file`, `csv_key_column=id` und `csv_key_value` auf den jeweiligen CSV-Datensatz. Die übrigen vier Arten verwenden `document_files.csv` und dessen `record_key`. Gleichlautende interne IDs verschiedener Belegarten führen nicht zu ZIP-Kollisionen. Fremde Mandanten und Parent-Dokumente werden nicht mit exportiert; berechtigt geteilte Parent-Kontakte können zur Prüfung der eigenen Belege verwendet werden. Die bestehenden CSV-Stammdatenfilter bleiben unverändert.

PDF-Namen verwenden das tatsächlich gespeicherte Feld `<controller>id`, beispielsweise `invoiceid=110395` → `110395.pdf`, auch wenn die interne ID `55893` lautet. Dies ändert ausschließlich ZIP-Einträge. `documentid` und der historische `filename` bleiben in der Übersicht erhalten; tatsächliche Archivpfade und Dateien ändern sich nicht. Namen werden auf sichere ASCII-Zeichen begrenzt. Fehlende oder veränderte/ungeeignete Nummern erhalten `--id-<interne ID>`; ohne verwendbaren Namen gilt `document--id-<interne ID>.pdf`. Bei gleicher Nummer innerhalb desselben Ordners erhält die nach interner ID später gelesene Fassung einen ID-Zusatz, bei weiteren Kollisionen zusätzlich einen Zähler. Groß-/Kleinschreibung wird bei Kollisionen gleich behandelt. Nummern verschiedener Belegarten dürfen gleich lauten, da ihre Ordner getrennt bleiben.

Die Auswahl richtet sich nach dem tatsächlich gespeicherten jeweiligen Belegdatum, unabhängig vom Archivierungs- oder Wiederherstellungsdatum. Es werden ausschließlich bestehende PDF-Registrierungen der Version 1 mit kanonischem DEEC-Pfad, passender Dokument-/Mandantenzuordnung, gültiger PDF-Struktur, Dateigröße und SHA-256 übernommen. Es gibt keine Neurenderings, keine ungeprüften Recovery-Kandidaten und keinen Rückgriff auf unregistrierte Ersatzdateien.

Ein eindeutig registriertes, verifiziertes Original eines logisch gelöschten ausgestellten Belegs kann mit exportiert werden; der Löschstatus bleibt in der externen Übersicht und zusätzlich im internen Ausschlussinventar sichtbar. Der normale Dokumentdownload und seine Behandlung gelöschter Belege bleiben unverändert. Entwürfe ohne Ausstellungsmerkmal und ohne Originalreferenz stehen mit `delivery_note=draft_no_original_requested` in der Übersicht und erfordern kein Original-PDF. Unklare Datei-/Nummern-/Archivbezüge werden separat als ausgeschlossen ausgewiesen und verhindern eine positive Vollständigkeitsmeldung.

Mehrdeutige Archivversionen, abweichende Dateinamen/Pfade, Größen-/Hashfehler und nicht freigegebene historische Versionskonflikte erscheinen als nicht gelieferte angeforderte PDFs in der Übersicht, mit den genauen Ursachen im internen Fehlstelleninventar. Gültig freigegebene Wiederherstellungen bleiben exportierbar; ihre tatsächlichen Archivierungs-/Erzeugungsdaten und historischen Konflikte bleiben intern erhalten. Dadurch wird keine neue steuerliche Dokumentänderung behauptet.

## Getrennter interner Prüfexport

Dieser ZIP-Typ ist ausdrücklich **INTERNAL DOCUMENT REVIEW ONLY**, `metadata.export_kind=internal_document_review`, und enthält keine steuerlichen Stamm-/Positions-CSV-Datensätze. Er verwendet denselben geschützten ZIP-Ablauf, dieselben Belegdatierungen und Archivprüfungen. `document_index.csv` führt jeden datierten Beleg einmal auf. `document_manifest.csv` führt jede verfügbare PDF-Fassung sowie alle fehlenden, ungültigen und ausgeschlossenen Fälle auf; bei mehreren Fassungen hat ein Beleg mehrere Manifestzeilen. Alle Zeilen verweisen über `record_key` auf den Index.

| Status im Manifest | Inhalt / Behandlung |
| --- | --- |
| `archived_original` | Verifizierte registrierte Archivfassung; `pdf_origin` unterscheidet insbesondere geprüfte Rekonstruktionsdoppel. Datei unter `originals/<Modul>/<Belegart>/<Nummer>.pdf`. |
| `historical_reconstruction_pending_original_comparison` | Weiterhin gültige, über `publish-reviewable` veröffentlichte Rekonstruktion unter `review/<Modul>/<Belegart>/<Nummer>--<SHA-Präfix>.pdf`. Jede Fassung bleibt separat, ohne automatische Originalauswahl. |
| `historical_diagnostic_reconstruction` | Sichtbar auf jeder Seite als unbestätigt markierter Diagnosebeleg mit gespeicherten Daten und offenen Problemen, unter `diagnostics/<Modul>/<Belegart>/<Nummer>--<SHA-Präfix>.pdf`. Keine validierte Rekonstruktion, Originalauswahl oder Archivfreigabe. |
| `historical_unreviewed_candidate` | Sicher zugeordnete historische Sammelkandidaten oder Original-Uploads unter `candidates/<Modul>/<Belegart>/<Nummer>--<SHA-Präfix>.pdf`. Noch keine Bestätigung von Inhalt oder Originalherkunft; nicht im GoBD-Originalbestand. |
| `missing` | Kein registriertes Original und keine verfügbare veröffentlichte Rekonstruktion; reine Vorbereitungsdateien reichen nicht. Kein PDF-Eintrag. |
| `invalid` | Archivprüfung oder veröffentlichter Kandidat technisch gesperrt, etwa durch Größen-/SHA-Fehler, veralteten Datenfingerprint oder geänderten Nachweis. Kein PDF-Eintrag. Gültige andere Fassungen können weiterhin enthalten sein. |
| `excluded` | Nicht ausgestellter Beleg oder gelöschter ausgestellter Beleg ohne verifiziertes Original. Kein Prüf-PDF und keine Reaktivierung. |

Entsprechend dem vorhandenen Originalexport bleiben verifizierte Archivoriginale gelöschter ausgestellter Belege im Paket, mit `deleted=1` und zusätzlicher Zeile in `excluded_documents.csv`. Gelöschte Belege erhalten keine Freigabe von Prüf-PDFs. Alle offenen/gesperrten/ausgeschlossenen Manifestzeilen stehen zusätzlich in `missing_documents.csv`. Ein reiner Entwurf wird im Prüfexport als `excluded` berichtet; das bestehende GoBD-Profil behandelt solche Entwürfe weiterhin wie zuvor.

Die Manifeste enthalten Mandant, Modul, Belegart, interne ID, gespeicherte Nummer/Datierung, historischen Dateinamen, PDF-Größe, SHA-256, ZIP-Pfad und `evidence_status`. Bei Prüf-PDFs kommen Erzeugungszeitpunkt, aktueller Geschäftsdatenfingerprint, SHA-256 des geschützten Rekonstruktionsnachweises und die unveränderte Betreiberbestätigung der Datenbankprüfung hinzu. Bestehende Archiv-/Wiederherstellungsinformationen bleiben für Originale erhalten. Eine neue Papierprüfung oder steuerliche Belegänderung wird nicht behauptet.

Es werden die von `DEEC_DocumentReview` aktuell validierten Veröffentlichungen sowie eindeutig gebundene historische Sammelkandidaten und Original-Uploads mit eigenem ungeprüftem Status berücksichtigt. Reine unveröffentlichte Rekonstruktionen, veraltete Nachweise, fremde Mandanten und gesperrte Dateien gelangen nicht ins ZIP. Die vorhandenen Dateien außerhalb des Webroots werden gestreamt; keine Rekonstruktion, keine neue Auswahl/Freigabe und keine Archivregistrierung. Der normale GoBD-Originalexport zählt diese Prüf-PDFs weiterhin nicht als Originale.

Für separat veröffentlichte Diagnose-PDFs bedeutet die technische Prüfung ausschließlich aktuelle Identität, Nachweis, Datenfingerprint und Datei-Integrität; ihre Geschäftswerte sind ausdrücklich nicht als konsistent bestätigt. `diagnostic_issues` enthält die tatsächlichen offenen Probleme, `database_confirmation.database_values_verified` bleibt `false`. Diagnosen ohne gespeichertes Datum werden zusätzlich und ausdrücklich **ohne Zuordnung zum ausgewählten Zeitraum** aufgenommen (`date_scope=undated_diagnostic_not_assigned_to_period`); andere Zeilen behalten `date_scope=selected_period`. Die Metadaten zählen `documents`, `dated_documents` und `undated_diagnostic_documents` getrennt. Das ist keine nachträgliche Datierung. Der GoBD-Originalexport bleibt von dieser Ergänzung unberührt.

## Paket und interne Nachweise prüfen

Das externe ZIP mit `unzip -t /pfad/zum/paket.zip` prüfen. Danach `readme.txt`, `metadata.json` → `documents.summary` und **alle** Zeilen in `document_files.csv` auswerten. `complete=false` bzw. **INCOMPLETE** bedeutet fehlende angeforderte Originale oder ungeklärte Ausschlüsse. PDF-Pfade und CSV-Schlüssel sind bereits bei der Erstellung gegengeprüft. Diese Aussage gilt nur für die angeforderten gespeicherten Belegdatierungen; sie ist keine Zusicherung rechtlicher GoBD-Konformität. Undatierte Belege und XML-Originale gehören nicht zu dieser PDF-Option.

Zu jedem externen Paket werden im mandanteneigenen Exportverzeichnis unter **`.gobd-reports/`** zwei interne Dateien dauerhaft erhalten:

- `<paketname>.audit.zip`: bisheriges vollständiges technisches Inventar einschließlich Fehlstellen, Ausschlüssen, Archiv-/Recovery-Nachweisen, Konflikten und Prüfsummen. Enthält keine zweite Kopie der PDFs; deren Pfade und Hashes beziehen sich auf die tatsächliche Lieferung.
- `<paketname>.verification.json`: tatsächlicher Prüfzeitpunkt und Benutzer, Paketgröße/-SHA-256, SHA-256 des technischen Inventars sowie Größe und SHA-256 jedes tatsächlichen Lieferungseintrags.

Verzeichnis 0700, Dateien 0600; nicht in der Exportliste und nicht per HTTP zugänglich. Betreiber können diese Nachweise lokal mit den Lieferdateien vergleichen, etwa `sha256sum /pfad/zum/paket.zip`. Die Nachweise enthalten interne Daten und gehören nicht in die externe Weitergabe. Bei Fehlern vor der Paketveröffentlichung kann ein gültiger interner Nachweis ohne öffentliche ZIP zurückbleiben; er dokumentiert keine erfolgte Übergabe.

Der **interne Prüfexport** behält seine eigene vollständige `checksums.sha256`. Nach Entpacken in ein privates Verzeichnis mit `sha256sum -c checksums.sha256` prüfen und alle `missing`-/`invalid`-/`excluded`-Zeilen auswerten. Originale, ungeprüfte Kandidaten, Rekonstruktionen und Diagnosen bleiben getrennt. `complete=true` bestätigt hier die Verfügbarkeit im beschriebenen Umfang, keine Originalgleichheit. Endgültige Freigaben erfolgen dokumentbezogen im Register **Dokument** oder über den vorhandenen [Recovery-Ablauf](document-recovery.md).

Die Anwendung prüft vor Veröffentlichung sämtliche ZIP-Einträge auf Größe und SHA-256, PDF-/CSV-Verknüpfungen und Inventar-/Lieferzähler. Quelländerungen, Schreib-/ZIP-Fehler oder Fehler beim Speichern der internen Nachweise verhindern die Veröffentlichung. Bestehende Pakete und Originale werden nicht überschrieben. Temporäre Dateien liegen unter `.gobd-*` mit 0700 und werden entfernt; fertige ZIPs haben 0600. Große PDFs und Downloads werden gestreamt. Beim Download werden PHP-Ausgabe-/Kompressionspuffer abgebaut und die Session freigegeben, damit große Dateien das PHP-Speicherlimit nicht durch einen vollständigen Antwortpuffer überschreiten. Der Listenlink nutzt die Installationsbasis und öffnet keinen zusätzlichen Tab.

## Betrieb und Verifikation

Der PHP-Webserverbenutzer benötigt Leserechte auf die kanonischen Archive und Schreib-/Hardlinkrechte im bestehenden mandanteneigenen Exportverzeichnis. PHP-ZIP muss verfügbar sein; temporär muss Platz für das fertige Paket und die technischen Nachweise vorhanden sein. Die geschützten Nachweise bleiben dauerhaft erhalten und sind in die bestehende Betriebssicherung einzubeziehen. Exportstaging und Ziel liegen auf demselben Dateisystem. Die bestehenden Apache-Regeln müssen aktiv sein: `files`, `media` und `cache` laufen durch den Frontcontroller, `files/documents` und versteckte Stagingpfade sind direkt gesperrt. Andere Webserver müssen dieselbe bereits erforderliche Routingsperre durchsetzen. Kein öffentlicher statischer Alias für Exporte. Keine rekursiven Rechteänderungen oder Änderungen an Originalen sind erforderlich.

Für Prüfexporte muss derselbe PHP-Benutzer außerdem den bereits gebundenen externen Prüfbereich, Kandidaten und Nachweise lesen können. Beide ZIP-Typen werden beim Download auf aktuellen Mandanten, Administratorstatus und Rechte aller acht Belegarten geprüft, auch nach Umbenennung. Unvollständige Pakete mit `document-review-`-Präfix werden abgewiesen. Der Prüfexport benötigt ebenfalls nur SELECT-Rechte; es gibt keine neue Konfiguration.

Lokale Regressionen:

```sh
php tests/documents/gobd-export.php
python3 tests/documents/gobd-export-db.py
python3 tests/documents/gobd-export-ui.py
python3 tests/security/attachment-http.py
```

Die Datenbankprüfung verwendet ausschließlich eine kurzlebige netzwerklose MariaDB unter `/tmp/dewawi-gobd-export-db-*` und einen SELECT-only-Benutzer. Sie prüft alle acht Arten, inklusive Datumsgrenzen, fehlende/widersprüchliche Registrierungen, gelöschte/ausgeschlossene Belege, Wiederherstellungsnachweise, ZIP-/CSV-Verweise, Controller-Validierung, alte CSV-/SQL-Exporte, unveränderte Geschäfts-/Archivdaten, leere Pakete und eine 32-MB-PDF unter 24 MB PHP-Speicherlimit. Chromium prüft die reale Maske auf Deutsch/Englisch bei 375/1440 Pixeln; isoliertes Apache/PHP prüft private 0600-Downloads, aktuelle Belegrechte, Mandantentrennung, Parent-Child-Negativfälle, umbenannte Pakete und Unterverzeichnisinstallationen. Es werden keine Produktivkundendaten für Tests verwendet.

Ergänzend werden sichtbare/doppelte/fehlende/ungeeignete Nummern, mehrere veröffentlichte Fassungen, alle Prüfstatus einschließlich ungeprüfter Original-Uploads, unveröffentlichte und gesperrte Kandidaten, unveränderte Nachweise und die Trennung vom GoBD-Originalbestand geprüft. Beide ZIP-Typen verarbeiten eine 32-MB-PDF unter 24 MB PHP-Limit. `python3 tests/documents/document-recovery-db.py` prüft den Prüfexport zusätzlich mit den real gerenderten PDFs aller acht bestehenden Recovery-Fixtures und mehreren Fassungen.

Die Herkunftsspalte gehört sowohl zum externen Lieferverzeichnis als auch zu den geschützten technischen Inventaren. Ihre Werte werden vor Paketveröffentlichung gegen die tatsächlichen Archivnachweise geprüft; eine falsch als `issued_original` deklarierte Rekonstruktion verhindert die Veröffentlichung. Vollständigkeit beschreibt den gelieferten Archivumfang, keine pauschale historische Originaldatei-Gleichheit. Für gemischte Rechnungsfreigaben siehe [Batchanleitung](invoice-original-batch.md).
