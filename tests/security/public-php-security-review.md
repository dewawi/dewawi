# DEWAWI – Security Phase 3: Öffentliche PHP-Einstiegspunkte

Stand: 28.10.2026. Untersucht wurde der aktuelle Repository-Stand einschließlich der vorhandenen Production-/Development- und Fehlerbehandlungsmaßnahmen.

## Ergebnis und Methode

Das vollständige [Inventar](php-entrypoints-inventory.tsv) erfasst **3.935 versionierte PHP-/Include-Dateien**: 3.747 `.php`, 185 `.phtml`, eine `.phar` und zwei `.php-dist`-Vorlagen. Auch die tatsächlichen Arbeitsverzeichnisdateien und vorhandenen `.htaccess`-Dateien wurden abgeglichen. Laufzeitdateien mit PHP-Endungen werden unabhängig vom Inventar durch die neue Apache-Regel ebenfalls gesperrt.

Für jede inventarisierte Datei sind Kategorie und HTTP-Zugänglichkeit vor/nach Phase 3 dokumentiert. „Vorher direkt erreichbar“ beschreibt die Apache-Zugriffsmöglichkeit; eine Datei kann anschließend ohne Ausgabe enden oder mangels Abhängigkeiten fehlschlagen. Die Bewertung ist keine Behauptung, dass jede Bibliotheksklasse einen ausführbaren Angriff bietet.

Die Untersuchung umfasste Aufrufer in Anwendung, Views, JavaScript und Bibliotheken, die relevanten Dateisystem-/Datenbankoperationen sowie Session-, Berechtigungs-, Methoden- und CSRF-Prüfungen der tatsächlichen Endpunkte. Nicht jede Zeile der mehreren Tausend Bibliotheksklassen wurde fachlich auditiert. Alle inventarisierten gesperrten Pfade wurden dagegen einzeln über einen isolierten Apache geprüft. Für diese Zugriffsproben wurden harmlose Dateien anstelle unbenötigter Original-Demos verwendet; gefährliche Demos wurden nicht ausgeführt.

Nach den bisherigen Regeln waren **599 Pfade potentiell direkt erreichbar**. Jetzt bleiben **12 URL-Pfade zugelassen**: Front Controller, Installer und zehn durch ZF1 geschützte Manager-URLs. **3.923 inventarisierte Pfade liefern HTTP 403**. Dadurch entfallen 587 zuvor erreichbare interne/Demo-Pfade; die zehn benötigten Manager-Pfade erhalten zusätzlich die Anwendungskontrollen.

| Kategorie | Dateien | Verwendung und Entscheidung |
| --- | ---: | --- |
| Front Controller | 1 | `index.php`: bestehendes ZF1-Routing bleibt öffentlich; bestehende ACL und Fehlerbehandlung gelten weiterhin. |
| Installer-Einstieg | 1 | `install/index.php`: vor Konfiguration verfügbar, danach durch den bestehenden Konfigurationscheck gesperrt/umgeleitet. POST benötigt einen Session-CSRF-Token. |
| Manager-Endpunkte | 10 | Je fünf Endpunkte für FileManager/MediaManager; unveränderte Browser-URLs, jetzt über ZF1 authentifiziert und geprüft. |
| Interne Anwendung | 856 | Bootstrap, Controller, Models, Forms, Helper und Views: kein direkter HTTP-Zugriff. |
| DEEC-Komponenten | 48 | Server-seitige Einbindung bleibt möglich; kein direkter HTTP-Zugriff. |
| Übersetzungen | 101 | Interne PHP-Arrays; server-seitige Einbindung bleibt möglich. |
| Installer-Includes/Templates | 18 | Nur über den Installer einbinden. |
| Cronjob | 1 | `cron.php`: ausschließlich PHP-CLI. |
| Regressionstests/Fixtures | 39 | CLI bzw. isolierte Testserver; unverändert nicht öffentlich. |
| Manager-Includes/Konfiguration/Sprachen | 96 | Kein direkter HTTP-Zugriff; interne Includes der freigegebenen Manager bleiben erhalten. |
| elFinder-Connector/Vorlagen | 3 | Ein aktiver Minimal-Connector und zwei Vorlagen; kein legitimer Aufrufer des Minimal-Connectors gefunden, HTTP gesperrt. |
| Weitere Bibliotheken/Includes | 2.427 | Einschließlich Zend, TCPDF-Kern/Fonts, PHPMailer, OpenCart, eBay und pChart-Kern; nur server-seitig benötigt. |
| Drittanbieter-CLI-Werkzeug | 1 | TCPDF-Fontwerkzeug; bereits mit eigener Apache-/CLI-Sperre, zusätzlich vom Standardverbot erfasst. |
| Drittanbieter-Beispiele/Demos | 333 | pChart-Beispiele/Bugs, TCPDF-Beispiele und CKEditor-Posted-Data-Samples; kein produktiver HTTP-Aufrufer gefunden. |

