# Changelog

## 1.1.8 – 20.11.2026

### Description

DEWAWI 1.1.8 erweitert die bestehenden ERP-, Kontakt-, Shop- und Dokumentabläufe um
Teamkalender, persistente Anfragen und Bestellungen, Lagerplanung und kontrollierte
Archivierungs- und Prüfwerkzeuge. Die Version enthält außerdem Verbesserungen an
Mandantentrennung, Berechtigungen, Übersetzungen und responsiver Bedienung.

Die Änderungen sind im lokalen Git-Verlauf seit dem Release-Tag `1.1.7` enthalten.
Das Release-Datum ist der 20.11.2026; Push, Tag und Deployment erfolgen separat.

### Neue Funktionen

- Teamkalender mit privaten Terminen, Zeitzonen, ganztägigen Terminen, Erinnerungen,
  internen und externen Teilnehmern sowie ausdrücklich versendeten Einladungen und
  Absagen im iCalendar-Format.
- Zusätzliche Kontaktrollen, getrennte Vor- und Nachnamen für Ansprechpartner,
  Ansprechpartnerauswahl in Einkaufsbelegen, Kontakt-CSV-Import und Exportprofile,
  Tag-Sammelbearbeitung und interaktive Kontaktkarte mit kontrollierten
  Geocoding-Werkzeugen.
- Persistente Shop-Anfragen mit interner Verwaltung, Kontaktzuordnung,
  Angebotsentwürfen und konfigurierbarer Artikel-/Variantenauswahl.
- Persistente Shop-Bestellungen mit gespeicherten Checkout-Snapshots,
  Statushistorie und berechtigungsgeprüfter Übernahme als ERP-Auftragsentwurf.
  Bestehende Bestellungen bleiben intern auch bei deaktiviertem Checkout bearbeitbar.
- Lagerplanung für Reservierungen und erwartete Zugänge sowie verknüpfte
  Bestandsbewegungen und Verfügbarkeitsauswertung.
- XRechnung-UBL-XML-Ausgabe und Vorprüfungen; geschütztes Dokumentarchiv mit
  unveränderlichen Versandkopien, Bestandsaudit und kontrollierten Werkzeugen zur
  Prüfung und Wiederherstellung historischer Dokumente. Fehlende Originale können
  als deutlich gekennzeichnete lesbare Kopien aus gespeicherten Belegdaten angezeigt
  werden. Angebots-/Auftragsrevisionen und redaktionelle Rechnungskorrekturen
  erhalten die ursprünglichen Belege und ihre Werte.
- Versionierte technische Datenpakete und Artikelprofile, interne technische
  Anfrageprüfungen mit gespeicherten Prüfständen und Vergleich von bis zu drei
  ausdrücklich ausgewählten Kandidaten. Referenzrechner decken unter anderem
  Verdichterpolynome sowie thermische und hydraulische Kopplungen ab; die Grenzen
  für reale technische Freigaben sind unten beschrieben.
- Optional aktivierbare ALBIS-Leasinganzeige für freigegebene Shop-Artikel mit
  simuliert getesteter API-Anbindung und Übernahme gewählter Konditionen in eine
  Anfrage. Zugangsdaten und Token-Verschlüsselungsschlüssel kommen aus der
  Serverumgebung; bestehende Shops und Artikel werden nicht automatisch aktiviert.

### Verbesserungen

- Einheitliche Rückmeldungen, Ladezustände und Fehlerbehandlung für AJAX,
  Auto-Save, Uploads, Downloads und Listenaktionen; gemeinsame SVG-Buttons und
  besser bedienbare mobile Listen und Werkzeugleisten.
- Überarbeitetes Laden und vervollständigte deutsche/englische Übersetzungen
  einschließlich Installer, PDF- und E-Mail-Kontexten; strikte Qualitätsprüfungen
  für Kataloge, Platzhalter und sichtbare Texte.
- Shop-Suche und Vorschauen, Produkt-/Kategoriezuordnung, responsive Bilder,
  Seitennavigation, Slugs, Weiterleitungen, SEO-Metadaten, Sitemap und Indexierung.
  Neue Shops bleiben standardmäßig bis zur Freigabe auf `noindex`.
