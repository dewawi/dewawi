# Versandkostenkern und Entwurf für die spätere Persistenz

C4A.1 ergänzt `Shops_Service_ShippingCalculator` als reinen Service. Er liest keine Registry, Session oder Datenbank, versendet keine Nachrichten und verändert keine Bestellungen. Die bestehenden Warenkorb-, Checkout- und ERP-Abläufe rufen ihn noch nicht auf. Konfiguration wird als serverseitiger Datenbestand übergeben, zunächst vollständig validiert und erst danach kalkuliert. Es gibt keinen Regelparser, keine Packoptimierung und keine neue Abhängigkeit.

## Bestehende Daten und Komponenten

Im geprüften Schema besitzen `item` und `shop` jeweils eine numerische Primär-ID und `clientid`; `item.shopid` ist die vorhandene Shopzuordnung. `Items_Service_ShopAssignment` prüft die manuelle Zuordnung. `Shops_Model_DbTable_Item::getCartItem()` begrenzt öffentliche Artikel auf Shop, Mandant, Freigabe und Löschstatus. Diese Wege bleiben für einen späteren Loader maßgeblich; eine SKU identifiziert keinen Artikel eindeutig.

`item.length/width/height/weight` beschreiben den Artikel, `packlength/packwidth/packheight/packweight` die Verpackung. Die Felder sind `decimal(12,4)`, in `Items_Form_Item` als Zentimeter und Kilogramm beschriftet. Der Kern benutzt ausschließlich die Verpackungsfelder; fehlende Verpackungswerte werden nicht aus Artikelmaßen ersetzt. Eine Versandklasse oder Packstückanzahl existiert im geprüften Artikelstamm noch nicht.

`shippingmethod` enthält ID, Titel, Reihenfolge, Mandant und Verwaltungsfelder; es enthält weder Shopzuordnung noch Preise. `Application_Model_DbTable_Shippingmethod::getShippingmethods()` liefert Titel als Schlüssel **und** Wert. Verkaufs- und Einkaufsdokumente sowie Prozesse und Aufgaben speichern Versandbezeichnungen in `shippingmethod` als Text. Admin-Formular, Controller und Modell verwalten diesen Bezeichnungsstamm. Klassen, Tarifschlüssel und neue Preise dürfen diese Texte nicht umdeuten. Eine spätere optionale Zuordnung zur ERP-Versandbezeichnung benötigt eine Prüfung des zuständigen ERP-Mandanten; sie erzeugt keine kostenpflichtige Position.

`DEEC_PriceRule` und der PriceRule-Helper berechnen Artikelpreisänderungen, `DEEC_PriceRuleShop` arbeitet mit bestehenden Preisregeln und Datenbankzugriffen. Sie modellieren keine Transportgruppen. Deshalb verwendet der isolierte Versandkern eigene begrenzte Ganzzahlarithmetik. Die Auswahl positiver `specialprice` vor `price` und die Mengenbegrenzung entsprechen dem bestehenden Shop-Warenkorb. Steuerberechnung bleibt bei `Application_Controller_Action_Helper_Calculate`; sie wird hier nicht aufgerufen. `OrderSnapshot` berechnet und speichert weiterhin seine bisherigen Beträge unverändert.

## Schnittstelle und Vertrauensgrenzen

```php
$result = (new Shops_Service_ShippingCalculator())->calculate(
    $context,       // ['clientid' => 7, 'shopid' => 41], bereits autorisierter Shop-Datenmandant
    $configuration, // geprüfter serverseitiger Konfigurationsbestand eines Shops
    $itemRows,      // aktuelle, berechtigt geladene Artikelzeilen einschließlich Verpackungsfeldern
    $selection,     // ausschließlich [['itemid' => 101, 'quantity' => '2.0000']]
    ['country' => 'DE', 'method' => 'delivery'] // oder ausdrücklich gewählte 'pickup'
);
```

