# T1 – Audit des Übersetzungssystems

Stand: 19. November 2026, lokaler Workspace `/var/www/dewawi`, Ausgangscommit `c59fa45d`.
Der lokale Arbeitsstand einschließlich bereits vorhandener Änderungen war maßgeblich.
Während des Audits wurden fremde Änderungen separat bis `d68f7a8c` committed; der
Fingerabdruck der untersuchten Produktquellen blieb dabei identisch. Der Abschlusslauf
verwendet diesen aktuellen lokalen Stand.
Produktivcode, Sprachdateien, Datenbanken und Migrationen wurden in T1 nicht geändert.

## Ausführen und Berichte lesen

```bash
cd /var/www/dewawi
python3 tests/translations/audit.py > /tmp/dewawi-translations-audit.json
python3 tests/translations/audit.py --summary
PYTHONDONTWRITEBYTECODE=1 python3 tests/translations/audit-test.py
php tests/translations/runtime.php
php tests/translations/runtime.php --benchmark
```

Python 3 und PHP CLI mit den bestehenden ZF1-Abhängigkeiten genügen; keine neue Bibliothek.
`audit.py` schreibt ausschließlich JSON auf stdout. Kataloge werden mit PHP `token_get_all`
und `TOKEN_PARSE` untersucht, ohne sie auszuführen. Zulässig sind ausschließlich direkt
zurückgegebene Arrays aus String-Schlüsseln und String-Werten. Andere Ausdrücke werden
als Fehler gemeldet; es gibt kein `eval`. Die Syntax-/Formatprüfung bewahrt doppelte
Definitionen, die ein gewöhnliches `require` bereits überschreiben würde.

Standardmäßig folgt ein isolierter CLI-Lauf der echten Übersetzungs-, Bootstrap-Locale-,
Plugin- und Helper-Methoden. Authentifizierung, Sessionbeschaffung, Bootstrap-Ressourcen
und Konfigurationsmodell sind dabei Fixtures. Keine Datenbankverbindung, HTTP-Anfrage,
echte Sitzung, Mailzustellung oder PDF-Erzeugung wird gestartet. Nur bekannte Testdateien
werden in einem eigenen zufälligen `/tmp`-Verzeichnis angelegt und wieder entfernt.
`--no-runtime` überspringt auch diesen Anwendungscode und führt nur die statische Analyse aus.
`runtime.php` allein setzt vertrauenswürdige lokale PHP-Kataloge voraus; `audit.py` prüft
deren Arrayformat und Encoding vor dem Laufzeittest.

Exitcodes von `audit.py`: **0** = Audit erfolgreich ausgeführt, vorhandene Befunde sind
weiterhin im Bericht; **1** = Befunde mit `--fail-on-findings` (für diesen T1-Stand erwartet);
**2** = Katalog-/Encodingfehler oder fehlgeschlagener Laufzeit-/Modellabgleich.
Ein grüner Charakterisierungstest bedeutet ausdrücklich nicht, dass die dokumentierten
Produktfehler behoben sind. Ein fehlendes PHP/Python oder ein Prozessfehler bricht den Lauf ab.

Der eingecheckte [report.json](report.json) enthält das vollständige datei- und zeilengenaue
Inventar samt Texten, Datei-/Schlüsseldifferenzen, Überschreibungen, Verwendungen,
dynamischen Aufrufen, Kandidaten und Laufzeitresultaten. Der Hash `source_sha256`
bezieht sich auf die gelesenen Produktquellen und Kataloge; Einzelhashes und Hashes der
Auditprogramme erlauben den Abgleich eines späteren lokalen Laufs. Keine Zeitstempel
oder Benchmarkzeiten werden in den normalen Bericht eingefügt.
[validation.json](validation.json) protokolliert zusätzlich tatsächlich ausgeführte
Bestandstests und den einmaligen CLI-Benchmark.

Wichtige JSON-Felder:

| Feld | Bedeutung |
| --- | --- |
| `catalogues` | Alle PHP-Dateien, SHA-256, Bytes, Einträge einschließlich doppelter Definitionen |
| `file_pairs`, `missing_unique_keys` | Dateivergleich und sprachweiter Schlüsselvergleich getrennt |
| `module_language_matrix` | Vorhandene Kataloge und tatsächliche Anwendungsmodule |
| `request_loading` | Ladefolge, require-Anzahl, effektive Schlüssel und Kollisionen je Requestkontext |
| `unresolved_request_calls` | Statisch modellierte Ladelücken mit `exists_elsewhere` zur Unterscheidung von Kataloglücken |
| `usage`, `dynamic_calls` | Direkte und indirekte Belege; keine Löschfreigabe |
| `issues` | Encoding, Syntax/Format, Duplikate, Platzhalter, Sprach- und Schreibbefunde |
| `hardcoded_candidates`, `indirect_translation_candidates_absent_from_catalogues` | Manuell zu prüfende UI-/Formularfundstellen |
| `javascript_bridge` | JS-Schlüssel gegenüber dem tatsächlich ausgeführten Message-Export |
| `runtime.static_model_comparisons` | Abgleich aller effektiven Texte mit den echten Klassen für 28 Kontexte |

## Architektur und tatsächliche Ladefolge

1. [application/Bootstrap.php](../../application/Bootstrap.php), `_initLocale()` ab Zeile 128,
   bootstrapped zunächst den Mandantenkontext. Die Locale stammt vorrangig aus
   `Zend_Auth`-Session `storage->language`, danach aus der Mandantenkonfiguration.
   Voraussetzung ist lediglich `file_exists(languages/<Wert>)`. Sonst wird der dritte
   `scandir`-Eintrag gewählt: aktuell `de_DE`, ohne explizite Priorität oder Sprachen-Allowlist.
   `Zend_Locale` und `Zend_Currency` werden in der Registry hinterlegt; Konfiguration und
   Zeitzone werden nur für angemeldete Benutzer herangezogen.
2. Eine neue Instanz [DEEC_Translate](../../library/DEEC/Translate.php) erhält die Locale.
   `languages/<locale>/default/*.php` wird sortiert unter Domain `default` geladen und
   unter `DEEC_Translate` in der Registry registriert. Kein `Zend_Translate`-Adapter und
   keine Registrierung als `Zend_Translate`/`Zend_Translate_Adapter`.
