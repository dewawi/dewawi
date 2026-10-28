# DEWAWI – Security Phase 4: Dateizugriff und Uploads

Basis: Repository einschließlich der abgeschlossenen Security-Phasen 1–3. Keine produktive Datenbank wurde geöffnet oder verändert. Bestandsdateien wurden weder verschoben noch gelöscht; sämtliche schreibenden Tests verwenden temporäre Dateien und In-Memory-Datenbankadapter.

## Bestandsaufnahme und Grenzen

Untersucht wurden direkte Dateilinks in Anwendung, Views und JavaScript, die tatsächlichen Erzeuger/Leser dieser Pfade, die Dateimodelle, DEEC-/ZF1-Rechte und Mandantenfreigaben sowie die Manager-Einstiegspunkte mit ihren Includes, UploadHandler und URL-Funktionen. Der ergänzende [Pfadindex](file-access-paths.tsv) enthält 143 Quelltextreferenzen auf `files/`, `media/` und `cache/` in `application/`, `library/DEEC/` und `js/`. Dynamisch zusammengesetzte Operationen sind zusätzlich in der folgenden Tabelle erfasst. Der Index ist eine Suchhilfe, keine Datenbank- oder Bestandsdateiliste.

Die lokalen Speicherverzeichnisse enthalten keine zu migrierenden Geschäftsdateien und keine Symlinks. Aussagen zur Datenzuordnung beruhen auf dem vorhandenen Code und isolierten Datensätzen, nicht auf einer Untersuchung produktiver Kundendaten. Nicht jeder mögliche Inhalt hochgeladener Dokumente oder jede Drittanbieterfunktion wurde vollständig auditiert.

