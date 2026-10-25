# FIN-03 – E-Rechnungsdaten und Vorprüfung

Stand: 25.10.2026. Keine XML-Erzeugung, kein neuer HTTP-Endpunkt, keine Schemaänderung.

## Verwendung

Der bestehende `Sales_Service_DocumentPayloadService` stellt zusätzlich bereit:

```php
$result = (new Sales_Service_DocumentPayloadService())->prepareEInvoice($invoiceId, $export, 'xrechnung');
```

Profil `en16931` dient der gemeinsamen Datenbasis, auch für einen späteren ZUGFeRD-EN16931-Export. Profil `xrechnung` ergänzt die geprüften deutschen Anforderungen. `$result` enthält `data`, `errors` mit `rule`, `field`, `message`, außerdem `warnings`, `valid` und stets `xml_validation_required = true`. `valid` bezeichnet nur diese Datenvorprüfung, keine vollständige EN-/XRechnung-Konformität.

Ohne View-Berechtigung für Verkaufsrechnungen oder bei fremden/gelöschten Rechnungen wird kein Payload zurückgegeben. Ein explizit gewähltes Bankkonto benötigt zusätzlich die bestehende Kontakt-View-Berechtigung und muss zum ausgewählten Mandanten gehören. Der Aussteller wird über `invoice.clientid` und den ausgewählten Mandanten ermittelt, nicht über den Heimatmandanten des angemeldeten Benutzers. Die bestehenden Rechnungszugriffsregeln werden nicht erweitert.

## Vorhandene und fehlende Angaben

| Inhalt | Quelle / Behandlung |
| --- | --- |
| Rechnungsnummer, Datum, Währung, Liefer-/Leistungsdatum | `invoice.invoiceid`, `invoicedate`, `currency`, `deliverydate` |
| Käufer einschließlich Anschrift und USt-ID | Gespeicherte `invoice.billing*`, `contactid`, `vatin`; keine Ersetzung durch aktuelle Kontaktdaten oder Exportparameter |
| Ausstellername und Anschrift | Aktuelle `client.company`, `address`, `postcode`, `city`, `country`; Kontakt-E-Mail aus `client.email` |
| Positionen | `invoicepos`: Identität, Bezeichnung, Beschreibung, SKU, Menge, Einheit, Preis, Währung, Steuersatz und Betrag |
| Rabatte/Zuschläge | Bestehende `pricerulepos` und die tatsächliche Vererbung auf Unterpositionen; effektiver Einzelpreis vom bestehenden PriceRule-/Calculate-Helper |
| Netto/Steuern/Brutto | Gespeicherte Rechnungsbeträge bleiben erhalten. Gegenprüfung über `Calculate::calculate()`, niemals über den schreibenden `direct()`-Pfad. Steuergruppen aggregieren dessen Positionsnettos und übernehmen dessen Steuerbeträge. Abweichungen werden gemeldet, nicht repariert. |
| Zahlungsbetrag | Bestehendes `buildPaymentData()` mit zusätzlichem optionalem Parameter für einen vorzeichenbehafteten Saldo; das bisherige PDF-Verhalten bleibt Standard |
| Bankdaten | Vorhandene `bankaccount`-Zeile nur bei expliziter Auswahl durch `payment.bank_account_id`; keine automatische Verwendung eines Kundenkontos als Verkäuferkonto |

Nicht eindeutig strukturiert gespeichert sind insbesondere Aussteller-Steuerkennungen und gegebenenfalls eine separate Lieferantenkennung, UN/ECE-Einheitencodes, die präzise Nullsteuer-/Befreiungskategorie, Befreiungsgründe, standardisierte Zahlungsart und rechnungsspezifische Zahlungsbedingungen/-ziele. Für XRechnung fehlen zusätzlich eindeutige Käuferreferenz, Geschäftsprozesskennung, Aussteller-Ansprechpartner/Telefon und elektronische Adressen samt Kennungsschema. Ein Rechnungs-Leistungszeitraum ist ebenfalls nicht vorhanden.

Diese Angaben können ausschließlich für die Vorprüfung in `$export` übergeben werden:

- `seller`: `vat_id`, `tax_id`, `identifier`, `contact_name`, `contact_phone`, `contact_email`, `endpoint`, `endpoint_scheme`.
- `buyer`: nur `endpoint`, `endpoint_scheme`; `buyer_reference` und `business_process` auf oberster Ebene. `invoice.reference` wird wegen seiner unklaren fachlichen Bedeutung nicht ungeprüft als BT-10 verwendet.
- `payment`: `means_code`, `bank_account_id`, `due_date`, `terms`.
- `unit_codes`: Zuordnung gespeicherter Einheitenbezeichnung zu UN/ECE-Code, z. B. `['Stück' => 'H87']`.
- `tax`: `category`, `exemption_reason`, optional je Position in `line_tax[Positions-ID]`.
- `period_start`, `period_end`.

Es wurden keine neuen Datenbankfelder eingeführt. Für diese reine Vorbereitung ist keine Migration erforderlich. Vor einer späteren dauerhaften Erfassung dieser Angaben muss ihre fachliche Zuordnung festgelegt werden. Aktuelle Aussteller-/Bankstammdaten und explizite Metadaten sind keine historischen Snapshots und müssen mit dem Original abgeglichen werden. Das Ergebnis enthält dafür einen Hinweis. Bestehende Rechnungen, interne Notizen und Original-PDFs werden nicht verändert; Notizen und Mandantentoken gelangen nicht in den Payload.

