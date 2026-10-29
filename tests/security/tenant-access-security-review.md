# Security Phase 5: Mandantenisolation und Objektberechtigungen

Stand: 29.10.2026. Ausgangspunkt: `secure stored file access and prevent url upload ssrf`. Keine Commits, kein Push, keine produktiven Datenbankzugriffe und keine externen Integrationsaktionen.

## Prüfumfang und tatsächliche Regeln

Die [Bestandsaufnahme](tenant-access-inventory.tsv) erfasst alle 103 Controller-Dateien unter `application/`, ihre Basisklassen, selbst definierten Aktionen sowie explizit referenzierte Models und Services. Geerbte Aktionen sind über die jeweiligen DEEC-Basisklassen berücksichtigt. Die statische Bestandsaufnahme ist keine Behauptung, jede Kombination dynamisch getestet zu haben.

Untersucht wurden insbesondere:

| Bereich | Geprüfte Zugriffspfade und Komponenten |
| --- | --- |
| Zentrale Grenzen | `Bootstrap::_initAuth/_initClient`, `Application_Plugin_Acl/Client/State`, `Application_Model_DbTable_Client::getSwitchableClients`, `DEEC_Permission`, `DEEC_Controller_Action/MultiEntityAction/DocumentAction/PositionAction`, `DEEC_Model_DbTable_Entity/Position`, `DEEC_Query`, `DEEC_List_Query` |
| Contacts | Contact, Contactperson, Address, Phone, Email, Internet, Bankaccount, Tag, Download, Downloadset, Emailmessage, Attachment; Import/Export, Kontaktverlauf und `ContactEditViewModel`; objektbezogene Datei- und E-Mail-Zuordnung aus Phase 4 |
| Items | Item, Variant, Attribute/Option, Position/Positionset, Itemlist, Inventory, Pricerule/Pricerulepos, Ledger, Stock; `Items_Service_Variant/Stock`, Itemvariantopt und Itemstock |
| Sales | Quote, Salesorder, Invoice, Deliveryorder, Creditnote, Reminder; Positionen und Sets, PDF-/XML-Export, Kopieren, Finalisieren, Stornieren und Statusänderungen |
| Purchases | Quoterequest und Purchaseorder einschließlich Positionen, Sets und Dokumentausgabe. Ein eigener Controller für Eingangsrechnungen ist in diesem Stand nicht vorhanden. |
| Processes | Process, Position/Positionset, Zuordnung von Kontakten, Verantwortlichen und Preisregeln; operative Vorgänge bleiben von fiskalischen Dokumenten unterschieden |
| Users/Admin | Eigenes Profil, Passwort, Sperren, Mandantenwechsel, Benutzer-/Rechteverwaltung und Einstellungen; Category, Page, Menü-/Blockstrukturen, Shop, globale Module und sonstige Stammdatentabellen |
| Weitere Module | Tasks, Campaigns einschließlich Empfänger/Feedback/Unsubscribe, Statistics, eBay- und Shop-Verwaltung, öffentlicher Storefront und Checkout; Dashboard, Kommentare, Medien und generischer Papierkorb |

Authentifizierung und aktiver Mandant stammen weiterhin aus dem pro Request aktualisierten Konto und der geprüften Session. Ein normaler Benutzer darf seinen Heimatmandanten, dessen direkten Elternmandanten und direkte Kinder des Heimatmandanten auswählen. Die Auswahl erweitert diese Menge nicht; Geschwister, Enkel und unverbundene Mandanten werden dadurch nicht freigegeben. Administratoren besitzen die bereits vorhandene globale Auswahl aktiver Mandanten.

Bei einem ausgewählten Kindmandanten gehören Contacts und Items weiterhin dem direkten Elternmandanten. Sales, Purchases, Processes und Statistics gehören dem ausgewählten Mandanten. `Client.id` ist der vom Server gesetzte effektive Modulkontext; `User.clientid` bleibt der ausgewählte Mandant. Explizites `setClientId()` bleibt für geprüfte interne Querzugriffe und öffentliche Site-Kontexte erhalten. Request-Werte sind hierfür kein Berechtigungsnachweis.

Die bestehende ACL ordnet Contacts-Aktionen `contact`, Items-Aktionen `item` und Positionsaktionen ihrem Elternobjekt zu. Lesen/Export/Download benötigen `view`, Bearbeiten/Status/Sortieren `edit`, Erstellen/Kopieren `add`, Löschen `delete`. Die bestehenden Regeln für Module ohne verwaltete Berechtigungsspalte bleiben erhalten: authentifizierter Zugriff im jeweiligen Mandanten, keine neu erfundenen Rollenbits. Interne Model-Aufrufe setzen einen bereits autorisierten Serverkontext voraus; es wurde kein zweites Berechtigungssystem eingeführt.

