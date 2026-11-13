# GoBD-Export und interner Dokument-Prüfexport

Die vorhandene Maske `/admin/export` exportiert das bestehende steuerliche CSV-Profil und optional PDF-Belegbilder. Die Implementierung bleibt mandantenübergreifend und für frei gewählte Zeiträume nutzbar. Es gibt keine SQL-Migration und keine Neuberechnung historischer Geschäftswerte.

## Bedienung und Berechtigungen

Mandant auswählen, Von/Bis setzen und den GoBD-Export starten. Ohne PDF-Checkbox bleibt das bisherige fachliche CSV-Profil erhalten. Mit **PDF-Belegbilder mit exportieren** entsteht ein `gobd-documents-...zip`. Beide Datumsgrenzen sind eingeschlossen. Angebote und Aufträge sind nicht auf Datensätze mit Rechnungsverknüpfung begrenzt. Gelöschte Datensätze werden nicht pauschal herausgefiltert.

**Dokumente zur internen Prüfung exportieren** erstellt unabhängig von der Checkbox ein separates `document-review-...zip` mit allen acht Belegarten und den verfügbaren Archiv-/Prüffassungen. Es ist keine externe Originallieferung und enthält keine steuerlichen Stamm-/Positions-CSV-Datensätze.

Beide PDF-Paketarten benötigen Administratorzugriff im aktiven Mandanten und aktuelle Leserechte aller acht Belegarten. Beim geschützten Download werden die Rechte erneut geprüft, auch für umbenannte Pakete. Parent-Child-Freigaben und Feature-Berechtigungen werden nicht erweitert. Der normale Dokumentdownload bleibt unverändert.

Die gespeicherten Formularfilter bleiben im `sessionStorage` des Browser-Tabs getrennt nach Installation, Mandant und Benutzer erhalten. Zurücksetzen stellt die bisherigen Standardwerte her. Die Route bleibt `/admin/export/export?type=gobd&from=YYYY-MM-DD&to=YYYY-MM-DD`, optional `include_documents=1` oder `review_export=1`. Andere CSV-, SQL- und Listenexporte erhalten keine PDF-Option.

**Vor einem PDF-Paketlauf** die privaten Verzeichnisse gemäß [Preflight-Betreiberanleitung](gobd-package.md) konfigurieren. Technische Berichte und sensible Stagingdateien liegen außerhalb von Installation und Repository. Ein separater, SHA-gebundener Einzel- oder ausdrücklich bestätigter Scope-Nachweis ist für jede extern gelieferte PDF erforderlich; ohne Entscheidung bleiben die PDFs als Nichtlieferung sichtbar. Die steuerlichen CSV-Datensätze bleiben enthalten.

## Externer Umfang und Paketinhalt

Das steuerliche CSV-Profil umfasst Angebote, Kundenaufträge, Rechnungen und Gutschriften samt Positionen sowie bestehende Stamm-/Referenzdaten. `invoices.prepayment`, historische Nummern, Beträge, Steuern, Status- und Löschwerte werden unverändert geliefert.

Die externe PDF-Option ist ausdrücklich auf sechs Ausgangsbelegarten begrenzt (`metadata.export_kind=outgoing_documents`):

`sales/quote`, `sales/salesorder`, `sales/invoice`, `sales/deliveryorder`, `sales/creditnote`, `sales/reminder`.

`purchases/quoterequest` und `purchases/purchaseorder` sind außerhalb dieses angeforderten Umfangs (`documents.outside_scope_types`); intern bleiben beide Arten erhalten. Bestehende Pakete werden nicht umgeschrieben.