## Sicherheitsbewertung und konkrete Fundstellen

Die Schwere beschreibt den Befund **vor** dieser Änderung. Die kritischen Abläufe wurden durch Codeflussanalyse festgestellt, ohne sie gegen die produktive Anwendung auszuführen.

| Schwere | Befund und Fundstelle | Auswirkung / Bewertung | Behebung |
| --- | --- | --- | --- |
| **Kritisch** | pChart-Sandbox: `library/pChart/examples/sandbox/render.php:19` liest Request-JSON mit `extract`; Zeilen 61–64 setzen ungeprüfte Werte in PHP-Code ein; Zeilen 319, 349 und 397 verwenden `eval`. | Öffentlicher Demo-Code erlaubt einen Pfad von Request-Daten zu PHP-Codeausführung. Kein Anwendungs-Login oder CSRF-Schutz. | Alle Demo-PHP-Aufrufe gesperrt. pChart-Klassen werden weiterhin server-seitig von den Statistikmodellen eingebunden. |
| **Kritisch** | Beide Manager: `dialog.php:24` setzt `verify` selbst; `ajax_calls.php:81` verarbeitet `save_img`, um Zeile 122 wird mit Request-Pfad/-Name geschrieben. `upload.php` prüfte ebenfalls nur die selbst erzeugbare Markierung. | Ein Gast konnte eine Manager-Session vorbereiten und anschließend Dateioperationen außerhalb der Anwendungskontrollen erreichen. Besonders die Kombination aus frei übergebenem Dateinamen und direkter PHP-Auslieferung war gefährlich. | Manager-URLs werden vor Ausführung zu `index.php` umgeschrieben. Anmeldung, frischer Benutzer-/Mandantenkontext, DEEC-Rechte, serverseitiger Formular-Kontext, CSRF und Pfad-/Dateinamenprüfung sind erforderlich. Native Konfigurationsaufrufe ohne Freigabe des Controllers enden ebenfalls mit 403. |
| **Hoch** | `library/elFinder/php/connector.minimal.php:29` und `:40` bilden die Speicherwurzel aus `extrapath`/`directory`; `:63` startet den Connector ohne DEWAWI-Anmeldung. | Dateioperationen auf einer vom Aufrufer beeinflussbaren Wurzel, abhängig von lokalen Dateirechten; keine Benutzer-/Mandantenbindung. | Der nicht benötigte Connector sowie Bibliotheksklassen und Connector-Vorlagen sind über HTTP gesperrt. Keine Drittanbieterdatei geändert. |
| **Hoch** | `cron.php`: ursprünglich begann der Ablauf unmittelbar mit `_CRONJOB_` und `index.php`; danach folgen Datenbankzugriffe, Kampagnenversand, Statusänderungen und weitere Hintergrundarbeit. | Ein anonymer HTTP-GET konnte Hintergrundaktionen anstoßen; keine Anmeldung, Methoden- oder CSRF-Prüfung. | Apache sperrt den Pfad. Zusätzlich beendet ein PHP-SAPI-Check HTTP-Aufrufe vor Initialisierung mit 403. CLI bleibt verfügbar. |
| **Hoch** | Beide Manager: `dialog.php:120` kann bei nicht vorhandenem Ordner den Unterordner leeren. Die bisherigen `checkRelativePath`-Prüfungen in `execute.php`, `ajax_calls.php` und `force_download.php` begrenzten Pfade nicht auf den freigegebenen Mandantenordner. | Ein gültiger relativer Pfad war noch kein autorisierter Pfad. Fehlender Kontext oder manipulierte Download-/Schreibpfade konnten fremde Ordner erreichen; ein UI-Schreibschutz allein genügte nicht. | Formular-Kontext bindet Benutzer, ausgewählten Mandanten und Daten-Eigentümer. Pfade müssen innerhalb des Session-Ordners bleiben; `realpath` prüft auch vorhandene Eltern und Symlinks. Unbekannte Dialog-Unterordner fallen auf den autorisierten Ordner zurück. Schreiboperationen benötigen Edit-Rechte. Alte Clipboards werden bei Kontextwechsel entfernt. |
| **Hoch** | Installer-POSTs zu `importSQL`/`done` sowie schreibende Manager-Aufrufe hatten keinen CSRF-Token. | Eine fremde Seite konnte die Session eines Anwenders für zustandsändernde Requests verwenden. | Session-Token mit `random_bytes`/`hash_equals`. Installer-Formulare enthalten den Token. Manager-AJAX sendet einen Header; POST-Formulare und Blueimp-/Iframe-Upload-Daten enthalten ihn ebenfalls. Mutierende Endpunkte benötigen POST. |
| **Mittel** | TCPDF-Beispiele, pChart-Demos außerhalb der Sandbox und ähnliche Testskripte waren öffentlich; produktive Aufrufer hierfür wurden nicht gefunden. | Unnötige Rechen-/Dateioperationen und mögliche Diagnoseausgaben außerhalb der sicheren Anwendungsinitialisierung. | Gezieltes Verbot von PHP-/Include-Dateien statt Sperrung kompletter Asset-Verzeichnisse. |
| **Niedrig** | Manager-`index.php`, `dewawi-dialog.php`, Sprachdateien und reine Bibliotheks-/Font-Includes waren teils direkt erreichbar. | Meist leere Antworten, isolierte Fragmente oder Fehler bei fehlenden Abhängigkeiten; unnötige Angriffsfläche. | Standardmäßig keine direkten PHP-/Include-Aufrufe außerhalb der zwölf geprüften Pfade. |