Die IDs im Beispiel sind synthetisch. Eine HTTP-Anbindung muss den Kontext aus authentisiertem ERP-Kontext bzw. `SiteContext` ableiten und darf weder Kontext noch Konfiguration oder Artikelzeilen aus dem Request übernehmen. Der Kern ist keine Benutzerberechtigungsprüfung. Bei Parent-Child-Freigaben bestimmt der aufrufende Server anhand bestehender Modulfreigaben den tatsächlichen Shop-Datenmandanten. Ein Kindmandant mit freigegebenem Shop-Datenbestand kann dessen autorisierten Kontext verwenden; die bloße Freigabe gemeinsamer Artikel gestattet keinen Zugriff auf dessen Versandtarife. Es gibt keine automatische Vererbung von Tarifen zwischen Mandanten oder Shops.

Konfigurationskopf, jede enthaltene Konfigurationszeile und jede übergebene Artikelzeile müssen dieselben `clientid` und `shopid` tragen. Gemischte Bestände werden vollständig abgewiesen, auch wenn die fremde Zeile für den konkreten Warenkorb nicht gebraucht würde. Ein Loader muss daher vorher sauber eingrenzen. Auswahlen können keine Preise, Klassen, Packdaten oder Tarif-IDs enthalten; zusätzliche Felder werden abgelehnt. Ausgewählte Artikel müssen freigegeben und nicht gelöscht sein. Doppelte Artikel-IDs im Artikelbestand sowie doppelte Konfigurations-IDs sind ungültig; mehrfach ausgewählte Artikel werden vor der Berechnung nach ID zusammengefasst.

Dezimaldaten verwenden einen Punkt ohne Tausendertrennzeichen; vorzugsweise werden die Dezimalstrings aus der Datenbank übergeben. Negative Werte, Exponentialschreibweise, nicht endliche Zahlen, zu viele Nachkommastellen und Überläufe werden abgewiesen. Mengen verwenden die vorhandene `ShoppingCart::validQuantity()`-Regel: positiv, höchstens vier Nachkommastellen und insgesamt höchstens 100000 je Artikel. Der Kern setzt 64-Bit-PHP voraus, wie die geprüfte Entwicklungsumgebung.

## Konfigurationsvertrag Version 1

Der Kopf enthält `version: 1`, `clientid`, `shopid`, `currency`, `minor_units: 2`, `pickup` als echtes Boolean und die fünf folgenden Sammlungen. Alle Zeilen enthalten eine positive `id`, `clientid`, `shopid` und optional `deleted: 0`. IDs dürfen auch als ganzzahlige SQL-Strings vorliegen. Es gibt keine aktiven Konfigurationsvorgaben oder Händlertarife im Anwendungscode.

| Sammlung | Zusätzliche Felder | Bedeutung |
| --- | --- | --- |
| `classes` | `title`, `combine_same_class` Boolean, `required_fields` | Freier Titel; ausdrückliche Erlaubnis zum Bündeln derselben Klasse. Erforderliche Verpackungsfelder aus `packweight`, `packlength`, `packwidth`, `packheight`. |
| `assignments` | `itemid`, `classid`, `packages_per_unit`, `separate` Boolean | Eine Zuordnung je Artikel und Shop. Positive ganzzahlige Packstückanzahl je Verkaufseinheit; `separate` verbietet Bündelung zusätzlich zur Klassenregel. |
| `zones` | `countries` | Nicht leere Liste eindeutiger ISO-Ländercodes in Großbuchstaben. Jedes Land gehört innerhalb dieses Konfigurationsbestands genau einer Zone. |
| `tariffs` | `classid`, `zoneid`, `model`, modellabhängige Felder und optionale Grenzen | Genau ein Tarif je Klasse und Zone im übergebenen Währungsbestand. |
| `combinations` | `hostclassid`, `guestclassid`, `priority`, `max_guest_packages`, `max_guest_weight`; optional `max_packlength`, `max_packwidth`, `max_packheight` | Gerichtete Mitversanderlaubnis mit positiver, eindeutiger Priorität; kleinere Priorität wird zuerst geprüft. Alle Grenzen müssen ausdrücklich angegeben bzw. bewusst weggelassen werden. |