| Inhalt | Bedeutung |
| --- | --- |
| Fachliche CSV-Dateien | Bisherige steuerliche Stamm-/Bewegungsdaten; fehlende PDFs entfernen keine Datensätze. |
| `data_dictionary.csv` | Spaltenbeschreibungen und tatsächliche Datentypen, Längen, Präzision, Nachkommastellen, NULL-Zulässigkeit und Primärschlüssel. |
| `relationships.csv` | Logische Beziehungen zwischen den gelieferten Datensätzen. |
| `document_files.csv` | Dokumentidentität, sichtbare Nummer, Datum, Dateiname, Lösch-/Schreibschutzmerkmal, CSV-Bezug, PDF-Pfad, Lieferung, Herkunft, Ausstellerstatus und Fehlstellengrund. |
| `documents/<Modul>/<Belegart>/<Belegnummer>.pdf` | Unveränderte Bytes geprüfter registrierter Archivfassungen mit separater Ausstellerbestätigung. Bei Namenskollisionen eindeutiger Zusatz mit interner ID. |
| `metadata.json`, `readme.txt` | Auswahlkriterien, Struktur, Dateien, tatsächliche Zeilenzahlen und belegartbezogene PDF-Lieferübersicht. |

`csv_file`, `csv_key_column`, `csv_key_value` verknüpfen PDF und Geschäftsdaten. Bei Rechnungen ist der Schlüssel die **interne ID** in `invoices.csv`, nicht Dateiname oder sichtbare Nummer. Arten ohne eigenes fachliches CSV referenzieren `record_key` in `document_files.csv`. Belegart und tatsächlicher Referenzschlüssel werden vor Veröffentlichung geprüft. Mehrere historische Fassungen mit derselben sichtbaren Nummer bleiben über unterschiedliche interne IDs getrennt.

`pdf_supplied=1` und `delivery_note=supplied` bestätigen die tatsächliche Dateilieferung. Leerer Pfad und `pdf_supplied=0` bedeuten Nichtlieferung. `missing_reason` benennt beispielsweise eine fehlende Registrierung, einen Integritätsfehler, einen Ausstellerkonflikt oder eine erforderliche Ausstellerprüfung. Private Prüfpersonen, Vergleichstexte, Pfade und Recovery-Parameter gehören nicht in das externe Paket.

`pdf_origin` beschreibt Archivherkunft, nicht Versand oder juristische Ausstelleridentität: `issued_original`, `verified_recovered_original`, `reviewed_reconstruction_duplicate` oder `registered_archive_origin_unconfirmed`. Ein geprüftes Rekonstruktionsdoppel ist keine unveränderte historische Originaldatei. Ungeklärte Archivherkunft bleibt fachlich zu prüfen. Nicht freigegebene erkennbare Rekonstruktionen werden extern nicht geliefert.

`issuer_status=manually_verified` ist eine gesonderte, dokumentierte Einzel-Ausstellerbestätigung. `scope_attested` bezeichnet eine ausdrückliche unabhängige Erklärung für einen benannten Dokument-/SHA-Umfang, keine behauptete Einzelvollprüfung. Für große Archive steht die [lesende Sammelvorprüfung](gobd-archive-screening.md) mit separater Paketfreigabe bereit. `conflict` und `review_required` verhindern die PDF-Aufnahme und erscheinen als Lieferlücken; sie löschen keine Geschäftsdaten. Die [private Ausstellerprüfung](gobd-package.md) verwendet vollständige Dokumentprüfung und belastbare Unterlagen, keine automatische Entscheidung allein über Namen, `clientid`, Dateiname oder einen Texttreffer.

## Status und Vollständigkeit richtig lesen

Das kompatibel beibehaltene Feld **`issued` beschreibt den technischen Schreibschutz** nach `DEEC_Permission::isDocumentReadonly()`: vorhandene Belegnummer, `completed`, `cancelled` oder Zustand 105/106. Es ist kein Beweis einer Kundenübergabe oder eines Versands. Auch `completed`, `state`, `cancelled` und `deleted` sind unverändert gespeicherte technische bzw. fachliche Zustandsmerkmale. Historische Korrekturfassungen werden dadurch nicht automatisch als versendet bezeichnet. `metadata.document_status` erklärt diese Unterscheidung; Versandnachweise werden nicht erfunden.

`documents.by_type` und README zählen je angeforderter Art:

