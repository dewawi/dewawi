# C5B – Fachlicher Steuervertrag und Verifikation

Stand: 2026-11-22. Anwendungsversion bleibt 1.1.8.

## Entscheidungsgrundlage vor der Implementierung

Der vorhandene ERP-Helper `Calculate` rundet zuerst den tatsächlich berechneten Nettowert jeder Position auf Cent, summiert die ungerundete Steuer je Steuersatz und rundet jede Steuergruppe vor der Gesamtsumme. Er übernimmt gespeicherte Positionssteuersätze und einen ausdrücklich gesetzten Belegschalter `taxfree`; er ermittelt weder Lieferorte noch Befreiungsnachweise. `contact.vatin` und `contact.taxfree` sind manuelle Stammdaten ohne Prüfstatus, Geltungszeitraum oder Transaktionsnachweis. Der Gastcheckout hat keine belastbare Zuordnung eines verifizierten Steuerkunden. Eine Firmenangabe oder ein E-Mail-Treffer ersetzt diese nicht. Die bestehenden Artikelsteuersätze enthalten keine Zielland-/OSS-/Befreiungsregeln; `item.origincountry` bezeichnet keine verifizierte Versandlageradresse.

Amtliche Grundlagen, abgerufen am 2026-11-22:

- [UStAE 3.10 Abs. 5 – Einheitlichkeit/Nebenleistungen](https://www.bundesfinanzministerium.de/Content/DE/Downloads/BMF_Schreiben/Steuerarten/Umsatzsteuer/Umsatzsteuer-Anwendungserlass/Umsatzsteuer-Anwendungserlass-aktuell.pdf?__blob=publicationFile&v=87): eine unselbstständige Nebenleistung folgt der Hauptleistung. Ein eigener wirtschaftlicher Zweck erfordert eine gesonderte Beurteilung.
- [UStAE 10.1 Abs. 11 – Entgeltaufteilung](https://usth.bundesfinanzministerium.de/usth/2024/A-Umsatzsteuergesetz/III-Bemessungsgrundlagen/Paragraf-10/ae-10-1.html) und [aktuelle konsolidierte Fassung](https://www.bundesfinanzministerium.de/Content/DE/Downloads/BMF_Schreiben/Steuerarten/Umsatzsteuer/Umsatzsteuer-Anwendungserlass/Umsatzsteuer-Anwendungserlass-aktuell.pdf?__blob=publicationFile&v=44): einfache, sachgerechte Aufteilung unterschiedlich besteuerter Leistungen; Verhältnis der Einzelverkaufspreise als anerkannter Ansatz. **Die gruppenbezogene Nettoaufteilung unten ist eine begrenzte fachliche Ableitung, keine amtliche universelle Versandformel.** Sie setzt die ausdrücklich bestätigte Nebenleistungsbehandlung und geprüfte Nettopreise voraus.
- [§ 3 Abs. 6 UStG](https://www.gesetze-im-internet.de/ustg_1980/__3.html): bei bewegten Lieferungen ist grundsätzlich der Beginn der Beförderung maßgeblich, vorbehaltlich der Sondervorschriften. Die Rechnungsadresse allein bestimmt ihn nicht.
- [§ 3c UStG](https://www.gesetze-im-internet.de/ustg_1980/__3c.html): innergemeinschaftlicher B2C-Fernverkauf benötigt eine gesonderte Prüfung des Bestimmungslands und der Voraussetzungen der Ausnahmen. DEWAWI besitzt dafür keine belastbaren Schwellen-/Verzichts-/OSS-/Registrierungs-/Ziellanddaten.
- [§ 6a UStG](https://www.gesetze-im-internet.de/ustg_1980/__6a.html): innergemeinschaftliche Befreiung erfordert zusätzliche, nachgewiesene Voraussetzungen einschließlich gültiger ausländischer USt-IdNr. bei den betreffenden Erwerbern.
- [§ 6 UStG](https://www.gesetze-im-internet.de/ustg_1980/__6.html): Ausfuhrbehandlung setzt die tatsächlichen Voraussetzungen und Nachweise voraus. Eine ausländische Adresse ist kein Exportnachweis.
- [§ 1 Abs. 2 UStG](https://www.gesetze-im-internet.de/ustg_1980/__1.html): deutsches Staatsgebiet und Umsatzsteuer-Inland sind nicht identisch. Büsingen/Helgoland sowie weitere Sondergebiete benötigen gesonderte Behandlung. Postleitzahlenbelege: [Gemeinde Büsingen](https://www.buesingen.de/de/Unser-Buesingen/Deutsche-Insel-in-der-Schweiz/Postleitzahl), [amtliches Gemeindeverzeichnis Helgoland](https://www.statistikportal.de/de/gemeindeverzeichnis/01056025).

## Begrenzte Freigabe

Neue Aufteilungen und abweichende Rechnungs-/Lieferländer erfordern die ausdrückliche Shop-Richtlinie `de_ancillary_net_v1` in der bestehenden Versandverwaltung. Sie bestätigt normalen deutschen Verkäufer-/Regelbesteuerungskontext, tatsächlichen Versand-/Abholbeginn im deutschen Umsatzsteuer-Inland, unselbstständige Warenversandnebenleistung, fachlich geprüfte Artikelsteuersätze und keine Sonderregel wie Reihengeschäft, Reverse Charge oder autonome Transportleistung. Keine Richtlinie wird automatisch gesetzt. Der bestehende positive Einsteuersatz-Inlandsfall wird unter dem bisherigen C4B-Vertrag erhalten; dies erweitert keine internationalen Regeln.

Bei bestätigtem DE-Profil und Lieferung im gewöhnlichen deutschen Umsatzsteuer-Inland ist auch eine abweichende gültige Rechnungsadresse zulässig: berechnet wird reguläre deutsche Umsatzsteuer, keine Befreiung. Physische Lieferungen in andere EU-Staaten und Drittländer bleiben wegen fehlender Nachweise/Regeln auf Anfrage. Unbekannte oder nicht nachgewiesene 0-%-Behandlung wird nicht aus einem numerischen Nullsatz abgeleitet. Historische Bestellungen bleiben unverändert lesbar und übernehmbar.

Der Betreiber muss tatsächlichen Versandort, Artikel-/Nebenleistungsbehandlung und Sonderfälle fachlich prüfen, bevor er das Profil auswählt. Die Richtlinie ist eine administrative Erklärung, keine automatische Adress-, Rechts- oder USt-IdNr.-Prüfung. Sondergebiete und unbekannte Fallkonstellationen bleiben Anfragefälle.

## Aufteilungs- und Rundungsvertrag

Für unterschiedliche Steuersätze wird jede tatsächlich berechnete C4A-Versandgruppe einschließlich ihrer zugelassenen Mitversandmitglieder getrennt behandelt. Ein Paket-/Palettenentgelt wird nur seinen Mitgliedern zugeordnet. Gewichte sind deren von `Calculate` tatsächlich berechnete Nettopositionswerte einschließlich Menge und aktuellem Sonderpreis, nach Positionsrundung. Andere Sendungen erhalten keinen Anteil. Derselbe Steuersatz wird innerhalb der Versandgruppe zusammengefasst.

Bei positivem Gruppenentgelt: proportionale Aufteilung in ganzen Cent, Abrunden der Anteile und deterministische Verteilung der Restcent nach größtem Divisionsrest; bei Gleichstand aufsteigender Steuersatz. Bei einer einzigen Steuergruppe ist kein Wertverhältnis nötig. Bei mehreren Steuersätzen und ausschließlich nullwertigen Mitgliedern ist kein belastbarer Schlüssel vorhanden: Anfrage. Nullwertige Mitglieder erhalten bei vorhandener positiver Basis keinen Wertanteil. Nullfracht benötigt keine positive Nebenleistungsverteilung.

Steuern berechnet ausschließlich `Calculate`. Die Versandsteueranteile ergeben sich aus der Differenz der ERP-Steuergruppe vor und nach Hinzufügen des jeweiligen gespeicherten Versandanteils, in deterministischer Reihenfolge. Dadurch wird ein Cent weder doppelt gerundet noch verloren. Die ERP-Übernahme verwendet reguläre Belegpositionen ohne erfundene Artikelnummern. Summenabweichungen führen zum vollständigen Rollback.

## Verifikation und Übergabe

### Freigegebener Abschlussvertrag

Auf ausdrückliche Entscheidung vom 2026-11-22 wird die bisherige C5A-/C4B-Legacy-Ausnahme **nur für neue Abschlüsse** aufgehoben. Fehlende Versandkonfiguration bedeutet unbekannte Fracht und damit unbekannte Steuer/Bruttosumme: vorhandene unverbindliche Anfrage verwenden. Ohne aktivierten Kontakt-/Anfragekanal erscheinen Erklärung und Shop-Kontaktadresse, ersatzweise der Link zur Shopstartseite. Keine automatische Deaktivierung von Shop, Katalog oder Warenkorb. Sobald Fracht und Steuer bestimmbar sind, ist der verbindliche Abschluss wieder verfügbar. `unavailable` bleibt von einer bloß ungeklärten Fracht/Steuer getrennt.

### Unveränderliche Speicherung

Neue Checkout-Snapshots haben Version **3**; Positions- und C4A-Frachtsnapshots bleiben Version 1. Gespeichert werden `tax.version`, `rule`, `calculation_version`, `calculator`, `rounding`, Länder-/Verkäuferkontext und ausdrücklich **nicht verifizierter** Kundenstatus. `freight.tax_allocations` enthält Versandgruppe, Steuersatz, zugehörigen Nettowarenwert und Netto-/Steuer-/Bruttoanteil. `tax.groups` enthält Waren-/Versandnetto, Gruppennetto, Gruppensteuer und Gruppenbrutto je Satz.

Beim Review sind sowohl das gewählte Betreiberprofil als auch Mandantenland, Artikel-/Steuerdaten, Preise, Adressen und C4A-Konfiguration an den bestehenden Fingerprint gebunden. Der endgültige Abschluss validiert diese serverseitig erneut und verwendet die bestehende Transaktion und Shop-Sperre.

Bei der ERP-Übernahme werden nur gespeicherte Eingaben mit dem vorhandenen `Calculate` reproduziert. Eine andere aktuelle Konfiguration löst keine historische Tarifberechnung aus. Netto/Gesamtsteuer/Brutto **und jede Steuergruppe** müssen übereinstimmen; auch eine kompensierende Centverschiebung zwischen zwei Gruppen führt zum vollständigen Rollback. Der historische Vertrag der Snapshotversionen **1 und 2**, einschließlich damaliger Legacy-Fracht und gespeicherter Nullsätze, bleibt erhalten. Bestehende Bestellungen werden nicht verändert. Zukünftige Änderungen an `Calculate` oder diesem Regelvertrag müssen die gespeicherte Berechnungsversion weiterhin reproduzieren oder gesondert versioniert werden.

### Unabhängige Referenzwerte

Erwartungen wurden vorab von Hand festgelegt, nicht durch Kopieren der Aufteilungsfunktion erzeugt. Beträge in EUR. Versandanteile sind netto; Steuer/Gesamt enthalten Ware und Versand.

| Warenbasis | Zugehörige Fracht und Aufteilung | Netto gesamt | Steuer gesamt | Brutto gesamt |
| --- | --- | ---: | ---: | ---: |
| 100 @ 19 % | 12 @ 19 % | 112,00 | 21,28 | 133,28 |
| 100 @ 19 % + 100 @ 7 % | gemeinsames Paket 12: je 6 | 212,00 | 27,56 | 239,56 |
| je 100 @ 19/7 % | getrennte Sendungen: 10 @ 19 %, 100 @ 7 % | 310,00 | 34,90 | 344,90 |
| 200 @ 19 % + 100 @ 7 % | gemeinsame Palette 120: 80 @ 19 %, 40 @ 7 % | 420,00 | 63,00 | 483,00 |
| 3 × 25 @ 19 % + 25 @ 7 % | 0,03: 0,02 @ 19 %, 0,01 @ 7 % | 100,03 | 16,00 | 116,03 |
| je 0,03 @ 19/7 % | 0,01: Restcent @ 7 % | 0,07 | 0,01 | 0,08 |
| je 0,03 @ 19/7 % | 0,03: 0,01 @ 19 %, 0,02 @ 7 % | 0,09 | 0,01 | 0,10 |
| kostenloser Artikel @ 19 % + 100 @ 7 % | 10 vollständig @ 7 % | 110,00 | 7,70 | 117,70 |
| nullwertige Ware, nur 19 % | 10 @ 19 % | 10,00 | 1,90 | 11,90 |
| je 100 @ 19/7,7 % | gemeinsame Fracht 12: je 6; Dezimalsatz als Rechenfixture | 212,00 | 28,30 | 240,30 |
| 40 Mio. @ 19 % + 1 Mio. @ 7 % | 40 Mio.: 39.024.390,24 @ 19 %, 975.609,76 @ 7 % | 81.000.000,00 | 15.152.926,83 | 96.152.926,83 |

Zusätzlich mit echter C4A-Konfiguration: zwei getrennte Sendungen zu je 100 Warenwert, Fracht 12 @ 19 % und 120 @ 7 % ergeben 332,00 netto / 36,68 Steuer / 368,68 brutto. Derselbe Inhalt als erlaubter Mitversand einer gemeinsamen Palette wird mit 120 Fracht und 80/40 Aufteilung zu 420,00 / 63,00 / 483,00. Preisquelle ist der tatsächlich berechnete Sonderpreis, nicht der höhere Listenpreis.

### Tatsächlich ausgeführte Tests

Alle DB-/HTTP-/Browserprüfungen liefen ausschließlich mit temporären Datenbanken, synthetischen Mandanten/Bestellungen, temporären Sessions/Profilen und Mail-Fixtures. Keine produktiven Daten, externen SMTP-Empfänger oder Dienste wurden verwendet. Während C5B wurden parallel fremde Updater-/Wartungsänderungen im Arbeitsverzeichnis bearbeitet. Ein Lauf dort wurde dadurch mit `Maintenance unavailable` blockiert. Die abschließenden Integrationsprüfungen verwenden deshalb ein temporäres Quellabbild von `d113ae69` plus ausschließlich den C5B-Dateien. Fremde Änderungen wurden weder zurückgesetzt noch in C5B übernommen. Die Integration mit diesen parallel entstehenden Updateränderungen ist damit nicht bescheinigt.

| Prüfung | Ergebnis |
| --- | --- |
| `php tests/security/shop-shipping-tax.php` | 118 Referenz-/Aufteilungs-/Restcent-/Nullwert-/Länder-/Manipulationsprüfungen bestanden; reale ERP-Berechnung ohne DB. |
| `python3 -B tests/security/shop-checkout-db.py` | Vollständiger Runner bestanden: 17 Checkout-DB-, 80 Warenkorb-, 130 Bestell-/ERP-, 296 Bestell-HTTP-/Browser-, 75 öffentliche Checkout-, 154 Checkout-Chromium-, 114 kombinierte Legacy-/Versand-HTTP-, 47 Versand-Chromium-, 41 neue Steuer-HTTP- und 88 neue Steuer-Chromiumfälle; 45 bestehende C4B-DB- und zunächst 41 neue Steuer-DB-Fälle. |
| Abschließende gezielte DB-Wiederholung mit denselben PHP-Fixtures | 45 C4B- und nun 45 C5B-Steuer-DB-Fälle bestanden; zusätzlich vier negative Snapshot-Kontexte (Legacy-Flag, falsche Währung, falsches Zielland, fehlende Versandart). Die 17/80/130 Basis-DB-Fälle wurden ebenfalls erneut bestanden. |
| `python3 -B tests/security/shop-shipping-db.py` | Frischinstallation und echter M1-Upgradepfad einschließlich 002 schemagleich, wiederholtes Apply ohne erneute Ausführung; 70 Persistenz-/Rechtefälle, 139 Go-live-DB-Fälle, 101 Go-live-HTTP-/Chromiumfälle und 147 Versand-HTTP-/Chromiumfälle bestanden. |
| `php tests/security/shop-shipping.php` | 404 unveränderte C4A-Rechenkernfälle bestanden. |
| `python3 -B tests/install/migrations.py` | Abschließend alle 124 M1-Fälle bestanden: unveränderte Baseline, Historie, Reihenfolge, Prüfsummen, Teilfehler, Parallelität und HTTP-Schutz. Ein erster Lauf brach beim Wiederherstellen der synthetisch beschädigten Baseline mit generischem Konfigurations-/DB-Fehler ab; Ursache nicht eindeutig ermittelt. Vollständiger Wiederholungslauf und unveränderter Ausgangsstand bestehen. Keine Updateränderung vorgenommen. |
| `python3 -B tests/install/import-db.py` | Vollständige Installation, historische Upgradegleichheit über 124 Basistabellen, Fehlerbehandlung und 66 UOM-Fälle bestanden. Die simulierte UOM-Ausnahme gehört zum bestandenen Rollbacktest. |
| `php tests/documents/calculation.php`, `pdf-totals.php` | 736 / 244 Berechnungs-/PDF-Fälle bestanden. |
| `python3 -B tests/documents/calculation-db.py` | **Vorhandener Fehler:** Fixture enthält keine Tabelle `documentrevision`; Ausgabe/Finalisierung scheitert bereits vor den Shopfällen. Derselbe Fehler wurde im unveränderten Ausgangsstand `d113ae69` reproduziert. Nicht als bestanden gewertet, nicht im Versandsteuerauftrag verändert. |
| `python3 -B tests/security/shop-inquiry-quote.py` | 770 Anfrage-/Transaktions-/Datenschutz-/PDF-/Replayfälle, Variantenfälle und Formular-/Katalog-/Varianten-HTTP sowie Chromium für zwei Produktfamilien bei 390/1280 Pixeln bestanden. |
| `php tests/security/permissions.php`, `tenant-access.php`, `form-input-validation.php` | 450 / 269 / 20 Fälle bestanden. |
| `php tests/security/shop-inquiries.php`, `contact-inquiries.php`, `shop-inquiry-calculation.php`, `inquiry-dynamic-form.php` | 58 / 47 / 16 / 31 Fälle bestanden. |
| `php tests/security/shop-email-content.php`, `shop-email-recipients.php` | 88 / 52 Fälle bestanden, einschließlich gespeicherter DE/EN-Steuergruppen für beide Empfänger. Protokollierte SMTP-Ablehnungen sind synthetische Negativfälle. |
| `php tests/items/shop-assignment.php` | 26 Zuordnungs-/Mandantenfälle bestanden. |
| `php tests/translations/runtime.php`, `cleanup.php` | 157 / 86 Laufzeit-/Cleanupfälle bestanden. |
| `php tests/site/seo-metadata.php`, `robots-sitemap.php`, `slugs.php`, `cms-structure.php`, `search.php`, `legacy-redirects.php`, `technical-baseline.php`, `public-errors.php` | 63 / 37 / 71 / 200 / 168 / 61 / 132 / 10 Fälle bestanden. |
| PHP-/PHTML-Syntax, Python-AST, `git diff --check` der C5B-Dateien | Bestanden. JavaScript der neuen Browserfixture wird durch tatsächliche Chromium-Ausführung geprüft; keine neue produktive JS-Implementierung. |

Die neuen Browserfälle decken DE/EN und 1280/390/320 Pixel ab: Steuergruppen und gespeicherte Bestätigung, bekannte Fracht bei unbekannter Steuer, Legacy-Anfrage und Kontakthinweis ohne Anfragefunktion, Tastatur/Fokus und kein horizontaler Überlauf. HTTP-Negativfälle prüfen manipulierte Steuern/Befreiungen, geänderte Artikelsteuern, widerrufenes Betreiberprofil, erneuten Review, EU-B2C/B2B ohne belastbare Nachweise, Drittland, Anfrage-/Bestell-Replay und unveränderten Warenkorb. Bestehende Isolation-/Rechte-/CSRF-Prüfungen bleiben erhalten.

### SQL, offene Freigaben und Betriebsaufnahme

Die zusätzliche Spalte `shop.shippingtaxpolicy` ist nötig, um die ausdrückliche Betreiberentscheidung shop- und mandantenbezogen zu speichern. Sie ist optional, standardmäßig `NULL` und wird nicht automatisch aktiviert. Offizieller Entwicklungsupgrade: `install/config/migrations/1.1.8-1.1.9/002-shop-shipping-tax.sql`, abhängig von `001-shop-shipping.sql`; Registry und `structure.sql` sind synchron. **001, historische Updates, eingefrorene Baseline und M1–M2F-Code wurden in C5B nicht geändert.** Anwendungsversion bleibt 1.1.8; die neue unveröffentlichte Migration ist durch den C5B-Auftrag ausdrücklich erlaubt.

Vor einer separat autorisierten Installation den tatsächlichen Stand von Spalte und zentraler Migrationshistorie prüfen. Bei regulär fehlender 002 den bestehenden kontrollierten M1-Weg verwenden. Bei vorhandener Spalte ohne passende erfolgreiche Historie oder fehlgeschlagenem/teilweisem Lauf zuerst den konkreten Zustand klären; das nicht idempotente `ALTER TABLE` nicht manuell oder automatisch erneut ausführen. Keine alternative offizielle Migration und keine HTTP-Migration. Bestehende Installationen ohne 002 können weiterhin die bisherigen Versandsettings speichern; das neue Profilfeld wird ausgeblendet. Positive Mischfracht bleibt dort Anfrage, ein bestehender positiver Einsteuersatz-Inlandsfall bleibt nutzbar.

Nach regulärer Migration pro Shop tatsächlich prüfen und ausdrücklich speichern: Verkäufer-/Versandland, gewöhnliches deutsches Umsatzsteuer-Inland, Artikelsteuersätze und Nettowerte, Nebenleistungscharakter, Tarif-/Gruppenzuordnung, Sonderfälle und fachliche Freigabe des Profils. Den neuen Go-live-Bericht neu laden und die vorhandene reale Abnahme durchführen. Die Prüfung ermittelt keinen realen Produktionszustand und schaltet den Shop nicht um.

Nicht umgesetzt/freigegeben sind physische EU-/Drittlandlieferungen, Befreiungen ohne transaktionsbezogene Nachweise, OSS-/Schwellen-/Registrierungsregeln, verifizierte B2B-/USt-IdNr.-Identitäten, Sondergebiete oder eigenständige Transportleistungen. Dafür fehlen belastbare Daten und ERP-Regeln. Die Unterscheidung wird dokumentiert, statt eine Umsatzsteuerbefreiung zu vermuten. Für diese Fälle bleibt der vorhandene Anfrage-/Angebotsprozess erforderlich.

### Betroffene Dateien

- Steuer-/Snapshotvertrag: `application/modules/shops/services/OrderSnapshot.php`, `OrderWorkflow.php`, `ShippingConfiguration.php`.
- Checkout/ERP-/Maildarstellung: `CheckoutController.php`, `checkout/_review.phtml`, `_shipping.phtml`, `_summary.phtml`, neu `_taxes.phtml`, `order/view.phtml`, `application/controllers/helpers/Email.php`.
- Administrative Freigabe: `admin/forms/Shipping.php`, `views/scripts/shipping/edit.phtml`, `services/ShopReadiness.php`, DE/EN-Admin-/Shopübersetzungen.
- Schema: 002, Migrationsregistry, `structure.sql`.
- Regressionen: neue `shop-shipping-tax.php`, `shop-checkout-tax-db.php`, `checkout-tax-runner.html`/`checkout-tax-http.py`; angepasste bestehende Checkout-/Versand-/Bestell-/Mail-/Go-live-Fixtures und Runner. Die C4B-/C5A-Berichte verweisen ausdrücklich auf diesen aktuellen Vertrag.

Kein Push, Deployment, Release, Tag, produktiver SQL-Lauf oder Versionswechsel. Kein Beginn von C5C.
