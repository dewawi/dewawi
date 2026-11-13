# GoBD-Pakete: private Ausstellerprüfung, Preflight und Vergleich

Diese Anleitung ist generisch. Konkrete Identitäten, Zeiträume, Eingabepakete, PDF-Texte, Entscheidungen und Ergebnisse ausschließlich in einem privaten Betreiberverzeichnis außerhalb von Installation und Git-Worktree halten. Keine solchen Dateien in das Repository kopieren. Die Werkzeuge verändern weder ZIP-Eingaben noch Geschäftsdatensätze oder Archivregistrierungen.

## Einmalige Betriebskonfiguration

Für PDF- und interne Prüfexporte benötigt der tatsächliche PHP-Webworker:

- `DEWAWI_GOBD_REPORT_DIR`: vorhandenes, kanonisches privates Verzeichnis mit 0700, außerhalb von Installation und Repository. Staging und dauerhafte technische Nachweise liegen dort. Der PHP-Benutzer benötigt Schreibrechte. Dieses Verzeichnis muss für die bestehende exklusive Hardlink-Veröffentlichung auf demselben Dateisystem wie das Exportziel liegen.
- Optional `DEWAWI_GOBD_ISSUER_REVIEW_DIR`: privates Verzeichnis mit 0700 für Ausstellerentscheidungen. Pro Mandant liegt dort gegebenenfalls eine Datei `<interne Mandanten-ID>.json` mit 0600. Keine Symlinks oder öffentlichen Webserver-Aliase. Ein Shell-`export` konfiguriert keinen bereits laufenden PHP-FPM-/Apache-Prozess; die Variablen müssen über dessen bestehende Betriebskonfiguration bereitgestellt werden.

Gültige registrierte Archiv-PDFs werden auch ohne Ausstellerdatei oder positive Entscheidung exportiert. `review_required` bezeichnet dann den offenen Ausstellerstatus; eine manuelle Prüfung wird nicht behauptet. Bestätigte Fremdaussteller bleiben gesperrt. Ein fehlender privater Berichtsordner oder eine vorhandene ungültige/fremde Ausstellerdatei verhindert den PDF-Paketlauf mit einer Fehlermeldung; bestehende Pakete bleiben erhalten. Ausstellerprüfung und separate Paketfreigabe sind optionale Betreiberwerkzeuge. Ein reiner CSV-Export benötigt keine Ausstellerdatei. Es gibt keine SQL-Migration.

Die Verzeichnisse vom Betreiber mit gezielter Eigentümerzuordnung und 0700 anlegen. Keine rekursiven Rechteänderungen an bestehenden Archiven. Technische Nachweise liegen unter `$DEWAWI_GOBD_REPORT_DIR/<Mandanten-ID>/`: `<Paketname>.audit.zip` und `<Paketname>.verification.json`, jeweils 0600. Alte Nachweise am früheren Speicherort werden weder verschoben noch gelöscht. Die neuen privaten Nachweise sind in die Betriebssicherung einzubeziehen. Ein Nachweis kann bereits vor der abschließenden ZIP-Veröffentlichung angelegt sein; seine Existenz beweist weder eine erfolgte Veröffentlichung noch eine tatsächliche Übergabe.

## Große historische Archive

Für registrierte Bestände ist alternativ die [direkte Archivvorprüfung mit Cache, Prioritätenliste, explizitem Sammelnachweis und gesonderter Paketfreigabe](gobd-archive-screening.md) verfügbar. Sie benötigt keinen PDF-ZIP-Zwischenexport. Ein Sammelnachweis ist eine tatsächliche fachliche Erklärung anhand unabhängiger Nachweise für einen benannten SHA-Umfang, keine automatisch erzeugte Einzelprüfung.

## Ausstellerentscheidung vorbereiten