3. [application/plugins/Translate.php](../../application/plugins/Translate.php),
   `routeShutdown()`, lädt **alle** vorhandenen `<module>/common.php`, außer `default`,
   in alphabetischer Verzeichnisreihenfolge als `<module>_common`. Das ist eine explizite
   modulübergreifende Basis für Menüs und geteilte Bezeichner; Berechtigungen und
   Feature-Abonnements filtern diese Dateien nicht.
4. Danach lädt der Plugin alle PHP-Dateien des gerouteten aktiven Moduls sortiert in
   dessen Domain. Ein aktives `common.php` wird dabei nochmals per `require` ausgewertet,
   unter einer zweiten Domain. Bei `default` verhindert das bestehende Loaded-Flag eine
   zweite Verzeichnisauswertung. Es wird nicht rekursiv nachgeladen.
5. Der Plugin setzt die View-Sprache. Nur bei vorhandener `User`-Registry wird der
   Sprachumschalter über [Language.php](../../application/models/DbTable/Language.php)
   aus mandantengefilterten, nicht gelöschten Datenbanksprachen aufgebaut. Das ist eine
   andere Sprachenmenge als die tatsächlich vorhandenen Verzeichnisse. Die Beispieldaten
   in `install/config/data.sql:280` installieren nur `de_DE` als Mandantensprache.

**Wesentliche Priorität:** Innerhalb einer Datei gewinnt die letzte doppelte Definition;
innerhalb einer Domain gewinnt die alphabetisch spätere Datei. Zwischen Domains gewinnt
hingegen die **zuerst** geladene Domain: `default` vor allen Common-Domains vor aktivem
Modul. `t($key, $args, $domains)` kann eine andere Suchreihenfolge explizit vorgeben.
`load()`/`loadDir()` erlauben nach erfolgreicher Markierung einer Domain keine Ergänzung.

Die Standard-Domains lauten aktuell:

```text
de_DE: default → calendar_common → contacts_common → items_common
       → processes_common → purchases_common → sales_common
       → statistics_common → users_common → [aktives Modul]
en_US: default → calendar_common → items_common → [aktives Modul]
```

Das eng gefasste Ziel „nur globale und tatsächlich benötigte Modulübersetzungen“ wird
damit **nicht vollständig erfüllt**. Nur die Detaildateien bleiben auf das aktive Modul
begrenzt; alle Common-Kataloge sind globale Abhängigkeiten. Ein Wechsel zu ausschließlich
aktivem Common würde bestehende Menüs brechen. T2 sollte die benötigten gemeinsamen
Schlüssel bestimmen und die bestehende modulabhängige Architektur beibehalten.

### Helper, Komponenten und Sonderpfade

| Pfad | Verhalten und Grenze |
| --- | --- |
| `application/views/helpers/Translate.php:3` | Projektklasse heißt wie ZF1 `Zend_View_Helper_Translate`; `configs/application.ini:13` weist auf den Projekthelper. Keine Registry/falscher Typ: unveränderter Schlüssel. Keine automatische HTML-Escapierung. |
| `library/DEEC/Form.php:358`, `List.php:149`, `Pdf.php:47` | Expliziter Translator vor Registry; das einmal ermittelte Objekt bleibt in der Komponente. `Form::setLocale()` steuert keine Katalogauswahl. Die üblichen Labels, Optionswerte und Validierungsfehler werden indirekt übersetzt. |
| `application/forms/Language.php`, `Client.php` | Restliche `Zend_Form`-Selektoren neben den DEEC-Formularen. Kein allgemeiner ZF1-Translator registriert; Optionsnamen kommen aus den jeweiligen Datenquellen. |
| `application/plugins/ListResponse.php:5` | AJAX-Suche rendert Content und Toolbar im bereits geladenen Requestkontext; kein zusätzlicher Katalogloader. |
| `application/views/scripts/partials/*`, modulübergreifende Helper | Partials und gemeinsam verwendete Komponenten laden keine Herkunftsmodulkataloge selbst. Maßgeblich bleibt das geroutete Modul. |
| `library/Zend/Controller/Front.php:923` und `:941` | `routeShutdown` liegt vor der Dispatch-Schleife. Ein späteres Forward/Modulwechsel löst keinen erneuten Übersetzungs-Route-Hook aus. Dynamische Forwards deshalb gesondert testen. |
| `application/cli/contact-geocoding-dry-run.php:86` | CLI lädt ausdrücklich `de_DE/default` und `de_DE/contacts`; kein HTTP-Plugin. Andere Geocoding-CLI-Pfade verwenden eigene Bootstrap-/Workerlogik. |
| `tools/document-pdf-availability.php:105` | Eigenständige Registrierung mit nur `default`; in T1 nicht ausgeführt, da Werkzeug Dokumente/DB verarbeitet. |
| `application/modules/calendar/models/Invitation.php:16,28` | Explizite Allowlist `de_DE/en_US`, separater Translator nur für `calendar`; Mails erhalten dessen Texte. Keine automatische globale Domain in diesem Translator. |
| `library/DEEC/Feedback.php:213` | Eigener `default`-Translator bei vorhandenem Locale-Verzeichnis; sonst Registry. Feedback-E-Mail-Blöcke können der Dokumentensprache folgen. |
| `application/modules/sales/services/CreateDataFactory.php:35`, entsprechende Purchases-/Processes-Services | Standardbelegtitel werden über den Registry-Translator erzeugt, unabhängig von der primären Dokumentensprache aus der Sprachentabelle. |

E-Mail: `application/controllers/helpers/Email.php:485–625` ersetzt im Shop-Body zuerst
fachliche Platzhalter, dann `t#tKEYt#t` mittels des aktuellen View-Translators. Der Betreff
wird dort unverändert aus dem Template übernommen. `#HELLO_NAME#` wird über den
View-Translator aufgebaut; `#PAYMENT_METHOD#` ist fest `Überweisung` (`:555`).
Sales/Purchases/Processes `services/EmailFormFactory.php` verwenden gespeicherte Templates
und ersetzen unter anderem `[DOCID]`/`[CONTACTID]`. Eine allgemeine Auswahl eines
E-Mail-Katalogs nach Dokumentensprache ist dort nicht implementiert. Gespeicherte
Templateinhalte und die real aktivierten Sprachen wurden ohne Datenbankzugriff nicht geprüft.

