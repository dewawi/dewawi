# Einheitliche Meldungen und AJAX-Ladezustände

## Umfang und Bestandsaufnahme

Die ersten drei Phasen sind umgesetzt: gemeinsame Meldungskomponenten und die vollständige Integration des interaktiven E-Mail-Versands. Phase 4.1 erweitert diese Komponenten um Lösch- und Stornierungsaktionen, Phase 4.2 um ausdrücklich ausgelöste Erstell- und Speicheraktionen und Phase 4.3 um Dokument- und Toolbar-Aktionen. Weitere Aktionen werden separat migriert.

| Vorhandene Stelle | Befund | Entscheidung / Umsetzung |
| --- | --- | --- |
| `js/global.js`: `pushMessages()` | Eine neue `#messages`-ID pro Text, ungeprüftes HTML, keine Behandlung verschachtelter Validierungsfehler | Einen Container wiederverwenden, Texte deduplizieren und mit `.text()` ausgeben; vier Schweregrade und Validierungsobjekte unterstützen |
| `removeMessages()` | Auskommentierte automatische Ausblendung, faktisch ohne Funktion | Nur noch explizit aufrufen; kein Aufruf beim Laden oder Anzeigen einer Meldung |
| `application/views/helpers/Messages.php` | Unescaped Flashtexte ohne gemeinsame Komponenten | Übersetzungen escapen, bekannte Erfolgsschlüssel zuordnen, nach Schweregrad gruppieren; typisierte Flashmeldungen unterstützen |
| `css/ui.css`: `.dw-alert`, `.dw-btn` | Drei Meldungsvarianten; helle Schriftfarben; `.is-loading` nur für Finalisierung | Vier Varianten mit vorhandenen Tokens, dunkler Textfarbe, Umbruch, Schließen-Button und gemeinsamem statischem Ladezustand |
| `#output` im E-Mail-Partial | Separater roter Meldungsblock | Bestehenden Container für dieselben Meldungskomponenten verwenden; außerhalb des sichtbaren Bereichs liegende Versandfehler in den sichtbaren Bereich scrollen |
| `sendMessage()` / `resendMessage()` | Keine Requestsperre, kein Fortschritt, fehlende oder unklare Erfolgsprüfung | Gemeinsame Versandabwicklung mit exklusiver Sperre, übersetztem Status, Buttonzustand und begrenzter Requestdauer |
| FlashMessenger im E-Mail-Helper | Zusätzliche Meldung trotz AJAX-Antwort | Bei AJAX keine Flashmeldung einreihen; normale Requests behalten FlashMessenger |
| E-Mail-Historie in `contacts` | Technische Transportantwort im sichtbaren Fehlertext | Verständlichen übersetzten Status anzeigen; vorhandene Speicherung bleibt erhalten, negative Transportresultate werden serverseitig geloggt |
| `layout.phtml` / `plain.phtml` | Bestehende JavaScript-Konfiguration, beide laden `global.js` | Kleinen gemeinsamen Übersetzungskatalog aus dem Messages-Helper mit sicheren JSON-Flags bereitstellen |
| `#loading` / `ajaxStart` | Globales Overlay vorhanden, globale AJAX-Aktivierung auskommentiert; Suche verwendet Overlay ausdrücklich | Keine globale Aktivierung; Suche, Keepalive und Auto-Save behalten ihr Verhalten |
| Dokumentfinalisierung | Vorhandene eigene `.is-loading`-Sperre und Validierungsdarstellung | Gemeinsame Buttonzustandsverwaltung wiederverwenden; vorhandene Validierungsdarstellung und Route erhalten |
| `js/contacts.js` | Meldungen über `pushMessages()`, bei Kontaktpersonenzuordnung bereits lokale `disabled`-Behandlung | Zentrale Darstellung wirkt sofort; weitere lokale Ladezustände folgen separat |
| Kampagnen und Shop | Kampagnen nutzen `resendMessage()`; Shop nutzt denselben PHP-Meldungshelper, aber `shops.js` und eigenes Layout | Kampagnen übergeben den betätigten Button; Shop erhält die Schließen-Behandlung für den gemeinsam gerenderten Button |

## Komponentenvertrag

- `pushMessages(messages, type, target)` akzeptiert weiterhin Strings, Arrays sowie `{message}`, `{messages}` und `{errors}`. `type` ist `success`, `danger` / `error`, `warning` oder `info`; bestehende Aufrufe ohne Typ bleiben Fehlermeldungen. Standardziel ist `#messages`, beim E-Mail-Formular `#output`.
- Pro Schweregrad wird der vorherige Block ersetzt. Information entfernt keinen Fehler; Erfolg ersetzt Fehler im gleichen Zielcontainer. Fehler haben keinen Timer und können ausdrücklich geschlossen werden. Unabhängige Bereiche sollen eigene vorhandene Zielcontainer verwenden.
- Dynamische Texte werden niemals als HTML ausgewertet. Bestehendes Markup in Flashtexten wird deshalb als Text sichtbar; beabsichtigte HTML-Formatierung wird nicht mehr ausgeführt.
- `setButtonLoading($buttons, loading, label)` speichert und restauriert Inhalt einschließlich DOM-Knoten, `disabled`, `aria-disabled`, `aria-busy` und eine bereits vorhandene `.is-loading`-Klasse. Neue Aktionen müssen zusätzlich ihren Request gegen Mehrfachausführung sperren und in `complete` aufräumen. Auto-Save bekommt keine globale Sperre.
- PHP-Flashmeldungen können optional als `['type' => 'warning', 'message' => 'TRANSLATION_KEY']` eingereiht werden; alte Stringmeldungen bleiben unterstützt. Unbekannte Stringmeldungen werden als Fehler eingestuft.

## Versandverhalten

Während eines interaktiven Versands ist in derselben Seite kein zweiter Versand oder erneuter Versand möglich. Nur der betätigte Button erhält einen Ladezustand. Erneuter Versand verlangt vorher eine ausdrückliche Bestätigung. Kampagnen aktualisieren nach bestätigtem Erfolg weiterhin Empfänger und Fehlerübersicht.

Nur `ok === true` bestätigt den Versand. `ok === false` führt zum allgemeinen Versandfehler. Netzwerkfehler, Timeout, Parserfehler und Antworten ohne eindeutiges Resultat führen zum Hinweis, zunächst die Historie zu prüfen. Es gibt keinen automatischen Wiederholungsversuch. Der Timeout beträgt 120 Sekunden und bricht eine möglicherweise noch laufende serverseitige Verarbeitung nicht verlässlich ab. Die Erfolgsaussage bezeichnet SMTP-Annahme und verspricht keine endgültige Zustellung.

