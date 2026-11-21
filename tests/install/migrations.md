# M1 – lokale Migrationsprüfung

Prüfdatum: **21.11.2026**. Umgebung: PHP CLI 8.3.6, MariaDB 10.11.14.
Anwendungsversion bleibt **1.1.8**.

## Ergebnisse

| Prüfung | Tatsächlich ausgeführtes Ergebnis |
| --- | --- |
| `python3 -B tests/install/migrations.py` | PASS: 124 Prüfungen |
| `python3 -B tests/install/import-db.py` | PASS: echter Installer, vollständiges veröffentlichtes 1.1.7-Upgrade, Vergleich aller 124 Tabellen, historische Teil-/Fehlerzustände und 66 UOM-Prüfungen |
| `php -l tools/migrate.php` | PASS |
| `php -l tools/migrations/Migrator.php` | PASS |
| `php -l tests/install/migrations/schema.php` | PASS |
| Python-AST, JSON-Validierung und `git diff --check` | PASS |

Die M1-Prüfungen decken ab:

- Vollständige frische 1.1.8-Basis und vollständiges historisches Upgrade mit
  erhaltenem zusätzlichem `address.street`-Index; eingefrorener Schemaabgleich.
- Fehlende Tabelle/Spalte/Index/Fremdschlüssel, geänderte Defaults/Typen sowie
  ein Fremdschlüssel mit falscher Zieldatenbank verhindern die Registrierung.
- Standardaufruf und Plan ohne dauerhafte Schreibzugriffe; Status/Plan funktionieren
  mit reinen SELECT-Rechten. Eine Basisregistrierung benötigt Schreibrechte.
- Eine/mehrere synthetische DDL-/DML-Migrationen, feste Reihenfolge, mehrere
  Versionsübergänge, wiederholte Aufrufe ohne erneute Ausführung.
- Prüfsummenabweichung, fehlende Dateien/registrierte Migrationen/Abhängigkeiten,
  geänderte Abhängigkeitsdefinitionen und Lücken verhindern die Ausführung.
- Konsolidierte Updates und Einzelmigrationen verwenden in beiden Wechselrichtungen
  dieselbe Historie. Veränderte Bundles und fehlende Vorgänger werden abgewiesen;
  direkter SQL-Import scheitert am SIGNAL-Schutz.
- Ein Fehler in Anweisung 3 erhält die zuvor ausgeführten DDL-/DML-Anweisungen,
  schreibt `failed` und verhindert spätere SQL-Anweisungen sowie Wiederholungen.
- Ein getöteter CLI-Prozess nach DDL hinterlässt `started`; eine zweite
  Updater-/Basis-Ausführung wird währenddessen durch dieselbe Datenbanksperre
  verhindert. Nach Verbindungsende wird die Sperre freigegeben, der Teilzustand
  bleibt gesperrt und wird nicht automatisch repariert.
- Ungültige Historientabelle, falsche abgeschlossene Statement-Anzahl und
  Historienlücken werden erkannt; Session-Manipulation und ausführbare
  MySQL-/MariaDB-Kommentare sind in Migrationen ausgeschlossen.
- Verbindungsfehler geben weder Zugangsdaten noch rohe Exceptions aus.
- GET/POST direkt auf beide PHP-Einstiegspunkte ergeben 404, auch ohne Apache-Regeln.
- Daten-Dumps aller ursprünglichen Tabellen sowie zweier synthetischer Mandanten
  bleiben identisch.

Die Leserechteprüfung deckte die eingeschränkte Sichtbarkeit von
`REFERENTIAL_CONSTRAINTS` auf; der Runner liest Fremdschlüssel deshalb über
`SHOW CREATE TABLE`. Der Fremddatenbank-Test benötigte getrennte DROP-/ADD-Schritte
für denselben Constraint-Namen. Diese Befunde wurden vor dem abschließenden
erfolgreichen Gesamtlauf behoben. Die bestehende UOM-Suite protokolliert ihren
absichtlich ausgelösten Fehler und besteht einschließlich Rollback-Prüfung.

## Grenzen und Nacharbeit

Alle Datenbanken liefen in eigenen temporären Verzeichnissen mit ausdrücklich
gewähltem Unix-Socket und `--skip-networking`. HTTP lief nur auf Loopback.
Keine vorhandene Installation, Kundendatenbank oder Produktivdienste wurden
angesprochen. Ein separater MySQL-Server wurde nicht geprüft.

Die veröffentlichten SQL-Dateien und der alte Installer blieben unverändert.
M1 enthält keine realen 1.1.9-Migrationen und keine konsolidierte Release-Datei.
Vor 1.1.9 sind echte Feature-Migrationen, synchronisiertes Neuinstallationsschema,
geprüfte Historieninitialisierung für den zukünftigen 1.1.9-Neuinstallationssnapshot
und Freigaben separat erforderlich. Historische Datenübernahmen und unklare
Teilzustände benötigen die dokumentierte manuelle Zustandsprüfung; eine
Schema-Basisprüfung ersetzt diese nicht.

Bedienung und verbindlicher Release-/Wiederherstellungsablauf:
[tools/migrations/README.md](../../tools/migrations/README.md).
Kein Push, Deployment, Tag oder Versionswechsel.