PDF: `DEEC_Pdf::translate()` verwendet normalerweise den injizierten/Registry-Translator;
`document['language']` allein wechselt ihn nicht. `correction()` lädt dagegen in
`library/DEEC/Pdf.php:140` einen neuen `default`-Translator nach `en* → en_US`, sonst `de_DE`.
Auch `Sales_Service_DocumentPayloadService::build()` ab Zeile 40 verwendet diese Zuordnung
für die Revisionsbezeichnung. `Pdf.php:425–428` enthält einen weiteren lokalen Fallback
auf `default/document.php` für einen speziellen Textpfad. Dadurch können UI-Sprache,
Dokumentenüberschrift, Korrekturtext und Zahlenformatierung voneinander abweichen.
Die tatsächliche vollständige PDF-Ausgabe wurde nicht erzeugt; das Verhalten des
Übersetzungsadapters und die genannten Codepfade sind belegt. Historische Originale
dürfen in T2 nicht durch Nachübersetzen oder Neuberechnung verändert werden.

Shop/CMS: `DEEC_Site_Context::getLanguage()` (`library/DEEC/Site/Context.php:38`) speist
HTML-Sprache und Metadaten (`application/layouts/scripts/site.phtml:8`,
`application/modules/shops/views/helpers/SiteMetadata.php:172`), aber nicht `_initLocale()`.
Für einen anonymen englischen SiteContext wählt der Bootstrap im Test dennoch `de_DE`.
Shop-Sprachen/Dateinamen, Produkt- und CMS-Inhalte liegen zusätzlich in mandanten-/shopbezogenen
Datenbanktabellen. Sie sind kein PHP-Katalog und wurden nicht aus Kundendaten gelesen.

## Fallback, Fehler und Cache

| Fall | Nachgewiesenes Verhalten | Priorität |
| --- | --- | --- |
| Schlüssel/Domain fehlt | Schlüssel selbst; kein `en_US → de_DE`, kein Region-zu-Basissprache-Fallback, kein Missing-Key-Logging | P1 |
| Wert ist leer | Leerer String, kein Fallback. Aktuelle Kataloge haben keine leeren Werte | P2 |
| Formatargumente fehlen | Ohne Argumentliste bleibt der rohe Formattext stehen | P2 |
| Zu wenige Argumente / ungültiges Format mit Argumenten | PHP 8.3 wirft `ValueError`; `catch (Exception)` in `Translate.php` fängt dies nicht. Synthetisch reproduziert; keine aktuell fehlerhaften Katalogplatzhalter gefunden | P1 |
| `setLocale()` | Ändert nur den Namen; Daten und Loaded-Flags bleiben alt. `setLocale('')` ergibt `de`, obwohl nur `de_DE` existiert | P1 für wiederverwendete Instanzen |
| Fehlende Datei bei `load()` | Domain wird trotzdem dauerhaft als geladen markiert; spätere gültige Datei ignoriert | P1 |
| Fehlendes Verzeichnis bei `loadDir()` | Nicht markiert, späterer Versuch möglich | dokumentierte Asymmetrie |
| Falscher Rückgabetyp | Lautlos ignoriert, Domain danach gesperrt | P1 |
| Syntaxfehler in Katalog | `ParseError` propagiert, keine lokale Behandlung | P1 |
| Ungültige Session-Locale mit existierendem Pfad | `../application` besteht `file_exists`, wird in ZF1 zu `root`, danach `Zend_Currency_Exception`. Keine Feststellung einer beliebigen Dateiausführung oder eines Mandantenzugriffs | P1 |

`application/modules/users/controllers/UserController.php:356` speichert den angeforderten
Sprachwert ungeprüft in die Session. T2: gegen installierte/erlaubte Locale-Codes prüfen
und einen expliziten gültigen Default wählen. Benutzerrechte, Sprachverfügbarkeit und
SaaS-Abonnements dabei weiterhin getrennt halten.

`DEEC_Translate` besitzt nur einen Instanzcache (`data`, `loaded`). Der separat initialisierte
`Zend_Cache` aus `Bootstrap::_initCache()` wird hier nicht genutzt. Neue Instanzen lesen
geänderte Dateien erneut; geladene Instanzen sehen Änderungen nicht. PHP-OPcache kann
die Kompilierung beeinflussen, ersetzt aber keinen Übersetzungsdaten-/Locale-Cache.
Für langlebige Worker sind deshalb pro Locale/Verarbeitungsschritt getrennte Instanzen
oder eine ausdrücklich geprüfte Lebensdauer nötig. Geocoding-/Kalender-DB-Worker wurden
nicht gegen laufende Datenbanken gestartet.

## Sichere Fehler und Korrekturempfehlungen

P1 bezeichnet funktionale Anzeige-/Laufzeitprobleme; P2 redaktionelle oder wartungsbezogene
Fehler. Eine bestätigte fehlende Definition ist von der Sichtbarkeit eines bestimmten
Requestpfads zu unterscheiden. Die vollständigen Fundstellen stehen im JSON.