Die Historie wird nur nach bestätigtem Erfolg aktualisiert. Scheitert diese Aktualisierung, bleibt der Versanderfolg erhalten und eine separate Warnung fordert zum Neuladen auf. Empfänger, Anhänge, Dokumentkontext, SMTP-Auswahl, Datenbankstatus und serverseitige Zugriffsprüfungen bleiben erhalten.

Die Sperre gilt innerhalb einer geöffneten Seite. Sie bietet keine Idempotenz zwischen Browser-Tabs, Geräten oder nach einem Neuladen. Eine solche Erweiterung würde eine getrennte fachliche und serverseitige Änderung erfordern.

## Phase 4.1 – Löschen und Stornieren

### Geprüfte Aufrufstellen und Antwortverträge

| Stelle | Geprüftes Verhalten / Änderung |
| --- | --- |
| `js/global.js`: `trash()` | JSON-POST an `/trash/add/`; `ok`, `deletedIds`, `blockedIds`. `ok: false` kann bestätigte Teilresultate enthalten. Nur angeforderte, ausdrücklich bestätigte IDs werden entfernt. Lokale Entfernung bei `action === 'add'` bleibt ohne Serverrequest. |
| `DewawiToolbar.actions.delete` | Übergibt jetzt den tatsächlich betätigten Button an `trash()`. Standard-Toolbars, Inline-Aktionen, MultiForm und Admin-Pageblocks verwenden diese gemeinsame Aktion. |
| `deleteEntity()` | Wrapper bleibt abwärtskompatibel und akzeptiert optional den Button als viertes Argument. Im Repository gibt es derzeit keine produktiven Aufrufstellen dieses Wrappers; direkte Verwendung wird im Browser-Fixture geprüft. |
| `application/views/helpers/Toolbar.php`, `application/views/helpers/MultiForm.php` und Toolbar-Dispatch | Vorhandene Zielattribute, Einzel-/Mehrfachauswahl und Berechtigungsdarstellung reichen aus. Keine Änderungen nötig. |
| `application/controllers/TrashController.php` | Antwortet mit fachlichem Ergebnis und ID-Listen, verarbeitet gültige Teilresultate und schützt Mandanten sowie schreibgeschützte Dokumente. Bleibt unverändert, einschließlich bisheriger Flashmeldungen. |
| `library/DEEC/Controller/Action.php` | Native `deleteAction()` verwendet FlashMessenger und Redirects; sie wird von `trash()` nicht aufgerufen und bleibt unverändert. `cancelAction()` bestätigt Erfolg mit `ok: true`, auch für bereits stornierte Einträge. |
| `library/DEEC/Controller/DocumentAction.php` | Stornierung prüft Datensatz und Readonly-Status innerhalb einer Transaktion und liefert `ok: true` oder `ok: false` mit HTTP 403. Keine fachlichen Änderungen. Weitere Controller-Overrides von `cancelAction()` wurden im Anwendungsverzeichnis nicht gefunden. |
| `DewawiToolbar.actions.cancel` | Bestehende Bestätigung und Zielroute bleiben erhalten. Nur `ok === true` startet Listenaktualisierung oder bisherigen Reload der Edit-/View-Ansicht. |
| `deleteAttachment()` | Die drei vorhandenen Aufrufstellen sind `application/views/scripts/partials/email-messages.phtml`, `application/views/scripts/partials/downloads.phtml` und `application/modules/contacts/views/scripts/attachment/upload.phtml`. Alle übergeben jetzt `this` und verwenden bestehende Buttonklassen. |
| `Contacts_AttachmentController::deleteAction()` | Lieferte bisher eine leere HTTP-200-Antwort. Als einzige Backend-Anpassung bestätigt eine zusätzliche JSON-Rückgabe nach erfolgreicher Verarbeitung jetzt `ok: true` und eindeutige `deletedIds`. Rechte-, Pfad- und Batchprüfungen sowie Löschregeln bleiben erhalten. |
| `search()` / `reloadHistory()` | Bestehende Aktualisierungswege und Navigation bleiben bestehen. Der vorhandene Callback von `search()` und ein neuer optionaler Callback von `reloadHistory()` stellen die Aktionsmeldung nach Austausch des Inhalts wieder her. |

### Architektur und Fehlerbehandlung

`setButtonLoading()` bleibt die einzige Funktion zur Buttonzustandsverwaltung. Eine kleine gemeinsame Requestsperre in `beginActionRequest()` ist nach Aktion, Modul, Controller und Datensatz-ID gegliedert. Sie greift vor einer erneuten Bestätigung und verhindert auch überlappende Mehrfachlöschungen. Andere Datensätze, andere Aktionen und Hintergrundrequests erhalten keine globale Sperre. Der aufrufende Button wird optional übergeben; alte Aufrufe ohne Button bleiben durch dieselbe Datensatzsperre geschützt.

Alle drei Requestpfade räumen in `complete` auf. Buttoninhalt, Icons, ursprüngliches `disabled`, `aria-disabled`, `aria-busy` und `is-loading` werden über die vorhandene Funktion wiederhergestellt, soweit das Element nach erfolgreicher Löschung noch vorhanden ist. Der bestehende statische Ladeindikator bekommt auch auf Iconbuttons eine sichtbare Schriftgröße; währenddessen wird deren Hintergrundicon ausgeblendet. Es wurden keine neuen CSS-Klassen, Frameworks oder parallelen Meldungskomponenten eingeführt.

Löschergebnisse werden nur bei einem booleschen `ok` und einer gültigen `deletedIds`-Liste verarbeitet. IDs außerhalb der angeforderten Auswahl werden ignoriert. Fehlende IDs, blockierte Einträge und ausdrücklich bestätigte Teilresultate führen zu einer Fehlermeldung statt zur pauschalen Erfolgsaussage. Fehlende oder falsch typisierte Resultate und ungültiges JSON führen zu einem unklaren Aktionsstatus, ohne Entfernung von Einträgen oder Erfolgsnavigation.

Fachlich negative Antworten (`ok === false`), auch bei einem HTTP-Fehler, erhalten einen übersetzten allgemeinen Fehlertext. Netzwerkunterbrechung, Timeout, Parserfehler und HTTP-Fehler ohne eindeutiges fachliches Ergebnis erhalten den Hinweis, vor einer Wiederholung die aktuelle Ansicht zu prüfen. Es gibt keine automatische Wiederholung. Die Requestdauer ist auf 120 Sekunden begrenzt; ein clientseitiger Timeout garantiert keinen serverseitigen Abbruch.

Die neuen Aktionen verwenden den bestehenden Zielcontainer `#messages`. Ihre Fehler bleiben ohne Timer sichtbar und werden bei Bedarf in den sichtbaren Bereich gescrollt. Rückmeldungen werden nach erfolgreicher Listen- oder Historienaktualisierung wieder eingesetzt. Ein nachfolgender Erfolg ersetzt einen Fehler über `pushMessages()`. Ungeprüfte Antworttexte werden nicht angezeigt. Bestehende Übersetzungsschlüssel für Löschen und Stornieren wurden wiederverwendet und fehlende Schlüssel für Deutsch und Englisch ergänzt.

