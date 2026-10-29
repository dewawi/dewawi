# Einheitliche Meldungen und AJAX-Ladezustände

## Umfang und Bestandsaufnahme

Die ersten drei Phasen sind umgesetzt: gemeinsame Meldungskomponenten und die vollständige Integration des interaktiven E-Mail-Versands. Weitere Module erhalten bereits die sichere zentrale Meldungsdarstellung; ihre Aktionen werden separat migriert.

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

## Priorisierte Folgeschritte (Phase 4, noch nicht umgesetzt)

| Priorität | Bereich und konkrete Dateien | Nächster begrenzter Schritt / notwendige Regressionen |
| --- | --- | --- |
| P1 | Löschen und Stornieren: `js/global.js` (`trash`, `deleteEntity`, Toolbar-Aktion `cancel`), `application/views/helpers/Toolbar.php`, `library/DEEC/Controller/Action.php` | Betätigte Buttons und jeweilige Aktion sperren, Timeout/Fehler aufräumen; Bestätigungen, Berechtigungen, Mandantentrennung und Listenaktualisierung prüfen |
| P1 | Erstellen und explizites Speichern: `js/global.js` (`save`, `createEntity`, `saveEntity`), `library/DEEC/Controller/Action.php` | Lokale Rückmeldung und Requestsperre für ausdrückliche Aktionen; native Formularvalidierung und Redirects erhalten |
| P1 | Verkauf/Einkauf und Dokumenterstellung: `js/global.js`, `library/DEEC/Controller/DocumentAction.php`, `application/modules/sales/forms/Toolbar.php`, `application/modules/purchases/forms/Toolbar.php`, deren `invoice`-/`purchaseorder`-Views | Generieren und weitere PDF-Aktionen integrieren; vorhandene Validierungszusammenfassung weiterverwenden; Lifecycle-, Rechte- und Berechnungstests ausführen |
| P2 | Auto-Save und Positionen: `js/global.js` (`edit`, `editPosition`, `addPosition`, `addSet`, `deletePosition`, `deleteSet`), `application/views/helpers/ToolbarPositions.php` | Status am betroffenen Feld bzw. Positionsbereich; keine globale Sperre. Reihenfolge synchroner Saves vor Finalisierung beibehalten; Feldfehler und Summenregressionen prüfen |
| P2 | Kontakte und modale Dialoge: `js/contacts.js`, `js/global.js` (`modalWindow`, `modalWindowClose`) | Bestehende lokale `disabled`-Behandlung durch gemeinsame Zustandsverwaltung ergänzen; Fokus, Validierung und Verhalten nach erneutem Öffnen prüfen |
| P2 | Artikel und Listenaktionen: `application/modules/items/views/scripts/item/index.phtml`, `application/modules/items/views/scripts/item/edit.phtml`, `application/modules/items/forms/Toolbar.php`, `js/global.js` | Gemeinsame Toolbar-Aktionen nach deren P1-Migration übernehmen; Filter, Auswahl und Mehrfachbearbeitung erhalten |
| P2 | Upload und Download: `application/modules/contacts/controllers/AttachmentController.php`, `application/views/scripts/partials/email-messages.phtml`, `application/views/scripts/partials/downloads.phtml`, `js/filemanager.js`, `js/global.js` (XRechnung-Download) | Zustände in bestehendem Upload-iframe und bei Blob-Downloads vereinheitlichen; Dateivalidierung und Downloadrechte prüfen. Native Downloads haben kein verlässliches Browserereignis für das Ende des Downloads |
| P3 | Kampagnen: `application/modules/campaigns/views/helpers/Errors.php`, `application/modules/campaigns/models/List/Recipients.php`, `js/global.js` (`getCampaignRecipients`, `getCampaignErrors`) | Technische Statusdetails und Fehler beim Aktualisieren der Übersichten separat vereinheitlichen; Bounce-/Complaint-Regeln erhalten |
| P3 | Shop-Oberfläche: `application/layouts/scripts/site.phtml`, `css/shop.css`, `js/shops.js` | Eigenes Shop-Design und Bootstrap-Verwendung separat prüfen, bevor das interne `ui.css` übernommen wird |

## Ausgeführte Prüfungen

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

## Geänderte Dateien

- Zentrale Komponenten: `js/global.js`, `css/ui.css`, `application/views/helpers/Messages.php`.
- Layout-Konfiguration: `application/layouts/scripts/layout.phtml`, `application/layouts/scripts/plain.phtml`.
- E-Mail-Integration: `application/views/scripts/partials/email-messages.phtml`, `application/modules/contacts/views/scripts/email/index.phtml`, `application/controllers/helpers/Email.php`.
- Bestehende weitere Aufrufer: `application/modules/campaigns/models/List/Recipients.php`, `application/modules/campaigns/views/helpers/Errors.php`, `js/shops.js`.
- Übersetzungen: `languages/de_DE/default/messages.php`, `languages/en_US/default/messages.php`.
- Tests und Dokumentation: `tests/security/quote-email.php`, `tests/security/notifications.php`, `tests/ui/notifications.html`, `tests/ui/notifications.py`, `tests/ui/notifications-review.md`.

## Commit-Vorschläge

Die Änderungen bilden wegen der gemeinsam verwendeten Komponenten einen zusammenhängenden Implementierungsschritt:

`unify notifications and ajax loading states`

Nachgelagerte, jeweils separat zu implementierende Schritte:

- `unify loading states for delete and cancel actions`
- `unify loading states for document and toolbar actions`
- `unify autosave and upload feedback`

Es wurden keine Commits erstellt und keine Änderungen gepusht.