| Befund | Konkrete Belege | Empfehlung für T2 |
| --- | --- | --- |
| Englische Navigation verliert gemeinsame Modultexte | `en_US/sales/common.php`, `contacts/common.php`, `purchases/common.php` usw. fehlen; `MENU_SALES` bleibt auf einem englischen `default`-Request als Schlüssel stehen. Mit echtem Plugin reproduziert | Gemeinsame Bezeichner für beide Sprachen vervollständigen; Menüs außerhalb des eigenen Moduls testen |
| Geteilte Kontaktanzeige hat ungeladene Schlüssel | `application/modules/sales/views/helpers/ContactData.php:8,11,23`, Purchases `:8,11,27`: `CONTACTS_CONTACT_DATA`, `CONTACTS_PHONE`, `CONTACTS_INTERNET` liegen in Contacts-Details. `CONTACTS_PHONE` fällt in beiden Sprachen im Sales-Kontext auf den Schlüssel zurück | Tatsächlich gemeinsam benötigte Kontaktbezeichner in den bestehenden gemeinsamen Katalogbereich einordnen |
| Beide Sprachen enthalten unaufgelöste direkte Schlüssel | 28 Aufrufstellen / 21 verschiedene Schlüssel fehlen in sämtlichen Katalogen; u. a. `ADMIN_NEW_COUNTRY`, `STOCKS`, `POSITIONS_SET`, `ITEMS_DATA_SHEET`, `TOOLBAR_ADD`, `SUBMIT` | Jeden fachlichen Aufrufer prüfen; bestehende äquivalente Schlüssel bevorzugen. `DEWAWI` ist ein beabsichtigter Markenfallback, kein Übersetzungsfehler |
| Fehlende Formularbezeichner | 33 Kandidaten an Label-/Heading-Sinks; z. B. `SAVE` in `application/modules/users/forms/Password.php:52`, `ADMIN_CATEGORY_HEADER` in `admin/forms/Category.php:202` | Nach Renderingprüfung richtige Definition/Verwendung ergänzen; Metadatenkandidaten nicht pauschal als sichtbaren Fehler zählen |
| Englische Passworttexte fehlen | `USERS_PASSWORD_ACTUAL/NEW/CONFIRM` sind in `de_DE/default/common.php`, aber weder in `en_US/default/user.php` noch anderswo englisch vorhanden | Englische Passwortformulare einschließlich Submit und Validierung prüfen |
| Deutsche PDF-Texte im englischen Katalog | Neun Werte `DOCUMENTS_*_ID_%s_FROM_%s` in `languages/en_US/default/document.php:190–198`, etwa „Angebotsnummer %s vom %s“ | Alle neun fachlich korrekt übersetzen; Formatargumente unverändert erhalten |
| Weitere klar fremdsprachige Werte | `en_US/default/order.php:23–24` „aufsteigend“/„absteigend“; `en_US/default/countries.php:8` „Antigua und Barbuda“; `en_US/ebay/ebay.php:23` „Nachricht vom Käufer“; auch `ebiztrader/ebiztrader.php:23` | Sichtbare Texte übersetzen; Marken-/Versandproduktnamen gesondert beurteilen |
| Deutscher E-Mail-Text bleibt englisch | `languages/de_DE/default/common.php:25`: `EMAIL_MESSAGE => Message` | „Nachricht“; bestehende E-Mail-Abläufe beibehalten |
| Bestätigte Schreibfehler | 17 Definitionsstellen, vollständig unter `issues.confirmed_spelling_errors`; u. a. `Reflesh`, `succesfully`, `Dowland PDF`, `Billding address`, `COuntry`, `DoCument`, `Payed`, `Messeinheit` | Nur Werte korrigieren; historisch falsch geschriebene Schlüssel nicht umbenennen |
| Duplikate überschreiben Texte | 16 Wiederholungsdefinitionen innerhalb von Dateien, davon fünf mit anderem Wert; z. B. `DOCUMENTS_PHONE` in `de_DE/default/document.php:146,180` („Telefon“ → „Tel.“), `CONTROL_PANEL_DOCUMENT_MODIFIED` `:12,16`, drei englische `*_DELIVERY_DATE`-Werte („Delivery date“ → „Delivery date:“) | Gewinner und gewünschte Ausgabe ausdrücklich festlegen; in T1 keine Löschung |
| Globaler Katalog überschattet Modulwert | `SHOPS_FOOTER_COOKIE_NOTICE`: `en_US/default/common.php:70` gewinnt gegen `en_US/shops/shops.php:17` mit abweichendem Wortlaut | Gewollte gemeinsame Definition klären, Suchreihenfolge nicht ungeprüft umdrehen |
| UI-Texte umgehen Kataloge | Beispiele im folgenden Abschnitt; 256 automatisch gefundene Kandidaten, keine pauschal bestätigte Fehlerzahl | Nach Oberfläche priorisieren, vorhandene jQuery-/data-Attribut-Verträge erhalten |

### Redaktionelle und fachliche Prüfung

Alle 6.206 Definitionen wurden auf Encoding, Form, Duplikate, Leerwerte und Platzhalter
geprüft. Alle 114 Dateien gingen in den Sprach-/Schlüsselvergleich ein. 318 gleichlautende
DE/EN-Definitionen und 28 deutsche Worttreffer in englischen Werten sind **Prüflisten**,
keine Anzahl falscher Übersetzungen: Ländernamen, Marken, API-Versandnamen, HTML-Abstände
und „Standard“ können korrekt identisch sein. Die expliziten Textfehler oben wurden am
Quelltext bestätigt. Eine vollständige redaktionelle Abnahme jedes ERP-Textes durch einen
Fachübersetzer ist durch diese technische Prüfung nicht ersetzt.

Folgende fachliche Entscheidungen sind vor einer Vereinheitlichung erforderlich:

| Fundstelle | Auffälligkeit | Einstufung / Vorschlag |
| --- | --- | --- |
| `en_US/sales/salesorder.php:9` gegenüber `:10`, `de_DE/sales/common.php:22–24` | Proformarechnung, Auftragsbestätigung und „Sales order“ bezeichnen unterschiedliche Vorgänge | Manuelle Fachentscheidung; Dokumenttitel und Menütitel bewusst unterscheiden |
| `en_US/sales/deliveryorder.php`, `default/document.php:141` | „Delivery order“ für Lieferschein | Terminologieprüfung: meist „Delivery note“; Bedeutung im konkreten Workflow bestätigen |
| `en_US/sales/reminder.php:8`, `default/document.php:144` | „Reminder“ für Mahnung | „Payment reminder“/„Dunning notice“ je Mahnstufe; Kalendererinnerung nicht gleichsetzen |
| `en_US/purchases/purchaseorder.php:10` | `PURCHASE_ORDERS` hat Singularwert „Purchase order“, Menü ist plural | In Listenüberschriften „Purchase orders“ prüfen |
| `de_DE/purchases/purchaseorder.php:57`, `quoterequest.php:55` | „Erhaltene Anzahlung“ im Einkaufsworkflow | Richtung prüfen: regelmäßig „Geleistete Anzahlung“; keine automatische Werteänderung |
| `de_DE/items/attribute.php:9`, `option.php:9` | `*_ORDERING => Anzahl` | Reihenfolge/Sortierung gegenüber Menge im tatsächlichen Formular prüfen |
| `de_DE/items/item.php:17`, `en_US/items/item.php:20` | Einkaufspreis ↔ „Cost“ | Entscheiden, ob Beschaffungspreis oder Kostenbasis gemeint ist |
| `de_DE/default/common.php:7–8`, `en_US/default/common.php:8–9` | Sprachennamen Deutsch/Englisch bleiben in beiden Locales deutsch | Bewusste Eigennamen oder übersetzte Namen als UI-Konvention festlegen |
| Sales/Default allgemein | Quote/Quotation, Contact/Customer, Tax/Taxes/VAT, UOM/Mengeneinheit | Glossar aufbauen; Belegpositionen sind fachlich eher „line items“, kein blindes Ersetzen aller „Position“-Vorkommen |

