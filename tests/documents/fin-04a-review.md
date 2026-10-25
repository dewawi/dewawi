# FIN-04A – XRechnung 3.0.2 / UBL Invoice 2.1

Stand: 25.10.2026. FIN-03 aus `a021a7ab` bleibt unverändert. Der zusätzliche vorhandene Commit zur Seitenblockverwaltung wurde nicht verändert.

## Aufruf und Verhalten

```php
$result = (new Sales_Service_XRechnungService())->generate($invoiceId, $export);
if ($result['valid']) {
    $xml = $result['xml'];
}
```

Der Service verwendet ausschließlich `Sales_Service_DocumentPayloadService::prepareEInvoice($id, $export, 'xrechnung')` als fachlichen Payload. Keine zweite Datenaufbereitung, keine Datenbankschreibzugriffe und keine Zugriffe auf das Original-PDF. Berechtigungs- und Mandantenfehler bleiben Exceptions aus dem bisherigen Zugriffspfad. FIN-03-Prüffehler bleiben unverändert im Ergebnis nutzbar; ohne erfolgreiche Vorprüfung wird kein XML erzeugt. Zusätzliche XML-/Betrags-/Umfangsfehler werden im gleichen Fehlerformat ergänzt, `xml` bleibt dann `null`.

Eine zusätzliche reine Leseprüfung des Lebenszyklus verlangt bereits vor der Aufbereitung eine nummerierte Rechnung mit `state = 105`, `completed != 0` und ohne Stornoflag. Entwürfe, unvollständige oder stornierte Rechnungen werden abgelehnt. FIN-01/FIN-02, normale PDFs und die bestehende FIN-03-Vorprüfung wurden nicht geändert.

DOMDocument erzeugt UTF-8-XML mit den UBL-Invoice-, CAC- und CBC-Namespaces. Abgebildet werden CustomizationID, ProfileID, Nummer, Datum, Typ 380, Währung, Käuferreferenz, Verkäufer/Käufer einschließlich Anschriften, elektronischer Adressen, Steuerkennungen und Ausstellerkontakt, Lieferdatum/-zeitraum, Zahlungsart, Kontoverbindung/Zahlungsbedingungen, Steueraufschlüsselung, Rechnungssummen und Positionen mit Mengeneinheit, Beschreibung, Artikelnummer und effektivem Einzelpreis.

Bereits eingerechnete Preisregeln werden nicht erneut als `AllowanceCharge` ausgegeben. Der effektive FIN-03-Einzelpreis enthält den Rabatt bereits. Geldbeträge werden mit zwei Nachkommastellen formatiert; Mengen, Steuersätze und Einzelpreise ohne wissenschaftliche Schreibweise und ohne pauschale Preisrundung. Kontonummern bleiben Kennungen; IBAN/BIC werden für die XML-Darstellung normalisiert. Summen und Steuergruppen werden centgenau gegengeprüft. Mengen-/Preisprodukt und Steuerformel werden ausschließlich zur Konsistenzprüfung mit den Payload-Beträgen verglichen; die Prüfergebnisse ersetzen niemals Preise, Steuern oder gespeicherte Beträge. Widersprüche werden abgelehnt, kein Rundungsausgleich wird erfunden.

## Freigegebener Umfang

- Deutsche Verkäufer und Käufer, EUR, Rechnungstyp 380, Geschäftsprozess `urn:fdc:peppol.eu:2017:poacc:billing:01:1.0`, elektronische Adressen mit Schema `EM`.
- `S` mit 19 % und 7 %, auch gemischt; `Z` und `E` mit dem ausdrücklich in FIN-03 angegebenen Steuerfall und gegebenenfalls Befreiungsgrund.
- Zahlungsarten `10`, `30`, `58`; alle neun Kombinationen der drei Steuerkategorien mit den drei Zahlungsarten wurden extern geprüft.
- Aussteller-USt-ID oder Aussteller-Steuernummer mit zusätzlicher Lieferantenkennung, optionale Käufer-USt-ID; vorhandene deutsche USt-IDs im Format DE und neun Ziffern. Die Formatprüfung bestätigt keine tatsächliche steuerliche Registrierung.
- Eine oder mehrere Positionen, bereits berechnete und vererbte Rabatte, Sonderzeichen/Umlaute, Anzahlungen, Überzahlung und kleine effektive Einzelpreise.

Nicht freigegeben: Fremdwährungen/Auslandsparteien, andere Geschäftsprozesse und elektronische Adressschemata, andere S-Steuersätze, Reverse Charge, innergemeinschaftliche Lieferung, Export/nicht umsatzsteuerbare Fälle, andere Zahlungsarten sowie strukturierte Skonto-Zahlungsbedingungen. Diese Fälle werden ausdrücklich abgelehnt. Keine Benutzeroberfläche, kein Download-Controller, kein Versand, keine Migration und kein ZUGFeRD-PDF. FIN-04B wurde nicht begonnen.

## Echte externe Validierung

