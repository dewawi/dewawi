# Prüfung der Dokumentberechnung

Stand: 24.10.2026.

## Ergebnis und Rechenweg

Die geprüften Änderungswege berechnen die Dokumentwerte aus den gespeicherten Positionen und deren gespeicherten Preisregeln neu. Das gilt für Dokumentänderungen, Hinzufügen, Bearbeiten, Kopieren und Löschen von Positionen, Preisregeländerungen sowie Kopieren und Löschen von Positionsgruppen. Vor der erstmaligen Fertigstellung wird erneut berechnet und der gespeicherte Stand eingelesen.

- Preisregeln werden in ihrer gespeicherten Reihenfolge angewendet. Hauptpositionen geben ihre Regeln bei aktivierter Vererbung an Unterpositionen weiter.
- Positionsnetto = `round(regelberechneter Einzelpreis × Menge, 2)`.
- Die Zwischensumme addiert die gerundeten Positionsnettowerte.
- Steuerbeträge werden auf dieser Nettobasis je Steuersatz zusammengefasst und anschließend je Gruppe auf Cent gerundet. Gebrochene Steuersätze bleiben getrennt.
- Die Endsumme addiert Zwischensumme und gerundete Steuergruppen.
- Bei Steuerbefreiung sind alle Steuerbeträge null; auch gespeicherte Positionsbruttowerte berücksichtigen die Befreiung.
- Preise und Mengen behalten bei Eingabe und Speicherung die von den bestehenden Datenbankspalten unterstützten vier Nachkommastellen. Geldsummen werden auf Cent gerundet.
- PDF-Vorschauen von Entwürfen verwenden denselben Calculate-Helper. Gemischte Steuergruppen erscheinen separat im PDF. Anzahlung und Restbetrag beziehen sich auf die aktuelle numerische Endsumme.
- Preis- und Positionsänderungen sowie Gruppenkopien/-löschungen sperren das übergeordnete Dokument während ihrer Transaktion. Änderung und Neuberechnung werden gemeinsam bestätigt oder zurückgerollt. Fertigstellung sperrt ebenfalls das Dokument und prüft die Dokumentnummer erneut.
- Bereits ausgestellte Dokumente werden bei Anzeige oder erneutem Abruf nicht neu berechnet; vorhandene Original-PDFs und historische Summen bleiben erhalten.
- Positionsänderungen werden im Browser vor dem nächsten Schritt abgeschlossen. Verspätete ältere Antworten auf Positionsabfragen ersetzen keine neueren Ergebnisse.

## Geänderte Dateien

Zusätzlich zu den bei Arbeitsbeginn vorhandenen Änderungen wurden folgende Dateien für diese Aufgabe angepasst. Teile der Controller- und JavaScript-Anpassungen sind bereits im zwischenzeitlich hinzugekommenen Commit `b30af354` enthalten.

- `application/controllers/helpers/Calculate.php`
- `application/controllers/helpers/Position.php`
- `application/controllers/helpers/PriceRule.php`
- `application/modules/items/controllers/PriceruleposController.php`
- `application/modules/sales/controllers/PositionsetController.php`
- `application/modules/sales/controllers/helpers/Positions.php`
- `application/modules/sales/services/DocumentPayloadService.php`
- `application/modules/purchases/controllers/PositionsetController.php`
- `application/modules/purchases/controllers/helpers/Positions.php`
- `application/modules/purchases/services/DocumentPayloadService.php`
- `application/modules/processes/controllers/PositionsetController.php`
- `application/modules/processes/controllers/helpers/Positions.php`
- `application/modules/processes/services/DocumentPayloadService.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/Controller/PositionAction.php`
- `library/DEEC/Model/DbTable/Entity.php`
- `library/DEEC/Model/DbTable/Position.php`
- `library/DEEC/Pdf.php`
- `js/global.js`
- `tests/documents/position-read.php`
- `tests/documents/calculation.php`
- `tests/documents/calculation-db.php`
- `tests/documents/calculation-db.py`
- `tests/documents/pdf-totals.php`
- `tests/documents/calculation-review.md`

## Ausgeführte Prüfungen

Die 17 ausführbaren bestehenden/neuen PHP-Tests mit In-Memory-Fixtures und temporären PDF-Dateien wurden erfolgreich ausgeführt. Hinzu kamen die beiden weiteren Modulvarianten der Berechnungstests: insgesamt 19 erfolgreiche Testläufe. Die Berechnungstests enthalten 585 Prüfungen für Verkauf, 561 für Einkauf und 555 für Vorgänge; darin sind jeweils die gemeinsamen 229 Positionsleseprüfungen enthalten.

Wesentliche Befehle:

```sh
php tests/documents/calculation.php sales
php tests/documents/calculation.php purchases
php tests/documents/calculation.php processes
php tests/documents/pdf-totals.php
php tests/documents/lifecycle.php
php tests/security/document-edits.php
```

Zusätzlich erfolgreich: PHP-Syntaxprüfung aller betroffenen PHP-Dateien, `git diff --check`, vollständiges Parsen von `js/global.js` und eine JavaScript-Simulation vertauschter Positionsantworten.

Der zusätzliche Integrationstest wurde mit einer separaten MariaDB 10.11 unter `/tmp` ausgeführt. Die Instanz hatte ausschließlich einen lokalen Unix-Socket und keinen Netzwerk-Listener. Geprüft wurden echte DECIMAL-Speicherung, Preisregeln, Steuerbefreiung, Transaktions-Rollback nach einem Berechnungsfehler, Warten auf die Dokumentsperre, zwei gleichzeitige Fertigstellungen desselben Dokuments und Ablehnung eines veralteten Positionsspeicherversuchs nach Fertigstellung. Alle Prüfungen waren erfolgreich. Die Testinstanz und ihre Daten wurden danach entfernt; vorhandene Datenbanken wurden nicht verwendet.

Die aufbewahrte Fassung lässt sich mit folgendem Befehl erneut ausführen:

```sh
python3 tests/documents/calculation-db.py
```

Dafür werden die lokal bereits vorhandenen Programme `mariadb-install-db`, `mariadbd`, PHP mit PDO MySQL und Python 3 verwendet. Der direkte PHP-Aufruf ohne Test-Socket überspringt diesen separaten Integrationstest. Eine Sandbox muss das Anlegen des temporären Unix-Sockets erlauben.

## Kompatibilität und Prüfgrenzen

Es gibt keine Schemaänderung und keine neue Anwendungsabhängigkeit. Die bestehenden Schnittstellen bleiben aufrufbar; die Positionsmodelle akzeptieren zusätzlich einen optionalen Transaktionsparameter, damit Controller Änderung und Berechnung gemeinsam absichern können.

An Rundungsgrenzen können Entwurfssummen gegenüber der bisherigen ungerundeten Steuerspeicherung um Centbeträge abweichen. Die Steuergruppen und die angezeigte Endsumme stimmen anschließend überein. Ausgestellte historische Dokumente werden nicht nachträglich korrigiert. Synchrone Positionsänderungen können die Oberfläche während einer langsamen Speicheranfrage kurz blockieren.

Nicht ausgeführt wurde ein vollständiger Browser-End-to-End-Durchlauf mit einem angemeldeten Testmandanten, allen Vorlagen und sämtlichen externen Integrationen. Die Datenbank-Nebenläufigkeit wurde für die oben beschriebenen Szenarien geprüft. Daraus folgt keine uneingeschränkte 100%-Garantie für alle denkbaren Abläufe, direkte Datenbankänderungen oder externe Schreibwege.

Vorgeschlagene Commit-Nachricht: `fix document totals, tax exemption and price rule recalculation`