### Fest codierte Oberflächentexte und JavaScript

Bestätigte Beispiele unabhängig von der heuristischen Kandidatenliste:

- `application/layouts/scripts/site.phtml:91`: Suchplatzhalter „Was suchen Sie?“.
- `application/modules/shops/views/scripts/inquiry/catalog.phtml:9`, `variants.phtml:22,62`,
  `success.phtml:18–38,79`: deutsche Hinweise, Erfolgs-/Fehlertexte und Buttons.
- `js/shops.js:489,501,555,559,643`: deutsche Auswahl-/Validierungs-/Fehlermeldungen.
- `js/global.js:2280,2287`: deutsche Fallbacks im bestehenden Fehlerhandler.
- `js/shops.js:11` setzt `de-DE` für Währungsformatierung; `js/settings.js:165` setzt `lang: 'de'`.
- `application/modules/shops/views/scripts/cart/index.phtml:9–13,43` und Checkout `:27–30,46`
  rufen `translate('Product')`, `translate('Price')` usw. ohne entsprechende Katalogeinträge
  auf. Dadurch bleiben englische Fallbacktexte auch in deutscher UI stehen.
- `application/views/scripts/partials/history.phtml:127` übersetzt einen nicht katalogisierten
  deutschen Vollsatz; er bleibt auch auf Englisch deutsch.

`application/views/helpers/Messages.php:5` exportiert 76 explizit aufgezählte Schlüssel;
`layout.phtml:40` und `plain.phtml:32` serialisieren sie mit `JSON_HEX_*` nach `dwMessages`.
`Dewawi.message()` in `js/global.js:27` hat nur den Schlüssel als Fallback und kein
automatisches Nachladen/Formatieren. Alle 36 unmittelbar literal übergebenen JS-Schlüssel
der untersuchten Quellen sind in diesem Export vorhanden. Bedingte/dynamische Argumente
werden gesondert inventarisiert; daraus folgt keine Vollabdeckung aller JS-Pfade.
Kalender/Shop/Map nutzen zusätzlich serverseitige `data-*`-Texte bzw. eigene Textobjekte.
AJAX-Fragmente erhalten keinen neuen globalen Katalog allein durch Einfügen ins DOM.

## Verwendungen und Grenzen der Unbenutztanalyse

Untersucht wurden 1.085 Produktquelldateien: 837 PHP, 217 PHTML, zehn JavaScript,
15 SQL, vier INI, eine JSON und eine HTML. Darin wurden 1.443 literale
Übersetzungsaufrufe erkannt: 1.429 schlüsselartige und 14 Volltext-/Marken-/Textfallback-
Aufrufe. 830 unterschiedliche schlüsselartige Literale verteilen sich auf 809 definierte
und 21 nirgends definierte Schlüssel. 194 Aufrufe sind dynamisch/indirekt und werden
mit Ausdruck und ggf. zusammengesetztem Präfix ausgegeben.

Die 3.607 verschiedenen Katalogschlüssel werden konservativ klassifiziert:

| Klasse im Bericht | Anzahl | Aussage |
| --- | ---: | --- |
| `literal_call_found` | 809 | Direkter literaler Aufruf gefunden |
| `indirect_literal_candidate` | 1.781 | Literal in Metadaten, Optionen, SQL-/Templatekonfiguration oder anderem Produktcode; Datenfluss manuell prüfen |
| `possibly_dynamic` | 103 | Dynamisches Präfix oder Länder-/Regionscode als möglicher Verbraucher |
| `unresolved` | 914 | Kein hinreichender Verwendungsbeleg im untersuchten Quellumfang |
| sicher unbenutzt | **0** | Kein Löschkandidat ist durch dieses Audit freigegeben |

Beispiele dynamischer Quellen: `Form::renderElement()`/Optionsbezeichner,
`List::renderCell()`, `Pdf.php` mit `DOCUMENTS_ . strtoupper($controller)`,
Korrekturfeldnamen, Kalender-/Validierungsfehlercodes, Länder aus der DB
(`application/models/DbTable/Country.php:28`), Menüs und Mailmarker aus Templates.
Auch englische `DE_*`-Versandcodes sind nicht durch einen reinen Großbuchstabenpräfix
vollständig erfassbar. Texte externer Erweiterungen und Datenbankinhalte bleiben offen.
Testfixtures zählen nicht als Beleg produktiver Schlüsselverwendung.

Kataloge ohne gegenwärtiges Anwendungsmodul (`magento`, `portals`, `ebiztrader`) werden
vollständig inventarisiert. Sie sind keine belegten toten Dateien: Installationsmenüs,
historische Erweiterungen oder externe Module können Verbraucher sein. Sie werden in
einem normalen Request ohne entsprechendes aktives Modul nicht geladen.

## Tests, Sicherheit und verbleibende Prüfungen

Die Tests entsprechen der vorhandenen Infrastruktur eigenständiger PHP-CLI-Skripte und
Python-Runner. Keine neue Testplattform. Tatsächliche Ergebnisse stehen zusätzlich
maschinenlesbar in `validation.json`.