Verpackungsgewicht gilt im Vertrag als gesamtes verpacktes Gewicht **je Verkaufseinheit**, nicht je einzelnem Packstück. Bei mehreren Packstücken beschreiben die Verpackungsmaße eine verbindliche obere Grenze je Einzelpackstück. Vor einer späteren produktiven Nutzung muss der Bearbeiter diese Semantik für vorhandene Artikel bestätigen. Wenn Einzelpackstücke unterschiedliche Daten benötigen und die vorhandenen Felder keine verlässliche Obergrenze liefern, ist eine manuelle Frachtkalkulation erforderlich. Nettogewicht, Volumen, Palettenanzahl und Packanordnung werden nicht hergeleitet.

Die vorhandene Packstückanzahl der Zuordnung wird exakt mit der Menge multipliziert. Ergibt dies keine ganze Packstückzahl, lautet das Ergebnis `quote_required`; es wird nicht aufgerundet. Eine halbe Verkaufseinheit mit zwei belegten Packstücken je Einheit kann genau ein Packstück ergeben. Getrennt zu transportierende Verkaufseinheiten müssen dagegen ganzzahlig sein. Ihre Gruppen enthalten `shipments` als Wiederholungsanzahl und werden nicht tausendfach als Einzelobjekte angelegt.

### Tarife und Grenzen

Alle Kosten sind netto in der Währung des Kopfes. Version 1 unterstützt Währungen mit zwei Nachkommastellen; es gibt keine Umrechnung. Der spätere Loader muss die konfigurierte Währung gegen die vorhandenen freigegebenen Währungsstammdaten prüfen. Mehrere Währungen innerhalb einer Kalkulation sind unzulässig.

| `model` | Felder | Rechnung je Transportgruppe bzw. je wiederholter Einzelsendung |
| --- | --- | --- |
| `fixed` | `amount` | Festbetrag für die ausdrücklich gebildete Gruppe, unabhängig von ihrer Packstückanzahl. |
| `weight` | `tiers: [{up_to, amount}, …]` | Gesamtverpackungsgewicht entscheidet über die erste passende, einschließlich ihrer Obergrenze gültige Staffel. Grenzen müssen streng aufsteigen. |
| `additional_packages` | `base`, `additional` | Grundbetrag für das erste Packstück der tragenden Klasse plus Betrag für jedes weitere ihrer Packstücke. Paletten werden über die zugeordnete Klasse und Packstückanzahl abgebildet. |
| `percentage` | `percent` mit höchstens vier Nachkommastellen, 0 bis 100 | Prozentsatz des gerundeten Netto-Warenwerts aller Gruppenmitglieder. |
| `manual` | keine Preisfelder | Immer `quote_required`; wird auch als Gast nicht von einem anderen Transport absorbiert. |

Ein Betrag muss ausdrücklich vorhanden und mindestens null sein; `null` ist kein kostenloser Tarif. Geldparameter besitzen höchstens zwei Nachkommastellen. `min_weight/max_weight`, `min_packages/max_packages`, `min_value/max_value` begrenzen optional den Gültigkeitsbereich eines Tarifs, jeweils einschließlich der Grenze. Sie sind keine Preisdeckel oder Mindestgebühren. Ein außerhalb liegender Warenkorb benötigt eine Frachtanfrage; der Kern wählt keinen alternativen Tarif. Null als übermittelte optionale Grenze ist von einer weggelassenen Grenze zu unterscheiden; `null` als Wert ist ungültig.