### Tests in Phase 4.1

Nur isolierte Fixtures wurden verwendet; keine produktiven Lösch-/Stornierungsrequests, keine produktiven Datenbankänderungen und keine echten E-Mails.

| Ausgeführte Prüfung | Ergebnis |
| --- | --- |
| `python3 tests/ui/notifications.py` | Jeweils **228 Prüfungen** bei 1280 px und 390 px bestanden, einschließlich der bisherigen E-Mail-Prüfungen. Deutsch/Englisch, echte Toolbar-Dispatches, Einzel-/Mehrfachlöschung, Abbruch der Bestätigung, Doppelklick, identische und überlappende Requests, unabhängige Aktionen, Hintergrundrequest, Button-/Iconzustände, negative/ungültige Antworten, HTTP-/Netzwerk-/Timeout-/Parserfehler, nur bestätigte Teilresultate, Meldungen nach Listen-/Historienaktualisierung, Navigation, Pageblock-Reload, rein lokale Entfernung, Wrapper und alte Aufrufe ohne Button. |
| `php tests/security/notifications.php` | **70 Prüfungen** bestanden, einschließlich der 21 vollständig übersetzten Browser-Meldungsschlüssel in beiden Sprachen. |
| `php tests/security/attachments.php` | **224 Anhangprüfungen** bestanden, einschließlich expliziter JSON-Bestätigung eindeutiger gelöschter IDs, Batchablehnung, Berechtigungen und Mandantenfreigaben. Der wiederverwendete Setup führt zusätzlich **364 Quote-E-Mail-Prüfungen** mit abgefangenem Transport aus. |
| `php tests/security/pageblocks.php` | **74 Prüfungen** bestanden; ergänzt um den tatsächlichen gemischten Trash-Batch aus erlaubtem Blatt, geschütztem Container und fremdem Mandanten. |
| `php tests/security/document-edits.php` | **7400 Prüfungen** bestanden, einschließlich Lösch-/Stornierungsrouten, Readonly-Status und Mandantenberechtigungen. |
| `php tests/security/permissions.php` | **369 Prüfungen** bestanden. |
| `php tests/security/tenant-access.php` | **268 Prüfungen** bestanden, einschließlich Trash, Historie und freigegebener Mandantendaten. |
| `php tests/documents/lifecycle.php` | **879 Prüfungen** bestanden. |
| `python3 tests/documents/calculation-ui.py` | Dokument-/Inline-Notiz-Auto-Save und **26 Berechnungsprüfungen** bestanden. |
| `php -l` | Alle **10** geänderten PHP-/PHTML-Dateien ohne Syntaxfehler. |
| `git diff --check` | Ohne Befund. |

Der bestehende UI-Runner wird weiterverwendet. Er ersetzt im temporären Testskript ausschließlich harte `location.reload()`-Aufrufe durch einen Recorder; `setLocation()` wird im Fixture abgefangen. Dadurch sind die unveränderten Navigationsentscheidungen prüfbar, ohne den Test zu verlassen. AJAX wird vollständig abgefangen; Listenaktualisierung wird einschließlich ihres Rückmelde-Callbacks isoliert geprüft. Für Browserausführung und mobilen Viewport gelten die unten dokumentierten Umgebungsbedingungen.

### Geänderte Dateien in Phase 4.1

1. `js/global.js`
2. `css/ui.css`
3. `application/views/helpers/Messages.php`
4. `languages/de_DE/default/messages.php`
5. `languages/en_US/default/messages.php`
6. `application/modules/contacts/controllers/AttachmentController.php`
7. `application/modules/contacts/views/scripts/attachment/upload.phtml`
8. `application/views/scripts/partials/downloads.phtml`
9. `application/views/scripts/partials/email-messages.phtml`
10. `tests/ui/notifications.html`
11. `tests/ui/notifications.py`
12. `tests/security/notifications.php`
13. `tests/security/attachments.php`
14. `tests/security/pageblocks.php`
15. `tests/ui/notifications-review.md`

### Grenzen und separater Folgeschritt

Die Sperre gilt pro geöffneter Seite und laufendem Browserrequest. Sie bietet keine Idempotenz über Tabs oder Geräte hinweg und schützt nach einem Timeout nicht vor einer manuellen Wiederholung, während der Server möglicherweise noch arbeitet. Deshalb bleibt der unklare Status ausdrücklich als Prüfhilfe stehen. Bei einem unterbrochenen Anhang-Batch kann der Server bereits einzelne Dateien verarbeitet haben; ohne bestätigte Antwort entfernt der Browser keine Anhänge. Die bisherigen serverseitigen Regeln werden dadurch nicht erweitert.

Frontend und JSON-Erfolgsbestätigung des Anhangcontrollers sollten gemeinsam ausgeliefert werden. Ein alter Frontend-Aufrufer, der den Antwortinhalt ignoriert, bleibt kompatibel; das neue Frontend behandelt eine alte leere Antwort sicherheitshalber als unklar. Die physische Navigation, echte Serverlaufzeiten und die manuelle Screenreader-/Gerätebedienung wurden nicht mit produktiven Endpunkten geprüft.

Phase 4.1 ist abgeschlossen. Phase 4.2 ist im folgenden Abschnitt gesondert dokumentiert.

Commit-Vorschlag für den abgeschlossenen Schritt:

`unify loading states for delete and cancel actions`

## Phase 4.2 – Ausdrückliches Erstellen und Speichern

### Untersuchte Aufrufstellen und Antwortverträge

