# Einheitliche Meldungen und AJAX-Ladezustände

## Umfang und Bestandsaufnahme

Die ersten drei Phasen sind umgesetzt: gemeinsame Meldungskomponenten und die vollständige Integration des interaktiven E-Mail-Versands. Phase 4.1 erweitert diese Komponenten um Lösch- und Stornierungsaktionen. Weitere Aktionen werden separat migriert.

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

**Phase 4.2 wurde nicht begonnen und benötigt einen separaten Auftrag.** Die weiteren Migrationspunkte unten sind ausschließlich Planung.

Commit-Vorschlag für den abgeschlossenen Schritt:

`unify loading states for delete and cancel actions`

## Priorisierte Folgeschritte nach Phase 4.1 (noch nicht umgesetzt)

| Priorität | Bereich und konkrete Dateien | Nächster begrenzter Schritt / notwendige Regressionen |
| --- | --- | --- |
| P1 | Erstellen und explizites Speichern: `js/global.js` (`save`, `createEntity`, `saveEntity`), `library/DEEC/Controller/Action.php` | Lokale Rückmeldung und Requestsperre für ausdrückliche Aktionen; native Formularvalidierung und Redirects erhalten |
| P1 | Verkauf/Einkauf und Dokumenterstellung: `js/global.js`, `library/DEEC/Controller/DocumentAction.php`, `application/modules/sales/forms/Toolbar.php`, `application/modules/purchases/forms/Toolbar.php`, deren `invoice`-/`purchaseorder`-Views | Generieren und weitere PDF-Aktionen integrieren; vorhandene Validierungszusammenfassung weiterverwenden; Lifecycle-, Rechte- und Berechnungstests ausführen |
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