1. Im bestehenden `/admin/export` den autorisierten Mandanten und den gewünschten Zeitraum wählen. **Dokumente zur internen Prüfung exportieren** erstellt weiterhin ein separates Paket mit allen acht Arten und sämtlichen verfügbaren Fassungen. Es ist keine externe Originallieferung.
2. Dieses Paket unverändert in ein privates Arbeitsverzeichnis außerhalb des Git-Worktrees kopieren. Eingaben und JSON-Dateien benötigen 0600, das unmittelbare Arbeitsverzeichnis 0700. Bei den folgenden Befehlen sind alle Variablen bereits mit den tatsächlichen privaten Betreiberwerten zu setzen; sie sind keine Repository-Konfiguration.

```sh
umask 077
python3 tests/documents/gobd-package.py \
  --action=preflight --client="$CLIENT" \
  --zip="$PRIVATE_WORK/internal-review.zip" \
  --output="$PRIVATE_WORK/internal-preflight.json" \
  --issuer-template="$PRIVATE_WORK/issuer-template.json"
```

Ein internes Paket endet regelmäßig mit Exitcode 1: fachliche Freigabe erforderlich. Das Ergebnis bleibt im privaten Bericht. Die Vorlage enthält jede konkrete Dokumentidentität und PDF-SHA, aber ausschließlich `decision=unresolved`, leere Prüfperson/Prüfzeit/Basis/Aussteller und unbestätigte Prüfmerkmale. Eine automatische positive Erklärung wird nie erstellt.

3. Die zuständige Fachperson beurteilt die **tatsächliche juristische Ausstelleridentität getrennt von Mandant, Empfänger und Dateiname**. Die zusätzlich erzeugte private Datei `<Berichtsname>.pdfs.jsonl` enthält begrenzte maschinenlesbare PDF-Texte und Einzelentscheidungen als Lesehilfe; der JSON-Hauptbericht enthält dazu die kompakte Übersicht. Eine Textsuche bestätigt keinen Aussteller. Bei Scans, fehlender/mehrdeutiger Texterkennung oder widersprüchlichen Angaben das vollständige PDF und belastbare Unterlagen prüfen. Empfängername, Mandantennummer oder ein einzelner Treffer genügen nicht.
4. Für jede tatsächlich geprüfte Fassung in einer privaten Kopie der Vorlage ausfüllen:

| Feld | Erforderliche Erklärung |
| --- | --- |
| `decision` | `confirmed_same_issuer`, `confirmed_foreign_issuer` oder weiterhin `unresolved`. |
| `reviewed_by` | Tatsächliche Prüfperson bzw. deren eindeutiger Betreiberverweis. |
| `reviewed_at` | Tatsächlicher Bestätigungszeitpunkt mit Zeitzonenoffset, Format `YYYY-MM-DDTHH:MM:SS+HH:MM`. Kein erfundener historischer Zeitpunkt. |
| `basis` | Konkrete private Vergleichsgrundlage mit nachvollziehbaren Ausstellermerkmalen und Verweis auf Unterlagen. |
| `actual_issuer` | Tatsächlich festgestellter juristischer Aussteller, keine aus `clientid` abgeleitete Annahme. |
| `full_document_reviewed` | Nur bei tatsächlich vollständiger Dokumentprüfung `true`. |
| `issuer_compared_with_tenant` | Nur nach ausdrücklichem fachlichem Vergleich mit der juristischen Identität des exportierenden Mandanten `true`. |

Identität, PDF-SHA und `tenant_identity_sha256` unverändert erhalten. Die Mandantenbindung bezieht sich auf die aktuellen gespeicherten Identitätsfelder, nicht auf einen behaupteten historischen Aussteller. Verändert sich diese Grundlage, sind die Entscheidungen fachlich erneut zu prüfen; die Software schreibt keine alten Bestätigungen automatisch um. Mehrere Fassungen brauchen jeweils eigene SHA-gebundene Entscheidungen. Verifizierte Fremdaussteller werden als Konflikt dokumentiert, nicht passend umbenannt.

