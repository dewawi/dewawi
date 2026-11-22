# C5A – Shop-Go-live-Prüfung und Stabilisierung

Prüfdatum: 2026-11-22. Anwendungsversion unverändert **1.1.8**.

## Ergebnis und Geltungsbereich

Die unten genannten technischen Prüfungen sind mit synthetischen Daten und isolierten Datenbanken bestanden. Sie erteilen **keine produktive Freigabe für Renocold oder einen anderen realen Shop**. Produktive Shopdaten, Tarife, Bestellungen, Benutzer, SMTP- und Zahlungsdienste wurden nicht verwendet oder verändert. Es gab kein Deployment.

Unter **Administration → Shops → Shop bearbeiten → Go-live-Prüfung** wird der gespeicherte Zustand des ausgewählten Shops lesend geprüft. Nach Auto-Save die Seite neu laden. Die kompakte Zusammenfassung zeigt „Bereit“, „Warnung / manuell prüfen“ oder „Blockiert“; aufklappbare Prüfhinweise nennen Einstellungen, betroffene Artikel-/Kategorie-IDs und fehlende Schemaelemente. Ein separater technischer Status berücksichtigt die ausdrücklich manuellen Abnahmen nicht. Diese bleiben in der Gesamtbewertung Warnungen. Es gibt keine automatische Aktivierung, Reparatur, Migration oder gespeicherte Produktionsfreigabe.

## Tatsächlich gefundene und behobene Fehler

1. **Fehlendes Versand-Schema:** Der C4B-Loader konnte bei gesetzter Versandwährung fehlende Versandtabellen ungeprüft abfragen; bei fehlender `shop.shippingcurrency` entstand außerdem ein Zugriff auf einen nicht vorhandenen Array-Schlüssel. Der neue Metadatencheck erkennt Tabellen und benötigte Spalten, bevor konfigurierte Versanddaten gelesen werden. Konfigurierte Checkouts liefern bei fehlendem Schema `quote_required` mit unbekanntem Betrag, ohne Legacy-Freigabe. Die Go-live-Prüfung blockiert verbindlichen Checkout. Fehlende Versandwährung erhält den ausdrücklich gekennzeichneten Legacy-Ablauf; die Prüfung meldet ihn als Blocker für einen vollständig konfigurierten Bestellshop.
2. **Gelöschte Shops in der Hostauflösung:** Aktiv markierte, gelöschte Shopzeilen konnten öffentlich aufgelöst werden oder eine gültige Zuordnung mehrdeutig machen. Die bestehende Resolverabfrage schließt gelöschte Shops jetzt aus. Regressionen prüfen sowohl alleinstehende gelöschte Shops als auch den Konflikt mit einem aktiven Shop.
3. **Übersetzungs-Cleanup-Fixture:** Der Warenkorb wurde ohne `siteContext` gerendert und verursachte einen Fatal Error. Nur die Fixture erhielt einen synthetischen Kontext; ihre Erwartung wurde auf den inzwischen verwendeten Warenkorb-Preishinweis aktualisiert. Kein produktiver Kontext-/Warenkorbcode wurde hierfür verändert.
4. **Veraltete CMS-Testassertion:** Die Prüfung erwartete `.text(title)`, obwohl das vorhandene JavaScript bereits `.text(item.title)` verwendet. Die Assertion prüft nun den tatsächlich verwendeten sicheren Textaufbau für Titel **und** Artikelnummer. Der Produktionscode blieb hierfür unverändert.
5. **Neue Go-live-Ansicht bei 320 Pixeln:** Der Browsertest fand überlange Prüftexte. Die neue Ansicht bricht Texte und technische Details um; Desktop-/Mobilregressionen bestehen.

## Technisch bestanden

Alle folgenden Befehle wurden tatsächlich ausgeführt. Datenbank- und HTTP-Runner starten ausschließlich temporäre MariaDB-/HTTP-Instanzen; Browser verwenden temporäre Profile und Mail-Fixtures.

