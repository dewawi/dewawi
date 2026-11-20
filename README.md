# DEWAWI

## Description

DEWAWI ist ein Open Source Warenwirtschaftssystem mit Fokus auf schnelle und effiziente Unternehmensprozesse.

## License

It is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License v3 or later, see http://www.gnu.org/licenses/gpl.html

## Requirements

  * PHP 5.3.3 or greater
  * MySQL 4.1 or greater, and either MySQLi or PDO library must be enabled
  * DEWAWI is OS / server independent

## Contact

http://www.dewawi.com

## Credits

- [TCPDF (6.6.5)](https://tcpdf.org/)
- [jQuery (1.12.4)](https://jquery.com/)
- [miniCSS (3.0.1)](https://minicss.org/)
- [TinyMCE (5.2.2)](https://www.tiny.cloud/)
- [Zend Framework (1.22.0)](https://framework.zend.com/)
- [ZF1 Future (1.22.0)](https://github.com/Shardj/zf1-future)
- [RESPONSIVE filemanager (9.14.0)](https://www.responsivefilemanager.com/)
- [PHPMailer (6.1.7)](https://github.com/PHPMailer/PHPMailer)
- [pChart (2.1.4)](http://www.pchart.net/)
- [Datejs (1.0)](https://github.com/datejs/Datejs)
- [Leaflet (1.9.4)](https://leafletjs.com)

## Shopbestellungen bearbeiten

Unter `shops/order` zeigen die Filter **Neu** und **Offene Bestellungen** die gespeicherten Bestellungen des freigegebenen Datenmandanten. Offen umfasst `new`, `in_progress` und `transferred`; historische Bestellungen ohne Status bleiben über den Filter „Unbekannt“ erreichbar. Ein deaktivierter öffentlicher Checkout verhindert die interne Bearbeitung vorhandener Bestellungen nicht.

Die Detailansicht zeigt gespeicherte Adressen, Positionen und Beträge. Eine vorläufige Kontaktzuordnung aus dem Checkout ersetzt die ausdrückliche Kontaktwahl bei der ERP-Übernahme nicht. Die Übernahme benötigt Anzeigen, Bearbeiten und Anlegen für Verkaufsaufträge sowie Anzeigen für Kontakte. Dabei gelten weiterhin die getrennten Mandantenfreigaben für Shops, Kontakte, Artikel und Verkaufsaufträge. Der erzeugte Verkaufsauftrag ist zunächst ein regulärer Entwurf. Änderungen am Bearbeitungsstatus der Shopbestellung ändern keine ERP-Dokumente und bestätigen weder Zahlung, Versand, Rechnung noch eine Leasingzusage.

### Versandnachweise und notwendiger Folgeschritt

Neue Checkout-Benachrichtigungen werden in der bestehenden `emailmessage`-Historie mit `module=shops`, `controller=order` und `documentid` gleich der internen Bestell-ID gespeichert. Angezeigt werden nur Datensätze desselben Datenmandanten und derselben Bestellung. Frühere `checkout`-Einträge werden nicht anhand von E-Mail-Adresse, Betreff oder öffentlicher Bestellnummer nachträglich zugeordnet. Fehlende Nachweise bedeuten deshalb nicht, dass keine Nachricht versendet wurde.

„Vom Mailserver angenommen“ belegt die SMTP-Annahme, nicht die Zustellung. Ein begonnenes oder fehlgeschlagenes Protokoll kann auch ein unklares Ergebnis nach einem Verbindungsabbruch bedeuten. Ein Fehler vor der Nachrichtenerstellung, etwa bei der SMTP-Konfiguration oder Vorlage, kann ohne Versanddatensatz bleiben; das Serverprotokoll nennt dafür die interne Bestell-ID. Fehler nach Nachrichtenerstellung sind über die Versandprotokoll-ID nachverfolgbar. Technische Rohmeldungen und Zugangsdaten werden in der Bestellansicht nicht ausgegeben.

Ein Wiederholungsbutton wird nicht angeboten: Für diese Nachrichten gibt es bisher keine atomare Reservierung und keinen eindeutigen Idempotenzschlüssel je Bestellung, Benachrichtigungszweck und Empfänger. Bei einem Fehler muss die berechtigte Administration zuerst Bestell-ID, Datenmandant, Versandprotokoll-ID und gegebenenfalls das Ergebnis beim Mailprovider prüfen. Keine zweite Bestellung und keine unkontrollierte SMTP-Wiederholung auslösen.

Ein späterer sicherer Wiederholungsmechanismus benötigt eine getrennt beauftragte Umsetzung: unveränderliche Empfänger und Nachrichtenkopien eindeutig an Bestellung und Benachrichtigungszweck binden, Versandversuche atomar reservieren, parallele Ausführungen ausschließen und unklare Annahmeergebnisse vor einer Wiederholung auflösen. Bereits angenommene Empfänger dürfen nicht erneut bedient werden. Dafür notwendige SQL-Änderungen müssen die freigegebenen Migrationsregeln einhalten; C3 führt keine Schemaänderung ein.

Die isolierte Regression läuft mit `python3 tests/security/shop-checkout-db.py`; Datenbanken, Sitzungen und Versand-Fixtures werden temporär angelegt. Es werden keine produktiven Datenbanken oder SMTP-Dienste benötigt.