5. Die ausgefüllte Datei mit 0600 als `$DEWAWI_GOBD_ISSUER_REVIEW_DIR/$CLIENT.json` bereitstellen. Bestehende Entscheidungen gezielt erhalten und prüfen; keine ungeprüfte Vorlage über bestätigte Nachweise kopieren. Die Datei ist auf 128 MiB begrenzt. Bestätigung durch die Fachperson ist eine Betreiberhandlung; der Vorlagengenerator erteilt sie nicht.

Diese Ausstellerprüfung ersetzt keine Originalauswahl, keinen historischen Inhaltsvergleich und keine Recovery-Archivfreigabe. Bereits vorhandene [Auswahl-/Freigabe-/Importverfahren](document-recovery.md) bleiben erforderlich. Ungeprüfte Rekonstruktionen und Diagnosen gelangen durch eine Ausstellererklärung nicht in den GoBD-Originalbestand. Es werden keine PDFs neu erzeugt.

## Externen Export erzeugen und prüfen

In der vorhandenen Exportmaske denselben Mandanten und Zeitraum wählen, **PDF-Belegbilder mit exportieren** einschalten und den GoBD-Export starten. Das steuerliche CSV-Profil bleibt vollständig erhalten. Extern werden ausschließlich die sechs Verkaufsbelegarten berücksichtigt. Archivpfad, PDF-Struktur, Größe, SHA, bestehende Freigaben und die separate Ausstellerentscheidung werden geprüft. `scope_attested` kennzeichnet einen ausdrücklich anhand unabhängiger Nachweise bestätigten benannten Umfang, ohne eine Einzelvollprüfung zu behaupten.

`issuer_status=manually_verified` bedeutet den dokumentierten manuellen Ausstellervergleich, keinen Versandnachweis. `issuer_conflict` wird als Nichtlieferungsgrund mit leerem PDF-Pfad und korrekten Fehlstellenzählern ausgegeben. Ein offener Ausstellerstatus allein sperrt die PDF nicht. Alle betroffenen CSV-Datensätze bleiben enthalten. Intern stehen die Originale weiterhin im vollständigen Prüfexport zur Verfügung.

Für den optionalen Preflight das fertige ZIP, seine zugehörige `.verification.json` und gegebenenfalls eine unveränderte Kopie der beim Export verwendeten Ausstellerdatei in ein eigenes privates Arbeitsverzeichnis dieses Exportlaufs übernehmen und sichern. Ohne Ausstellerdatei im folgenden Befehl `--issuer-review` weglassen. Die Prüfdatei bindet auch den SHA einer verwendeten Ausstellerdatei; spätere Änderungen am laufenden Mandantenmanifest dürfen die archivierte Erklärung nicht ersetzen. Danach:

```sh
python3 tests/documents/gobd-package.py \
  --action=preflight --client="$CLIENT" \
  --zip="$PRIVATE_WORK/external-export.zip" \
  --verification="$PRIVATE_WORK/delivery-verification.json" \
  --issuer-review="$PRIVATE_WORK/issuer-review.json" \
  --output="$PRIVATE_WORK/final-preflight.json"
```

Der Prüfer kontrolliert ZIP-Grenzen und CRCs, SHA-256 sämtlicher Einträge, unabhängigen Liefernachweis, CSV-Spalten und Zeilenzahlen, Datenwörterbuch, Metadaten, Beziehungen, interne Dokument-IDs, Nummern, Datierungen, PDF-Pfade, Inventarabdeckung und Vollständigkeitszähler. Doppelte sichtbare Nummern bleiben über interne IDs getrennt. Beziehungslücken und mehrdeutige Verweise werden zur fachlichen Klärung gemeldet; außerhalb des Zeitraums liegende Vorgänger werden nicht erfunden. PDF-Texte und konkrete Befunde verbleiben ausschließlich im privaten Bericht.