Bereits wirksame Maßnahmen blieben bestehen: sichere Production-Konfiguration und Fehlerausgabe, bestehende Root-Sperren für interne Verzeichnisse/SQL/Logs, deaktivierte Verzeichnislisten und MultiViews, `tests/.htaccess`, die Manager-Konfigurationssperren und TCPDFs CLI-Werkzeugschutz.

## Bewusst zugelassene Endpunkte und Abhängigkeiten

- **`index.php` und die normalen ZF1-URLs:** Login, öffentliche Shop-/Feedback-Funktionen, bestehende AJAX-Aufrufe und der autorisierte Attachment-Download bleiben im vorhandenen Routing. Das Standardverbot greift vor dem bisherigen „vorhandene Datei unverändert ausliefern“-Zweig.
- **`install/index.php` und `/install/`:** Nur die vorhandenen sieben Installationsschritte; GET/HEAD für die Anzeige, POST mit Token. Installer-CSS und Bilder bleiben statisch verfügbar. Sobald eine ausreichend große `configs/database.ini` existiert, greift der vorhandene Installationsschutz vor weiteren Schritten.
- **`library/{FileManager,MediaManager}/{dialog,upload,execute,ajax_calls,force_download}.php`:** Referenzen wurden in `js/global.js`, Formular-/Iframe-Views und dem bibliothekseigenen JavaScript gefunden. Diese URLs bleiben erhalten, führen aber ausschließlich über `Bootstrap::_initFileManagerRoutes`, ACL und `MediaController::managerAction` zu den internen Includes.
- **FileManager:** Wird in Kontakt-, Artikel-, Dokument-, Kampagnen-, Benutzer- und Admin-Views verwendet. Der bestehende View-Helper bereitet einen Benutzer-/Mandantenkontext und einen Ordner vor. Bei geteilten Kontakten/Artikeln bleiben ausgewählter Mandant und Daten-Eigentümer getrennt.
- **MediaManager:** Der tatsächliche Aufrufer ist die Admin-Medienseite. Der bisherige Mandantenordner `media/<Kurzpfad>/` bleibt erhalten; der Zugang benötigt weiterhin Admin-Rechte.
- **Manager-Assets:** JavaScript, CSS, Icons und Vorschaubilder bleiben erreichbar. Das eigene `js/filemanager.js` ergänzt CSRF-Token, ohne Drittanbieter-JavaScript zu ändern. Die server-seitigen Bibliotheksaufrufe behalten Arbeitsverzeichnis, erforderliche PHP-Globals und `SCRIPT_FILENAME`/`SCRIPT_NAME`/`PHP_SELF`; der echte Multipart-Upload wurde darauf geprüft.
- **elFinder:** `js/settings.js:166` verweist noch auf einen nicht vorhandenen `connector.php`. Dieser alte Verweis war bereits vorher unvollständig. Es wurde kein Aufrufer für den tatsächlich enthaltenen `connector.minimal.php` gefunden. Es wird kein neuer Connector angelegt und kein ungeschützter Ersatz aktiviert.
- **Server-seitige Bibliotheken:** Statistikmodelle laden pChart-Klassen; PDF-Code lädt TCPDF; OpenCart/eBay/PHPMailer sind server-seitige Integrationsklassen. HTTP-Sperren beeinträchtigen `require`/`include` nicht. Öffentliche jQuery-/Leaflet-/Editor-Ressourcen sind keine PHP-Dateien und bleiben verfügbar.