- Einwilligungsabhängiges Tracking, kontrollierte Formulareinsendungen sowie
  dokumentierte Prüfungen für HTTPS, Caching, Verfügbarkeit und Backup/Restore.
- Angebotsnachverfolgung mit Rückmeldungshistorie und Wiedervorlagen; verbesserte
  Listenfußzeilen, Bestandsfilter und Standards für Mengeneinheiten.

### Fehlerbehebungen

- Korrekturen an Positions-Auto-Save, Artikel-/Kategoriefiltern, Produktbildern,
  dynamischen Anfragefeldern, Angebots-PDFs und Kontaktzuordnungen.
- Erhalt bestehender Standardauswahlen für Beleg- und Kampagnenanhänge;
  getrennte SMTP-Wege, Empfänger und protokollierte Versandversuche.
- Korrekturen an paralleler Belegnummernvergabe und Finalisierung,
  Original-PDF-Zugriffen, historischen Vorschauen und lesbaren Belegkopien.
- Installer-Fehler stoppen den Import und verhindern eine Erfolgsmeldung;
  Neuinstallationen enthalten keinen unzulässigen ungekürzten TEXT-Index auf
  `address.street`.

### Sicherheitsverbesserungen

- Serverseitige Mandanten- und Objektprüfungen für Belege, Kontakte, Dateien,
  Konfiguration und Shop-Verwaltung unter Erhalt erlaubter Parent-Child-Freigaben.
- Geschützte Dateiauslieferung, eingeschränkte öffentliche PHP-Einstiegspunkte,
  abgesicherter Filemanager und Prüfungen gegen SSRF bei URL-Uploads.
- Sicherere Produktionsvorgaben und Fehlerantworten, Eingabevalidierung sowie
  Rechte-, HTTP-Methoden- und CSRF-Prüfungen in zustandsändernden Abläufen.
- Schutz finalisierter Belegwerte und Originale; Prüfsummen, getrennte
  Korrektur-/Kopienablagen und explizite Freigaben für historische Nachweise.

### SQL-/Schemaänderungen

Die offizielle Upgrade-Datei bleibt
[`install/config/update-1.1.7-1.1.8.sql`](install/config/update-1.1.7-1.1.8.sql).
Neuinstallationen verwenden [`structure.sql`](install/config/structure.sql) und
[`data.sql`](install/config/data.sql). Es gibt keinen neuen Migrationsrunner.

- Neue Tabellen: `contactrole`, `stockplanning`, `slugredirect`, `documentarchive`,
  `emailmessageattachment`, `technicaldataset`, `itemtechnicalprofile`,
  `inquirytechnicalreview`, `calendarparticipant`, `documentrevision`,
  `invoicecorrection` und `shoporderhistory`.
- Zusätzliche Ansprechpartnerfelder, Anfrageverknüpfungen und -indizes,
  Shop-/Bestelltoken, Checkout-Snapshots und ERP-Bezüge, Kalender- und
  Einladungsfelder, Korrekturanhänge sowie ALBIS-Einstellungen und Artikel-Freigabe.
- Datenübernahmen für Ansprechpartnernamen und Kontaktrollen, eindeutige
  Anfrage-/Bestelltoken, bestehende Shop-Indexierung, Mengeneinheiten-Defaults,
  abgeleitete Planbestände sowie Kalender-Eigentümer und iCalendar-UIDs.
- Der vollständige Upgrade-Abgleich und bekannte Unterschiede werden im
  [lokalen Release-Prüfbericht](tests/install/release-1.1.8.md) festgehalten.

### Erforderliche Upgrade-Schritte

1. Tatsächlichen Versions- und Schemazustand feststellen. Vor dem autorisierten
   Datenbankupdate konsistente Sicherungen von Datenbank, Code, Konfiguration,
   `files`, `media` und `templates` erstellen und die Wiederherstellbarkeit prüfen.
