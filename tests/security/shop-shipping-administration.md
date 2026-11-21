# Versandverwaltung und Persistenz

C4A.2 ergänzt die dauerhafte Konfiguration des C4A.1-Rechenkerns. `admin/shipping` verwaltet Shopoptionen, Klassen, Länderzonen, Tarife, Mitversandregeln und Artikelzuordnungen mit DEEC-Formularen und Listen. Der Artikelbildschirm enthält zusätzlich einen eigenen Versandbereich. Die Konfiguration wirkt noch nicht im öffentlichen Checkout und verändert weder Bestellungen noch ERP-Belege.

## Bedienung und Rechte

Zuerst einen Shop ausdrücklich auswählen und unter **Versandoptionen** eine eigene verfügbare Währung mit zwei Nachkommastellen festlegen. Selbstabholung ist standardmäßig aus. Danach Klassen und Lieferzonen anlegen, passende Tarife und Artikel zuordnen und gegebenenfalls gerichtete Mitversandregeln ergänzen. Neue Einträge starten inaktiv. Versandklassen haben frei wählbare Namen, eine Sortierung, eine ausdrückliche Erlaubnis zur Bündelung derselben Klasse sowie konfigurierbare Verpackungs-Pflichtfelder.

Konfigurationsverwaltung und gespeicherte Tarifberechnung benötigen einen angemeldeten Administrator und denselben freigegebenen Datenmandanten für `admin` und `shops`. Der Ausgangsmandant stammt aus `User.clientid`, also der validierten bestehenden Anmeldung/Client-Auswahl, niemals aus POST-Feldern. Ein bereits durch das Artikelmodul auf den Elternstamm umgestelltes `Client.id` ersetzt diesen Ausgangspunkt nicht; danach gelten die ausdrücklichen Einträge aus `Client.modules`. Eine alleinige Parent-Child-Freigabe für Artikel oder Shops gewährt keine Tarifadministration. Gibt eine Installation ausdrücklich sowohl `admin` als auch `shops` desselben Elternmandanten frei, wird dieser Kontext berücksichtigt.

Im Artikelbereich sind Anzeigen und Bearbeiten getrennt: die Versandzuordnung benötigt `items/item/view`, Änderungen zusätzlich `items/item/edit`, sowie übereinstimmende freigegebene Datenmandanten für `items` und `shops`. Dieser Weg zeigt Klassennamen und die Zuordnung des konkreten Artikels, aber keine Tarife. Die normale Shopzuordnung bleibt maßgeblich. Verpackungsgewicht in kg beschreibt das gesamte Verpackungsgewicht einer Verkaufseinheit; Verpackungsmaße in cm müssen eine belegte Obergrenze je Einzelpackstück sein. Ein unbekannter Klassenbezug oder fehlende erforderliche Packdaten bleibt nicht berechenbar.

Die vorhandenen Admin-Rechte werden wiederverwendet. Es wird keine neue SaaS-Berechtigung oder automatische Aktivierung des öffentlichen Checkouts eingeführt. Ein deaktivierter Checkout verhindert die interne Konfigurationspflege nicht; Administratorrechte ersetzen umgekehrt keine öffentliche Shop-Freigabe. Ein späterer öffentlicher Zugriff muss in C4B ausdrücklich an `SiteContext` und die bestehenden Feature-Prüfungen angebunden werden.

Länder müssen im zuständigen Länderstamm verfügbar sein. Pro Shop darf ein Lieferland in genau einer aktiven Zone vorkommen. Überschneidungen werden abgewiesen. Gewichtsstaffeln werden als Tabelle mit aufsteigenden einschließlich geltenden Obergrenzen und Netto-Beträgen eingegeben. Die fünf Modelle aus C4A.1 bleiben unverändert: Pauschale, Gewicht, Grundbetrag mit zusätzlichen Packstücken, Prozentsatz und individuelle Frachtanfrage. Zahlen verwenden einen Dezimalpunkt ohne Tausendertrennzeichen. Optionale Grenzen beschränken die Tarifgültigkeit und sind keine Preisdeckel. Ein ausdrücklich gespeicherter Betrag 0 ist von einem leeren, unbekannten Betrag zu unterscheiden.