| Ausgeführt | Ergebnis |
| --- | --- |
| `php -l` für alle 114 Kataloge und die beiden neuen PHP-Auditdateien | 116/116 erfolgreich |
| `audit-test.py` | 18 erfolgreiche Tests: Tokenisierung einschließlich Unicode-Escapes, nicht ausgeführter Fremdcode, Duplikate, Formate, dynamische Schlüssel, Domainreihenfolge, Kandidatentrennung |
| `runtime.php` | 110 erfolgreiche Charakterisierungsprüfungen einschließlich bekannter Fehler |
| Vollständiger Audit | Erfolgreich; alle 28 Kontexte stimmen bei Domainfolge, Schlüsselzahl und SHA-256 der effektiven Texte mit dem statischen Modell überein |
| `tests/ui/buttons.php` | 165 Prüfungen erfolgreich |
| `tests/ui/toolbar-buttons.php` | 2.259 Prüfungen erfolgreich; dessen eingebundener Button-Test meldet zusätzlich 165 |
| `tests/sales/quote-monitoring.php` | 293 Prüfungen erfolgreich |
| `tests/security/quote-email.php` | 364 Prüfungen erfolgreich; zwei erwartete Fixture-SMTP-Ablehnungen auf stderr |
| `tests/security/form-input-validation.php` | 20 Prüfungen erfolgreich |
| `tests/security/notifications.php` | **Vorhandener Fehler:** nach 370 erfolgreichen Hauptprüfungen scheitert der zusätzliche Dokumenthelper-Subprozess: `Sales_Service_DocumentRevisionService` nicht geladen, `Finish.php:12`, Aufruf `notifications.php:67`; Elternprozess bricht `:211` ab |
| `tests/contacts/history-states.php` | **Vorhandener Fehler:** `history['states']` fehlt (`:57`), dann „Missing history status: 100“ (`:69`). Kein Beleg eines Übersetzungsdefekts; Fixture-/History-Vertrag getrennt prüfen |

Die beiden Bestandsfehler wurden unverändert belassen: T1 ändert keine ihrer produktiven
Abhängigkeiten. Sie dürfen nicht als bestandene Gesamttests ausgegeben werden.
Isolierte Kontextprüfungen bestätigen unveränderte `Client`-/`Config`-Registrywerte in allen
28 Kontexten; sie simulieren keinen vollständigen angemeldeten Mehrmandantenrequest.
Der bestehende Quote-E-Mail-Test deckt zusätzlich Rechte, Empfänger und Parent-Child-Sharing
mit In-Memory-Fixtures ab. Zugriffskontrolle und Feature-Prüfungen bleiben im Produktcode
unverändert. Übersetzungsstrings allein gewähren keine Modulberechtigung.

Offene manuelle/Integrationstests für T2: authentifizierter Sprachwechsel pro Mandant,
abweichende Dokumentensprache, tatsächliche PDF-/E-Mail-Ausgabe mit Testdaten,
englischer öffentlicher Shop/CMS, Datenbankmenüs/-templates, XHR-/Forwardpfade und
Erweiterungen. Browser/Chromium, SMTP, echte Dokumentgenerierung, DB- und Migrationstests
wurden nicht gestartet, da keine entsprechenden Produktänderungen vorgenommen wurden
und keine isolierte Datenbank für diesen Auftrag verwendet wurde. Kein Produktionszugriff.

## T2: empfohlene Reihenfolge

1. Reproduzierte Lade-/Fallbackfehler gezielt absichern: Formatfehler unter PHP 8,
   Locale-Allowlist/Default, Domain-Lebensdauer und fehlgeschlagene Loads. Gewünschte
   Fallbackpolitik ausdrücklich festlegen; keine implizite Umstellung aller Anzeigen.
2. Englische gemeinsame Bezeichner und die tatsächlich verwendeten fehlenden Schlüssel
   vervollständigen. Geteilte Kontaktkomponenten, Navigation, Passwortformulare und
   Shop-Erfolgs-/Fehlermeldungen zuerst; Modulgrenzen erhalten.
3. Die bestätigten falschen Sprach-/Schreibwerte korrigieren, Platzhalter und vorhandene
   Schlüssel beibehalten. ERP-Glossar und Dokumenttitel fachlich abstimmen.
4. UI-/Dokument-/Mail-/Site-Sprachen in isolierten End-to-End-Fixtures prüfen. Freigegebene
   historische Dokumente unverändert erhalten; Datenbanktemplates nur separat autorisiert bearbeiten.
5. Kollisionen und Duplikate mit gewünschter Gewinnerdefinition bereinigen; ungeklärte
   Schlüssel und externe Kataloge nicht löschen. Erst danach Performanceoptimierung bewerten.

Keine Migration und keine manuellen Datenbanknacharbeiten erforderlich. Die folgenden
Tabellen bilden die vollständige lokale Datei-/Modulbestandsaufnahme ab.

## Bestandszahlen und Modul-/Sprachmatrix

| Kennzahl | de_DE | en_US |
| --- | ---: | ---: |
| PHP-Katalogdateien | 66 | 48 |
| Definitionen einschließlich Duplikaten | 3526 | 2680 |
| Verschiedene Schlüssel innerhalb der Sprache | 3513 | 2664 |
| Katalogverzeichnisse einschließlich default | 16 | 14 |

Gesamt: **zwei Anwendungssprachen, 13 Anwendungsmodule, 17 Katalogbereiche einschließlich
default, 114 Dateien, 6.206 Definitionen und 3.607 verschiedene Schlüssel**. 2.570 Schlüssel
existieren in beiden Sprachen. Englisch fehlen sprachweit 943 der 3.513 deutschen Schlüssel;
94 englische Schlüssel fehlen im Deutschen. Die englische Abdeckung des deutschen
Schlüsselbestands beträgt exakt 2.570 / 3.513 = 73.16 % (gerundet). Dies ist
Schlüsselparität, keine Aussage über korrekte Texte oder erreichbare Übersetzungen.

Matrix: `Dateien / Definitionen / verschiedene Schlüssel`; `common` bedeutet tatsächlich
vorhandene Common-Datei. „Extern/Altbestand“ bezeichnet fehlendes aktuelles Modulverzeichnis.

| Bereich | Anwendung | de_DE | de common | en_US | en common |
| --- | --- | ---: | --- | ---: | --- |
| admin | ja | 4 / 250 / 250 | – | 2 / 157 / 157 | – |
| calendar | ja | 1 / 147 / 147 | ja | 1 / 147 / 147 | ja |
| campaigns | ja | 1 / 148 / 146 | – | 1 / 50 / 50 | – |
| contacts | ja | 9 / 451 / 449 | ja | 4 / 409 / 409 | – |
| default | ja | 18 / 927 / 924 | ja | 16 / 836 / 836 | ja |
| ebay | ja | 1 / 135 / 135 | – | 1 / 131 / 131 | – |
| ebiztrader | Extern/Altbestand | 0 / 0 / 0 | – | 1 / 43 / 43 | – |
| items | ja | 12 / 314 / 313 | ja | 8 / 260 / 258 | ja |
| magento | Extern/Altbestand | 1 / 34 / 34 | – | 1 / 38 / 38 | – |
| portals | Extern/Altbestand | 1 / 28 / 28 | – | 0 / 0 / 0 | – |
| processes | ja | 2 / 110 / 109 | ja | 1 / 100 / 98 | – |
| purchases | ja | 3 / 139 / 139 | ja | 3 / 101 / 99 | – |
| sales | ja | 7 / 512 / 512 | ja | 7 / 387 / 385 | – |
| shops | ja | 1 / 157 / 156 | – | 1 / 16 / 16 | – |
| statistics | ja | 2 / 52 / 52 | ja | 1 / 5 / 5 | – |
| tasks | ja | 1 / 112 / 110 | – | 0 / 0 / 0 | – |
| users | ja | 2 / 10 / 10 | ja | 0 / 0 / 0 | – |