| Speicher / Zugriff | Tatsächliche Verwendung und Aufrufer | Einstufung und Entscheidung |
| --- | --- | --- |
| `files/contacts/<Mandant-Shards>/<Kontakt-Shards>/...` | FileManager aus Kontakt-, Verkaufs-, Einkaufs- und Prozessansichten; `DEEC_Pdf::getOutputFile()` schreibt hier auch ausgestellte Geschäftsbelege. `Document` zeigte PDFs bisher über einen direkten Dateilink. | Vertraulich und objektbezogen. Mandantenfreigabe der Kontakte bleibt erhalten. Bekannte Belegnamen werden zusätzlich anhand der acht Verkaufs-/Einkaufsmodelle geprüft; Kontaktansicht allein genügt nicht für eine Rechnung. Gelöschte Belege und mehrdeutige Namenszuordnungen werden abgewiesen. Auch Kopien in Unterordnern behalten diese Prüfung anhand des Belegnamens. |
| `files/contacts/<id>/...` ohne Mandant-Shards | Alte Auflistungen in Kontakt-/Einkaufs-/Prozess-ViewModels und `contact/files.phtml`. | Keine belastbare Mandantenzuordnung im Dateipfad. HTTP bleibt gesperrt, auch nach Anmeldung. Keine automatische Übernahme in einen anderen Pfad. |
| `files/uploads/<Mandant-Shards>/...` | Gemeinsamer Legacy-Uploadbereich des FileManager; Administrationsansichten; serverseitige Bilder in Artikel-/Artikellisten-PDFs. Der Profil-Helper verwendete ebenfalls diesen gemeinsamen Bereich. | Mandantenbezogen, keine pauschale öffentliche Freigabe. Gemeinsamer Manager-Zugriff nur im vorbereiteten Administrationskontext des eigenen Mandanten. Neue Profil-Uploads verwenden zusätzlich `users/<userid>/`; Profilkontext, Benutzer und Ordner müssen übereinstimmen. |
| `files/attachments/<module>/<controller>/<Mandant-Shards>/<Objekt-Shards>/<name>` | `AttachmentController`, `Emailattachment`-Model, E-Mail-/Belegsynchronisation. DB-Zuordnung enthält Eigentümer, Parent, Dateiname und Speicherort. | Bestehender geschützter Attachment-Ablauf bleibt vorrangig; ACL, Elternobjekt, Mandant, Dateityp und kanonischer Pfad werden weiterhin geprüft. Sowohl ID-Downloads als auch alte Pfad-URLs funktionieren für Berechtigte. |
| `files/items/<Mandant-Shards>/<Artikel-Shards>/...` | FileManager in Artikelansichten und alte Datenblatt-PDFs. | Interne Artikeldokumente: Artikelrechte und Eigentümer erforderlich. Öffentliche Produktbilder werden hierdurch nicht freigegeben. |
| `files/images/<name>` | Backend-Vorschauen von Artikellisten, Inventaren und Admin-Tags; Vorlagenlogos in `DEEC_Pdf` und alten Artikel-PDFs. | Kein pauschal öffentlicher Bilderordner. HTTP nur bei passender DB-Referenz in `itemlist.image`, `inventory.image`, `tag.image` oder `template.logo`, einschließlich Modulrecht/Administratorrecht und Mandant. Serverinterne PDF-Verwendung bleibt möglich. Nicht zugeordnete Dateien werden abgewiesen. |
| `media/<Mandant-Shards>/images`, `category`, `slide`, `header`, `downloads` / `download` | Artikel-CSV-Import registriert Produktmedien; `MediaController` und Admin-Kategorie-/Slide-Ansichten verwalten Medien; Shop-Item-/Kategorie-/Slide-Views und Feed verlinken sie. | Gemischter Speicher. Öffentlich nur bei DB-Zuordnung zum aktuellen `SiteContext`: Shop-Artikel nach bestehendem SiteEntity-Scope, aktive Kategorien/Slides, Shop-Logo oder das bestehende Artikel-Feld `downloads`. Gelöschte/andere Mandanten werden nicht veröffentlicht. Interne Medien benötigen Rechte oder den vorbereiteten Administrationskontext. |
| Flache Shop-URLs `/media/images/...`, `/media/category/...`, `/media/slide/...`, `/media/header/...`, `/media/downloads/...` | Vorhandene Shop-Templates und Produkt-Feed. | Unveränderte Browser-URLs werden erst nach bestätigter Veröffentlichung auf den kanonischen Speicher des Shop-Mandanten abgebildet. Der bestehende Unterschied `download` beim Kategorie-Upload und `downloads` im Shop-Link wird über die Medienzuordnung aufgelöst. Kein Fallback auf beliebige globale Dateien. |
| `cache/document/<module>/<controller>/...` | `DEEC_Pdf` erstellt Entwürfe und Vorschauen. | Neue Ausgabe enthält Mandant und Objekt-ID. HTTP verlangt Belegrechte, passenden Eigentümer und erwarteten Dateinamen. Alte ungebundene Cache-Dateien werden nicht freigegeben; Vorschauen können über den vorhandenen Preview-Ablauf neu erzeugt werden. |
| `cache/item/...`, `cache/itemlist/...` | Alte Artikel-/Artikellisten-PDF-Views. | Neue Vorschauen liegen unter `<clientid>/<id>/preview.pdf`; Rechte, Objektexistenz und Mandant werden geprüft. Alte `cache/item/<id>.pdf` bleiben gesperrt. |
| `cache/thumbs/files/...`, `cache/thumbs/media/...` | Manager-Dialog, `include/utils.php::create_img()`, UploadHandler. | Abgeleitete Vorschauen erben die Prüfung des ursprünglichen Speicher-/Objektpfades. Öffentlich nur für zugeordnete öffentliche Medien. Auch Symlinks in Thumbnail-Verzeichnissen werden zurückgewiesen. |
| `files/downloads/<Mandant-Shards>/<name>` | Kontakt-Downloadkatalog, `Contacts_DownloadController`, Download-/Tracking-Ansichten und `download`-Metadaten. Die bestehende Liste ist ein mandantenweiter Katalog, keine individuelle Empfängerfreigabe. | Authentifizierung, Kontakt-Ansichtsrecht, erlaubter Eigentümer und eindeutiger aktiver Download-Datensatz erforderlich. Lesen/Ändern der Metadaten ist mandantengebunden. Kein öffentlicher Token-Downloadcontroller wurde gefunden. |
| `files/export/<Mandant-Shards>/...` | Admin-ExportController erzeugt CSV, SQL, ZIP und GoBD-Ausgaben. | Nur Administrator im eigenen ausgewählten Mandanten. Bereits bestehende Apache-Sperren für rohe SQL-/Logdateien bleiben erhalten; ZIP-/CSV-Exporte können berechtigt abgerufen werden. |
| `files/import/...` | Kontakt-/Artikel-/Attribut-/Options-CSV-Importe; serverseitige Verarbeitung. | Immer HTTP 403. Kein fachlicher öffentlicher Dateilink erforderlich. Keine Importdaten oder produktiven Importaktionen ausgeführt. |
| Zend-Cache, Logs, Sitzungen, Konfigurationen | Bootstrap-Cache, Cron-/Integrationslogs, Sitzungsablage. Integrations-Logcontroller lesen feste Logdateien innerhalb ihrer bestehenden Controller-Rechte. | Bestehende Apache-Sperren bleiben vor dem Datei-Routing. Andere nicht zuordenbare Cache-Dateien werden nicht ausgeliefert. |
| Direkte CSV-/ZIP-/PDF-Responses und temporäre Exportdateien | `DEEC_Controller_Action`, Kontakt-/Artikel-/Bestands-Exportservices, Statistik-Export, `DEEC_Controller_DocumentAction`. | Bestehendes Controller-Routing bleibt erhalten. Geschäftliche PDF-Responses erhalten zusätzlich sichere Dateinamen, `nosniff`, private Cache-Header und HEAD-Behandlung. Temporäre Exporte bleiben im bestehenden Erzeugungs-/Bereinigungsablauf. |
| Gastangebot aus Shop-Anfrage | `Shops_InquiryController` erzeugt nach einer eigenen Anfrage ein Angebot/PDF und zeigt einen Downloadlink an. | Ausschließlich die erzeugende Sitzung erhält eine Freigabe für genau Site, Mandant, Quote-ID und relativen Dateipfad. Kein allgemeiner Gastzugriff auf Angebote. |