Warenwerte werden aus vierstelligen Artikelpreisen und vierstelligen Mengen wie im ERP je zusammengefasster Position auf Cent gerundet. Prozentsätze werden danach je Sendung kaufmännisch auf Cent gerundet; eine halbe Cent-Einheit wird aufgerundet. Die Berechnung erfolgt mit Ganzzahlen und prüft jede Addition/Multiplikation auf Überlauf. Der maximale berechenbare Warenwert der Auswahl sowie Versandbetrag je Gruppe und insgesamt beträgt 99999999,99 Währungseinheiten. Gewichtssummen bewahren acht Nachkommastellen aus Gewicht mal Menge; Gewicht wird vor der Staffelwahl nicht abgerundet.

### Kombination und getrennte Transporte

Die Klassenbezeichnungen sind reine Daten. Eine Klasse kann beispielsweise „Paket“, „Sperrgut“, „Spedition Standard“, „Spedition Großgut“ oder „Sondertransport“ heißen; keine dieser Bezeichnungen wird in PHP ausgewertet.

Zunächst werden nur ausdrücklich kombinierbare Positionen derselben Klasse zusammengefasst. Jede andere Position wird getrennt nach ihrem Tarif berechnet. Danach werden gerichtete Mitversandregeln in konfigurierter Reihenfolge geprüft. Beide Klassen müssen Bündelung erlauben; individuell getrennte Artikel sind ausgeschlossen. Beide Gruppen müssen bereits für sich vollständig berechenbar sein. Fehlende Tarife, manuelle Fracht und unbekannte Pflichtdaten werden dadurch nicht verdeckt.

Die Regeln begrenzen die **gesamte zusätzliche Ladung** eines Trägertransports, nicht nur das zuletzt hinzugefügte Gastprodukt. Bei weiteren Gastklassen müssen weiterhin alle bisher angewandten Grenzen eingehalten sein. Gewicht muss für jede zusätzliche Position bekannt sein; konfigurierte Größenlimits benötigen bekannte Verpackungsmaße. Die Grenzen sind eine ausdrückliche fachliche Zulassung, keine rechnerische Prüfung, ob sich die Ware auf einer Palette unterbringen lässt. Ketten, Zyklen, doppelte Klassenpaare oder unklare Prioritäten werden zurückgewiesen.

Bei zulässigem Mitversand entfällt nur der eigenständige Gasttarif. Der Trägertarif wird mit allen Gewichten und Warenwerten neu geprüft; sein Packstück-Grundtarif zählt weiterhin die Packstücke der tragenden Klasse. Gibt es keine gültige gemeinsame Konstellation, bleiben die zuvor berechenbaren Transporte getrennt. Das Ergebnis muss nicht der billigste theoretisch mögliche Transport sein; eine Optimierung wird nicht behauptet.

Die Beispiele A–E in `shop-shipping.php` verwenden ausschließlich synthetische Werte: Standardpalette 120 plus erlaubtes Paket ergibt 120; zwei Standardpaletten ergeben 120 + 90 = 210; Großgut plus erlaubtes Paket ergibt 450; Sondertransport bleibt eine Frachtanfrage; zwei eigenständige Transporte werden separat berechnet.

## Ergebnisse und spätere Snapshots

| Status | `net` | Bedeutung |
| --- | --- | --- |
| `calculated` | Dezimalstring, auch ausdrücklich `0.00` | Alle Transportgruppen sind berechnet, oder der Kunde hat ausdrücklich die konfigurierte Selbstabholung gewählt. |
| `quote_required` | `null` | Fehlende Klasse/Tarife/Pflichtdaten, manuelle Fracht, überschrittene Grenzen, mehrdeutige Konfiguration oder ungültige Rechendaten. |
| `unavailable` | `null` | Lieferland nicht angeboten, Selbstabholung nicht freigegeben, Artikel nicht verfügbar oder Shop-/Mandantenkontext fremd. |