Verwendet wurde das [offizielle Bundle XRechnung 3.0.2 / 2026-08-31](https://xeinkauf.de/aktuelles/xrechnung/xrechnung-bugfix-summer-2026/), Download:

`https://projekte.kosit.org/api/v4/projects/356/packages/maven/de/xeinkauf/xrechnung-3.0.2-bundle/2026-08-31/xrechnung-3.0.2-bundle-2026-08-31.zip`

- KoSIT Validator **1.6.3**, unveränderte `validator-1.6.3-standalone.jar` aus diesem Bundle.
- `xrechnung-3.0.2-validator-configuration-2026-08-31.zip`, unveränderte `scenarios.xml`; die enthaltene Konfiguration trägt das Erstellungsdatum 02.09.2026.
- UBL Invoice 2.1 XSD, EN-16931-Schematron **1.3.16** und XRechnung-Schematron **2.6.0** aus der Konfiguration.
- Portable Temurin-JRE 21 ausschließlich unter `/tmp`, weil Java ursprünglich nicht installiert war. Keine Systeminstallation und keine Werkzeuge/Bundles/JARs im Repository.

Bundle-SHA-256: `5f5426741a03e0b9abc7de83acd97309c09498241d132fc035f9c830be7203de`.

Validator-JAR-SHA-256: `799e64befca97d4080e03608c80b85dd5a5ecc5f4ae4f35d1116ec2855b9a7c9`.

Alle 14 tatsächlich erzeugten Fixtures wurden vom offiziellen Validator im Szenario **EN16931 XRechnung (UBL Invoice)** angenommen: **Schema Y, Schematron Y, ACCEPTABLE; 14 angenommen, 0 abgelehnt**.

| Fixture | Geprüfter Fall |
| --- | --- |
| `standard-s-58.xml` | Mehrere Positionen, 19 %, vererbter Rabatt, SEPA |
| `single-s-58.xml` | Einzelposition |
| `mixed-s-58.xml` | 19 % / 7 %, Sonderzeichen, Umlaute, Leistungszeitraum |
| `standard-s-30.xml` | Überweisung, Anzahlung 100 EUR, Zahlbetrag 178,46 EUR |
| `standard-s-10.xml` | Barzahlung, vollständig bezahlt |
| `seller-tax-id.xml` | Steuernummer/FC statt Aussteller-USt-ID, Käufer-USt-ID |
| `overpaid-s-58.xml` | Überzahlung, negativer Zahlbetrag unverändert |
| `fractional-price-s-58.xml` | Kleiner effektiver Preis nach Rabatt, keine Exponentialschreibweise |
| `standard-z-10.xml`, `standard-z-30.xml`, `standard-z-58.xml` | Nullsteuer mit jeder unterstützten Zahlungsart |
| `standard-e-10.xml`, `standard-e-30.xml`, `standard-e-58.xml` | Steuerbefreiung mit jeder unterstützten Zahlungsart |

Die Eingaben liegen im aktuellen Arbeitslauf unter `/tmp/dewawi-xrechnung-fin04a-xml`, die vollständigen KoSIT-Reports unter `/tmp/dewawi-xrechnung-fin04a-reports`. Nach Bereinigung von `/tmp` lassen sie sich mit den folgenden Befehlen erneut erzeugen; die Werkzeuge müssen dann erneut aus dem genannten Bundle entpackt werden:

```sh
php tests/documents/e-invoice.php /tmp/dewawi-xrechnung-fin04a-xml
/tmp/dewawi-fin04a-java/bin/java -jar /tmp/dewawi-fin04a-validator/validator-1.6.3-standalone.jar -s /tmp/dewawi-fin04a-validator/config/scenarios.xml -r /tmp/dewawi-fin04a-validator/config -o /tmp/dewawi-xrechnung-fin04a-reports /tmp/dewawi-xrechnung-fin04a-xml/*.xml
```

Die externe Validierung belegt diese konkreten Testrechnungen. Sie wird nicht bei jedem Service-Aufruf automatisch ausgeführt. `xml_validation_required` aus FIN-03 bleibt deshalb erhalten; `valid` bedeutet erfolgreiche Daten-/Exportvorprüfung und XML-Erzeugung, keinen neuen KoSIT-Prüflauf für beliebige aktuelle Stammdaten. Eine tatsächliche Rechnung benötigt vor Übermittlung weiterhin die Prüfung ihrer konkreten XML-Datei. Aktuelle Aussteller-/Bankdaten und explizite Exportmetadaten bleiben hinsichtlich der Übereinstimmung mit dem ausgestellten Original zu bestätigen, wie bereits in FIN-03 dokumentiert.

## Tests und Dateien

Erfolgreich ausgeführt:

- `php -l` für `application/modules/sales/services/XRechnungService.php` und `tests/documents/e-invoice.php`; `git diff --check`.
- `php tests/documents/e-invoice.php`: **352 Prüfungen**, einschließlich Berechtigungen, Mandanten, unveränderter FIN-03-Fehler, Abschlussstatus, widersprüchlicher Beträge, nicht unterstützter Fälle, XML-Escaping und verbotener XML-1.0-Zeichen. Jeder XML-Aufruf prüft Datenbestand, Schreibprotokoll, Währungskontext und Hash/Zeitstempel einer temporären Original-PDF-Fixture auf Unverändertheit.
- Bestehende Regressionen: `calculation.php sales` **645**, `pdf.php` **134**, `pdf-totals.php` **170**, `lifecycle.php` **842**, `tests/security/document-edits.php` **7398** Prüfungen. Die Zählungen enthalten teilweise gemeinsam geladene Fixtures.
- Offizieller KoSIT-Prüflauf für die oben aufgeführten **14** XML-Dateien; XSD und beide Schematron-Stufen erfolgreich.

Geänderte Dateien: neuer `Sales_Service_XRechnungService`, erweiterte `tests/documents/e-invoice.php`, diese Dokumentation. Kein Produktionsdatenbankzugriff. Keine Commits oder Pushes.

Commit-Vorschlag: `add validated XRechnung UBL invoice XML generation`