### Vollständiges Dateiinventar und Parität

Format aller 114 Anwendungskataloge: UTF-8 ohne BOM, ausführbare PHP-Datei mit
direkt zurückgegebenem String-Array. Keine weiteren Dateiformate unter `languages/`.
`de_DE/admin/client.php`, `en_US/admin/client.php` und `de_DE/users/common.php` geben
gültige leere Arrays zurück; dies sind keine leeren Übersetzungswerte oder Syntaxfehler.
In der Tabelle stehen Definitionen einschließlich Wiederholungen. Fehlmengen vergleichen
Schlüssel in derselben relativen Datei; verschobene Definitionen können sprachweit vorhanden
sein. Deshalb sind 22 fehlende englische Gegenstücke nicht gleichbedeutend mit 22 vollständig
unübersetzten Funktionsbereichen. Umgekehrt kann eine vorhandene Datei sehr unvollständig sein.

| Relativ unter languages/&lt;locale&gt;/ | de Einträge | en Einträge | Schlüssel nur in de-Datei | Schlüssel nur in en-Datei |
| --- | ---: | ---: | ---: | ---: |
| admin/admin.php | 239 | 157 | 82 | 0 |
| admin/client.php | 0 | 0 | 0 | 0 |
| admin/menu.php | 9 | – | 9 | 0 |
| admin/page.php | 2 | – | 2 | 0 |
| calendar/common.php | 147 | 147 | 0 | 0 |
| campaigns/campaigns.php | 148 | 50 | 96 | 0 |
| contacts/bankaccount.php | 4 | – | 4 | 0 |
| contacts/comment.php | 2 | – | 2 | 0 |
| contacts/common.php | 21 | – | 20 | 0 |
| contacts/contact.php | 127 | 120 | 14 | 7 |
| contacts/contactperson.php | 23 | 23 | 0 | 0 |
| contacts/download.php | 6 | – | 6 | 0 |
| contacts/inquiry.php | 244 | 244 | 0 | 0 |
| contacts/map.php | 22 | 22 | 0 | 0 |
| contacts/pricerule.php | 2 | – | 2 | 0 |
| default/category.php | 16 | 16 | 0 | 0 |
| default/comment.php | 2 | – | 2 | 0 |
| default/common.php | 54 | 85 | 10 | 41 |
| default/controlpanel.php | 25 | – | 23 | 0 |
| default/countries.php | 202 | 202 | 0 | 0 |
| default/document.php | 213 | 196 | 24 | 8 |
| default/feedback.php | 30 | 27 | 3 | 0 |
| default/menu.php | 31 | – | 31 | 0 |
| default/messages.php | 136 | 117 | 21 | 2 |
| default/months.php | 12 | 12 | 0 | 0 |
| default/order.php | 24 | 23 | 1 | 0 |
| default/pagination.php | 5 | 5 | 0 | 0 |
| default/position.php | 26 | 23 | 5 | 2 |
| default/settings.php | 28 | 28 | 0 | 0 |
| default/state.php | 11 | 11 | 0 | 0 |
| default/tabs.php | 34 | 18 | 16 | 0 |
| default/toolbar.php | 70 | 59 | 11 | 0 |
| default/user.php | – | 6 | 0 | 6 |
| default/validation.php | 8 | 8 | 0 | 0 |
| ebay/ebay.php | 135 | 131 | 5 | 1 |
| ebiztrader/ebiztrader.php | – | 43 | 0 | 43 |
| items/attribute.php | 8 | – | 8 | 0 |
| items/common.php | 13 | 2 | 12 | 1 |
| items/import.php | 10 | – | 10 | 0 |
| items/inventory.php | 14 | 13 | 1 | 0 |
| items/item.php | 64 | 65 | 6 | 7 |
| items/itemlist.php | 20 | 20 | 0 | 0 |
| items/ledger.php | 45 | 41 | 7 | 3 |
| items/option.php | 8 | – | 8 | 0 |
| items/pricerule.php | 30 | 26 | 6 | 2 |
| items/stock.php | 24 | 24 | 0 | 0 |
| items/stockplanning.php | 69 | 69 | 0 | 0 |
| items/variant.php | 9 | – | 9 | 0 |
| magento/magento.php | 34 | 38 | 0 | 4 |
| portals/portal.php | 28 | – | 28 | 0 |
| processes/common.php | 4 | – | 4 | 0 |
| processes/processes.php | 106 | 100 | 14 | 7 |
| purchases/common.php | 11 | – | 11 | 0 |
| purchases/purchaseorder.php | 65 | 49 | 26 | 9 |
| purchases/purchases.php | – | 2 | 0 | 2 |
| purchases/quoterequest.php | 63 | 50 | 23 | 9 |
| sales/common.php | 38 | – | 38 | 0 |
| sales/creditnote.php | 66 | 49 | 27 | 10 |
| sales/deliveryorder.php | 64 | 49 | 25 | 9 |
| sales/invoice.php | 114 | 99 | 25 | 10 |
| sales/quote.php | 102 | 90 | 21 | 9 |
| sales/reminder.php | 66 | 49 | 27 | 9 |
| sales/sales.php | – | 2 | 0 | 2 |
| sales/salesorder.php | 62 | 49 | 23 | 10 |
| shops/shops.php | 157 | 16 | 140 | 0 |
| statistics/common.php | 3 | – | 3 | 0 |
| statistics/statistics.php | 49 | 5 | 48 | 4 |
| tasks/tasks.php | 112 | – | 110 | 0 |
| users/common.php | 0 | – | 0 | 0 |
| users/user.php | 10 | – | 10 | 0 |