| Stelle | Tatsächlicher Vertrag / Umsetzung |
| --- | --- |
| `createEntity()` / Toolbar `multi-add` | Einziger produktiver Aufrufer ist die gemeinsame Toolbar-Aktion. Sie übergibt nun den betätigten Button als zusätzliches optionales Argument. Kontakte und weitere MultiEntities liefern eine HTML-Zeile; Positionspreisregeln liefern JSON mit `ok`, `calculation` und `reloadPositions`. Beide Formen bleiben unterstützt. |
| `DEEC_Controller_MultiEntityAction::addAction()` und `DEEC_Form::renderMultiRow()` | Nach Transaktion wird eine einzelne `.dw-multiform__item`-Zeile mit ID, Modul und Controller ausgegeben; Fehler sind JSON mit `ok: false`. Die umgebende aktuelle DEEC-MultiForm trägt `data-controller` am inneren `.dw-multiform`, nicht am äußeren Kontext. Dies wird bei der Zielsuche berücksichtigt. |
| `add(data, params)` | Produktiver synchroner Aufrufer ist der Inline-Change-Handler für `data-action="add"`. Der rohe Rückgabewert bleibt erhalten, einschließlich negativer Antworten und HTML-Strings. Bei Transportfehlern bleibt er `null`. Das Inline-Add-Merkmal wird erst nach bestätigter Erstellung entfernt. |
| `add(data)` | Asynchroner Legacy-Zweig, aufgerufen vom sonstigen `save()`-Zweig. Sein Rückgabewert bleibt `undefined`. Die vorhandene Payload-Ergänzung und Zielroute bleiben erhalten. Alte `button.add`-/`button.addMulti`-Einfügepunkte bleiben zusätzlich zu aktuellen MultiForm-Listen unterstützt. |
| `Items_TagController::addAction()` | Bei ausgewähltem Tag wird ein gespeicherter Tagentity-Datensatz ohne `ok` zurückgegeben; bei neuem Tag eine ganze MultiForm. Die spezifische JSON-Ausnahme wird nur für `items/tag` mit gültiger ID, positivem Tag, korrektem Item-Kontext und passendem Parent akzeptiert. Die HTML-Hülle wird auf ihre einzelne erwartete Zeile reduziert. |
| `save()` bei `action === 'add'` | Bisherige native Übermittlung bleibt nativ. Legacy-Pflichtfelder und Browservalidierung werden vor dem Ladezustand geprüft. Der Dirty-State bleibt bis zum Dokumentwechsel erhalten; nur die Unsaved-Warnung wird während beabsichtigter nativer Übermittlung unterdrückt. |
| Native `.add form`-Übermittlung | Der bisherige pauschale Dirty-Reset wurde durch eine lokale Sperre des Hauptformulars (`form.id === controller`) ersetzt. Bei einem Submitter mit Namen wird dessen Wert trotz Deaktivierung erhalten. Nach Rückkehr über den Browsercache stellt `pageshow` den Zustand wieder her. Formularabbrüche durch nachfolgende Handler sowie synchrone Submit-Ausnahmen räumen auf. Kein allgemeiner AJAX-Formularersatz. |
| `save()` bei `action === 'password'` | `Users_UserController::passwordAction()` liefert bei Erfolg `saved: true`, bei Sperre `saved: false` und bei Validierungsfehlern teils nur einen übersetzten Meldungstext. Nur bestätigter Erfolg setzt Dirty zurück und schließt den Dialog. Pflichtfelder und Passwortbestätigung werden vor dem Request geprüft. |
| Passwort-Synchronität | `save()` hatte und behält keinen direkten Rückgabewert. Die tatsächlichen Aufrufer (`password.phtml` sowie ältere programmgesteuerte Aufrufe in `js/settings.js`) benötigen kein synchrones Passwortresultat. Nur dieser einzelne Request wurde asynchron, damit der Ladezustand gezeichnet werden kann und weitere Klicks während des Requests unterdrückt werden. |
| `saveEntity()` / `edit()` | `saveEntity()` delegiert weiterhin an das synchrone `edit()` und gibt dessen Ergebnis zurück. Ohne zusätzliches Buttonargument bleibt es der bestehende Auto-Save-Pfad. Mit Buttonargument gibt es eine lokale Requestsperre, strikte Erfolgsprüfung und übersetzte Rückmeldung. |
| Weitere `edit()`-Aufrufer | Feldänderungen, Inline-Bearbeitung, Dokumentnotizen, TinyMCE und `js/contacts.js::applyContact()` benötigen ihre bisherigen synchronen Ergebnisse. Sie erhalten keine neue globale Sperre oder Erfolgsmeldung. `js/trash.js` enthält eigene gleichnamige Legacy-Funktionen und wird nicht verändert. |
| `DEEC_Controller_Action`, Kontakt-/Dokument-Save-Services | Native Create-Aktionen können direkt auf Edit-Seiten weiterleiten. Native POST-Saves validieren serverseitig und verwenden Redirect/Flash. AJAX-Saves bestätigen `ok: true`; Fehler enthalten `ok: false` und optional `errors`. Backend, Berechtigungen, Transaktionen und Geschäftsregeln bleiben unverändert. |
| Toolbar / MultiForm / CSS | Vorhandene Zielattribute und `setButtonLoading()` reichen aus. Die Toolbar bekommt eine `save`-Dispatch-Aktion; alte Save-Buttons ohne `data-action` werden innerhalb `.dw-toolbar` bedient. Am Passwortbutton werden bestehende Buttonklassen und `save(this)` verwendet. Keine CSS-Erweiterung erforderlich. |
| Variantenformular | Da der pauschale `.add form`-Dirty-Reset entfällt, setzt die bestehende Varianten-Erstellung Dirty ausdrücklich erst nach bestätigtem `ok: true` vor ihrer unveränderten Navigation zurück. Der Requestpfad wird sonst nicht umgebaut. |

### Architektur, Dirty-State und Fehlerbehandlung

`beginActionRequest()` bleibt der gemeinsame Sperrmechanismus. Nur wenn ein Bestätigungstext übergeben wurde, erscheint ein Dialog; vorhandene Lösch-/Stornierungsaufrufe übergeben ihn unverändert. Erstellen, Speichern und native Übermittlung rufen denselben Mechanismus ohne Bestätigung auf. `setButtonLoading()` bleibt die einzige Buttonkomponente. Die Sperren werden nach Ziel und Parent-Kontext beziehungsweise Datensatz unterschieden; unabhängige Aktionen und Hintergrundrequests bleiben möglich.

`createEntity()` und beide `add()`-Zweige prüfen das Resultat vor jedem Dirty-Reset. JSON benötigt einen ausdrücklichen positiven Status; beobachtete Legacy-Tag-Datensätze werden nur im oben beschriebenen Sondervertrag akzeptiert. Für HTML prüft `getCreateResponse()` eine einzelne erwartete MultiForm-Zeile mit positiver ID und passendem Modul/Controller. Allgemeine Seiten, Login-/Fehlerseiten, leere oder fehlerhafte Antworten, mehrere Wurzelelemente und falsche Kontexte bestätigen keine Erstellung. Skripte werden beim Parsen nicht übernommen. Dies ersetzt keine allgemeine HTML-Sanitization: korrekt zugeordnete Formularfragmente stammen weiterhin vom vertrauenswürdigen Serverrenderer.

Bestätigte HTML-Zeilen werden in die bestehende Liste eingefügt, fokussiert und anhand ihrer ID vor doppelter Einfügung geschützt. JSON-Resultate behalten Berechnung und Positionsaktualisierung über `applySaveResponse()`. Listenaktualisierungen behalten ihre Rückmeldung über den vorhandenen Such-Callback. Explizites `saveEntity(..., button)` akzeptiert nur positive `ok`-/`saved`-Bestätigung ohne negativen Status oder Validierungsfehler. Fehlgeschlagene oder unklare Antworten verändern den Dirty-State nicht.