| Feld | Zählweise |
| --- | --- |
| `total` | Alle ausgewählten datierten Datensätze, einschließlich Entwürfen und gelöschten Datensätzen. |
| `issued` | Technisch schreibgeschützte Belege; kompatibler Altname, keine Versandbehauptung. |
| `delivered_pdfs` | Belege mit tatsächlich gelieferter PDF, je Beleg einmal. |
| `missing_pdfs` | Technisch schreibgeschützte Belege ohne gelieferte PDF, auch bei vorhandener, aber gesperrter Datei. |
| `deleted_issued_without_pdf` | Darin enthaltene gelöschte schreibgeschützte Belege ohne gelieferte PDF. |
| `unresolved_original_references` | Ungeklärte Originalbezüge ohne technisches Schreibschutzmerkmal; intern entsprechend ausgeschlossene Datensätze. |

Extern machen fehlende angeforderte PDFs oder ungeklärte Originalbezüge die Lieferung **INCOMPLETE**. Ein gelieferter gelöschter Beleg ist keine Fehlstelle. Alle datierten Belege bleiben unabhängig von Dateiname und Archivstatus auffindbar. Die Vollständigkeit bezieht sich nur auf den tatsächlich ausgewählten PDF-Lieferumfang, nicht auf rechtliche Konformität oder umfassende historische Originalgleichheit.

Undatierte Belege sind keinem Zeitraum zugeordnet. Der vollständige mandantenweite [Archivaudit](document-archive-migration.md) bleibt für solche Fälle, ausgeschlossene Bestände und nicht zugeordnete Dateien erforderlich. Der interne Prüfexport enthält veröffentlichte undatierte Diagnosen zusätzlich mit `date_scope=undated_diagnostic_not_assigned_to_period`, ohne ein Belegdatum zu erfinden.

## CSV-Struktur und historische Nachweisgrenzen

Alle tatsächlich gelieferten CSVs einschließlich Datenwörterbuch und Beziehungen stehen mit Datenzeilenzahlen ohne Kopfzeile in `metadata.csv_files` und im README. CSV-Parser zählen auch mehrzeilige Felder korrekt. UTF-8, Semikolon, Dezimalpunkt und doppelte Anführungszeichen werden dokumentiert. Eingebettete Anführungszeichen werden verdoppelt; Backslashes sind wörtliche Daten. NULL und leere Zeichenketten bleiben im CSV als Leerfelder nicht unterscheidbar.

`metadata.tables[*].columnSchema` enthält die benötigten tatsächlichen Schemaattribute, keine Datenbanknamen oder internen Schema-Pfade. `unavailableColumns` meldet fehlende optionale Profilspalten ausdrücklich. Beziehungen sind logische Verknüpfungen, keine behaupteten physischen Fremdschlüssel. Sichtbare Geschäftsnummern können historisch mehrfach vorkommen; Vorgänger können außerhalb des gewählten Zeitraums liegen. Fehlende Referenzen werden nicht durch erfundene Datensätze ergänzt.

Ein **leeres strukturiertes Anzahlungsfeld beweist nicht, dass keine Anzahlungen stattgefunden haben**. Belegpositionen, gespeicherte Texte, PDF-Unterlagen und externe Finanzbuchhaltung können weitere Nachweise enthalten. Beträge werden nicht nachberechnet oder ergänzt. Das Anlegen von `invoice.prepayment` ist in der vorhandenen Migration `install/config/update-1.0.1-1.0.2.sql` belegt; daraus folgt kein Kalenderdatum der Einführung oder Ausführung in einer konkreten Installation.

Der Export liefert gespeicherte aktuelle Stammdaten und direkt auf Belegen gespeicherte Adress-/Positionstexte. Diese Beleg-Snapshots sind keine Änderungslogs und kein Beweis unveränderter Ursprungswerte. Ein vollständiges Alt-/Neuwert-Journal ist nicht Teil des vorhandenen Exportprofils. Letzte Änderungszeitpunkte, Kontakt-Beleglisten sowie Zugriffs-/Importprotokolle ersetzen dieses Journal nicht. Historische Änderungen sind erforderlichenfalls mit zusätzlichen belastbaren Quellen nachzuweisen, ohne fiktive Historienzeilen zu erzeugen.