Mitversand benötigt aktive, bündelbare Klassen, eine eindeutige Priorität sowie Grenzen für die gesamte Beiladung. Ketten und Zyklen bleiben unzulässig. Zusätzliche Gewichts- oder Maßinformationen werden nicht geschätzt. Getrennte Artikel und manuelle Frachtanfragen werden nicht durch einen höheren Einzelpreis verdeckt. Weitere Packstücke/Paletten erhalten Mehrkosten ausschließlich aus dem entsprechenden Tarifmodell.

Tarife und ihre Staffelbedingungen werden als vollständige Formulare gespeichert. Diese Formulare sind bewusst vom allgemeinen Feld-Auto-Save ausgenommen: Aktivierung, Referenzen und komplette Staffeln müssen atomar zusammen geprüft werden. Die Formulare verwenden die vorhandenen DEEC-Komponenten und den bestehenden nativen Submit-Schutz; Fehler erscheinen am Feld und in einer verlinkten Zusammenfassung. Modelwechsel deaktivieren unzutreffende Eingaben. Deutsch/Englisch, Tastaturbedienung und schmale Ansichten sind berücksichtigt.

Deaktivieren ist möglich, Löschen/ungeprüftes Kopieren wird nicht angeboten. Vor Deaktivierung einer verwendeten Klasse/Zone müssen deren aktive Tarife, Artikelzuordnungen und Kombinationen deaktiviert werden. Währungswechsel bei inkompatiblen aktiven Tarifen wird abgewiesen. Inaktive Entwürfe dürfen unvollständige Referenzen haben; Aktivierungen müssen den kompletten aktiven Shopbestand bestehen. Auch inaktive logische Tarif-/Artikelzuordnungen reservieren ihren Unique-Key: zum Wiederverwenden den vorhandenen Eintrag bearbeiten.

## Tatsächliches Datenmodell

Es werden fünf neue InnoDB-Tabellen angelegt, jeweils mit positiver Primär-ID, `clientid`, `shopid`, `active`, `ordering`, Erstell-/Änderungsmetadaten und `deleted`:

| Tabelle | Fachliche Daten |
| --- | --- |
| `shopshippingclass` | Name, `combine_same_class`, begrenzte JSON-Liste der erforderlichen vorhandenen Packfelder. |
| `shopshippingzone` | Name und ISO-Ländercodes als validierte JSON-Liste. Länderüberschneidung wird unter einer gemeinsamen Shop-Sperre geprüft. |
| `shopshippingtariff` | Name, Klasse, Zone, Währung, Modell und geschlossenes modellgebundenes Parameter-JSON einschließlich Staffeln/Gültigkeitsgrenzen. Keine frei auswertbaren Formeln. |
| `shopitemshipping` | Bestehende Artikel-ID, Klasse, positive ganze Packstücke je Einheit, ausdrückliche Einzeltransport-Vorgabe. Keine Kopie von Gewicht oder Maßen. |
| `shopshippingcombination` | Tragende Klasse und Beiladung, eindeutige Priorität und Grenzen für zusätzliche Packstücke, Gewicht und optionale Einzelpackmaße. |

`shop` erhält `shippingcurrency` (zunächst unbekannt/null) und `pickupenabled` (standardmäßig 0). `shop` und `item` erhalten je einen zusammengesetzten Unique-Index `(clientid,id)` als Referenzschlüssel. Es werden keine Artikel zugeordnet und keine vorhandenen Gewichte, Preise, ERP-Versandbezeichnungen oder historischen Dokumente verändert.

Klassen-/Zonenreferenzen besitzen zusammengesetzte Fremdschlüssel mit Mandant und Shop. Jede neue Tabelle ist auch bei noch leeren Entwurfsreferenzen an einen gültigen Shop gebunden. Artikelreferenzen sichern `(clientid,itemid)` ab; die Übereinstimmung der tatsächlichen Artikel-Shopzuordnung wird zusätzlich unter Sperre vor dem Speichern geprüft. Auf einen `(clientid,shopid,itemid)`-Fremdschlüssel wird verzichtet, damit die vorhandene manuelle Shopänderung im Artikelstamm nicht durch eine frühere Versandzuordnung blockiert wird. Nach einem Shopwechsel werden alte Versandzuordnungen beim Laden ignoriert und niemals auf einen anderen Shop übertragen.

