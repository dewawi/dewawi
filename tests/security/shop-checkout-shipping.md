# C4B – Versand im Checkout und ERP

Stand: 2026-11-21. Anwendungsversion unverändert 1.1.8.

**Fortschreibung durch C5B (2026-11-22):** Dieser Bericht beschreibt C4B und dessen historische Snapshotversion 2. Neue Bestellungen verwenden Snapshotversion 3 und den [C5B-Steuervertrag](../shops/shipping-tax.md). Auf ausdrückliche fachliche Entscheidung entfällt die Legacy-Freigabe für neue Abschlüsse mit unbekannten Versandkosten: Anfrage beziehungsweise Kontakthinweis. Historische Versionen 1 und 2 behalten ihre Anzeige- und Übernahmeregeln. C5B ergänzt das bestätigte DE-Profil und die gruppenbezogene Versandsteueraufteilung; die hier beschriebenen damaligen Steuergrenzen sind deshalb kein aktueller Freigabekatalog.

## Berechnung und Abschluss

`ShippingConfiguration::calculateCheckout()` bietet ausschließlich lesenden Zugriff auf den bestehenden C4A-Kern. Shop und Mandant stammen aus `SiteContext`; Checkout-/Warenkorb-Features und der gespeicherte Checkout-Schalter werden geprüft. Artikel, Preise, Versandklassen, Verpackungsdaten und Tarife werden aus dem Serverbestand gelesen. Die administrativen Methoden behalten ihre Rechteprüfungen.

Der Kunde wählt Lieferung oder ausdrücklich aktivierte Selbstabholung. Das tatsächliche Lieferland stammt aus der normalisierten Lieferadresse. Der bestehende zweistufige Ablauf bleibt erhalten: Formular, dann Prüfung. Warenwert, Versandart, Versand netto, Netto-Gesamtsumme, Umsatzsteuer und Brutto-Gesamtsumme werden getrennt dargestellt. Die seitliche Übersicht wird nach der Prüfung aktualisiert; Eingabeänderungen kennzeichnen die Beträge als erneut zu prüfen. Es gibt keine Berechnungsanfragen bei jedem Tastendruck.

`calculated` erlaubt den Abschluss bei vollständig bestimmter Steuer. `quote_required` bietet bei aktiviertem Kontakt-/Anfrage-Feature eine ausdrücklich unverbindliche Frachtanfrage an. Diese benutzt `shopinquirydata`, `saveOnce()`, die bestehenden Kontaktbenachrichtigungen und die bestehende Anfrageverwaltung. Liefer-/Rechnungsadresse und Warenkorb werden serverseitig übernommen. Interne Berechnungsdaten bleiben gespeichert, erscheinen aber nicht als technische Formularfelder in der Anfrageansicht. Ohne entsprechendes Feature wird die Kontaktaufnahme empfohlen. `unavailable` bietet keinen Bestellabschluss an; Adresse oder Versandart müssen geändert werden.

Ohne `shippingcurrency` bleibt der bisherige Legacy-Checkout erhalten. Der neue Snapshot kennzeichnet dies ausdrücklich durch `legacy: true`, `status: quote_required` und einen unbekannten Versandbetrag. Die angezeigten und gespeicherten bisherigen Summen enthalten keine Versandkosten; Prüfung, Bestätigung, ERP-Ansicht und E-Mail weisen darauf hin. Das ist die begrenzte Kompatibilitätsausnahme für noch nicht konfigurierte Shops und wird nicht als kostenloser Versand behandelt. Konfigurierte Shops fallen bei fehlerhaften oder unvollständigen Tarifen nicht auf diesen Legacy-Pfad zurück.

## Steuergrenzen

Die vorhandene ERP-Komponente `Calculate` berechnet Nettopositionen, gruppiert Umsatzsteuer nach Steuersatz und rundet je Steuergruppe. Eine allgemeine Versand-/Nebenleistungsaufteilung oder länderabhängige Steuerermittlung existiert dort nicht.