## Nachgewiesene Probleme und Korrekturen

| Schwere | Fundstelle / Problem | Korrektur |
| --- | --- | --- |
| Hoch | `TrashController::addAction`: Die Route prüfte den tatsächlichen JSON-Zielcontroller und dessen Löschrecht nicht; Kontext und Dokument-Lebenszyklus konnten vom normalen Controllerpfad abweichen. | Zielmodul und Modellname validieren, Ziel-ACL prüfen, effektiven Eigentümer serverseitig bestimmen, Datensätze gesperrt laden, Elternzugriff und vorhandene Lösch-/Dokumentregeln beachten. Kaskaden laufen im selben Kontext und in einer Transaktion. Lagerkorrektur verwendet den vorhandenen Stock-Service. |
| Hoch | Zahlreiche alte `get*`, `update*`, `delete*`, Sperr- und Sortiermethoden in Application/Admin, Items, Contacts, Tasks und Positionsets verwendeten nur `id`; ältere eBay-/Shop-Updates umgingen die Entity-Grenze. | Vorhandene `getEntityWhere/getEntitiesWhere/updateById` verwenden; Task-/Campaign-Sets und Integrationsmodelle nutzen die vorhandene DEEC-Entity-Basis. Bulk-Sortieren und Bulk-Löschen werden ebenfalls eingegrenzt. |
| Hoch | Alte Controller reichten nach Prüfung des ersten Formularfeldes zusätzlich gepostete Felder an Models weiter. `id`, `clientid` und Beziehungsfelder konnten so Änderungen beeinflussen. | Alte Teil-Speicherungen auf definierte Formularfelder begrenzen; Entity-Erstellung übernimmt keine Browser-ID, Updates verändern keine ID, Eigentümer- oder Erstellerdaten. MultiEntity-Beziehungen und Varianten-Eltern sind über gewöhnliche Feldänderungen nicht umhängbar. Absichtliche Admin-Benutzerzuordnungen bleiben nach Validierung des Zielmandanten möglich. |
| Hoch | `DEEC_Controller_MultiEntityAction` kontrollierte nicht durchgehend das Elternobjekt. Das betraf Erstellen, Einzel-/Sammellesen, Bearbeiten, Kopieren und Löschen zugeordneter Datensätze. | Objektkontext-Prüfung in `DEEC_Permission` mit dem bestehenden Modell-/ACL-System; Elternprüfungen in MultiEntity-Hooks und Ansprechpartnerauswahl. `copyAction` verwendet den vorhandenen `loadRow`-Hook. Modulübergreifende Kommentare benötigen weiter Zugriff auf das Elternobjekt, ohne neue fachliche Schreibrechte einzuführen. |
| Hoch | Nichtfiskalische Positions-/Set-Routen und Preisregeln konnten Elternkontext bzw. Quellmodulprüfung umgehen. | Vorhandenen State-Guard auf Items/Processes/Tasks/Campaigns erweitern; gültige Eltern-/Typkombinationen, Kind-, Master- und Setzugehörigkeit prüfen. Preisregeländerungen benötigen Zugriff und Bearbeitungsrecht auf das Quellobjekt; Sperre und Berechnung verwenden dessen tatsächlichen Modulkontext. |
| Hoch | Dashboard und `Contacts_Model_Get::history` lasen Dokumente ohne jeweilige Modul-Leserechte. Der Verlauf veränderte zudem den globalen Registry-Kontext. Listen-/Statistik-/Task-Joins über die fachliche Kontaktnummer konnten gleich nummerierte Kontakte fremder Eigentümer einbeziehen. | Dokument-Leserechte einzeln prüfen; Dokumente dem ausgewählten, gemeinsame Kontakte/Artikel dem freigegebenen Eigentümer zuordnen. Den globalen Kontext beim Verlauf unverändert lassen. Betroffene Joins um den passenden Kontakte-/Items-/Admin-Eigentümer ergänzen. |
| Hoch | eBay- und OpenCart-Log-Ansichten lesen installationsweite Dateien mit Einträgen mehrerer Mandanten. | Zugriff über das vorhandene globale Administrator-Flag beschränken. Keine neue Rolle oder künstliche Zuordnung der Logzeilen zu einzelnen Mandanten. |
| Mittel | Lagerbuchungen prüften den Artikel, aber nicht den Eigentümer des Lagers; Verantwortlichenauflösung konnte fremde Benutzerdaten einbeziehen; Profil-Sperren nahmen eine fremde ID entgegen. | Lagerreferenz gegen den effektiven Items-Kontext prüfen, auch beim Ändern. Dokument-Verantwortliche auf aktive Benutzer des Dokumentmandanten begrenzen; der handelnde Benutzer selbst bleibt auch nach erlaubtem Mandantenwechsel zulässig. Profil-Sperren betreffen ausschließlich den angemeldeten Benutzer. |
| Mittel | Numerische Client-/Shop-/Account-Filter konnten rohe Werte in altes SQL übernehmen. | Positive Integer validieren; ungültige Werte ergeben einen nicht freigegebenen Null-Kontext. |
| Mittel | Die Gast-Ausnahme der Shops-ACL erfasste auch alte Verwaltungscontroller; Datei-Manager-Kontexte konnten einen Mandantenwechsel überleben. | Nur tatsächliche öffentliche Storefront-Controller freigeben; Verwaltungsrouten verlangen Anmeldung. Bei einem erfolgreichen Mandantenwechsel beide Manager-Namespaces einschließlich Clipboard und Token entfernen. Vorhandene Objekt-/Dateiprüfungen bleiben zusätzlich aktiv. |