Die beiden Directory-Implementierungen wurden abgeglichen: Aktive Controller verwenden den ZF1-Directory-Helper; `DEEC_Directory` liefert insbesondere die Shard-Pfade. Neue Kontextprüfungen akzeptieren nur gültige numerische IDs, prüfen vorhandene Pfadbestandteile auf Symlinks und verwenden für neu angelegte Verzeichnisse 0775. Bestehende Dateirechte werden nicht verändert.

## Nachgewiesene Probleme und Behebung

Die Schwere bezeichnet den Ausgangsbefund nach Phase 3.

| Schwere | Fundstelle / Problem | Änderung |
| --- | --- | --- |
| Hoch | `.htaccess`: Vorhandene Dateien unter `files`, `media` und `cache` übersprangen durch `-s`/`-l`/`-d` das ZF1-Routing; nur Attachments hatten bereits einen geschützten Pfad. | Datei-Routing vor dem statischen Bypass, mit Autorisierung im bestehenden AttachmentController/Model. Der frühere anonyme Bypass wird im Apache-Test mit einer harmlosen Datei reproduziert und anschließend blockiert. |
| Hoch | Kontaktordner enthalten sowohl Kontaktdateien als auch Verkaufs-/Einkaufsbelege. Eine alleinige Kontaktprüfung oder ein Manager-Download hätte Belegrechte umgangen. | Metadatenprüfung des Belegs einschließlich Eigentümer, Existenz, Löschstatus und DEEC-Recht. Manager-Dateizugriffe und rekursive Ordneroperationen prüfen dieselben Rechte; keine Freigabe durch Kopieren eines Rechnungsnamens in einen Unterordner. |
| Hoch | `library/FileManager/upload.php` und `library/MediaManager/upload.php`: POST-URL wurde ohne Netzgrenzen, Pinning, Größen- oder Transferzeitbegrenzung per cURL geladen. | Beide Wrapper verwenden vor dem Legacy-Include `DEEC_UrlDownload`; der ursprüngliche URL-Zweig erhält keine URL mehr. Auch Dialog-Uploads verlangen Schreibrecht. |
| Hoch | `application/controllers/MediaController.php::uploadAction()` / `deleteAction()`: Pfad-/Unterordnerparameter und Dateitypen waren unzureichend begrenzt; ein Medien-Datensatz allein prüfte das Elternobjekt nicht. | Nachgewiesene Kategorie-/Slide-Kontexte verlangen Administrator, eigenen Parent und passende Speicherart. Artikelkontexte verwenden DEEC-Elternobjekt-/Schreibrechte. Kanonische Pfade, Symlink-Sperren, Größen-/Typ-/Dateinamenprüfung vor Schreib-/Löschoperationen. |
| Hoch | Profil-FileManager zeigte den gemeinsamen mandantenweiten Legacy-Uploadbereich ohne Datei-/Benutzerzuordnung. | Neuer benutzerbezogener Unterordner; alte gemeinsame Uploadbestände bleiben außerhalb eines Administrationskontexts gesperrt. Keine Migration bestehender Dateien. |
| Mittel | `Contacts_Model_DbTable_Download::getDownload()` / `updateDownload()` verwendeten nur die ID; Upload-Adapter prüfte beim Ersetzen nicht die Formular-Dateivalidatoren. | Bestehende Entity-Methoden mit Mandanten-/Löschscope; Uploadvalidierung vor Speicherung, keine Überschreibung bestehender Dateien, konsistenter Eigentümer für Metadaten und Speicher. Dateilinks werden URL-/HTML-kodiert. |
| Mittel | Vorhersehbare, nicht ausreichend mandanten-/objektgebundene Vorschaupfade. | Neue Cache-Pfade enthalten Mandant und Objekt. Alte Dateien bleiben unangetastet und gesperrt. `Document` und der alte Print-Helper verwenden den vorhandenen Preview-Endpunkt. |
| Mittel | Private Dateiantworten und Legacy-Manager-Downloads hatten keine einheitlichen Cache-/MIME-Schutzheader. | Private Downloads: `private, no-store`, `nosniff`; sichere Content-Disposition. Inline-PDF-/Bild-/Audio-/Videoausgabe bleibt möglich, aktive Dateiinhalte erhalten eine Sandbox-CSP. Andere Typen werden als Download ausgeliefert. |