Bei alten Paketen ohne unabhängigen Liefernachweis, fehlenden Strukturangaben, ungeklärter Herkunft oder geändertem Umfang bleibt eine fachliche Prüfung erforderlich. Nicht eindeutig lesbare alte CSV-Maskierung wird als Fehler gemeldet und nicht stillschweigend repariert. Der aktuelle Export verdoppelt eingebettete Anführungszeichen und behandelt Backslashes wörtlich.

| Ergebnis | Exitcode | Bedeutung |
| --- | --- | --- |
| `technically_verified` | 0 | Die vorgesehenen technischen Prüfungen einschließlich vorliegender Ausstellerbestätigungen sind erfolgreich. Keine rechtliche Konformitäts- oder Versandbestätigung. |
| `professional_review_required` | 1 | Technisch auswertbar, aber fachliche Entscheidung erforderlich, etwa bei Fehlstellen, fehlender Ausstellerbestätigung, Scans ohne Bestätigung, unbekannter Archivherkunft oder Beziehungslücken. |
| `not_releasable` | 2 | Technischer Fehler, falsche Zuordnung/Mandant, widersprüchliches Inventar oder bestätigter Fremdaussteller. Nicht freigeben. |

Ein bestätigter Ausstellerkonflikt bleibt auch dann sichtbar, wenn seine PDF berechtigt nicht geliefert wurde. Keine positive Vollständigkeitsmeldung wird daraus abgeleitet. Die Fachperson entscheidet über verbleibende Befunde und die tatsächliche Übergabe. Bei Fehlern oder notwendigen Änderungen ein **neues Exportpaket** erstellen; fertige ZIPs werden nicht bereinigt oder ersetzt. Ausgabeziele werden nie überschrieben.

## Zwei vorhandene Exportversionen vergleichen

Beide ZIPs mit 0600 in das private Arbeitsverzeichnis legen. Der Vergleich ist rein lesend und benötigt keine Datenbankverbindung:

```sh
python3 tests/documents/gobd-package.py \
  --action=compare \
  --before="$PRIVATE_WORK/earlier-export.zip" \
  --after="$PRIVATE_WORK/later-export.zip" \
  --output="$PRIVATE_WORK/export-comparison.json"
```

Der JSON-Bericht trennt Dateien, Tabellen-/Spaltenstruktur, Metadaten, Datensatzanzahlen, geänderte Exportmitgliedschaft, Geschäftswerte, finanzielle Werte, Statuswerte sowie Dokument-/PDF-Inventare. Neu gelieferte ganze Datensätze werden von Zeilenänderungen in bereits vorhandenen CSVs getrennt. Sichtbare Nummern sind keine eindeutigen Vergleichsschlüssel; interne IDs bleiben maßgeblich. Ein geänderter Zeitraum oder Filter ist keine nachgewiesene Datenbanklöschung.

Alle Einzelabweichungen stehen vollständig in der zusätzlich erzeugten privaten `<Berichtsname>.changes.jsonl`; es gibt keine stillschweigende Begrenzung auf eine Stichprobe. Gleichwertige reine Dezimalformatierung wird separat ausgewiesen. Ausschließlich `completed: 0/leer → 1` bei unverändertem `state=105` und ansonsten unveränderten gemeinsam vorhandenen Werten wird als **Normalisierungskandidat** ausgewiesen. Das bestätigt weder die Ursache noch einen Versand und ändert keine Daten. Die konkrete Erklärung bleibt durch Betreiberunterlagen zu belegen. Finanzielle Änderungen werden davon unabhängig ausgewiesen. Der Vergleich endet mit fachlicher Prüferfordernis; er ist keine automatische Freigabe.

Benötigt werden Python 3.11 oder neuer mit Standardbibliothek unter Linux/Unix, PHP/TCPDF für die synthetischen Regressionen und lokal `pdftotext` für die begrenzte Texthilfe. Fehlende/abgebrochene Texterkennung erzeugt keine automatische positive Prüfung. Die PDF-Analyse ist durch Größe, Laufzeit, Arbeitsspeicher und Textausgabe begrenzt; Inhalte sind kein aktiver Anwendungs- oder Shell-Code.