Das Ergebnis enthält Version, Netto-Preisgrundlage, Währung, geprüften Gesamtwarenwert `goods_net`, Kontext, Ziel, aufgelöste Zone, kanonische Auswahl und Transportgruppen. Jede Gruppe enthält Klasse und gespeicherten Klassentitel, Mitglieder mit Menge, Packdaten, serverseitigem Artikelpreis und Preisquelle, angewandten Tarif einschließlich Parametern, Mitversandregeln, Berechnungsgrundlagen und maschinenlesbare Gründe. `net` einer Gruppe enthält ihre gesamten Kosten; `net_per_shipment` und `shipments` erklären wiederholte Einzeltransporte. Bei Teilproblemen bleibt der Gesamtpreis null, auch wenn einzelne Gruppen berechenbar sind.

Im Nachweis sind normalisierte Geldparameter und `net_value_cents` Ganzzahlen in Cent. Verpackungsparameter sind Ganzzahlen mit vier Dezimalstellen, Prozentsätze ebenfalls mit vier Dezimalstellen; `units` beschreibt diese Skalen und die Maßeinheiten. `basis.weight_kg`, `basis.net_value`, Mitglieder-Menge und Artikelpreis sowie die fertigen Versandpreise sind Dezimalstrings. Die Grundlagen beziehen sich bei wiederholten Einzeltransporten auf eine Sendung. `chargeable_packages` erfasst die tragende Klasse, `total_packages` zusätzlich die mitfahrenden Packstücke. Fremde Konfiguration wird vor Ausgabe von Gruppen oder Parametern zurückgewiesen.

Eine spätere Snapshot-Erweiterung muss den akzeptierten Kalkulationsstand vollständig übernehmen: Kontext, Ziel, Währung, Ergebnisstatus, Netto-Versandbetrag oder null, Auswahl, Gruppen, Klassenbezeichnungen, Tarif- und Regel-IDs samt Parameterkopien, Verpackungsdaten und Mengen. Preise und Grundlagen dürfen nach Bestellungseingang nicht aus aktuellen Tarifen rekonstruiert werden. Die Versionskennung des jetzigen `OrderSnapshot`-Decoders akzeptiert nur Version 1; eine neue Bestell-Snapshotversion erfordert einen ausdrücklich rückwärtskompatiblen Leser. Historische Snapshots erhalten keine nachträglich berechneten Frachtkosten.

Umsatzsteuer und Verteilung von Versandkosten bei gemischten Artikelsteuersätzen sind vor einer späteren Checkout-/ERP-Anbindung fachlich festzulegen und mit dem bestehenden Calculate-Helper zu prüfen. Der Kern bestätigt weder Zahlung, Lieferung noch Rechnung und erstellt keine ERP-Versandpositionen.

## Konkreter Datenbankentwurf für einen späteren Auftrag

**Entwurf, kein Schemaänderungsauftrag.** Version 1.1.8 ist bereits veröffentlicht. Weder `structure.sql` noch `update-1.1.7-1.1.8.sql` werden in C4A.1 geändert. Umsetzung und Ausführung zukünftiger Migrationen 1.1.8 → 1.1.9 sowie des neuen Migrationssystems benötigen einen gesonderten Auftrag.

Vorgeschlagen sind fünf kleine, shopbezogene Tabellen mit numerischer Primär-ID, `clientid`, `shopid` sowie vorhandener Audit-/Soft-Delete-Konvention:

| Tabelle im Entwurf | Fachliche Spalten und Eindeutigkeiten |
| --- | --- |
| `shopshippingclass` | `title`, `combine_same_class`, `required_fields` als begrenzte JSON-Liste. Optional später eine ERP-Versandartenreferenz nach gesonderter Rechteprüfung. |
| `shopshippingzone` | `title`, `countries` als validierte JSON-Liste. Keine PLZ-Semantik in Version 1. Überlappende Länder werden beim Speichern des gesamten Shopbestands unter Sperre abgewiesen. |
| `shopshippingtariff` | `classid`, `zoneid`, `currency`, `model`, `parameters` als modellgebundenes JSON entsprechend der obigen Felder; eindeutige Kombination `(clientid, shopid, classid, zoneid, currency)`. Keine parallelen Preisfelder in `shippingmethod` oder `item`. |
| `shopitemshipping` | `itemid`, `classid`, positive ganze `packages_per_unit`, `separate`; eindeutig `(clientid, shopid, itemid)`. Keine Kopien vorhandener Gewichts-/Maßfelder. |
| `shopshippingcombination` | `hostclassid`, `guestclassid`, `priority`, `max_guest_packages`, `max_guest_weight`, optionale maximale Einzelpackmaße; eindeutig je Shop für Klassenpaar und Priorität. |