## URL-Uploads und andere HTTP-Downloads

Die URL-Funktion bleibt erhalten, weil beide vorhandenen Manager sie konfigurieren und im Uploaddialog anbieten. Ihre Absicherung benötigt nur eine kleine gemeinsame DEEC-Klasse. Es gibt keinen neuen allgemeinen HTTP-Client und keine neue Konfiguration.

`DEEC_UrlDownload` akzeptiert ausschließlich explizite HTTP-/HTTPS-URLs auf den zugehörigen Standardports. Zugangsdaten, Fragmente, Steuerzeichen, alternative Schemata, mehrdeutige numerische Hostschreibweisen und ungültige Dateinamen werden abgewiesen. Loopback, private IPv4/IPv6-Adressen, Link-local/Metadaten, CGNAT, Multicast, IPv4-mapped IPv6 und ausgewählte Transition-/Dokumentationsbereiche werden blockiert. Alle gefundenen A-/AAAA-Adressen müssen zulässig sein; gemischte öffentliche/private Antworten werden vollständig verworfen.

Die normalisierte URL und der cURL-Hostname stimmen überein. Eine geprüfte IP wird mit `CURLOPT_RESOLVE` festgeschrieben; Proxys, Verbindungswiederverwendung und Redirects sind deaktiviert. TLS-Zertifikat und Hostname werden geprüft. Die tatsächlich verbundene Peer-IP muss der festgeschriebenen IP entsprechen. Nicht-200-Antworten und leere Dateien werden nicht übernommen.

