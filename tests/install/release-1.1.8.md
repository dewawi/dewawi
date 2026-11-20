# DEWAWI 1.1.8 – lokale Release-Prüfung

Prüfdatum und Release-Datum: **20.11.2026**. Ausgangsstand: `8f74693b`.
Veröffentlichter Vergleichsstand: Tag `1.1.7`, Commit
`7e0f63a2b18c7280cf0b85e6765b50fb0e23b730`.

Die Release-Vorbereitung ändert die beiden tatsächlichen Versionsanzeigen auf
`1.1.8`, synchronisiert die normative Versionsangabe in `AGENTS.md` und die
zugehörige Übersetzungs-Prüfausnahme und ergänzt das zentrale
[Changelog](../../CHANGELOG.md). Historische Berichte, alte Migrationen und
synthetische Tests für frühere Versionsstände bleiben erhalten. Es wurde kein
neuer Migrationsrunner und keine 1.1.9-Migration angelegt.

## Schema und Upgrade

`python3 -B tests/install/import-db.py` prüft den echten Installer mit aktueller
Struktur und Initialdaten, sichere SQL-Fehlerbehandlung, die bisherigen
Upgrade-Fixtures, teilweise ausgeführte/wiederholbare gezielte Schritte und
66 UOM-Prüfungen. Der Test wurde um den vollständigen unveränderten Upgrade-Lauf
aus dem tatsächlichen veröffentlichten 1.1.7-Schema ergänzt. Er vergleicht alle
124 Tabellen mit der frischen Installation, einschließlich Spaltendefinitionen,
Defaults, Indizes und Constraints. Spaltenreihenfolge und aktuelle
AUTO_INCREMENT-Zähler sind dabei keine semantischen Schemaunterschiede.

**Ergebnis: bestanden.** Einziger Unterschied ist der aus 1.1.7 geerbte zusätzliche
Index `address.street`, auf der getesteten MariaDB mit Präfix 1024. Er wird durch
das Upgrade unverändert erhalten und ist für die Anwendung nicht erforderlich.
Der Test prüft ausdrücklich die Erhaltung der ursprünglichen Indexdefinition;
alle übrigen Definitionen müssen übereinstimmen. Kein kritischer Schemaunterschied
und keine zusätzliche SQL-Nacharbeit für einen unveränderten 1.1.7-Ausgangsstand.

Die SQL-Dateien wurden durch diese Release-Vorbereitung **nicht geändert**:

| Datei | SHA-256 |
| --- | --- |
| `install/config/structure.sql` | `0e984ecbb67d6d78ea7b37d94d78c02d6d0f7dd453056a728c4c3096e4e1eb97` |
| `install/config/update-1.1.7-1.1.8.sql` | `3ba7aa1e20985927ca3af47240320e09dc7c945f8a05c5f585f84b1e01e636be` |

Bei bereits teilweise oder vollständig ausgeführter aktiver Migration bleibt
eine individuelle Zustandsprüfung Pflicht: insbesondere spätere Kalender-,
Dokumentkorrektur-, ALBIS- und Bestell-Snapshot-Ergänzungen können noch fehlen.
Nur tatsächlich fehlende Schritte der offiziellen Datei nach geprüftem Backup
anwenden. Der gesamte Upgrade-Lauf ist nicht idempotent. Die erfolgreiche lokale
Prüfung registriert keine reale Installation als migriert.

## Abschließende Prüfungen

Umgebung: PHP CLI 8.3.6, MariaDB 10.11.14, lokale Chromium-Fixtures.
Alle Datenbanken verwendeten eigene temporäre Verzeichnisse, ausdrückliche
Unix-Sockets und `--skip-networking`. HTTP und SMTP liefen ausschließlich lokal
mit synthetischen Datensätzen und Empfängern.