| Befehl | Ergebnis |
| --- | --- |
| `python3 -B tests/security/shop-checkout-db.py` | Bestanden: 17 Checkout-Transaktionsfälle, 80 Warenkorbfälle, 130 persistente Bestell-/ERP-Fälle, 296 Bestell-HTTP-/Browserfälle, 63 öffentliche Checkoutfälle, 154 Checkout-Chromiumfälle, 102 kombinierte Legacy-/Versand-HTTP-Fälle, 47 Versand-Chromiumfälle und 45 C4B-Snapshot-/Steuer-/ERP-/Transaktionsfälle. |
| `python3 -B tests/security/shop-shipping-db.py` | Bestanden: bestehender Neuinstallations-/Upgrade-Schemavergleich und wiederholter Apply, 70 Versand-Persistenz-/Rechtefälle, 147 Versand-HTTP-/Browserfälle sowie die neuen Go-live-Prüfungen unten. Keine Änderung am Updater oder seinen Migrationen. |
| Darin: `tests/shops/shop-go-live-db.php` | 130 Prüfungen: vollständige Konfiguration, zwei Artikel/Versandgruppen, deaktivierter Checkout, reiner Anfragebetrieb, unbekannte Legacy-Fracht, fehlende Klassen-/Tarifzuordnung, manuelle Fracht, fehlende Verpackungsdaten, ungültige Preise/Währungen/Steuern/Kategorien/Länder, beschädigtes Tarif-JSON, fehlende Tabellen und einzelne Spalten, Bildpfade/gesperrte Dateitypen/Symlinks, Fremdmandanten, geerbte Module, historische Bestellungen, unveränderte Geschäftsdaten und DE/EN-/Escaping-Ausgabe. |
| Darin: `tests/shops/shop-go-live-http.py` | 101 Prüfungen über die tatsächliche Shopbearbeitung: Berechtigungen, keine SMTP-Geheimnisse oder Schreibfelder in der Prüfung, gleichzeitig fehlende Versandtabellen ohne Absturz, vorhandene Tabinteraktion, aufklappbare Hinweise, DE/EN und 1280/390/320 Pixel ohne Überlauf. |
| `php tests/security/shop-shipping.php` | 404 Rechenkernfälle bestanden. |
| `php tests/site/seo-metadata.php`, `robots-sitemap.php`, `slugs.php`, `cms-structure.php`, `search.php`, `legacy-redirects.php`, `technical-baseline.php`, `public-errors.php` im selben Verzeichnis | 63 / 37 / 71 / 200 / 168 / 61 / 132 / 10 Fälle bestanden. |
| `python3 -B tests/site/frontend-assets-http.py` | 79 Chromiumfälle zu Abhängigkeiten, Navigation, Komponenten, AJAX und responsiver Darstellung bestanden. |
| `python3 -B tests/site/cart-http.py` | 132 Chromiumfälle zu Desktop/Mobil, Fokus, Konkurrenz und Netzfehlern bestanden. |
| `python3 -B tests/site/image-layout-http.py` | 132 Chromiumfälle zu Produktbildern und CMS-Karten bestanden. |
| `python3 -B tests/site/accessibility-http.py` | 98 Chromiumfälle zu Tastatur, Fokus, ARIA, Formularen, Warenkorb und responsiver Darstellung bestanden. Kein Ersatz für manuelle Accessibility-Abnahme. |
| `python3 -B tests/site/crawler-http.py`, `https-canonical-http.py` im selben Verzeichnis | 39 HTTP-Crawler-/SEO-Fälle und 35 isolierte HTTP-/HTTPS-/Host-/Headerfälle bestanden. |
| `php tests/security/permissions.php`, `tenant-access.php`, `shop-inquiries.php`, `contact-inquiries.php`, `shop-email-content.php`, `shop-email-recipients.php`, `shop-inquiry-calculation.php`, `inquiry-dynamic-form.php` im selben Verzeichnis | 450 / 269 / 58 / 47 / 84 / 52 / 16 / 31 Fälle bestanden. Mailprüfungen verwenden keinen echten SMTP-Versand. |
| `php tests/items/shop-assignment.php` | 26 Zuordnungsfälle bestanden. |
| `python3 -B tests/security/shop-inquiry-quote.py` | 770 Anfrage-/Transaktions-/Datenschutz-/PDF-/Replayfälle und 95 Variantenfälle sowie tatsächliche Formular-/Katalog-/Varianten-HTTP-Prüfungen und Chromium für zwei Produktfamilien bei 390/1280 Pixeln bestanden. |
| `php tests/translations/runtime.php`, `php tests/translations/cleanup.php` | 157 Laufzeitfälle und 86 Cleanupfälle bestanden. |