Grenzen: 10.240.000 Byte pro Download, 64 KiB Antwortheader, maximal fünf Sekunden Verbindungsaufbau, Low-Speed-Abbruch und ein Transferbudget innerhalb von 15 Sekunden ab Beginn. Headerangaben und tatsächlich geschriebene Bytes werden unabhängig begrenzt. Nach DNS-Rückkehr werden Auflösungen über fünf Sekunden abgewiesen. **Die native synchrone PHP-DNS-Auflösung lässt sich dadurch nicht während des Systemaufrufs unterbrechen; deren harte Wartezeit bleibt vom Betriebssystem-Resolver abhängig.** Das cURL-Budget wird um die bereits verstrichene Zeit gekürzt.

Staging-Dateien erhalten die privaten Rechte von `tempnam`. Fehlgeschlagene Downloads werden sofort bereinigt; nach Erfolg übernimmt der bestehende UploadHandler exakt die geprüften Bytes. Controller-`finally` und Shutdown-Bereinigung decken auch Legacy-`exit` ab. Die einzigen Änderungen an den beiden projektspezifisch bereits angepassten Manager-Uploaddateien sind jeweils die Auswahl des geprüften `input_stream`; UploadHandler und Utils bleiben unverändert. Gefährliche Namen, Doppelendungen, ausführbare MIME-Typen, Bild-/MIME-Widersprüche und zu große Uploads werden vor der Übernahme zurückgewiesen.

Weitere geprüfte Pfade:

- `DEEC_Sns` behält seine bestehende HTTPS-/AWS-Host-/Topic-/Zertifikatpfad-Prüfung und verwendet zusätzlich denselben Downloader mit 64-KiB-Grenze und Bereinigung. Damit folgen Zertifikats-/Bestätigungsabrufe keinen Redirects und verwenden die geprüfte IP. Keine echte SNS-Aktion wurde ausgeführt.
- `include/utils.php::get_file_by_url()` hat keinen gefundenen Anwendungsaufrufer. Der alte cURL-Zweig in `upload.php` wird durch die vorgeschaltete Entfernung von POST-`url` nicht mehr erreicht.
- Die Bild-/Datei-Helfer der Manager erhalten im aktuellen lokalen Modus geprüfte lokale Speicherpfade. FTP ist in der Repository-Standardkonfiguration deaktiviert; seine separaten Remote-Abläufe wurden nicht aktiviert oder live getestet.
- OpenCart-/Magento-/eBay-/Mail-Anbindungen verwenden konfigurierte Integrationsziele. Sie sind keine freien URL-Uploads und wurden nicht aufgerufen oder in eine neue Transportarchitektur überführt. Browser-CDNs, Karten-, Google-Viewer- und CAD-Viewer-Links sind keine serverseitigen Upload-Downloads. Geschützte Geschäftsdateien werden für solche externen Viewer nicht anonym freigegeben.

## Rückwärtskompatibilität und Altbestände

Erhalten bleiben bestehendes ZF1-/AJAX-Routing, die Manager-Browser-URLs mit Session/CSRF, Multipart- und URL-Uploads erlaubter Dateien, berechtigte Manager-/Attachment-Downloads, Medienzuordnungen und die vorhandene Mandantenfreigabe von Kontakten/Artikeln. Öffentliche Shop-Links benötigen weiterhin keine Anmeldung, aber eine nachweisbare Veröffentlichung auf dem jeweiligen Shop. Neue Vorschauen verwenden dieselben Funktionen mit eindeutigerem Speicherpfad.

Bewusst eingeschränkt sind rohe, nicht zugeordnete Dateien, alte ungebundene Cache-URLs, globale flache Medienbestände ohne sicheren Eigentümer und der gemeinsame Profil-Uploadbereich. Symlinks werden auch dann nicht akzeptiert, wenn sie derzeit innerhalb des Speicherbereichs zeigen. Rekursive Manager-Änderungen prüfen höchstens 1.000 Einträge und brechen bei größeren Bäumen ab.