| Aufruf | Ergebnis |
| --- | --- |
| `python3 -B tests/install/import-db.py` | PASS: Neuinstallation, vollständiges veröffentlichtes Upgrade, Vergleich aller 124 Tabellen, Teilzustände/Fehler und 66 UOM-Prüfungen |
| `python3 -B tests/install/backup-restore.py` | PASS: 38 Backup-/Restoreprüfungen mit synthetischen Dateien und Daten |
| `python3 -B tests/items/technical-data.py` | PASS: echtes 1.1.7-Upgrade, technische Daten-/Rechner-/Historien-/Parallelitätsprüfungen bis D7B2A und mobile/Desktop-HTTP-/Browserabläufe |
| `python3 -B tests/items/stock-movements-db.py` | PASS: Lagerbewegungen, Filter, Sortierung, Pagination und Mandantentrennung |
| `python3 -B tests/calendar/run.py` | PASS: Datenbank-, Zeit-/DST-, Rechte-, Erinnerungs-, Teilnehmer-, ICS-/SMTP-, HTTP- und Browserabläufe bei 1280/390 px |
| `python3 -B tests/security/shop-checkout-db.py` | PASS: 17 Checkout-, 72 Bestell-/ERP-/Parallelitäts- und 44 HTTP-/CSRF-/Browserprüfungen einschließlich deaktiviertem Checkout |
| `python3 -B tests/security/shop-inquiry-quote.py` | PASS: isolierte Anfrage-/Angebots-/PDF-Prüfungen, HTTP-Schritte, Katalog-/Variantenauswahl und Browserabläufe bei 390/1280 px |
| `python3 -B tests/site/albis-leasing-http.py` | PASS: 140 PHP- und 70 Chromium-Prüfungen; API-Antworten simuliert |
| `python3 -B tests/documents/document-recovery-db.py` | PASS: 3263 Wiederherstellungsprüfungen einschließlich 501 lesbarer Kopienprüfungen und 20 DE/EN-Desktop-/Mobil-Panels |
| `python3 -B tests/documents/document-archive-integrity-db.py` | PASS: 164 Archiv-, 27 Assessment-, 40 Revisions-, 84 Rechnungskorrektur-, 93 historische Validierungs- und 338 Dateiwiederherstellungsprüfungen; exakte lokale SMTP-Anhänge und acht Browser-Panels |
| `python3 -B tests/translations/validate.py --stage T6 --browser` | PASS: alle 20 Prüfschritte, darunter Syntax für 210 PHP-Dateien, 157 Runtimeprüfungen, strikter Audit und identischer Wiederholungslauf |
| `python3 -B tests/translations/acceptance.py --report /tmp/dewawi-118-audit.json --extended` | PASS: 149 HTTP-/Chromium- und zwölf erweiterte Prüfungen |
| `php tests/translations/acceptance-pdf.php` | PASS: 72 PDF-Sprach-/Zahlenformatprüfungen |
| `python3 -B tests/translations/acceptance-mail.py` | PASS: 14 lokale SMTP-/MIME-Prüfungen |
| `python3 -B tests/ui/notifications.py` | PASS: je 1985 AJAX-/Auto-Save-/Aktions-/Modalprüfungen bei 1280 und 390 px |
| `python3 -B tests/security/tenant-access-http.py` | PASS: 60 Apache-/Objekt-/ACL-/AJAX-/Trash-Prüfungen für Root- und Unterpfadinstallation |

Zusätzlich bestanden 27 direkte PHP-Regressionssuiten:

```text
tests/security/{permissions,tenant-access,admin-access,bootstrap-auth,file-access,
attachments,document-edits,contact-roles,contactpersons,shop-inquiries,
inquiry-selection,inquiry-dynamic-form,smtp-routing}.php
tests/documents/{e-invoice,document-archive,position-read}.php
tests/items/stock-planning.php
tests/site/{technical-baseline,search,slugs,seo-metadata,robots-sitemap,
public-errors,legacy-redirects,legacy-content,conversion-tracking}.php
tests/contacts/contact-deletion.php
```