Tarife sind je `(clientid,shopid,classid,zoneid,currency)`, Artikelzuordnungen je `(clientid,shopid,itemid)` eindeutig. Kombinationen sind je Klassenpaar und Priorität innerhalb des Shops eindeutig. Fremdschlüssel verhindern das Löschen referenzierter Stammdaten. Es gibt keine Umdeutung oder Änderung von `shippingmethod`.

## Serverseitiger Zugriff

`Shops_Service_ShippingConfiguration` kapselt mandantengeprüfte Verwaltung und den Zugriff auf gespeicherte Konfiguration. Schreibzugriffe sperren zuerst den betroffenen Shop, validieren und speichern innerhalb einer Transaktion und prüfen vor Commit den vollständigen aktiven Bestand über `ShippingCalculator::validateConfiguration()`. Das ist nur ein öffentlicher Zugang zur vorhandenen C4A.1-Konfigurationsprüfung; die Berechnungslogik wurde nicht dupliziert.

`calculate($shopId, $selection, $destination)` ist zunächst nur im autorisierten internen Administrationskontext zugänglich. Es liest einen Shopbestand unter derselben Sperre, projiziert ausschließlich aktive Datensätze in den C4A.1-Vertrag, lädt die aktuellen angefragten Artikel und ruft den unveränderten Berechnungsweg auf. Aktuell nicht mehr verfügbare Währungen oder fehlerhafte gespeicherte Konfiguration ergeben keinen erfundenen Preis. Unbekannte Kosten bleiben null; erfolgreiche Ergebnisse enthalten weiterhin Gruppen und Parameter-Nachweise. Fremde Kontexte werden abgewiesen. Historische Bestell-Snapshots werden weder erweitert noch neu berechnet.

Alle neuen HTTP-Schreibwege verlangen POST und ein Session-CSRF-Token. Zusätzliche fremde Formularfelder werden abgewiesen; Mandant und Shop können nicht über POST überschrieben werden. Rechte werden erneut im Service geprüft. Die vorhandene Aktionszuordnung von `DEEC_Permission` enthält jetzt `shipping` als Bearbeitungsaktion für den Artikelweg. Die neue Artikel-Schreibaktion bereitet keine Anhangsverzeichnisse vor. Es gibt keinen SMTP-Zugriff.

## Migration und spätere Betriebsaufnahme

Neue unveröffentlichte Entwicklungsmigration: `install/config/migrations/1.1.8-1.1.9/001-shop-shipping.sql`, im M1-Katalog registriert; unmittelbare Abhängigkeit ist die geprüfte `baseline/1.1.8`. Das Entwicklungsschema `structure.sql` enthält dieselben Tabellen, Spalten, Indizes und Constraints. Die eingefrorene Baseline, die alte `update-1.1.7-1.1.8.sql`, Anwendungsversion und Installer-Versionsangaben bleiben unverändert. Eine offizielle 1.1.9-Release-/Sammeldatei wurde nicht erzeugt.

Für eine spätere, gesondert freigegebene Betriebsaufnahme auf einer bestehenden vollständigen 1.1.8-Installation: Backup/Wiederherstellbarkeit und tatsächlichen Datenbankstand prüfen, Schreibzugriffe im Wartungsfenster kontrollieren, mit privater Konfiguration zunächst `tools/migrate.php --command=plan`, bei noch fehlender Registrierung ausdrücklich `--command=baseline`, danach erneut `plan` und `apply` ausführen. Jede dieser Datenbankaktionen bleibt ein eigener bewusster Operator-Schritt. Das aktuelle Block-Ergebnis führt sie ausschließlich in temporären Testdatenbanken aus.