Vor einem Rollout müssen echte Altbestände separat zugeordnet werden: insbesondere ungeshardete Kontaktordner, globale `files/images`, flache `media`-Dateien, alte Download-Dateien mit abweichendem Speicher-/Metadatenmandanten sowie bisherige gemeinsame Profilbilder. Erforderlich sind überprüfte Eigentümer-/Objekt-/Veröffentlichungsdaten; erst danach kann eine ausdrücklich geplante Migration oder Zuordnung erfolgen. Diese Änderung führt keine solche Migration aus. Gespeicherte E-Mail-Signaturen oder individuell angepasste PDF-Templates mit bisher öffentlich abrufbaren privaten Dateien benötigen gegebenenfalls eine fachlich geprüfte öffentliche Medienzuordnung.

## Verbleibende Risiken und Betriebsgrenzen

- Die neuen Auslieferungsregeln setzen den vorhandenen Apache-Rewrite-Betrieb voraus. Abweichende Webserver, Aliase oder vorgeschaltete statische Dateiserver müssen dieselben Zugriffsentscheidungen durchsetzen. Ein vorgeschalteter Cache darf private Antworten nicht speichern; früher gecachte private Dateien müssen separat aus solchen Caches entfernt werden.
- Datenbank-Medienreferenzen bestimmen die Veröffentlichung. Fehlerhafte Altzuordnungen oder bewusst öffentlich markierte Inhalte benötigen eine fachliche Prüfung; ein Dateiname allein wird nicht als Veröffentlichung akzeptiert. Öffentliche Medien dürfen eine Stunde öffentlich gecacht werden.
- Der Betriebssystem-Resolver begrenzt die blockierende DNS-Wartezeit. Transferlimits und Bytegrenzen sind umgesetzt und mit isoliertem Transport geprüft; es wurden keine echten internen oder externen Downloadziele getestet.
- Die Maßnahme ersetzt kein vollständiges Audit von Archivextraktion, Bild-/PDF-Decodern, FTP oder vertrauenswürdig konfigurierten Integrationen. Insbesondere Dekompressions-/Decoder-Ressourcen sind nicht durch die Grenze der heruntergeladenen komprimierten Bytes vollständig begrenzt. Es gibt keine Malwareprüfung.
- Zwischen Pfadprüfung und Dateioperation bleibt bei einem zusätzlich bösartigen lokalen Dateisystemschreiber ein mögliches Race. Das HTTP-Modell sperrt beobachtete Symlinks und unsichere Namen; ein Schutz gegen beliebige lokale Prozessrechte würde andere Dateisystem-/Betriebsmaßnahmen erfordern.
- Der vorhandene Gastangebotslink ist an eine Sitzung gebunden und kein weitergebbarer öffentlicher Link. Für neue Empfängerfreigaben wäre ein ausdrücklich autorisierter fachlicher Ablauf erforderlich.

## Ausgeführte Regressionstests