C1–C4B bleiben erhalten: serverseitige Preise und Fracht; bekannte Fracht in Summen; Anfrage statt verbindlicher Bestellung bei `quote_required`; kein Abschluss bei `unavailable`; Selbstabholung nur bei Freigabe; Review-/Idempotenz-/CSRF-/Sessionprüfungen; unveränderliche Snapshots einschließlich Version 1; ERP-Übernahme anhand gespeicherter Beträge mit unveränderten Rechten. Einzelheiten der bestehenden Steuergrenzen: [C4B-Prüfbericht](../security/shop-checkout-shipping.md).

PHP-Syntax aller zwölf geänderten/neuen PHP-/PHTML-Dateien, Python-Syntax der drei geänderten/neuen Pythondateien und `git diff --check` sind bestanden. Im abschließenden Lauf blieb kein Fehler oder ausgelassener oben aufgeführter Test. Die anfängliche Socket-Sandboxbeschränkung wurde durch die autorisierte Ausführung der weiterhin isolierten Runner aufgelöst.

Die neuen Versandproben verwenden Menge 1 je bestellbarem Artikel und einen gemeinsamen Warenkorb in allen gespeicherten Lieferländern. Das prüft Zuordnungen und Berechenbarkeit, bestätigt aber weder reale Tarife noch jede zulässige Menge und jede mögliche Artikelkombination. Nicht bestellbare, zugeordnete Katalogartikel können wie bisher sichtbar sein; tatsächliche Verfügbarkeit wird nicht aus Lagerbeständen zertifiziert.

## Blockiert

Nachgewiesene Blockierfälle der Prüfung sind unter anderem: inaktiver/falsch zugeordneter Shop, ungültige/mehrdeutige URL, fehlende Startseite, ungültige Grundeinstellungen, widersprüchliche Modulmandanten, fehlender Katalog für Checkout, ungültige Preise oder Kategoriezuordnungen, fehlende Benachrichtigungskonfiguration/Vorlagen und ungültige aktivierte Anfrageformulare.

Für verbindlichen Checkout blockieren zusätzlich fehlendes Versand-Schema, unbekannte Legacy-Fracht, fehlende/mehrdeutige Versandkonfiguration, fehlende Artikel-/Tarifzuordnung, nicht berechenbare Fracht, unbekannte Artikelsteuer und ein nicht eindeutig gepflegtes Mandantenland. Ein deaktivierter Checkout oder eine fehlende Versandmigration bei reinem Website-/Anfragebetrieb wird als Warnung eingeordnet; die Prüfung schaltet nichts um.

**Ein tatsächlicher produktiver Blockerbestand für Renocold wurde nicht ermittelt**, da keine produktive Datenbank oder reale Shopkonfiguration untersucht wurde. Die synthetischen Blockierfälle sind keine Aussage darüber, welche Einstellungen dort fehlen. Die tatsächliche Prüfung und fachliche Freigabe bleiben offen.

## Manuell erforderlich – wiederverwendbare Abnahme je Shop

Shop/Mandant: __________ · URL: __________ · Datum: __________ · Verantwortlich: __________