## Interner Prüfexport und Nachweise

`INTERNAL DOCUMENT REVIEW ONLY` behält alle acht Arten und alle sicher verfügbaren Fassungen:

| Status | Inhalt |
| --- | --- |
| `archived_original` | Geprüfte registrierte Archivfassung unter `originals/`; Herkunft und Aussteller sind getrennte Fragen. |
| `historical_reconstruction_pending_original_comparison` | Weiterhin gültige veröffentlichte Rekonstruktion unter `review/`; keine automatische Originalgleichheit. |
| `historical_diagnostic_reconstruction` | Sichtbar markierte Diagnose unter `diagnostics/` mit offenen Geschäftsdatenproblemen. |
| `historical_unreviewed_candidate` | Sicher zugeordneter, noch ungeprüfter historischer Kandidat oder Original-Upload unter `candidates/`. |
| `missing`, `invalid`, `excluded` | Fehlende, gesperrte oder ausgeschlossene Fälle mit konkretem Grund. |

`document_index.csv` führt Belege einmal, `document_manifest.csv` alle Fassungen, `missing_documents.csv` offene Fälle und `excluded_documents.csv` Ausschlüsse. Gelöschte schreibgeschützte Archivfassungen behalten ihre Löschmerkmale. Mehrere Fassungen werden nicht automatisch ausgewählt. `checksums.sha256` prüft alle internen Paketeinträge außer sich selbst.

Externe Pakete enthalten diese technischen Inventare nicht. Sie werden im privaten Berichtsverzeichnis als `.audit.zip` erhalten; die zugehörige `.verification.json` bindet Paketgröße/-SHA, jeden tatsächlichen Lieferungseintrag, Prüfzeitpunkt/-Benutzer und den SHA der verwendeten Ausstellerdatei. Das Audit enthält keine zweite Kopie aller PDFs. Kein solcher interner Bericht gehört zur externen Übergabe.

Der gesamte Ablauf einschließlich [Vorlagen, Ausstellerentscheidung, ZIP-Preflight und Versionsvergleich](gobd-package.md) ist dort mit generischen Betreiberbefehlen beschrieben. Bestehende Pakete werden nie stillschweigend geändert. Für die aktuelle Struktur und den aktuellen Umfang neue Pakete erzeugen.

## Gezielte Nachbearbeitung und lokale Tests

Originale zuerst über den vorhandenen vollständigen [Datei-/Archivaudit](document-archive-migration.md) suchen. Den [Review-Sammler](document-review.md) für eindeutige Zuordnungen verwenden, unterschiedliche Fassungen erhalten und ungeklärte Dateien separat inventarisieren. Nur geeignete, tatsächlich fehlende Belege über [Recovery](document-recovery.md) gezielt vorbereiten. Bestehende Auswahl-, Inhaltsvergleichs-, Freigabe- und Importprüfungen bleiben erforderlich; keine Hauptmigration, pauschale Neuerzeugung oder direkte SQL-Registrierung.

Synthetische lokale Regressionen:

```sh
php tests/documents/gobd-export.php
php tests/documents/gobd-issuer-test.php
python3 tests/documents/gobd-package-test.py
python3 tests/documents/gobd-export-db.py
python3 tests/documents/document-recovery-db.py
python3 tests/documents/gobd-export-ui.py
python3 tests/security/attachment-http.py
```

Die Datenbanktests verwenden ausschließlich kurzlebige netzwerklose MariaDB-Instanzen und synthetische Daten. Chromium prüft Deutsch/Englisch bei schmalem und breitem Viewport. Die [kompakte Softwarebeschreibung](gobd-software-description.md) erläutert den externen Umfang ohne konkrete Betreiberidentitäten oder Prüffälle.