`showSaveError()` zeigt übersetzte allgemeine Texte für Erstellen, Speichern und Validierung. Technische Servertexte werden in diesen expliziten Pfaden nicht ungefiltert ausgegeben. Netzwerk-/Parserfehler, uneindeutige HTTP-Fehler und unerwartete Antworten erhalten den vorhandenen Hinweis auf einen unklaren Aktionsstatus. Es gibt keine automatische Wiederholung. Fehler bleiben über die bestehende Meldungskomponente sichtbar, bis sie geschlossen oder durch einen bestätigten Erfolg ersetzt werden. Bei Passwort-Dialogschließung und nativer Navigation gibt es keine zusätzliche Erfolgsmeldung.

Die vorhandene synchrone Reihenfolge und Rückgabewerte von `add(..., params)`, `saveEntity()` und `edit()` bleiben erhalten. Insbesondere werden synchrone Saves vor Dokumentberechnung und Finalisierung nicht pauschal asynchron. Ohne explizites Buttonargument bleiben Verhalten und Rückgabevertrag von Auto-Save erhalten; dessen bestehende allgemeine Antwortbehandlung wird hier nicht global verschärft.

### Tests in Phase 4.2

Alle Requests, Passwortaktionen und nativen Formularübermittlungen in den Browser-Fixtures werden abgefangen. PHP-Tests verwenden die vorhandenen isolierten Adapter. Keine produktiven Speicheraktionen, Datenbankänderungen oder echten E-Mails.

| Ausgeführte Prüfung | Ergebnis |
| --- | --- |
| `python3 tests/ui/notifications.py` | Jeweils **388 Prüfungen** bei 1280 px und 390 px bestanden. Einschließlich der bisherigen Phasen und neuer Tests in Deutsch/Englisch: Mehrfachklick/Reentry, unabhängige Aktionen, Button-/ARIA-Wiederherstellung, HTML und JSON einschließlich JSON-Text/Legacy-Tag-Zeilen, fehlende/negative/ungültige Resultate, Validierung, Netzwerk-/HTTP-/Timeout-/Parserfehler, Dirty-State, keine doppelten MultiForm-Zeilen, Fokus, Berechnungsweitergabe, synchrone Rückgaben, Auto-Save ohne Buttonsperre, native Übermittlung ohne AJAX, Submitter-Wert, Browsercache-Rückkehr, Submit-Ausnahme sowie Passwortdialog nur nach bestätigtem Erfolg. |
| `php tests/security/notifications.php` | **76 Prüfungen** bestanden, einschließlich 23 Browser-Übersetzungen je Sprache und Erfolgseinstufung von `MESSAGES_CREATED`. |
| `php tests/security/profile-save.php` | **100 Profil-/Passwortprüfungen** bestanden. |
| `php tests/security/user-profile.php` | **126 Profil-/Passwortprüfungen** bestanden. |
| `php tests/security/contact-data.php` | **39 Prüfungen** mandantensicherer Kontaktdaten bestanden. |
| `php tests/security/document-edits.php` | **7400 Prüfungen** bestanden. |
| `php tests/security/permissions.php` | **369 Prüfungen** bestanden. |
| `php tests/security/tenant-access.php` | **268 Prüfungen** bestanden. |
| `php tests/documents/lifecycle.php` | **879 Prüfungen** bestanden. |
| `php tests/documents/calculation.php` | **647 Berechnungsprüfungen** bestanden. |
| `python3 tests/documents/calculation-ui.py` | Dokument-/Inline-Notiz-Auto-Save und **26 Berechnungsprüfungen** bestanden. |
| `php -l` | Alle **5** geänderten PHP-/PHTML-Dateien ohne Syntaxfehler. |
| `git diff --check` | Ohne Befund. |

### Geänderte Dateien in Phase 4.2

1. `js/global.js`
2. `application/modules/users/views/scripts/user/password.phtml`
3. `application/views/helpers/Messages.php`
4. `languages/de_DE/default/messages.php`
5. `languages/en_US/default/messages.php`
6. `tests/security/notifications.php`
7. `tests/ui/notifications.html`
8. `tests/ui/notifications-review.md`

### Grenzen und separater Folgeschritt

Die Sperren schützen laufende Requests in derselben Seite; sie sind keine serverseitige Idempotenz über Tabs, Geräte oder manuelle Wiederholungen nach einem Timeout hinweg. Ein weiterer ausdrücklich gestarteter Vorgang nach abgeschlossenem Request bleibt möglich. Der bestehende globale Dirty-State besitzt keine Revision pro Feld oder Request; eine bestätigte Speicherung setzt ihn weiterhin nach bisherigem Vertrag zurück.

Bei synchronen XHR-Aufrufen kann der Browser den gesetzten Ladezustand vor Abschluss nicht zuverlässig zeichnen. Auch jQuerys Timeoutoption begrenzt synchrone XHRs nicht verlässlich. Die Option wird gesetzt und Fehlerpfade räumen auf, die synchronen Browsergrenzen bleiben aber zugunsten der erforderlichen Rückgabekompatibilität bestehen. Der asynchrone Passwortpfad und die asynchrone MultiForm-Erstellung können ihren Ladezustand unmittelbar darstellen.

Native Navigation hat kein mit AJAX vergleichbares Abschlussereignis. Die Sperre endet beim Dokumentwechsel beziehungsweise nach `pageshow`; ein manuell im Browser abgebrochener Ladevorgang ohne neues Dokument kann deshalb eine erneute Ansicht erfordern. Die serverseitige Formularvalidierung und Redirects wurden nicht gegen produktive Endpunkte ausgelöst. Manuelle Screenreader-/Geräteabnahme bleibt offen.

Phase 4.2 ist abgeschlossen. Phase 4.3 ist im folgenden Abschnitt gesondert dokumentiert.

Commit-Vorschlag:

`unify loading states for create and save actions`

## Phase 4.3 – Dokument- und Toolbar-Aktionen

### Geprüfte Aufrufstellen und tatsächliche Verträge