| Abnahme | Nachweis / Entscheidung |
| --- | --- |
| Gespeicherten Go-live-Bericht neu laden und alle technischen Blocker einzeln beheben | __________ |
| Tatsächliches Schema und ausgeführte/teilweise ausgeführte C4A.2-Änderungen ausschließlich lesend feststellen | __________ |
| SaaS-Freigaben, Administrator-/ERP-Rechte und Parent-Child-Vereinbarungen bestätigen | __________ |
| DNS, Host-/Port-/Basispfad, TLS, Redirects, Indexierung und öffentliche Erreichbarkeit kontrollieren | __________ |
| Startseite, CMS, Menüs, Kategorien/Unterkategorien, leere Kategorien, Suche/Filter, Produkte, Bilder, Varianten und Lieferzeiten prüfen | __________ |
| Produktpreise, tatsächliche Verfügbarkeit, Versandklassen, reale Tarife, Lieferländer, Paket-/Palettengrenzen und Selbstabholung fachlich freigeben | __________ |
| Vollständigen Desktop-/Smartphone-Kundenablauf, einschließlich Bestätigung, Frachtanfrage, Kontakt und kontrollierter Fehlerfälle abnehmen | __________ |
| Shop-SMTP, E-Mailvorlagen, Kunden-/interne Zustellung und verantwortliche Bearbeiter mit ausdrücklich freigegebenen Testempfängern prüfen | __________ |
| Zahlungs-/Abwicklungsverfahren passend zum vorhandenen Checkout festlegen; keine Zahlungsintegration voraussetzen | __________ |
| Anbieter-/Kontaktangaben, Datenschutz, rechtliche Informationsseiten, Bestell-/Versand-/Zahlungshinweise und ihre Verlinkung fachlich/rechtlich abnehmen | __________ |
| Canonicals, historische URLs, Weiterleitungen und Sitemap mit realen Shopinhalten prüfen | __________ |
| Manuelle Accessibility-/Geräteabnahme sowie bestehende Betriebs-/Backup-/Rollback-Nachweise bestätigen | __________ |
| Verbindlichen Bestellbetrieb oder alternative Anfrage-/Bestellabwicklung ausdrücklich freigeben und Zuständigkeit dokumentieren | __________ |

Bekannte Steuergrenzen bleiben bestehen: bekannte Steuern und übereinstimmendes Rechnungs-/Liefer-/Mandantenland; positive Versandkosten benötigen einen einheitlichen Artikelsteuersatz. Ausland sowie positive Fracht bei gemischten Steuersätzen führen zur Anfrage. Nullversand und freigegebene Selbstabholung werden nach den bestehenden C4B-Regeln behandelt. Eine Erweiterung dieser Fälle ist ein gesonderter Auftrag.

Ein vorhandener Administratorzugang beweist keine gebuchten SaaS-Funktionen. Ein automatischer Abonnementsnachweis ist in dieser Prüfung nicht verfügbar. Rechtliche Inhalte werden unabhängig von ihrer Existenz immer zur manuellen Abnahme gestellt.

## Dateien und Datenbanknacharbeit

- Shop-Administration: `application/modules/admin/controllers/ShopController.php`, `services/ShopReadiness.php`, `views/scripts/shop/edit.phtml`, `views/scripts/shop/_readiness.phtml`.
- Öffentlicher Schutz: `application/modules/shops/services/ShippingConfiguration.php`, `library/DEEC/Site/Resolver.php`.
- Darstellung: `css/ui.css`, `languages/de_DE/admin/admin.php`, `languages/en_US/admin/admin.php`.
- Regressionen: die zwei neuen `tests/shops/shop-go-live-*`-Runner, bestehender Versand-DB-/HTTP-Runner samt HTTP-Fixture, `tests/translations/cleanup.php` und `tests/site/cms-structure.php`.

Keine SQL-Datei, kein Migrationssystem M1–M2F und keine Versionsreferenz wurden geändert. C5A benötigt keine eigene Migration. Falls C4A.2 noch fehlt oder teilweise angewendet wurde, ist vor einer gesondert autorisierten Datenbankänderung der tatsächliche Zustand zu prüfen und ausschließlich das Fehlende im bestehenden offiziellen Upgradeweg zu behandeln. Keinesfalls eine komplette nicht idempotente historische Migration ungeprüft wiederholen. Diese Arbeit führt keine Datenbanknacharbeit automatisch aus.

Kein Push, kein Deployment, keine produktiven Datenänderungen.
