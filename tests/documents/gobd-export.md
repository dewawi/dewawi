# GoBD-Export mit optionalen PDF-Belegbildern

Die vorhandene Maske unter `/admin/export` bietet zusätzlich die standardmäßig ausgeschaltete Option **PDF-Belegbilder mit exportieren**. Der Export bleibt für alle Mandanten und frei gewählte Zeiträume nutzbar. Es gibt keine Schemaänderung und keine Bindung an einen bestimmten Prüfzeitraum.

## Bedienung

- **Ohne PDFs:** Mandanten auswählen, `/admin/export` öffnen, Von und Bis setzen und die Checkbox ausgeschaltet lassen. Der bisherige CSV-Export einschließlich Datenwörterbuch, Beziehungen, Metadaten und Dateinamen bleibt erhalten.
- **Mit PDFs:** Dieselben Schritte ausführen und die Checkbox einschalten. Erforderlich sind Administratorzugriff im ausgewählten Mandanten und aktuelle Leserechte auf alle acht Belegarten. Der neue Dateiname beginnt mit `gobd-documents-`.
- **01.01.2019 bis 31.12.2021 mit Belegbildern:** Von `2019-01-01`, Bis `2021-12-31`, Checkbox einschalten, GoBD-Export starten. Beide Datumsgrenzen sind eingeschlossen. Andere Zeiträume funktionieren identisch.

Die Anfrage verwendet weiterhin `/admin/export/export?type=gobd&from=YYYY-MM-DD&to=YYYY-MM-DD`, optional mit `include_documents=1`. Fehlende, ungültige oder vertauschte Datumswerte werden serverseitig abgewiesen. Bestehende CSV-, SQL- und Listenexporte erhalten keine PDF-Option.

Das Ergebnis erscheint erst nach vollständiger Erstellung und Prüfung in der bisherigen Exportliste. Über deren Downloadlink herunterladen; bestehende Links unter `files/export/<Mandant-Shards>/...` bleiben nutzbar und werden durch die vorhandene geschützte Dateiroute bedient. Rechte werden beim Download erneut geprüft. Auch umbenannte ZIPs mit Dokumentmetadaten benötigen die Belegrechte.

## Inhalt und Referenzen

Das bisherige steuerliche CSV-Profil bleibt unverändert: Angebote, Aufträge, Rechnungen und Gutschriften samt Positionen sowie bestehende Stamm-/Referenzdaten. Die optionale Erweiterung ergänzt alle acht Belegarten:

`sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`, `purchases/quoterequest`, `purchases/purchaseorder`.

| ZIP-Inhalt | Bedeutung |
| --- | --- |
| Vorhandene CSVs, `data_dictionary.csv`, `relationships.csv` | Bisherige Daten und Beziehungen; CSV-Inhalte bleiben durch die PDF-Option unverändert. |
| `document_index.csv` | Alle datierten Belege im gewählten Zeitraum mit Status und eindeutigem `record_key` aus Mandant/Modul/Belegart/ID. |
| `documents/<Mandant>/<Modul>/<Belegart>/<ID>.pdf` | Unveränderte, verifizierte Original-PDFs aus dem zentralen Archiv. |
| `document_manifest.csv` | Aufgenommene PDFs mit Identität, Datum, Originaldateiname, Version, ZIP-Pfad, Größe, SHA-256 und vorhandenen Archiv-/Wiederherstellungsinformationen. |
| `missing_documents.csv` | Fehlende oder ungültige Archivoriginale und ausgeschlossene Belegbildfälle mit Grund. |
| `excluded_documents.csv` | Nachträglich gelöschte ausgestellte Belege sowie Datensätze mit Datei-/Archiv-/Nummernreferenz ohne gültiges Ausstellungsmerkmal. |
| `checksums.sha256` | SHA-256 aller ZIP-Einträge außer dieser Prüfsummenliste selbst. |
| `metadata.json`, `readme.txt` | Exportparameter, Umfang, Zähler und ausdrückliche Kennzeichnung fehlender Belegbilder. |

Bei den vier vorhandenen steuerlichen Dokumenttabellen verweisen `csv_file`, `csv_key_column=id` und `csv_key_value` auf den jeweiligen CSV-Datensatz. Die übrigen vier Arten verwenden `document_index.csv` und dessen `record_key`. Gleichlautende interne IDs verschiedener Belegarten führen nicht zu ZIP-Kollisionen. Fremde Mandanten und Parent-Dokumente werden nicht mit exportiert; berechtigt geteilte Parent-Kontakte können zur Prüfung der eigenen Belege verwendet werden. Die bestehenden CSV-Stammdatenfilter bleiben unverändert.

Die Auswahl richtet sich nach dem tatsächlich gespeicherten jeweiligen Belegdatum, unabhängig vom Archivierungs- oder Wiederherstellungsdatum. Es werden ausschließlich bestehende PDF-Registrierungen der Version 1 mit kanonischem DEEC-Pfad, passender Dokument-/Mandantenzuordnung, gültiger PDF-Struktur, Dateigröße und SHA-256 übernommen. Es gibt keine Neurenderings, keine Recovery-Kandidaten und keinen Rückgriff auf unregistrierte Ersatzdateien.