| Prüfung | Ergebnis |
| --- | --- |
| Alle `tests/security/*.php` | 26/26 erfolgreich; einschließlich 16 Production-/Development-Konfigurationen, 347 Berechtigungs-/Manager-Kontextfällen, 222 Attachment-Prüfungen sowie den neuen Datei-/SSRF-Tests. |
| `php tests/security/file-access.php` | 208 Fälle erfolgreich: Gäste, Objekt-/Mandantenrechte, erlaubte Eltern-/Kindfreigaben, gelöschte/mehrdeutige Belege, Kopien, rekursive Manager-Aktion, Benutzer-Uploads, Katalog-Uploads, Traversal/Symlinks, Header, interne Vorschauen, öffentliche Medien und Gastangebotsfreigabe. |
| `php tests/security/url-download.php` | 137 Prüfungen erfolgreich; DNS und HTTP vollständig ersetzt. Gesperrte IPv4-/IPv6-/Metadatenziele, obfuskierte Hosts, gemischte DNS-Antworten, IP-Pinning, Peer-Abweichung, Redirects, Fehlerantworten, TLS-/Proxyoptionen, Größen-/Headergrenzen, Timeoutoptionen und Staging-Bereinigung. Keine Zielkontakte. |
| `python3 tests/security/configuration-http.py` | **8.361 HTTP-Prüfungen erfolgreich**; Root-/Unterverzeichnisinstallation, bestehendes PHP-Inventar, echtes jQuery/CSRF im lokalen Chromium, echte Manager-Dialoge/Dateioperationen/Multipart-Uploads/Downloads. Erfolgreiche URL-Uploads verwenden echten UploadHandler mit vollständig simuliertem DNS/Download; interne URLs werden vor dem Netzwerkzugriff abgewiesen. Installer-Einstieg und Requirements-Schritt bleiben erreichbar. |
| `python3 tests/security/attachment-http.py` | Erfolgreich: früherer statischer Bypass isoliert reproduziert; echte Datei-/Attachment-Actions und Models mit In-Memory-Adapter; Root-/Unterverzeichnis, erlaubte Downloads/HEAD, verweigerte Gäste/fremde Mandanten/fehlende Belegrechte, Methoden, Traversal/Symlink, private Header und öffentliche Produktbilder. |
| `python3 tests/security/error-handling-http.py` | 140 Fälle erfolgreich: Production/Development, HTML/JSON, Initialisierung/Bootstrap, internes Logging, Cron, Varianten, Installer und Manager. |
| `python3 tests/security/session-http.py` | 25 Fälle erfolgreich: Gast-Bootstrap, Cookies, Login/Logout, Regeneration und Remember-me. |
| `php tests/documents/pdf.php` | 134 Fälle erfolgreich. |
| `php tests/documents/lifecycle.php` | 879 Fälle erfolgreich. |
| `php tests/documents/pdf-totals.php` | 170 Fälle erfolgreich. |
| `php tests/documents/e-invoice.php` | 369 Fälle erfolgreich. |

Zusätzlich erfolgreich: `php -l` auf allen **28** geänderten/neuen PHP-/PHTML-Dateien, Python-Syntaxprüfung beider geänderten HTTP-Tests und `git diff --check`. `js/trash.js` wurde mit V8 ohne Ausführung geparst; Node.js ist hier nicht installiert. Ein vollständiger Neuinstallations-Datenbankimport und echte Shop-/Mail-/FTP-/OAuth-Aktionen wurden in Phase 4 nicht ausgeführt. Die früheren Phasenberichte sind historische Stände; insbesondere deren offene Punkte zum statischen Dateizugriff und URL-Upload werden durch diesen Bericht fortgeschrieben.

## Geänderte Dateien

Insgesamt 34 geänderte/neue Dateien:

- `.htaccess`
- `application/Bootstrap.php`
- `application/controllers/MediaController.php`
- `application/controllers/helpers/Directory.php`
- `application/modules/contacts/controllers/AttachmentController.php`
- `application/modules/contacts/controllers/DownloadController.php`
- `application/modules/contacts/models/DbTable/Download.php`
- `application/modules/contacts/models/DbTable/Emailattachment.php`
- `application/modules/contacts/views/scripts/contact/files.phtml`
- `application/modules/contacts/views/scripts/download/edit.phtml`
- `application/modules/contacts/views/scripts/download/index.phtml`
- `application/modules/items/views/scripts/item/pdf.phtml`
- `application/modules/items/views/scripts/itemlist/pdf.phtml`
- `application/modules/shops/controllers/InquiryController.php`
- `application/modules/shops/models/DbTable/Item.php`
- `application/plugins/Acl.php`
- `application/views/helpers/Document.php`
- `application/views/helpers/FileManager.php`
- `application/views/helpers/MediaManager.php`
- `js/trash.js`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/Directory.php`
- `library/DEEC/Pdf.php`
- `library/DEEC/Sns.php`
- `library/DEEC/UrlDownload.php`
- `library/FileManager/upload.php`
- `library/MediaManager/upload.php`
- `tests/security/attachment-http.py`
- `tests/security/configuration-http.py`
- `tests/security/file-access-paths.tsv`
- `tests/security/file-access-security-review.md`
- `tests/security/file-access.php`
- `tests/security/permissions.php`
- `tests/security/url-download.php`

Es wurden keine Commits erstellt und keine Änderungen gepusht.

Commit-Vorschlag: `secure stored file access and prevent url upload ssrf`