| Stelle | Vertrag / Umsetzung |
| --- | --- |
| `previewPdf()` | POST an die bestehende `/preview/id/.../templateid/...`-Route; JSON mit `ok: true`, PDF-URL und optional lokalisierter Validierung. Explizite Buttons übergeben jetzt `this`; automatische Aufrufe bleiben ohne Buttonsperre. |
| Automatische Vorschau | Bestehender Change-Handler für `#templateid`/`#language` und Lazy-Load über `data-tab-load="previewPdf"` in den Verkaufs-/Einkaufs-Edit-Views. Reihenfolge und synchrone vorgelagerte Speicheroperationen bleiben erhalten. |
| `DocumentAction::previewAction()` | AJAX erzeugt eine Cache-Datei mit `overwrite: true`; die normale Route kann eine Inline-PDF liefern. Es wird kein `finalize`-Flag gesetzt. Backend unverändert. |
| `updateDocumentValidation()` | Bestehende Zusammenfassung und Feld-/Tablinks bleiben. Status, Titel und Listenform werden geprüft; Texte bleiben escaped. Ungültige Strukturen überschreiben die bestehende Zusammenfassung nicht. |
| `[data-document-finalize]` / `DocumentAction::saveAction()` | Bestehender AJAX-POST. Nur `ok === true` mit sicherer Weiterleitung führt zur Zielansicht. Validierungsfehler liefern `ok: false`, HTTP 422 und lokalisierte Details. Erfolg behält die vorhandene Flashmeldung `MESSAGES_SAVED`. |
| `sales` / `purchases` `Finish.php` | Expliziter Vorschaubutton, vorhandene Finalisierung und native Downloadlinks. Vorschau bekommt den Buttonkontext, Downloadlinks erhalten ein Attribut zur kurzen Klicksperre. `#output` / `.dw-finish__preview` und `.dw-document-validation` bleiben bestehen. |
| `sales` / `purchases` `Actions.php` | Bestehende Links zur Generierung erhalten vorhandene Buttonklassen und `data-document-generate`. Href, Zieldokumenttyp, Navigation und Übersetzungsaufrufe bleiben unverändert. |
| `DocumentAction::generateAction()` | Bestehende navigierbare GET-Route: Quellbeleg laden, Zielkonfiguration prüfen, neuen Beleg erstellen, Positionen kopieren, `MESSAGES_DOCUMENT_SUCCESFULLY_GENERATED` einreihen und auf dessen Edit-Seite weiterleiten. Keine Umstellung auf AJAX oder POST. |
| `DocumentAction::downloadAction()` | Erzeugt die Cache-PDF ohne Finalisierung und liefert eine native PDF-Antwort mit `Content-Disposition: attachment`. Kein Blob-AJAX und keine neue Erfolgsbehauptung. |
| `DewawiToolbar.actions.pdf` | Weiterhin `window.open(..., '_blank')`; dieselbe kurze Klicksperre wie bei nativen Finish-Downloads. Blockierte Popups geben die Sperre sofort frei. |
| Toolbar-/Berechtigungsstruktur | `sales/forms/Toolbar.php`, `purchases/forms/Toolbar.php`, zugehörige Edit-Views, `Application_Plugin_Acl`, `Application_Plugin_State` und `DEEC_Permission` wurden geprüft. Rendering der Auswahl und vorhandene serverseitige Prüfungen werden nicht verändert. |
| PDF-/Berechnungsstruktur | `DocumentAction::generatePdfDocument()` und `DEEC_Pdf` verwenden vorhandene Finalisierungs-, Berechnungs-, Cache- und Dateipfade. Fachliche Regeln, Steuern, Summen und Dokumentstatus bleiben unverändert. |

### Wiederverwendete Komponenten und Verhalten

Alle Ladezustände verwenden `setButtonLoading()`, alle Sperren den bestehenden `beginActionRequest()` ohne Bestätigungsdialog. `pushMessages()` gibt zusätzlich das erzeugte Alert-Element zurück, damit ein abgeschlossener Request nur seinen eigenen Fortschrittshinweis entfernt. Der bisherige Aufrufvertrag bleibt nutzbar. Meldungen stehen in `#messages`; der PDF-Container wird nur durch bestätigte Vorschauergebnisse ersetzt.

Explizite Vorschauen sperren dieselbe Aktion am Dokument gegen parallele Ausführung. Automatische Vorschauwechsel bleiben möglich und bekommen keine globale Bediensperre. Eine aufsteigende Requestnummer sorgt dafür, dass ältere Erfolge und Fehler weder die aktuelle PDF noch die Validierungsdarstellung überschreiben. Nach Abschluss wird der ursprüngliche Buttonzustand wiederhergestellt. Der Timeout beträgt bei den asynchronen Dokumentrequests 120 Sekunden.

PDF-URL und Weiterleitungsadresse werden auf einen nicht leeren String, lokale Root-Pfade beziehungsweise gleiche HTTP(S)-Origin und unzulässige Zeichen geprüft. Externe URLs, Protokoll-relative Adressen, Credentials und ausführbare Schemes werden nicht verwendet. Die PDF wird mit einem echten DOM-Element und `src`-Attribut eingesetzt; es gibt keine URL-Interpolation in einen HTML-String. Die Auflösung von Sperrschlüsseln verwendet die Dokumentbasis und funktioniert damit auch im mobilen srcdoc-Fixture.

Finalisierung sperrt identische Zielrouten auch bei mehreren auslösenden Links. Nur bestätigter Erfolg mit gültiger Weiterleitung navigiert über die vorhandene `setLocation()`-Funktion. Es wird keine redundante Erfolgsmeldung vor dem vorhandenen Flash erzeugt. Fachliche Ablehnung wird allgemein übersetzt; validierte Fehlerdetails bleiben direkt am Dokument verlinkt. Bei Netzwerk-/Timeout-/Parserfehlern, uneindeutigen HTTP-Fehlern oder unbrauchbarer Erfolgsantwort erscheint ausdrücklich: „Der Dokumentstatus konnte nicht bestätigt werden. Bitte prüfen Sie den aktuellen Dokumentstatus, bevor Sie die Aktion wiederholen.“ Es erfolgt keine automatische Wiederholung.

Native Generierungslinks behalten ihre Standardnavigation. Identische Links werden während der Navigation über dieselbe Requestsperre geschützt, unterschiedliche Ziele bleiben bedienbar. Ein Fortschrittshinweis behauptet keinen Erfolg. Bei abgebrochener Standardaktion durch einen anderen Eventhandler wird aufgeräumt; `pageshow` stellt Zustände nach Rückkehr über den Browsercache wieder her. Modifier-/Mittelklicks behalten ihr bisheriges Browserverhalten.

Native Downloads behalten `target="_blank"` beziehungsweise `window.open()`. Die lokale Klicksperre endet nach einer Sekunde, unabhängig vom tatsächlichen Downloadende. Dieser Timer bestätigt ausschließlich das Ende der Klicksperre; er zeigt keine Erfolgsmeldung. `pageshow` und ein blockiertes Popup können ebenfalls freigeben. XRechnung und seine Blob-Verarbeitung wurden nicht verändert.

### Tests in Phase 4.3

Alle Anwendungsrequests, Buchungen, Generierungen und Download-Navigationen wurden in den Browser-Fixtures abgefangen. PHP-Tests verwenden In-Memory-Adapter und temporäre Dateien. Es wurden keine produktiven Belege erstellt, gebucht oder finalisiert und keine echten E-Mails versendet.