Der direkte Aufruf von `tests/security/shop-inquiry-quote.php` ohne isolierten
Socket meldet ausdrücklich SKIP und zählt nicht als bestandener Test. Der
zugehörige Python-Integrationsrunner wird separat ausgeführt und ausgewiesen.

Syntaxprüfungen aller sieben geänderten PHP-/PHTML-Dateien, Python-AST-Prüfung des
geänderten Installerrunners, JSON-Validierung sowie Versions-, Dokumentations-
und Diff-Prüfungen bestanden. Temporäre Rohprotokolle liegen unter
`/tmp/dewawi-118-*`; sie ersetzen keine Prüfung einer Zielinstallation.

## Behobene Prüfkontextfehler

- Der strikte Übersetzungsaudit erlaubt die technische Versionsanzeige anhand
  eines exakten Texts. Die Ausnahme wurde von 1.1.7 auf 1.1.8 synchronisiert;
  keine Qualitätsprüfung wurde deaktiviert.
- Die Kontaktrollenprüfung suchte verbotene Geschäftsbezüge in der gesamten,
  inzwischen erweiterten SQL-Datei und fand dabei unabhängige Rechnungstabellen.
  Sie untersucht nun gezielt den tatsächlichen Rollen-Übernahmeschritt;
  Mandanten-, Rollen- und Negativprüfungen bleiben bestehen.
- Die Lagerplanungsfixture verwendete feste, inzwischen vergangene Novemberdaten.
  Die Anwendung behandelt überfällige Ereignisse am aktuellen Tag. Die unveränderten
  Chronologie-, Mengen- und Liefererwartungen verwenden nun relative zukünftige Tage.
- Der Suchtest erwartete einen deutschen Literal-Platzhalter, obwohl das Layout
  bereits die vorhandene Übersetzungsfunktion verwendet. Er prüft nun den escaped
  Übersetzungsaufruf und beide tatsächlichen Sprachwerte.
- Die Rechnungskorrekturfixture führte vom eigenen SQL-Kommentar bis zum Dateiende
  auch später angehängte Shop-/ALBIS-Schritte aus, ohne deren Tabellen zu besitzen.
  Sie führt nun genau die drei Revision-/Korrektur-/Anhangsanweisungen aus und
  vergleicht deren Resultat weiterhin mit der Neuinstallation. Der vollständige
  offizielle Upgrade-Lauf wird unabhängig im erweiterten Installertest geprüft.

Keiner dieser Schritte änderte fachliche Anwendung, Berechtigungen, SQL-Dateien
oder vorhandene Negativ-Erwartungen. Anfängliche Socket-/Chromium-Beschränkungen
der Sandbox wurden durch autorisierte lokale Testausführung behoben. Ein Aufbau
der temporären Bestell-Testdatenbank scheiterte zunächst; der unveränderte Runner
bestand im anschließenden vollständigen Lauf.

## Betriebsgrenzen und Freigabeumfang

Es gab keinen Zugriff auf produktive Datenbanken oder Kundendaten. Reale
SMTP-/TLS-/ALBIS-Provider, Produktions-FPM, kundeneigene Erweiterungen, ein
separater MySQL-Server sowie Safari/WebKit, Screenreader und echte Touchhardware
wurden nicht abgenommen. Historische UI-Berichte mit weiteren Grenzen bleiben
unverändert. Nicht alle vorhandenen Repository-Tests wurden erneut ausgeführt;
die Tabelle und die PHP-Liste benennen die tatsächlich geprüfte Auswahl.

**Release-Status: GO für die lokale Release-Vorbereitung von 1.1.8.** Alle oben
aufgeführten abschließenden Prüfungen bestanden; kein offener kritischer
Release-Blocker im geprüften Umfang. Anwendung und Installer zeigen 1.1.8.
Dies ist keine Bestätigung einer externen Veröffentlichung oder eines produktiven
Upgrades. Kein Push, Git-Tag oder Deployment durchgeführt.