## Regelbasis und Umfang

Geprüfte Basis ist [XRechnung 3.0.2](https://xeinkauf.de/app/uploads/2024/07/302-XRechnung-2024-06-20.pdf), ergänzt durch die veröffentlichten [EN-16931-Geschäftsregeln](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/). Die [KoSIT-Dokumentübersicht](https://xeinkauf.de/dokumente/) führt daneben XRechnung 4.0 ausdrücklich als Vorversion. FIN-03 verwendet diese Vorversion nicht.

Geprüft werden Kernangaben und Datentypen, Positionsbezeichnung/Mengen/Preise, gespeicherte Beträge gegenüber der vorhandenen Berechnung, bekannte Länder-/Währungscodes über die vorhandenen Zend/CLDR-Daten sowie die explizit unterstützten Einheitenzuordnungen. Die originalen Regeln und Codelisten müssen später zusätzlich auf das erzeugte XML angewendet werden; der vorhandene CLDR-Datenstand ersetzt keine versionierte Export-Codeliste.

- Steuersätze größer null werden standardmäßig als `S` vorbereitet. Unterstützte Steuerfälle sind `S`, `Z`, `E`; Steuersatz und Kategorie müssen zusammenpassen. Für `S/Z/E` ist eine Aussteller-USt-ID oder Steuernummer erforderlich; für die Lieferantenidentifikation genügt eine Steuernummer allein nicht. Siehe [BR-S-02](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-S-02/), [BR-Z-02](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-Z-02/) und [BR-E-02](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-E-02/).
- `taxfree` oder ein Nullsteuersatz legt keine fachliche Kategorie fest. `E` verlangt einen Befreiungsgrund ([BR-E-10](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-E-10/)); bei `Z` wird kein Befreiungsgrund erfunden. Reverse Charge, innergemeinschaftliche Lieferung, Export und nicht umsatzsteuerbare Vorgänge werden ausdrücklich als noch nicht unterstützte Fälle gemeldet.
- Ein positives Zahlungssaldo verlangt Zahlungsziel **oder** Zahlungsbedingungen ([BR-CO-25](https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-CO-25/)). Unterstützte Zahlungsarten sind Barzahlung (`10`), Überweisung (`30`) und SEPA-Überweisung (`58`); ein Konto wird nur bei Überweisung verlangt. SEPA erfordert eine geprüfte IBAN, BIC wird nicht pauschal verlangt. Andere Zahlungsarten werden als noch nicht unterstützt gemeldet, statt fehlende Karten-/Mandatsdaten zu erfinden.
- XRechnung ergänzt insbesondere Ansprechpartner, Stadt/Postleitzahl beider Parteien, Käuferreferenz, Zahlungsart und Geschäftsprozess sowie elektronische Adressen. Für EN 16931 werden Stadt/Postleitzahl, Käufer-USt-ID und BIC nicht pauschal als Pflichtfelder behandelt. Ein Leistungsdatum/-zeitraum wird übernommen, soweit vorhanden; es wird keine allgemeine Pflicht aus der angekündigten XRechnung-4.0-Vorversion abgeleitet.

Die Vorprüfung ist für diese Fälle gezielt begrenzt. Weitere gültige Steuerfälle, Zahlungsarten, Einheiten oder Adressschemata sind nicht automatisch ungültige Rechnungen: Sie werden als fehlende Zuordnung beziehungsweise nicht unterstützter Vorprüfungsfall gemeldet. Vollständige Codelisten-, Länder-Steuer-ID-, Transport-, Syntax- und Schematron-Prüfung sowie XML-/ZUGFeRD-Erzeugung sind nicht Bestandteil von FIN-03. FIN-04 wurde nicht begonnen.

## Tests

`php tests/documents/e-invoice.php` nutzt die vorhandene isolierte Dokument-Fixture und echte Modelle/Helper. Schreibzugriffe sind verboten. Jeder reguläre Prüflauf kontrolliert außerdem den vollständigen Datenbestand und den globalen Währungskontext auf Unverändertheit. Abgedeckt sind beide Profile, Empfänger/Aussteller, fehlende Pflichtangaben, gemischte Steuersätze, Steuerbefreiung/Nullsteuer, Rabatte und Vererbung, Fremdwährung, Zahlungsbedingungen, Überzahlung, IBAN, Perioden, fremde/gelöschte Mandanten-Datensätze, Bankkonten und Berechtigungen.

Erfolgreich ausgeführt:

- PHP-Syntaxprüfung beider geänderten/neuen PHP-Dateien und `git diff --check`.
- `php tests/documents/e-invoice.php`: 152 Prüfungen.
- `php tests/documents/calculation.php sales`, `purchases`, `processes`: 645 / 621 / 615 Prüfungen einschließlich der gemeinsamen Positions-Fixture.
- `php tests/documents/pdf.php`: 134 Prüfungen; `php tests/documents/pdf-totals.php`: 170 einschließlich der gemeinsamen PDF-Fixture.
- `php tests/documents/lifecycle.php`: 842 Prüfungen.
- `php tests/security/document-edits.php`: 7398 Prüfungen.

Keine Tests gegen Produktionsdatenbanken und keine XML-/Schematron-Validierung ausgeführt.

Geänderte Dateien: `application/modules/sales/services/DocumentPayloadService.php`, `tests/documents/e-invoice.php`, diese Dokumentation.