| Ausgeführte Prüfung | Ergebnis |
| --- | --- |
| `python3 tests/ui/notifications.py` | Jeweils **606 Prüfungen bei 1280 px und 390 px** bestanden. Enthält sämtliche bisherigen E-Mail-, Lösch-, Erstell- und Speicherprüfungen sowie neue Dokumentfälle in Deutsch/Englisch für Verkauf und Einkauf: Einzel-/Mehrfachklick, Fortschritt, automatische Template-/Sprachwechsel, verspätete Erfolgs-/Fehlerantworten, sichere URLs, DOM-Attribute, unveränderter PDF-Container bei Meldungen, Validierungsdetails, strikte Finalisierungsbestätigung, Timeout/Netzwerk/Parser/HTTP, unabhängige Aktionen, native Generierungsnavigation, Modifier-Klicks, Browsercache-Rückkehr und Freigabe nativer Download-Klicksperren. |
| `php tests/security/notifications.php` | **92 allgemeine Prüfungen**, zusätzlich **60 Verkaufs- und 24 Einkaufsprüfungen** an tatsächlich gerenderten Finish-/Actions-Helfern bestanden. Die gleichnamigen ZF1-Modulhelfer werden in getrennten PHP-Prozessen geladen; 31 Browser-Übersetzungen sind je Sprache vollständig. |
| `python3 tests/documents/calculation-ui.py` | Dokument-/Inline-Notiz-Auto-Save und **26 Berechnungsprüfungen** bestanden. |
| `php tests/documents/lifecycle.php` | **879 Prüfungen** bestanden. |
| `php tests/security/document-edits.php` | **7400 Prüfungen** bestanden. |
| `php tests/documents/calculation.php` | **647 Prüfungen** bestanden. |
| `php tests/documents/pdf.php` | **134 PDF-Prüfungen** bestanden. |
| `php tests/documents/pdf-totals.php` | **170 PDF-/Summenprüfungen** bestanden. |
| `php tests/security/permissions.php` | **369 Prüfungen** bestanden. |
| `php tests/security/tenant-access.php` | **268 Prüfungen** bestanden. |
| `php tests/security/attachments.php` | **224 Anhangprüfungen** und die im Setup enthaltenen **364 Quote-E-Mail-Prüfungen** bestanden. |
| `php tests/security/pageblocks.php` | **74 Prüfungen** bestanden. |
| `php tests/security/profile-save.php` | **100 Profil-/Passwortprüfungen** bestanden. |
| `php -l` | Alle **8** geänderten PHP-Dateien ohne Syntaxfehler. |
| `git diff --check` | Ohne Befund. |

Der vorhandene Browser-Runner wird weiterverwendet; er lädt jetzt auch die bestehenden Dokumentübersetzungen. Die einsekündigen Download-Timer werden mit einer kontrollierten Fixture-Uhr geprüft. Vorschau-Iframes sind echte DOM-Elemente mit isolierten lokalen Testadressen; reale PDF-Darstellung und Downloadabschluss werden dadurch nicht behauptet. Der mobile iframe übergibt sein Ergebnis synchron an die umgebende Fixture-Seite und meldet dort auch JavaScript-Fehler. Ein anfänglicher mobiler Testhänger wurde auf fehlende `type="button"`-Attribute in den neu geschriebenen Fixture-Buttons zurückgeführt und behoben; die Produktionshelfer hatten diese Attribute bereits korrekt gesetzt.

### Geänderte Dateien in Phase 4.3

1. `js/global.js`
2. `application/modules/sales/views/helpers/Finish.php`
3. `application/modules/purchases/views/helpers/Finish.php`
4. `application/modules/sales/views/helpers/Actions.php`
5. `application/modules/purchases/views/helpers/Actions.php`
6. `application/views/helpers/Messages.php`
7. `languages/de_DE/default/document.php`
8. `languages/en_US/default/document.php`
9. `tests/security/notifications.php`
10. `tests/ui/notifications.html`
11. `tests/ui/notifications.py`
12. `tests/ui/notifications-review.md`

Keine Backend-, Datenbank-, Berechnungs-, Toolbar-Formular- oder CSS-Änderungen waren erforderlich.

### Risiken und separate Sicherheitsmaßnahmen

`generateAction()` verändert den Zustand über GET und besitzt in der untersuchten Methode keine HTTP-Methodenbindung, CSRF-Prüfung oder serverseitige Idempotenzsicherung. Der Klickschutz ist ausschließlich UX-Schutz derselben geöffneten Seite. Neue Tabs, Modifier-Klicks, Wiederholung der URL, Browser-Reload, Prefetching oder andere Clients können zusätzliche Generierungen auslösen. Der vorhandene Zieltyp-Vertrag und die serverseitige Routing-/Mandantenlogik bleiben erhalten. Als **separater Sicherheitsauftrag** sind POST-/CSRF-Vertrag, explizite Prüfung der Zielberechtigungen, Transaktionsumfang und serverseitiger Idempotenzschlüssel mit Wiederverwendung des bereits erzeugten Folgedokuments zu analysieren und zu testen. Auch `saveAction()` besitzt in der untersuchten Methode keine POST-Bindung; der neue UI-Handler verwendet weiterhin POST, direkte GET-Aufrufe bleiben aber serverseitig möglich. Die Methoden-/CSRF-Prüfung dieser Route gehört ebenfalls in den separaten Sicherheitsauftrag. Diese Umstellungen wurden ausdrücklich nicht mit Phase 4.3 vermischt.

Eine native Navigation besitzt kein zuverlässiges Fehler-/Abbruchereignis für den endgültigen Serverzustand. Bei einer im Browser abgebrochenen Generierungsnavigation ohne `pageshow` kann die lokale Sperre bestehen bleiben. Es gibt absichtlich keine zeitgesteuerte Generierungswiederholung oder falsche Erfolgsbestätigung; vor einer manuellen Wiederholung muss der Belegbestand geprüft werden. Insbesondere darf das erneute Laden einer Generierungs-GET-URL nicht als sichere Statusprüfung verstanden werden.

Ein AJAX-Timeout beweist keinen Abbruch der serverseitigen Finalisierung. Seitenübergreifende Idempotenz sowie die fachliche Prüfung des aktuellen Status bleiben serverseitige Aufgaben. Die vorhandene Finalisierungslogik und deren Lifecycle-Regressionen wurden nicht geändert.

Die Vorschau-Reihenfolgenprüfung schützt JSON-Ergebnis und DOM. Die bestehende Cache-Datei verwendet weiterhin einen gemeinsamen Dateinamen und `overwrite: true`; konkurrierende serverseitige Dateischreibvorgänge werden durch einen ignorierten alten AJAX-Callback nicht rückgängig gemacht. Eine vollständig versionierte Vorschauablage wäre eine getrennte Backend-/Cache-Änderung. Auch das tatsächliche Laden der PDF-Datei kann nach erfolgreicher JSON-Antwort noch fehlschlagen.