Die vier nur englisch vorhandenen Dateien sind `default/user.php`,
`ebiztrader/ebiztrader.php`, `purchases/purchases.php` und `sales/sales.php`.
Kritische fehlende englische Gegenstücke: `default/menu.php`, `default/controlpanel.php`,
`contacts/common.php`, `sales/common.php`, `purchases/common.php`, sämtliche `tasks`- und
`users`-Dateien; zusätzlich fehlen `admin/menu.php`, `admin/page.php`, Contacts-Nebenkataloge,
vier Items-Kataloge, `default/comment.php`, `processes/common.php`, `statistics/common.php`
und `portals/portal.php`. Die Tabelle führt jedes Gegenstück einzeln auf.

### Zusatzinventar von Drittbibliotheken

602 Dateien in bestehenden `lang`/`langs`/`i18n`/`cultures`/`localization`-Verzeichnissen:
jQuery 278, CKEditor 170, TCPDF 48, FileManager 37, MediaManager 37, elFinder 30, TinyMCE zwei.
Diese Zahlen enthalten auch README-/Statusdateien und bedeuten nicht 602 unterstützte Sprachen.
Der vollständige Pfadbestand steht unter `scan.vendor_localization_files`. Die Pakete verwenden
eigene JS-Localeobjekte bzw. TCPDF-Spracharrays; sie werden nicht von DEEC_Translate geladen.
Zusätzlich sind 726 ZF1-CLDR-XML-Dateien unter `Zend/Locale/Data` inventarisiert: Format-/
Lokalemetadaten, keine 726 verfügbaren ERP-Übersetzungen. Die semantische Qualität fremder
Sprachpakete ist nicht Teil der de_DE/en_US-Katalogabnahme. TinyMCE enthält `de.js` und eine
README; Englisch verwendet die Bibliotheksvorgabe. Die ERP-Layouts wählen Datepicker und
formatCurrency-Dateien mit umgewandeltem Sprachcode; `de-DE`/`en-US`-Dateien sind vorhanden.

### Tatsächliche Requestabdeckung und Ladeaufwand

Spalten: `require-Aufrufe / verschiedene Dateien / effektive Schlüssel / unaufgelöste
direkte Modulaufrufe`. Unaufgelöste Aufrufstellen sind statische Befunde im frischen
Requestmodell, keine Anzahl tatsächlich besuchter Fehlerseiten. Globale Komponenten und
dynamische Argumente sind hier nicht als direkter Modulaufruf gezählt.

| Aktives Modul | de_DE | en_US | Direkte schlüsselartige Modulaufrufe |
| --- | ---: | ---: | ---: |
| default | 26 / 26 / 1160 / 0 | 18 / 18 / 985 / 0 | 0 |
| admin | 30 / 30 / 1410 / 6 | 20 / 20 / 1142 / 54 | 160 |
| calendar | 27 / 26 / 1160 / 0 | 19 / 18 / 985 / 0 | 24 |
| campaigns | 27 / 27 / 1306 / 1 | 19 / 19 / 1035 / 33 | 66 |
| contacts | 35 / 34 / 1588 / 2 | 22 / 22 / 1393 / 42 | 169 |
| ebay | 27 / 27 / 1295 / 1 | 19 / 19 / 1116 / 9 | 70 |
| items | 38 / 37 / 1460 / 7 | 26 / 25 / 1241 / 30 | 112 |
| processes | 28 / 27 / 1265 / 2 | 19 / 19 / 1083 / 32 | 61 |
| purchases | 29 / 28 / 1288 / 4 | 21 / 21 / 1084 / 14 | 31 |
| sales | 33 / 32 / 1634 / 4 | 25 / 25 / 1370 / 22 | 113 |
| shops | 27 / 27 / 1316 / 9 | 19 / 19 / 994 / 105 | 170 |
| statistics | 28 / 27 / 1209 / 0 | 19 / 19 / 990 / 45 | 56 |
| tasks | 27 / 27 / 1270 / 3 | 18 / 18 / 985 / 43 | 59 |
| users | 28 / 27 / 1170 / 0 | 18 / 18 / 985 / 0 | 8 |

Von 1.099 direkt schlüsselartig übersetzenden Modulaufrufen sind im Modell 39 deutsche
und 429 englische Aufrufstellen nicht erreichbar definiert. Davon liegen 15 deutsche
und 116 englische Aufrufstellen auf Schlüsseln, die in anderen Katalogen derselben Sprache
existieren: **Ladeproblem statt fehlender Übersetzung**. Die übrigen 24/313 haben in der
jeweiligen Sprache keine Definition. Ein später injizierter Translator oder alternativer
Aufrufkontext kann einen einzelnen statischen Befund verändern; konkrete Shared-Helper-
und Navigationsfehler sind zusätzlich zur Modellzählung im Laufzeittest reproduziert.

Schon `default` erfordert 26 Dateien / 67.790 eingelesene Quellbytes auf Deutsch und
18 Dateien / 53.496 Bytes auf Englisch. Items benötigt 38/26 require-Aufrufe; Sales 33/25.
Das aktive Common wird, soweit vorhanden, zweimal in verschiedenen Domains geladen.
Vollständige Ladepfade und Bytezahlen stehen unter `request_loading`; keine andere
Moduldetaildatei wird im normalen Request pauschal eingelesen.

Ein isolierter warmer CLI-Lauf ohne OPcache (`opcache.enable_cli=0`) maß jeweils
280 frische Translator-/Plugin-Kontexte, 20 Durchläufe über alle 14 Requestmodule:
de_DE 213.768 ms insgesamt / 0.763 ms je Kontext;
en_US 150.195 ms / 0.536 ms. Prozessspitze: 6.291.456 Bytes.
Das sind Messwerte dieses Hosts, kein Produktionsbenchmark, keine Kaltstartmessung und
kein isolierter Speicherbedarf pro Übersetzer. Ohne HTTP/DB-Last lässt sich daraus kein
relevanter Anwendungsengpass belegen. Die unnötigen Common-Doppelladungen sind dagegen
strukturell nachgewiesen. Erst die Abhängigkeiten und Sprachparität korrigieren, dann optimieren.

Audit-Quellfingerabdruck: `e209079d31951b5b79c57d24347b724da90a35d1cf5f396ee1b4c8236aaab02e`.