## Erhaltene Freigaben und Kompatibilität

- Gemeinsame Kontakte, Ansprechpartner, Artikel, Attribute und Optionen bleiben für berechtigte Kindmandanten verfügbar. Die Freigabe gemeinsamer Stammdaten überträgt keine Belege anderer Kindmandanten.
- Belegkopien, Positionen, finale PDF-Dateien, E-Invoice-Vorbereitung, Kommentare, Kontakt-/Artikel-Tags und fachliche Validierungsfehler verwenden weiterhin die bisherigen DEEC-Strukturen.
- Die bestehende Trennung von Lese- und Schreibrechten bleibt bestehen. Für Dateien gilt zusätzlich unverändert die strengere Regel aus Phase 4: Auch Bearbeiten/Löschen setzt Leserecht am Elternobjekt voraus.
- Storefront, Warenkorb, Checkout, Produktmedien und veröffentlichte Downloads bleiben öffentlich nach dem bestehenden Site-Kontext. Die Checkout-Bestellung erhält ihren Eigentümer ausdrücklich vom serverseitig ausgewählten Shop, auch bei einem angemeldeten ERP-Besucher.
- JSON-Antworten behalten die vorhandenen `ok`, `item/items`, `id`, `values`, `display`, `deletedIds/blockedIds` und Meldungsschlüssel. Verbotene AJAX-Schreibaufrufe liefern 403 statt einer Weiterleitung auf eine lesbare Seite. Fremde/nicht gefundene Objekte werden ohne Datensatzinhalt beantwortet.
- Keine Änderung an Drittanbieterbibliotheken, Datenbankschema, Production-/Development-Konfiguration, öffentlichem PHP-Allowlisting oder `global.js`.

## Bewusst unveränderte Regeln und Grenzen