`parameters` ist ein geschlossener, versionsgebundener Vertrag für die fünf implementierten Tarifmodelle; keine Ausdrücke, Skripte oder frei kombinierbaren Regeloperatoren. Mengen- und Packdaten behalten die Skala der bestehenden Artikelfelder; Geldwerte verwenden zwei, Prozentsätze vier Nachkommastellen. Der Loader projiziert validierte Parameter in die Eingabefelder des Kerns, prüft die Tarifwährung gegen den Kopf und übergibt nur die gewählte Währung. Administrations-/Auditfelder, Zonentitel und inaktive Zeilen gehören nicht in diese Projektion. Feste Währungs- und Selbstabholungsoptionen benötigen später shopbezogene Konfiguration, beispielsweise `shippingcurrency` und `pickupenabled` in `shop`, mit Selbstabholung standardmäßig deaktiviert.

Neue Tabellen sollten `(clientid, shopid, id)` als referenzierbaren Unique-Key besitzen. Klassen-, Zonen- und Kombinationsreferenzen müssen als zusammengesetzte Fremdschlüssel die Mandanten-/Shopspalten einschließen. Das bestehende Schema besitzt überwiegend einzelne Indizes und keine entsprechenden zusammengesetzten Referenzschlüssel: vor Umsetzung sind für `shop(clientid,id)` und `item(clientid,shopid,id)` die erforderlichen Indizes tatsächlich zu prüfen/anzulegen; bestehende Parent-Child-Zuordnungen dürfen dabei nicht umgehängt werden. Eine Artikelzuordnung darf nur zum vorhandenen Artikel-Shop und dessen Datenmandanten angelegt werden. Nach einem Shopwechsel darf keine alte Versandzuordnung auf den neuen Shop wirken.

Gleichzeitige Änderungen an Klassen, Zonen, Tarifen und Zuordnungen benötigen eine konsistente Konfigurationsaufnahme und serverseitige Transaktionen/Sperren. Bei einer Soft-Delete-Eindeutigkeit darf ein gelöschter Tarif nicht neben einem zweiten aktiven Datensatz reaktiviert werden; die oben genannten Unique-Keys reservieren die logische Zuordnung auch im gelöschten Zustand. Eine Administration müsste solche Zuordnungen kontrolliert wiederverwenden bzw. ändern. Tarifhistorie für Bestellungen entsteht durch unveränderliche Parameterkopien im Bestell-Snapshot, nicht durch Neubewertung alter Bestellungen.

## Prüfung

`php tests/security/shop-shipping.php` verwendet ausschließlich synthetische Arrays. Die Suite prüft Tarifmodelle, A–E, Mengen/Packstücke, getrennte Transporte, Mitversandgrenzen, manuelle Fracht, Selbstabholung, unbekannte und kostenlose Kosten, Dezimalfehler, Isolation, exakte Gewichte, Cent-Rundung, Überläufe und reproduzierbare JSON-Ergebnisse. Fehler und PHP-Warnungen lassen den Test scheitern.

`python3 tests/security/shop-checkout-db.py` prüft unveränderte C1/C2/C3-Flows einschließlich ERP-Entwurf, Historie, konkurrierender Übernahme und Rechtekombinationen in temporären Datenbanken und lokalen HTTP-/Chromium-Fixtures. Es werden keine produktiven Datenbanken oder echten Kundenbestellungen verwendet. Eine Versand-UI-/HTTP-Prüfung entfällt, weil dieser Block keine Versandoberfläche oder Route ergänzt.