Für einen einheitlichen bekannten Artikelsteuersatz bei übereinstimmendem Rechnungs-, Liefer- und Mandantenland übernimmt die Versand-Nebenleistung diesen gespeicherten Satz. Es gibt keinen fest programmierten Steuersatz. Das Mandantenland muss im vorhandenen `client.country` eindeutig als zweistelliger ISO-Code vorliegen; Freitext oder ein fehlender Wert lösen eine Prüfung aus. Die Versandsteuer entspricht der Differenz zwischen der gerundeten Dokumentsteuer mit und ohne Versand. Dadurch bleiben auch Rundungsfälle wie zweimal 0,03 netto bei 19 % mit dem ERP identisch.

Bei gemischten Steuersätzen und positiven Versandkosten, unbekannten Artikelsteuern, abweichenden Ländern oder unbekanntem Mandantenland wird die Lieferung zur Anfrage. Bekannte Versand-Nettobeträge bleiben sichtbar und gehen in die bekannte Netto-Zwischensumme ein; Umsatzsteuer und Brutto-Gesamtbetrag bleiben unbekannt. Bei echtem Nullversand beziehungsweise Selbstabholung ist keine Aufteilung einer positiven Nebenleistung nötig; bekannte gemischte Artikelsteuern bleiben im eindeutigen Inlandfall berechenbar.

Offene fachliche Entscheidungen für weitergehenden verbindlichen Checkout: Verteilung positiver Versandkosten auf gemischte Steuergruppen sowie Bestimmung der zulässigen Artikel-/Versandsteuer bei länderübergreifenden Lieferungen und Steuerbefreiungen. C4B schätzt dafür keine Werte. Für solche Fälle muss vor der Bestellung ein vollständiges Angebot erstellt werden.

## Unveränderliche Speicherung und ERP

Neue Checkout-Snapshots haben Version 2 und enthalten das vollständige C4A-Ergebnis unter `freight`: Methode, Status, Land, Währung, Nettobetrag, Steuer, Brutto, Steuerbasis, Gruppen, Mitglieder, Verpackungs-/Preisgrundlagen, Tarife und Konfigurationshash. Der C4A-Berechnungsstand bleibt Version 1. Historische Order-/Positionssnapshots der Version 1 bleiben lesbar und konvertierbar wie bisher.

Adresse, Warenkorb, Artikel-/Steuerwerte und Versandkonfiguration sind an den Review-Fingerprint gebunden. Vor dem Speichern werden Artikel und Versand erneut geprüft. Innerhalb der bestehenden Bestelltransaktion wird zusätzlich die Shopzeile gesperrt und erneut verglichen; die C4A-Verwaltung verwendet dieselbe Shop-Sperre. Abweichungen erzwingen eine neue Prüfung. Der bestehende Token-Abschluss und die lesende Wiederherstellung nach Antwortverlust bleiben erhalten. Frachtanfragen verwenden einen aus Checkout und geprüftem Inhalt abgeleiteten Idempotenzschlüssel und leeren den Warenkorb nicht.

Das ERP besitzt keine separate berechnete Versandkostenstruktur. Die gespeicherte Nebenleistung wird deshalb als reguläre Belegposition mit Menge 1, gespeichertem Nettopreis und Steuersatz übernommen, ohne Artikelreferenz oder erfundene Artikelnummer. Selbstabholung/Nullversand bleiben als Nullposition nachvollziehbar. Die bestehende ERP-Berechnung muss alle drei Bestellsummen exakt bestätigen; sonst wird die gesamte Übernahme zurückgerollt. Aktuelle Versandtarife werden dabei nicht gelesen.

Kunden- und interne Bestellbenachrichtigungen erhalten ihre Artikel und Summen aus der bereits gespeicherten Bestellung. Öffentliche Formularwerte können diese Summen nicht ersetzen. Es gibt keine neuen automatischen Mail-Wiederholungen oder Zahlungsfunktionen.

## Dateien