Ein frischer Entwicklungsimport von `structure.sql` enthält diese Migration bereits physisch, aber besitzt keine automatisch initialisierte M1-Historie. Darauf darf `apply` die CREATE-Migration nicht erneut ausführen. Die von M1 ausdrücklich für eine spätere Snapshot-Veröffentlichung zurückgestellte, verifizierte Initialisierung der abgedeckten IDs/Checksummen bleibt ein gesonderter Folgeauftrag. Weder Tabellenexistenz noch die Anwendungsversion dürfen erfolgreiche Ausführung vortäuschen; keine manuellen Erfolgseinträge erzeugen. Die Schema-Gleichheit des frischen Entwicklungsstands und des echten Upgrades ist isoliert geprüft, einschließlich des echten bestehenden Installers.

Nach fehlgeschlagenem oder unterbrochenem DDL-Lauf gilt unverändert das M1-Verfahren: keine automatische Wiederholung oder Teilfortsetzung; tatsächlichen Zustand, Verlauf und Backup prüfen und einen gesonderten Wiederherstellungsplan verwenden. Bereits ausgeführte Migrationen bleiben unveränderlich. Die Anwendung benötigt die neuen Tabellen vor Nutzung der Verwaltung und der Artikel-Versandansicht; SQL und Code müssen bei einer späteren Betriebsaufnahme entsprechend koordiniert werden.

## Verifikation

- `php tests/security/shop-shipping.php`: die 404 unveränderten C4A.1-Prüfungen.
- `python3 tests/security/shop-shipping-db.py`: vollständige eingefrorene 1.1.8-Basis mit echten M1-Kommandos aufrüsten, wiederholt anwenden und Schema mit frischem Entwicklungsimport vergleichen; danach Persistenz-/Referenz-/Rechtefälle und echte Controller-/Chromium-Fixtures.
- `python3 -B tests/install/migrations.py`: bestehende 124 M1-Sicherheitsprüfungen einschließlich Checksumme, Reihenfolge, teilweisem SQL, Prozessabbruch, konkurrierendem Updater und wiederholtem Lauf. Die Release-Fixtures laden den eingefrorenen SQL-Quellstand aus `8ab77ce4`, statt das fortgeschriebene Entwicklungsschema für eine neue Baseline zu halten.
- `python3 -B tests/install/import-db.py`: echter Installer mit Entwicklungsschema, historische vollständige 1.1.7→1.1.8-Schemavergleiche getrennt gegen die veröffentlichte Referenz und 66 UOM-Fälle.
- `python3 tests/security/shop-checkout-db.py`: bestehende C1/C2/C3-/ERP-/Sicherheitsregressionen.

Sämtliche Datenbanken, Sitzungen und Browserprofile sind isolierte temporäre Fixtures. HTTP-/Browserprüfungen verwenden die tatsächlichen Formulare, Controller, DEEC-Listen, ERP-Layout und JavaScript auf Deutsch/Englisch bei 1280/390/320 Pixeln. Keine produktiven Datenbanken, Kundenartikel oder echten Bestellungen werden verwendet. Checkout-Anbindung, Steuerbehandlung von Fracht und unveränderliche Versandkosten-Snapshots in neuen Bestellungen bleiben C4B vorbehalten.

Ergebnisse dieses Blocks: 404 reine Kernprüfungen, 70 Persistenz-/Rechteprüfungen und 147 HTTP-/Chromium-Prüfungen bestanden; Schema-Gleichheit und wiederholtes M1-Apply bestätigt. Zusätzlich bestanden 124 M1-Fälle, Installer/66 UOM-Fälle, die 740 C1/C2/C3-Prüfungen, 450 Rechte-, 269 Mandanten-, 20 Eingabevalidierungs-, 26 Shopzuordnungs-, 45 Empfänger- und 78 E-Mail-Inhaltsprüfungen. Die Übersetzungsruntime besteht 157 Prüfungen; alle 83 neuen deutschen/englischen Schlüssel stimmen überein. PHP-/PHTML-Syntax und Python-Syntax der geänderten Dateien wurden geprüft.