Manuelle Screenreader-/Gerätebedienung und echte produktive Dokumentnavigationen wurden nicht durchgeführt. **Phase 4.4 wurde nicht begonnen und benötigt einen separaten Auftrag.**

Commit-Vorschlag:

`unify loading states for document and toolbar actions`

## Priorisierte Folgeschritte nach Phase 4.3 (noch nicht umgesetzt)

| Priorität | Bereich und konkrete Dateien | Nächster begrenzter Schritt / notwendige Regressionen |
| --- | --- | --- |
| P2 | Auto-Save und Positionen: `js/global.js` (`edit`, `editPosition`, `addPosition`, `addSet`, `deletePosition`, `deleteSet`), `application/views/helpers/ToolbarPositions.php` | Status am betroffenen Feld bzw. Positionsbereich; keine globale Sperre. Reihenfolge synchroner Saves vor Finalisierung beibehalten; Feldfehler und Summenregressionen prüfen |
| P2 | Kontakte und modale Dialoge: `js/contacts.js`, `js/global.js` (`modalWindow`, `modalWindowClose`) | Bestehende lokale `disabled`-Behandlung durch gemeinsame Zustandsverwaltung ergänzen; Fokus, Validierung und Verhalten nach erneutem Öffnen prüfen |
| P2 | Artikel und Listenaktionen: `application/modules/items/views/scripts/item/index.phtml`, `application/modules/items/views/scripts/item/edit.phtml`, `application/modules/items/forms/Toolbar.php`, `js/global.js` | Gemeinsame Toolbar-Aktionen nach deren P1-Migration übernehmen; Filter, Auswahl und Mehrfachbearbeitung erhalten |
| P2 | Upload und Download: `application/modules/contacts/controllers/AttachmentController.php`, `application/views/scripts/partials/email-messages.phtml`, `application/views/scripts/partials/downloads.phtml`, `js/filemanager.js`, `js/global.js` (XRechnung-Download) | Zustände in bestehendem Upload-iframe und bei Blob-Downloads vereinheitlichen; Dateivalidierung und Downloadrechte prüfen. Native Downloads haben kein verlässliches Browserereignis für das Ende des Downloads |
| P3 | Kampagnen: `application/modules/campaigns/views/helpers/Errors.php`, `application/modules/campaigns/models/List/Recipients.php`, `js/global.js` (`getCampaignRecipients`, `getCampaignErrors`) | Technische Statusdetails und Fehler beim Aktualisieren der Übersichten separat vereinheitlichen; Bounce-/Complaint-Regeln erhalten |
| P3 | Shop-Oberfläche: `application/layouts/scripts/site.phtml`, `css/shop.css`, `js/shops.js` | Eigenes Shop-Design und Bootstrap-Verwendung separat prüfen, bevor das interne `ui.css` übernommen wird |

## Ausgeführte Prüfungen der Phasen 1–3

Alle Tests verwenden abgefangene AJAX-Aufrufe, Render-Fixtures oder bestehende In-Memory-Adapter. Es wurden keine echten E-Mails versendet und keine produktiven Datenbanken verwendet.

- `python3 tests/ui/notifications.py`: jeweils 80 Prüfungen bei 1280 px und 390 px bestanden; Deutsch und Englisch, Mehrfachklick, Send-/Resend-Sperre, Statuswechsel, negative und unklare Antworten, Timeout-/Parser-/Netzwerkfehler, Buttonwiederherstellung, Hintergrundrequest, Dokumentkontext, Kampagnenaktualisierung, Finalisierung, XSS, ARIA-Attribute, Textumbruch und berechneter Kontrast von mindestens 4,5:1.
- `php tests/security/notifications.php`: 56 Prüfungen bestanden; PHP-Meldungshelper, Flash-Gruppierung, Escaping, vier Schweregrade, Übersetzungskatalog und gerenderte E-Mail-Historie.
- `php tests/security/quote-email.php`: 364 Prüfungen bestanden; Empfänger, Berechtigungen, Mandantenfreigaben, erneuter Versand und AJAX ohne zusätzliche Flashmeldungen.
- `php tests/security/email-history.php`: 233 Prüfungen bestanden.
- `php tests/security/smtp-routing.php`: 15 Prüfungen bestanden.
- `php tests/security/shop-email-recipients.php`: 32 Prüfungen bestanden.
- `php tests/documents/lifecycle.php`: 879 Prüfungen bestanden.
- `python3 tests/documents/calculation-ui.py`: Notiz-Auto-Save bestanden, 26 Berechnungsprüfungen bestanden.
- `php -l` für alle geänderten und neu hinzugefügten PHP-/PHTML-Dateien; `git diff --check`.

Chromium benötigt in dieser Umgebung eine Ausführung außerhalb der Sandbox. Für 390 px verwendet der neue Runner einen echten iframe-Viewport, da Chromium die Fensterbreite sonst auf mindestens 500 px begrenzt. Nicht durchgeführt: echte SMTP-Verbindungen, manuelle Screenreader-Abnahme, manuelle Gerätetests oder vollständige End-to-End-Prüfungen aller Module.

## Geänderte Dateien der Phasen 1–3

- Zentrale Komponenten: `js/global.js`, `css/ui.css`, `application/views/helpers/Messages.php`.
- Layout-Konfiguration: `application/layouts/scripts/layout.phtml`, `application/layouts/scripts/plain.phtml`.
- E-Mail-Integration: `application/views/scripts/partials/email-messages.phtml`, `application/modules/contacts/views/scripts/email/index.phtml`, `application/controllers/helpers/Email.php`.
- Bestehende weitere Aufrufer: `application/modules/campaigns/models/List/Recipients.php`, `application/modules/campaigns/views/helpers/Errors.php`, `js/shops.js`.
- Übersetzungen: `languages/de_DE/default/messages.php`, `languages/en_US/default/messages.php`.
- Tests und Dokumentation: `tests/security/quote-email.php`, `tests/security/notifications.php`, `tests/ui/notifications.html`, `tests/ui/notifications.py`, `tests/ui/notifications-review.md`.

## Commit-Vorschläge der Phasen 1–3

Die Änderungen bilden wegen der gemeinsam verwendeten Komponenten einen zusammenhängenden Implementierungsschritt:

`unify notifications and ajax loading states`

Nachgelagerte, jeweils separat zu implementierende Schritte:

- `unify loading states for document and toolbar actions`
- `unify autosave and upload feedback`

Es wurden keine Commits erstellt und keine Änderungen gepusht.