## Verbleibende Risiken und Kompatibilität

1. **Apache-Konfiguration ist Teil des Schutzes.** `mod_rewrite` und die vorhandenen `.htaccess`-Direktiven müssen wirksam sein. Bei Nginx oder einer abweichenden Alias-/Handler-Konfiguration sind dieselben Einschränkungen serverseitig zu übernehmen. Die Tests verwenden Apache 2.4 mit `AllowOverride All`.
2. **Vorinstallationsphase:** Der Installer bleibt vor der ersten Konfiguration absichtlich ohne DEWAWI-Benutzeranmeldung verfügbar. CSRF ersetzt keinen administrativen Netzwerkzugangsschutz. Erstinstallation sollte nur für den zuständigen Betreiber erreichbar sein. Vorhandene Produktionskonfigurationen wurden nicht verändert.
3. **Alte Manager-Fenster müssen neu geöffnet werden.** Bereits vorhandene Sessions ohne gebundenen Kontext werden abgewiesen. Ein normaler Formularaufruf bereitet den Kontext neu vor. Login/Logout entfernen beide Manager-Namensräume; ein Mandantenwechsel macht den alten Kontext ungültig. Das bisherige gemeinsame Session-Modell pro Manager bleibt bestehen, einschließlich seiner Einschränkung bei mehreren gleichzeitig geöffneten Kontexten.
4. **Kein globaler Upload-Fallback:** Fehlender Kontext berechtigt nicht mehr zur globalen Dateiwurzel bzw. zum mandantenübergreifenden `uploads`-Ordner. Bestehende Dateien werden weder verschoben noch gelöscht; unbekannten Altbeständen wird nicht automatisch ein Mandant zugewiesen.
5. **Statische Bestandsdateien:** Das bisherige Bereitstellungsmodell für Medien, Vorschaubilder und nicht-PHP-Dateien bleibt erhalten. Insbesondere ersetzt die Absicherung der Manager nicht das Zugriffsmodell statischer Links unter `files/contacts`, `files/uploads` oder `media`. Vertrauliche Dokumente gehören in einen autorisierten Downloadablauf; eine Migration sämtlicher Altbestände wäre eine eigene Aufgabe. Der bereits geschützte `files/attachments`-Ablauf bleibt wirksam.
6. **Drittanbieter-Funktionen hinter dem Zugang:** Dieser Auftrag ersetzt kein vollständiges Audit der Archiv-, Bild-, URL-Upload- oder FTP-Implementierungen. Beispielsweise bleibt der URL-Upload eine serverseitige Netzwerkanfrage eines angemeldeten, schreibberechtigten Anwenders; Zielnetz-/DNS-Regeln hierfür wurden nicht neu eingeführt. Externe Integrationen wurden nicht live getestet. Nicht benötigte PHP-Endpunkte sind jedoch nicht mehr öffentlich erreichbar.
7. **Veränderte Aufrufkonventionen sind beabsichtigt:** HTTP-Cron-Aufrufe funktionieren nicht mehr; Scheduler müssen PHP-CLI verwenden. Manager-Schreibzugriffe und Downloads verwenden POST mit Token. Bei Skript-Endungen sowie PHP-Quell-/Backupvarianten außerhalb der Freigabe wird 403 geliefert. Ein zukünftiger direkter PHP-Endpunkt benötigt eine ausdrückliche Prüfung und Freigabe.
8. **GET-Anzeigen:** Manager-Dialoge dürfen weiterhin ausschließlich in ihrem festgelegten Kontext Ordner/Vorschaubild-Verzeichnisse für die Anzeige vorbereiten. Dateiänderung/-löschung über nutzergesteuerte Befehle ist dagegen nur mit POST, Token und Schreibrecht möglich.