1. Keine verantwortlichen- oder erstellerbezogenen Exklusivrechte erfunden: Das vorhandene System vergibt Modulaktionen und Mandantenzugriff, keine allgemeinen persönlichen Eigentumsrechte an Geschäftsdatensätzen. Die weitere fachliche Einschränkung einzelner Benutzer innerhalb eines Mandanten bleibt eine Produktentscheidung.
2. Authentifizierte Tasks-/Campaigns-/eBay-/Shop-Verwaltung bleibt gemäß bestehender ACL-Architektur ohne neue Berechtigungsspalten. Das ist eine breite Berechtigung innerhalb des erlaubten Mandanten. Die globalen Integrationslogs sind davon ausdrücklich ausgenommen.
3. Die globale Tabelle `module` besitzt keine `clientid`. Ihre internen, globalen Abfragen und die bestehenden Admin-Grenzen wurden nicht durch einen unpassenden Mandantenfilter ersetzt. Authentifizierungs-/Cron-Abfragen von Benutzern und die signierte Unsubscribe-Auflösung bleiben notwendige Ausnahmen zu gewöhnlichen Objektabfragen. Beim Unsubscribe wird das Token weiterhin vor Änderungen geprüft.
4. Nicht aufgerufene alte Hilfsmethoden, etwa `Statistics_Model_DbTable_Archive` und einige fehlerhafte SKU-Sammelmethoden, sind keine neuen öffentlichen APIs. Für sie wurden keine neuen fachlichen Rechte oder Schema-Reparaturen erfunden. Vor einer künftigen Controller-Verwendung benötigen sie eine eigene Berechtigungsprüfung. Direkte HTTP-Aufrufe interner Models bleiben durch die bestehenden Apache-Regeln gesperrt.
5. Einige alte Integrationscontroller referenzieren bereits fehlende Models bzw. uneinheitliche Methodensignaturen, insbesondere alte Shop-Account-/eBay-Item-Prototypen. Diese wurden nicht zu neuen funktionsfähigen Integrationen umgebaut. Echte Versand-, Zahlungs-, eBay-, FTP- oder Mail-Aktionen wurden nicht ausgeführt.
6. Kein vollständiger Browserdurchlauf mit produktiven Daten. Die neuen HTTP-Tests verwenden echte ACL, Router, DEEC-Aktionen, Models und TrashController mit isolierten Daten; der Ansprechpartner-Testcontroller ersetzt UI-Initialisierung und stellt ein begrenztes Testformular bereit. Die eigentlichen Berechtigungs-Hooks werden nicht ersetzt. SQL-Join-Bedingungen werden zusätzlich an den echten Query-Buildern geprüft. Die Tests beweisen die geprüften Fälle, keine allgemeine Fehlerfreiheit sämtlicher Erweiterungen.
7. Bestehende inkonsistente Daten werden nicht automatisch umgeschrieben. Neue Lagerreferenzen müssen gültig sein; die fachliche Behandlung bereits archivierter oder inkonsistenter Altbuchungen bleibt unverändert. Es wurde kein allgemeines CSRF-System für sämtliche alten ERP-Aktionen ergänzt.

## Ausgeführte Tests

Alle abschließenden Läufe erfolgreich:

| Test | Ergebnis |
| --- | --- |
| Alle 27 PHP-Skripte direkt unter `tests/security/` | PASS; darunter `tenant-access.php`: 268 neue Fälle, `permissions.php`: 369, `client-switch.php`: 53, `bootstrap-auth.php`: 29, `document-edits.php`: 7.400 |
| `tenant-access-http.py` | PASS: 48 Apache-Fälle in Root- und Unterverzeichnisinstallation; erlaubte/unerlaubte Objektzugriffe, AJAX-Änderungen, Kopieren, Zielberechtigungen des Papierkorbs, freigegebener Elternmandant, keine internen Fehlerdetails |
| `configuration-http.py` | PASS: 8.367 Apache-Fälle; 3.938 PHP-/Include-Dateien inventarisiert; bestehende Manager-CSRF-Prüfungen |
| `error-handling-http.py` | PASS: 140 Fälle zu Production/Development, HTML/JSON, Initialisierung/Bootstrap, internem Logging, Cron, Varianten, Installer und Managern |
| `attachment-http.py` | PASS: privater statischer Bypass reproduziert und geschlossen; Objekt-/Mandanten-/Methoden-/Traversal-/Symlink-/Cache-Prüfungen und öffentliche Medien |
| `session-http.py` | PASS: 25 HTTP-Sessionfälle |
| `tests/documents/calculation.php` | PASS: Sales 647, Purchases 623, Processes 617 Fälle |
| `tests/documents/e-invoice.php`, `lifecycle.php`, `pdf-totals.php`, `pdf.php`, `position-read.php` | PASS: 369 / 879 / 170 / 134 / 233 Fälle |
| `tests/contacts/history-states.php`, `tests/items/stock-summary.php`, `tests/lists/summary.php`, `tests/sales/quote-monitoring.php`, `tests/site/cms-structure.php` | PASS; darunter 14 übersetzte Verlaufsstatus, 293 Quote-Monitoring- und 134 CMS-Fälle |
| `tests/install/import-db.py` | PASS auf isolierter MariaDB 10.11.14: vollständige Installation, Indexe, Upgrade-Migrationen, Fehlerfälle und 66 UOM-Prüfungen |
| `tests/documents/calculation-db.py` | PASS auf isolierter MariaDB: Berechnung, Steuerbefreiung, Rollback, Elternsperre, konkurrierende Finalisierung desselben bzw. verschiedener Belege, unabhängige Mandanten, Nummerierung, Wiederholung und abgewiesene Zugriffe |
| Syntax und Diff | `php -l` auf allen 127 geänderten/neuen PHP-Dateien, Python-Syntaxprüfung und `git diff --check`: PASS |