2. Ältere Installationen über die vorhandenen historischen Übergänge in korrekter
   Reihenfolge zunächst auf den geprüften Ausgangszustand 1.1.7 bringen.
3. Schreibzugriffe im Wartungsfenster kontrollieren. Auf einem unveränderten
   1.1.7-Schema die offizielle Upgrade-Datei kontrolliert einmal ausführen.
   **Bei bereits teilweise oder vollständig ausgeführtem Upgrade zuerst Tabellen,
   Spalten, Indizes und Datenübernahmen prüfen und nur nachweislich fehlende Schritte
   aus derselben Datei anwenden.** Nicht idempotente `ALTER`-Anweisungen niemals
   blind wiederholen; bei unklaren Teilzuständen stoppen und den Zustand klären.
   `structure.sql` und den Neuinstaller nicht über eine bestehende Datenbank ausführen.
4. Zusammengehörige PHP-, View-, Sprach- und Assetdateien ausliefern; kundeneigene
   Konfiguration und persistente Dateien erhalten. Lesbarkeit durch den tatsächlichen
   PHP-/FPM-Benutzer einschließlich SVGs und privater Dokumentablagen prüfen.
   Schemaänderungen müssen vor Nutzung der betroffenen Funktionen vorhanden sein.
5. Caches über den bestehenden Betriebsablauf aktualisieren. Mit einem freigegebenen
   Testmandanten Anmeldung, DE/EN, Auto-Save, PDF-/XML-Zugriff, Shop-Anfragen und
   Bestellbearbeitung prüfen. Kalenderrechte für Bestandsbenutzer ausdrücklich
   vergeben; echte SMTP-/TLS- und ALBIS-Zustellung getrennt abnehmen.
6. Historische PDF-Bestände nur über die dokumentierten Audit-/Review-/Freigabewege
   behandeln. Alte Originale, Kopien, Prüfberichte und Kalender-UIDs erhalten;
   fehlende Nachweise nicht durch heutige Stammdaten oder Neuberechnung ersetzen.

### Bekannte Einschränkungen

- Die neue nummerierte Migrationsverwaltung ist für den Übergang 1.1.8 → 1.1.9
  vorgesehen und noch nicht implementiert. Ein Versionslabel beweist keinen
  abgeschlossenen Datenbankstand.
- Historische Bestellungen ohne gespeicherte Checkout-Snapshots erhalten keine
  erfundenen Daten und können nicht ungeprüft in ERP-Aufträge überführt werden.
- Historische PDF-Kopien sind keine Originale oder Versandnachweise. Alte Kopien
  bleiben erhalten und benötigen zusätzlichen Speicher. XRechnung-Ausgabe,
  Archivwerkzeuge und lokale Tests ersetzen keine externe fachliche Abnahme.
- Technische Referenzmodelle und synthetische Tests sind keine reale OEM- oder
  Maschinenfreigabe. Fehlende Herstellerdaten, Rechte, Einsatzgrenzen und unabhängige
  Referenznachweise bleiben Freigabeblocker.
- Historische Kalendertermine behalten ihre früheren lokalen Zeiten; unbekannte
  Zeitzonen, Sommerzeit-Mehrdeutigkeiten und Termine ohne Eigentümer benötigen
  gegebenenfalls eine ausdrückliche administrative Klärung.
- Die lokale Abnahme verwendet PHP 8.3 und MariaDB 10.11 sowie isolierte Browser- und
  Transport-Fixtures. Reale Provider, Produktions-FPM, kundeneigene Erweiterungen,
  MySQL auf einem separaten Server, Safari/WebKit und echte Touch-/Screenreader-
  Umgebungen sind damit nicht abgenommen. Die alten Mindestanforderungen im README
  sind keine Kompatibilitätszusage für diesen geprüften Stand.
- Grenzen früherer UI-Abnahmen bleiben dokumentiert, insbesondere die vollständige
  Firefox-Tastatur-/Modalabnahme und einzelne ausgeschlossene Inline-Aktionskontexte:
  [Integrationsbericht](tests/translations/FINAL-release-integration.md).