## Ausgeführte Tests

| Prüfung | Ergebnis |
| --- | --- |
| `php -l` auf allen 17 geänderten PHP-/PHTML-Dateien | Erfolgreich |
| Python-Syntax der drei geänderten HTTP-Testskripte | Erfolgreich |
| Alle `tests/security/*.php` | **24/24 erfolgreich**, einschließlich 16 Konfigurationsfällen und **347** Berechtigungs-/ACL-/Manager-Kontextfällen |
| `python3 tests/security/configuration-http.py` | **8.305 HTTP-Prüfungen erfolgreich**; jeder der 3.923 gesperrten inventarisierten Pfade im Webroot und Unterverzeichnis; positive Manager-Dialog-, Datei-Schreib-, Multipart-Upload- und Downloadfälle; Gäste, fehlende Token, GET-Mutationen, falsche Mandanten, schreibgeschützte Kontexte, Traversal und fremde Symlinks abgewiesen |
| Lokaler Chromium-Test innerhalb derselben Apache-Reihe | Erfolgreich: reales jQuery und `js/filemanager.js`; AJAX-Header, POST-Formularfelder und nach dem Vendor-Handler ergänzte Blueimp-/Iframe-Upload-Daten |
| `python3 tests/security/error-handling-http.py` | **140 Fälle erfolgreich**; Production/Development, HTML/JSON, Initialisierung/Bootstrap, Logging, Cron-HTTP-Sperre, Varianten, Installer und Manager-Fehlerpfade |
| `python3 tests/security/session-http.py` | **25 HTTP-Fälle erfolgreich**, einschließlich Widerruf alter Manager-Kontexte bei Login/Logout |
| `python3 tests/security/attachment-http.py` | Erfolgreich: Gäste/fehlende Rechte abgewiesen, autorisierte Legacy-/ID-Downloads und HEAD sowie öffentliche Medien erhalten |
| `python3 tests/install/import-db.py` | Vollständiger Import, Schema-/Migrations- und Fehlerfälle erfolgreich auf temporärer MariaDB 10.11.14; zusätzlich **66 UOM-Prüfungen** des bestehenden Runners erfolgreich |
| `git diff --check` | Erfolgreich |

Die Apache-Tests verwenden Registry-/Berechtigungs-Fixtures für die Anmeldung und prüfen den echten neuen Controller sowie die echten Manager-Dateioperationen. Die bestehenden Bootstrap-/Mandanten-Tests prüfen separat die reale Konto-/Mandantenvalidierung. Es wurden keine produktiven Datenbanken angesprochen und keine echten Cron-, Mail-, OAuth-, FTP- oder anderen externen Integrationsaktionen ausgeführt. Der Browser-Test lädt ausschließlich lokale Testressourcen; die Manager-CDNs wurden dabei nicht aufgerufen.

## Geänderte Dateien

- `.htaccess`
- `application/Bootstrap.php`
- `application/controllers/MediaController.php`
- `application/plugins/Acl.php`
- `application/views/helpers/FileManager.php`
- `application/views/helpers/MediaManager.php`
- `cron.php`
- `install/index.php`
- `install/templates/database.php`
- `install/templates/filePermissions.php`
- `install/templates/importSQL.php`
- `install/templates/introduction.php`
- `install/templates/license.php`
- `install/templates/requirements.php`
- `library/DEEC/Session.php`
- `library/FileManager/config/config.php`
- `library/MediaManager/config/config.php`
- `js/filemanager.js` (neu)
- `tests/security/configuration-http.py`
- `tests/security/error-handling-http.py`
- `tests/security/permissions.php`
- `tests/security/session-http.py`
- `tests/security/php-entrypoints-inventory.tsv` (neu)
- `tests/security/public-php-security-review.md` (neu)

Die ursprünglichen Drittanbieter-Implementierungen bleiben unverändert; ergänzt werden lediglich die beiden bereits projektspezifischen Manager-Konfigurationen. Keine neuen Frameworks oder produktiven Klassen wurden eingeführt. Es wurden keine Commits erstellt und nichts gepusht.

Commit-Vorschlag: `restrict public php entry points and secure file manager access`