- Öffentlicher Ablauf: `application/modules/shops/controllers/CheckoutController.php`, `services/ShippingConfiguration.php`, `services/OrderSnapshot.php`, `models/DbTable/Order.php`.
- ERP: `application/modules/shops/services/OrderWorkflow.php`, `views/scripts/order/view.phtml`.
- Darstellung: `application/modules/shops/views/scripts/checkout/`, `js/shops.js`, DE/EN-Shopübersetzungen.
- Benachrichtigungen/Anfragen: `application/controllers/helpers/Email.php`, `application/modules/contacts/services/InquiryView.php`.
- Regressionen: `tests/security/shop-checkout-shipping-db.php`, erweiterte Checkout-/Mail-/Anfragetests und `tests/site/checkout-shipping-*`.

## Verifikation

Ausgeführt ausschließlich mit temporären Datenbanken, Sessions, Browserprofilen und Mail-Fixtures; kein SMTP-Versand an echte Empfänger:

- `python3 -B tests/security/shop-checkout-db.py`: 871 Prüfungen, davon die bisherigen 740 C1–C3-Prüfungen und 131 zusätzliche C4B-Fälle. Geprüft werden öffentliche Versandberechnung, Manipulation, Adresse, Tarifänderung, fehlende Klasse/Verpackung, Selbstabholung, Gratisversand, Frachtanfrage/Replay, Steuergrenzen, Rundung, Snapshot-Isolierung, konkurrierende Konfigurationsschreiber und identische ERP-Summen. Chromium prüft DE/EN sowie 1280/390/320 Pixel, einschließlich Seitenübersicht und Anfrageabschluss.
- `php tests/security/shop-shipping.php`: 404 unveränderte Kernprüfungen, einschließlich gemischter Gruppen, weiterer Paletten und Sperr-/Großgutgrenzen.
- `python3 -B tests/security/shop-shipping-db.py`: Schema-Gleichheit frischer Installation und echtem M1-Upgrade, wiederholtes Apply, 70 Persistenz-/Rechtefälle und 147 HTTP-/Chromium-Prüfungen.
- `python3 -B tests/install/migrations.py`: 124 M1-Prüfungen.
- `python3 -B tests/install/import-db.py`: vollständiger Installer, historische Upgrade-Gleichheit und 66 UOM-Prüfungen. Die protokollierte simulierte UOM-Ausnahme gehört zum bestandenen Rollbacktest.
- `php tests/security/permissions.php`: 450; `tenant-access.php`: 269; `form-input-validation.php`: 20; `tests/items/shop-assignment.php`: 26.
- `php tests/documents/calculation.php`: 736; `tests/security/shop-inquiries.php`: 58; `contact-inquiries.php`: 47.
- `php tests/security/shop-email-content.php`: 84; `shop-email-recipients.php`: 52, einschließlich gespeicherter Summen für beide Empfänger, manipulierter Browserdaten und abgeschirmter Mail-Routingfelder beim Übergang zur Frachtanfrage.
- `php tests/translations/runtime.php`: 157. PHP-/PHTML- und Python-Syntax geprüft. Node ist nicht installiert; das geänderte JavaScript wird durch die tatsächlichen Chromium-Abläufe geprüft.

## SQL und Betriebsaufnahme

Keine neuen Tabellen oder Spalten erforderlich: vorhandene Snapshotfelder werden wiederverwendet. Keine SQL-Datei, eingefrorene Baseline, Anwendungsversion oder M1-Migration wurde geändert. Keine produktive Datenbank wurde verwendet. Es gibt keine C4B-SQL-Nacharbeit; die bereits zu C4A.2 gehörige Versandmigration muss vor Nutzung dieser Versandkonfiguration regulär installiert sein. Vor einer später gesondert freigegebenen Betriebsaufnahme sind Tarife, aktivierte Versandoptionen und das eindeutig gepflegte Mandantenland zu prüfen. Bestehende Bestellungen werden weder ergänzt noch neu berechnet.

Kein Push, kein Deployment, keine Versionserhöhung.