Ein eindeutig registriertes, verifiziertes Original eines logisch gelöschten ausgestellten Belegs kann mit exportiert werden; der Löschstatus bleibt sichtbar und der Fall steht zusätzlich in `excluded_documents.csv`. Der normale Dokumentdownload und seine Behandlung gelöschter Belege bleiben unverändert. Entwürfe ohne Ausstellungsmerkmal und ohne Originalreferenz stehen als `not_issued` nur im Index und erfordern kein Original-PDF. Unklare Datei-/Nummern-/Archivbezüge werden separat als ausgeschlossen ausgewiesen und verhindern eine positive Vollständigkeitsmeldung.

Mehrdeutige Archivversionen, abweichende Dateinamen/Pfade, Größen-/Hashfehler und nicht freigegebene historische Versionskonflikte erscheinen in der Fehlstellenliste. Gültig freigegebene Wiederherstellungen bleiben exportierbar; ihre tatsächlichen Archivierungs-/Erzeugungsdaten und historischen Konflikte stehen im Manifest. Dadurch wird keine neue steuerliche Dokumentänderung behauptet.

## Paket prüfen

In einem neuen privaten Verzeichnis prüfen; den tatsächlichen Downloadpfad einsetzen:

```sh
gobd_zip=/pfad/zum/heruntergeladenen/gobd-documents-export.zip
umask 077
gobd_check_dir=$(mktemp -d)
unzip -t "$gobd_zip"
unzip -q "$gobd_zip" -d "$gobd_check_dir"
(cd "$gobd_check_dir" && sha256sum -c checksums.sha256)
```

Danach `metadata.json` → `documents.summary` und die drei Dokumentmanifeste auswerten. `complete=false` beziehungsweise **INCOMPLETE** in der README bedeutet, dass Belegbilder fehlen oder ungeklärte Ausschlüsse vorliegen; der Datenexport ist trotzdem verfügbar. `included`, `missing`, `excluded` und `deleted_issued` unterscheiden aufgenommene Originale, Fehlstellen, ungeklärte Ausschlüsse und nachträgliche Löschungen. Eine erfolgreiche Prüfsummenprüfung allein bestätigt keine inhaltliche historische Originalgleichheit. Die Vollständigkeitsangabe gilt ausschließlich für den beschriebenen Umfang anhand gespeicherter Belegdatierungen; sie ist keine Aussage über undatierte Belege, historische Datenänderungen oder eine vollständige rechtliche GoBD-Konformität. XML-Originale gehören nicht zu dieser PDF-Option.

Die Anwendung prüft vor der Veröffentlichung alle ZIP-Einträge auf Größe und SHA-256, alle PDF-Verweise gegen tatsächliche CSV-Schlüssel, Dokumentindex/Fehlstellen/Ausschlüsse gegen die Zähler sowie die Prüfsummenliste. Eine Quelländerung während des Packens oder ein Schreib-/ZIP-Fehler verhindert die Veröffentlichung. Bereits veröffentlichte Pakete und Archivoriginale werden nicht überschrieben. Temporäre Exportdateien werden in einem privaten `.gobd-*`-Verzeichnis mit 0700 erstellt und bei Erfolg oder Fehler entfernt; das fertige ZIP hat 0600. Große PDFs werden per `ZipArchive::addFile` und Hash-Streams verarbeitet.

## Betrieb und Verifikation

Der PHP-Webserverbenutzer benötigt Leserechte auf die kanonischen Archive und Schreib-/Hardlinkrechte im bestehenden mandanteneigenen Exportverzeichnis. PHP-ZIP muss verfügbar sein; temporär muss Platz für das fertige Paket vorhanden sein. Exportstaging und Ziel liegen auf demselben Dateisystem. Die bestehenden Apache-Regeln müssen aktiv sein: `files`, `media` und `cache` laufen durch den Frontcontroller, `files/documents` und versteckte Stagingpfade sind direkt gesperrt. Andere Webserver müssen dieselbe bereits erforderliche Routingsperre durchsetzen. Kein öffentlicher statischer Alias für Exporte. Keine rekursiven Rechteänderungen oder Änderungen an Originalen sind erforderlich.

Lokale Regressionen:

```sh
php tests/documents/gobd-export.php
python3 tests/documents/gobd-export-db.py
python3 tests/documents/gobd-export-ui.py
python3 tests/security/attachment-http.py
```

Die Datenbankprüfung verwendet ausschließlich eine kurzlebige netzwerklose MariaDB unter `/tmp/dewawi-gobd-export-db-*` und einen SELECT-only-Benutzer. Sie prüft alle acht Arten, inklusive Datumsgrenzen, fehlende/widersprüchliche Registrierungen, gelöschte/ausgeschlossene Belege, Wiederherstellungsnachweise, ZIP-/CSV-Verweise, Controller-Validierung, alte CSV-/SQL-Exporte, unveränderte Geschäfts-/Archivdaten, leere Pakete und eine 32-MB-PDF unter 24 MB PHP-Speicherlimit. Chromium prüft die reale Maske auf Deutsch/Englisch bei 375/1440 Pixeln; isoliertes Apache/PHP prüft private 0600-Downloads, aktuelle Belegrechte, Mandantentrennung, Parent-Child-Negativfälle, umbenannte Pakete und Unterverzeichnisinstallationen. Es werden keine Produktivkundendaten für Tests verwendet.