Der gemeinsame abschließende PHP-Lauf umfasste 38 Skripte ohne Fehler; die beiden zusätzlichen Berechnungsvarianten und die HTTP-/MariaDB-Läufe wurden separat ausgeführt. Erwartete negative Testfälle erzeugen interne `NO_PERMIT`-/Exception-Logs, keine fehlgeschlagenen Tests.

Die Socket-Starts wurden zunächst von der Sandbox blockiert und anschließend mit Freigabe isoliert ausgeführt. Der alleinige Start von `tests/admin/uom.php` ohne Test-Socket meldete erwartungsgemäß SKIP; über `import-db.py` liefen die 66 Fälle erfolgreich. Der zusätzliche MariaDB-Berechnungstest enthielt zunächst veraltete Fixtures ohne gültigen Kontakt und Positionsinhalt. Diese Testdaten wurden an den bestehenden Finalisierungsvalidator angepasst; dessen Produktionsprüfung wurde nicht gelockert. Authentifizierungs-/Rollenfixtures in Berechnungs-, Kontakt- und Verlaufstests wurden ebenfalls an die tatsächlichen Zugriffsvoraussetzungen angepasst.

## Geänderte Dateien

Die vielen Model-Dateien enthalten überwiegend kleine Ersetzungen alter ID-Abfragen durch die bereits vorhandene Entity-API; es wurde keine neue Produktionsklasse eingeführt. Vollständige Liste der 130 geänderten bzw. neuen Dateien dieses Auftrags:

- `application/controllers/IndexController.php`
- `application/controllers/TrashController.php`
- `application/controllers/helpers/Calculate.php`
- `application/controllers/helpers/Query.php`
- `application/models/DbTable/Category.php`
- `application/models/DbTable/Comment.php`
- `application/models/DbTable/Deliverytime.php`
- `application/models/DbTable/Footer.php`
- `application/models/DbTable/Manufacturer.php`
- `application/models/DbTable/Media.php`
- `application/modules/admin/models/DbTable/Category.php`
- `application/modules/admin/models/DbTable/Config.php`
- `application/modules/admin/models/DbTable/Country.php`
- `application/modules/admin/models/DbTable/Currency.php`
- `application/modules/admin/models/DbTable/Deliverytime.php`
- `application/modules/admin/models/DbTable/Exchangerate.php`
- `application/modules/admin/models/DbTable/Filename.php`
- `application/modules/admin/models/DbTable/Footer.php`
- `application/modules/admin/models/DbTable/Increment.php`
- `application/modules/admin/models/DbTable/Language.php`
- `application/modules/admin/models/DbTable/Manufacturer.php`
- `application/modules/admin/models/DbTable/Page.php`
- `application/modules/admin/models/DbTable/Paymentmethod.php`
- `application/modules/admin/models/DbTable/Shippingmethod.php`
- `application/modules/admin/models/DbTable/Shop.php`
- `application/modules/admin/models/DbTable/Slug.php`
- `application/modules/admin/models/DbTable/Tag.php`
- `application/modules/admin/models/DbTable/Tagentity.php`
- `application/modules/admin/models/DbTable/Taxrate.php`
- `application/modules/admin/models/DbTable/Template.php`
- `application/modules/admin/models/DbTable/Textblock.php`
- `application/modules/admin/models/DbTable/Uom.php`
- `application/modules/admin/models/DbTable/User.php`
- `application/modules/admin/models/Entity/Category.php`
- `application/modules/admin/models/Entity/Menu.php`
- `application/modules/admin/models/Entity/Page.php`
- `application/modules/campaigns/models/DbTable/Campaignposset.php`
- `application/modules/contacts/controllers/ContactpersonController.php`
- `application/modules/contacts/controllers/DownloadController.php`
- `application/modules/contacts/controllers/DownloadsetController.php`
- `application/modules/contacts/models/DbTable/Contact.php`
- `application/modules/contacts/models/DbTable/Downloadset.php`
- `application/modules/contacts/models/DbTable/Emailattachment.php`
- `application/modules/contacts/models/DbTable/Emailmessage.php`
- `application/modules/contacts/models/DbTable/Emailtracking.php`
- `application/modules/contacts/models/Entity/Contact.php`
- `application/modules/contacts/models/Get.php`
- `application/modules/contacts/services/ContactEditViewModel.php`
- `application/modules/ebay/controllers/AccountController.php`
- `application/modules/ebay/controllers/IndexController.php`
- `application/modules/ebay/controllers/ListingController.php`
- `application/modules/ebay/controllers/LogController.php`
- `application/modules/ebay/controllers/OrderController.php`
- `application/modules/ebay/models/DbTable/Account.php`
- `application/modules/ebay/models/DbTable/Listing.php`
- `application/modules/ebay/models/DbTable/Order.php`
- `application/modules/ebay/models/Get.php`
- `application/modules/items/controllers/AttributeController.php`
- `application/modules/items/controllers/InventoryController.php`
- `application/modules/items/controllers/ItemlistController.php`
- `application/modules/items/controllers/LedgerController.php`
- `application/modules/items/controllers/OptionController.php`
- `application/modules/items/controllers/PositionsetController.php`
- `application/modules/items/controllers/PriceruleposController.php`
- `application/modules/items/models/DbTable/Inventory.php`
- `application/modules/items/models/DbTable/Item.php`
- `application/modules/items/models/DbTable/Itematrset.php`
- `application/modules/items/models/DbTable/Itemlist.php`
- `application/modules/items/models/DbTable/Itemoptset.php`
- `application/modules/items/models/DbTable/Pricerule.php`
- `application/modules/items/models/Entity/Ledger.php`
- `application/modules/items/models/Entity/Stock.php`
- `application/modules/items/services/Stock.php`
- `application/modules/processes/models/DbTable/Process.php`
- `application/modules/processes/models/DbTable/Processposset.php`
- `application/modules/processes/models/Entity/Process.php`
- `application/modules/purchases/models/DbTable/Purchaseorderposset.php`
- `application/modules/purchases/models/DbTable/Quoterequestposset.php`
- `application/modules/purchases/models/Entity/Purchaseorder.php`
- `application/modules/purchases/models/Entity/Quoterequest.php`
- `application/modules/sales/models/DbTable/Creditnoteposset.php`
- `application/modules/sales/models/DbTable/Deliveryorderposset.php`
- `application/modules/sales/models/DbTable/Invoiceposset.php`
- `application/modules/sales/models/DbTable/Quoteposset.php`
- `application/modules/sales/models/DbTable/Reminderposset.php`
- `application/modules/sales/models/DbTable/Salesorderposset.php`
- `application/modules/sales/models/Entity/Creditnote.php`
- `application/modules/sales/models/Entity/Deliveryorder.php`
- `application/modules/sales/models/Entity/Invoice.php`
- `application/modules/sales/models/Entity/Quote.php`
- `application/modules/sales/models/Entity/Reminder.php`
- `application/modules/sales/models/Entity/Salesorder.php`
- `application/modules/shops/controllers/AccountController.php`
- `application/modules/shops/controllers/CheckoutController.php`
- `application/modules/shops/controllers/LogController.php`
- `application/modules/shops/controllers/OrderController.php`
- `application/modules/shops/models/DbTable/Account.php`
- `application/modules/shops/models/DbTable/Order.php`
- `application/modules/statistics/models/Abstract.php`
- `application/modules/tasks/models/DbTable/Task.php`
- `application/modules/tasks/models/DbTable/Taskposset.php`
- `application/modules/tasks/models/Get.php`
- `application/modules/users/controllers/UserController.php`
- `application/modules/users/models/DbTable/User.php`
- `application/plugins/Acl.php`
- `application/plugins/Client.php`
- `application/plugins/State.php`
- `library/DEEC/Controller/Action.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/Controller/MultiEntityAction.php`
- `library/DEEC/Controller/PositionAction.php`
- `library/DEEC/List/Query.php`
- `library/DEEC/Model/DbTable/Entity.php`
- `library/DEEC/Permission.php`
- `library/DEEC/Query.php`
- `tests/contacts/history-states.php`
- `tests/documents/calculation-db.php`
- `tests/documents/calculation.php`
- `tests/security/bootstrap-auth.php`
- `tests/security/client-switch.php`
- `tests/security/contact-tags.php`
- `tests/security/contactpersons.php`
- `tests/security/document-edits.php`
- `tests/security/fixtures/entity.php`
- `tests/security/fixtures/tenant-access.php`
- `tests/security/permissions.php`
- `tests/security/tenant-access-http.py`
- `tests/security/tenant-access-inventory.tsv`
- `tests/security/tenant-access-security-review.md`
- `tests/security/tenant-access.php`

## Commit-Vorschlag

`enforce tenant isolation and object access checks`
