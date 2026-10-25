# FIN-02 – Schutz abgeschlossener Rechnungen und Gutschriften

Stand: 25.10.2026.

## Korrekturen

- Der geerbte `cancel`-Endpunkt konnte auch abgeschlossene Belege auf Status 106 setzen. Der bestehende Readonly-Schutz greift jetzt auch hier, mit erneuter Prüfung unter der Dokumentsperre.
- Entwurfsänderungen und Löschungen prüfen den aktuellen Datensatz innerhalb ihrer Schreibtransaktion. Ein inzwischen abgeschlossener Beleg darf weder zurückgesetzt noch gelöscht werden, auch wenn nur Status/Abschlussflag und noch keine Nummer vorliegen.
- Positionssortierung sowie Hinzufügen, Bearbeiten und Sortieren von Verkaufs-Positionsgruppen sperren den übergeordneten Beleg während des Schreibens. Die bereits vorhandenen Sperren für Positionsänderungen, Gruppenkopien und Gruppenlöschungen bleiben bestehen.
- Der bestehende State-Plugin prüft weiterhin manipulierte POST-/AJAX-Daten, tatsächliche Positionszugehörigkeit, Mandant und Berechtigungen. Für interne Notizen bleibt ausschließlich `notes` mit Edit-Berechtigung erlaubt; der Notizpfad berechnet keine Beträge und erzeugt kein PDF.
- Vorhandene PDF-Originale werden weiterhin wiederverwendet und atomar vor Überschreiben geschützt. Fehlende Originale von Rechnungen/Gutschriften können aus dem vorhandenen Dokumentanhang bytegleich wiederhergestellt werden. Ohne Original und Anhang wird der Abruf mit `Original PDF is unavailable` abgelehnt, statt aus veränderlichen aktuellen Vorlagen ein neues Original zu erzeugen. Nur der erstmalige Abschluss autorisiert die erste PDF-Erzeugung intern; HTTP-Parameter werden dafür nicht übernommen.

Nummernvergabe und Abschluss-Transaktion aus FIN-01 bleiben unverändert. Folgebelege und Gutschriften werden weiterhin als eigene Entwürfe erstellt. Keine Migration und keine Änderungen an Formularen, Layouts oder Dateiablage.

## Geänderte Dateien

- `application/plugins/State.php`
- `application/modules/sales/controllers/PositionsetController.php`
- `library/DEEC/Controller/Action.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/Controller/PositionAction.php`
- `library/DEEC/Pdf.php`
- `tests/security/document-edits.php`
- `tests/documents/calculation.php`
- `tests/documents/position-read.php`
- `tests/documents/pdf.php`
- `tests/documents/fin-02-review.md`

## Ausgeführte Prüfungen

Alle folgenden Prüfungen waren erfolgreich:

- `php -l` für die zehn geänderten PHP-Dateien; `git diff --check`.
- `php tests/security/document-edits.php`: 7398 Prüfungen.
- `php tests/documents/pdf.php`: 134 Prüfungen; `php tests/documents/pdf-totals.php`: 170 Prüfungen einschließlich der gemeinsamen PDF-Fixture.
- `php tests/documents/lifecycle.php`: 842 Prüfungen.
- `php tests/documents/position-read.php`: 231 Prüfungen.
- `php tests/documents/calculation.php sales|purchases|processes`: drei getrennte Läufe mit 645, 621 und 615 Prüfungen einschließlich der gemeinsamen Positions-Fixture.
- `python3 tests/documents/calculation-db.py`: isolierte MariaDB mit echtem Rollback, parallelem Abschluss desselben Belegs und verschiedener Rechnungen, getrennten Mandantenzählern, Wiederholung und Zugriffssperren. Außerhalb der Sandbox ausgeführt, da diese Unix-Sockets verhindert.
- Bestehende Sicherheitsregressionen `admin-access.php`, `user-profile.php`, `profile-save.php` und `item-tags.php`: 48, 126, 100 und 80 Prüfungen.

## Separater Sicherheitsbefund: direkte Datei-URLs

Die Repository-Datei `.htaccess` leitet ausdrücklich nur `/files/attachments` durch die Anwendung. Vorhandene Dateien unter `/files/contacts` und `/cache/document` passieren dagegen die anschließenden Regeln für statische Dateien/Verzeichnisse. `DEEC_Pdf::getOutputFile()` gibt URLs in diesen beiden Bereichen zurück.

Wenn der Webserver diese Regeln verwendet und keine zusätzliche Zugriffssperre besitzt, umgehen direkte HTTP-Abrufe die Anmeldung, Belegberechtigungen und Mandantenprüfung der Controller. Kenntnis eines Dateipfades reicht dann zum Lesen; vorhersehbare Cache-Dateinamen verschärfen das Problem. Dieser Vertraulichkeitsbefund bleibt außerhalb der FIN-02-Korrektur offen. Eine Prüfung der produktiven Webserverkonfiguration oder ein HTTP-Test gegen Produktionsdateien wurde nicht vorgenommen. Die Dateiablage wurde nicht umgestellt.

## Grenzen

Ohne vorhandenes Original oder archivierten Anhang lässt sich das ursprüngliche PDF mit dem vorhandenen Datenmodell nicht verlässlich rekonstruieren. Das betrifft auch einen fehlgeschlagenen ersten PDF-Lauf nach bereits bestätigtem Abschluss. Ein späterer Abruf erzeugt in diesem Fall kein Ersatzoriginal aus aktuellen Vorlagen. Die Abschluss-Transaktion wurde dafür nicht verändert.

Controller-/Plugin-Tests verwenden isolierte In-Memory-Fixtures; PDF-Tests schreiben ausschließlich temporäre Dateien. Verspätete Schreibzugriffe werden zusätzlich durch einen simulierten Abschluss zwischen Vorprüfung und Transaktionsbeginn geprüft. Der bestehende MariaDB-Test prüft die realen Abschluss-Transaktionen separat. Ein vollständiger angemeldeter Browser-End-to-End-Test mit realen Mandantendaten ist damit nicht abgedeckt.
