# B2 – Gemeinsame Button-Grundlage und Kontaktanfrage-Pilot

Stand: 18.11.2026. Grundlage ist die lokale [Bestandsaufnahme](buttons-icons-analysis.md). Dieser Bericht beschreibt die tatsächlich implementierte B2-Stufe; die umfassendere SVG-/Modulmigration aus der Bestandsaufnahme bleibt ein Folgeauftrag. Der bereits vorhandene, unversionierte Analysebericht wird nicht verändert oder in den B2-Commit aufgenommen.

## Umfang und Architekturentscheidung

Die bisherigen Renderer koppeln Iconabstand, sichtbaren Text und Icon-only-Darstellung an unterschiedliche Klassen bzw. ein fehlendes Label. `DEEC_Form` und der ältere Button-Helper erzeugten ihr Markup unabhängig. B2 führt ihre Ausgabe in `DEEC_Form::renderButtonHtml()` zusammen. Berechtigungen, Readonly, Übersetzung, Formularkontext und Aktionsrouting werden weiterhin vor der Präsentation durch die bisherigen Aufrufer vorbereitet. Die Präsentationsroutine selbst greift weder auf Registry/Request noch auf Datenbanken zu.

Neue Controls erhalten ausdrücklich `.dw-btn--control`. Bestehende `.dw-btn`, Aktionsklassen, `data-*`, native Typen und Handler bleiben erhalten. Ohne neue Metadaten behalten bestehende Formbuttons ihr bisheriges Markup und Styling. Die acht Positionsparameter des alten `Button(...)`-Helpers bleiben gültig; auch dieser nutzt dieselbe Ausgabe im Kompatibilitätsmodus. Somit stellt die gemeinsame Routine keine flächendeckende optische Migration dar.

Es gibt weder ein neues Framework noch einen zusätzlichen Action-Service oder eine Iconbibliothek. Die einzigen neuen JavaScript-Zeilen schützen ausdrücklich aktivierte Controls während Disabled/Loading; Toolbar-, Auto-Save-, Submit-, Mail- und Dokumenthandler wurden nicht umgebaut.

## Gemeinsame Schnittstelle

```php
public function renderButtonHtml(array $presentation, array $attribs = []): string
```

Die Methode liegt in `library/DEEC/Form.php`. Für Views delegiert der bestehende `Zend_View_Helper_Button::renderButton()` an diese Methode:

```php
$this->getHelper('Button')->renderButton(
    ['tag' => 'a', 'text' => $this->translate('TOOLBAR_VIEW'), 'icon' => 'view'],
    ['href' => $url, 'target' => '_blank', 'rel' => 'noopener']
);
```

Der direkte Aufrufer ist für Sichtbarkeit/Berechtigungen und die Übersetzung zuständig. Die Methode escaped Text und Attribute genau einmal. `text` ist Text, kein vorab escaptes HTML. `href`, `type`, `name`, `value`, `onclick`, `data-*`, `aria-*` und weitere vorbereitete HTML-Attribute bleiben beim Aufrufer; es wird kein Handler aus einem Icon oder einer CSS-Klasse abgeleitet.

| Präsentationsschlüssel | Werte / Verhalten |
| --- | --- |
| `tag` | `button` (Standard) oder `a`; andere Tags werden abgewiesen. |
| `text` | Bereits übersetzter Klartext. Bei Icon-only wird er als zugänglicher Name genutzt, sofern kein explizites `aria-label`/`aria-labelledby` vorliegt. |
| `icon` | Einer der unten aufgeführten vorhandenen Iconkeys oder `false` für kein Icon. Ohne explizite Angabe kann eine bekannte alte Aktionsklasse das Icon liefern. Keine beliebigen Pfade, URLs oder HTML-Fragmente. |
| `iconOnly` | Explizite Trennung von Label und sichtbarer Darstellung. Ohne Angabe gilt leerer Text als Icon-only. Icon-only benötigt ein bekanntes Icon und einen nicht leeren zugänglichen Namen; fehlerhafte Konfiguration wird abgewiesen. |
| `variant` | `default`, `primary`, `danger`, `ghost`. Ohne Angabe `default`, bei bestehender `dw-btn--primary`-Klasse weiterhin `primary`. |
| `size` | `sm`, `md`, `touch`; Standard `md`. |

Unbekannte Varianten, Größen, Icons oder native Buttontypen werden mit `InvalidArgumentException` zurückgewiesen. Native Buttontypen sind `button`, `submit`, `reset`; der Standard für direkte Aufrufe ist `button`. Links behalten ihre Linksemantik, ihren `href` und gegebenenfalls Target/Rel; ihnen wird kein `role=button` aufgezwungen.

Die internen Optionen `legacy` und `wrapLabel` sichern ausschließlich die bestehenden Form-/Positionshelper-Ausgaben. Neue Aufrufer verwenden sie nicht. Im Kompatibilitätsmodus gelten weiterhin die alten Klassen-/ARIA-Entscheidungen des jeweiligen Aufrufers. Die neue Routine erteilt dabei keine Berechtigung.

### Integration in `DEEC_Form`

Für `type=button` und `type=submit` sind `icon`, `variant`, `size`, `iconOnly` als optionale Top-level-Metadaten in Normalisierung **und** Elementablage aufgenommen. Bereits eines dieser Metadaten aktiviert die neue Präsentation. Fehlende Metadaten werden nicht pauschal an allen Elementen ergänzt. Das vorhandene `attribs['size']` für Formularfelder ist davon unabhängig.

```php
$this->addElement([
    'name' => 'save',
    'type' => 'submit',
    'label' => 'CONTACT_INQUIRIES_SAVE_STATUS',
    'icon' => 'save',
    'variant' => 'primary',
    'wrap' => false,
    'attribs' => ['class' => 'dw-btn dw-btn--primary'],
]);
```

`renderElementHtml()` prüft weiterhin `canRenderElement()` und wendet `applyModeToAttribs()` an. Nur toolbargebundene **Button**-Elemente erhalten wie bisher automatisch `data-action`; Submitbuttons bleiben native Submitter. Die vorhandene Attribut-Allowlist wird nicht erweitert. `renderElementWithAttribs()` bleibt unverändert und übernimmt keine temporären Rowattribute in den Formularzustand. Bestehende Button-ID-Entfernung und Wrapperkonventionen bleiben erhalten.

Bei neu aktivierten Buttons wird ein konfigurierter Top-level-`value`/`default` auch als HTML-`value` ausgegeben, einschließlich `"0"`. Für Direktaufrufe kann `value` wie bisher als HTML-Attribut übergeben werden. Legacyformulare erhalten dadurch keine neuen Submitterwerte.

Beim Zusammenführen der Button-/Submitzweige wird außerdem der im Analysebericht nachgewiesene doppelte Escapevorgang für Submitlabels beseitigt. Ein Label `A & B` erscheint als `A & B`, nicht als sichtbares `A &amp; B`. Der alte Helper escaped seine Text-/Attributparameter jetzt ebenfalls; beabsichtigtes HTML im bisherigen `$value`-Parameter wird als Text behandelt. Unter den inventarisierten 25 Aufrufstellen wurde kein solcher HTML-Labelverbraucher gefunden. Dies ist die bewusste Kompatibilitätsgrenze der Textschnittstelle.

## CSS und bestehende Icons

Das System bleibt in `css/ui.css` und ist für neues Layout auf `.dw-btn--control` begrenzt. Bestehende PNG-Regeln werden mit den entsprechenden `.dw-btn__icon--<key>`-Selektoren geteilt; es gibt keine duplizierten Bild-URLs, neue Bildassets oder SVG-Ausgabe. Bei neuen Controls wird nur der äußere Hintergrund unterdrückt. Ein dekorativer innerer Span trägt das PNG, der Text einen `.dw-btn__label`-Span. Dadurch brauchen reine Textbuttons keinen leeren Iconabstand.

Verfügbare Iconkeys: `add`, `edit`, `view`, `copy`, `settings`, `delete`, `cancel`, `clear`, `reset`, `apply`, `save`, `email`, `close`, `details`, `positions`, `back`, `pdf`, `get`, `daterange`, `select`, `up`, `down`, `filter`.

| Eigenschaft | B2-Vertrag |
| --- | --- |
| Normalgröße | `md`: mindestens 36 px hoch, Iconbox 16 px. |
| Dichte Zeilen | `sm`: 32 px, Iconbox 16 px. |
| Touch | `touch`: mindestens 44 px, Iconbox 20 px. Bei Viewport ≤ 767 px oder grobem Pointer werden auch `sm/md` mindestens 44 px groß. |
| Text | Umbruch erlaubt, Iconbox schrumpft nicht; lange Labels bleiben innerhalb ihres verfügbaren Bereichs. |
| Varianten | Neutral/Primary/Danger/Ghost über lokale `--dw-btn-*`-Variablen; vorhandene Aktions-/Funktionsklassen bleiben erhalten. |
| Hover/Active | Nur aktivierte Controls bekommen den Hover-/Pressedzustand. |
| Tastaturfokus | Sichtbare 2-px-Outline mit Abstand, auch im Forced-Colors-Modus. |
| Disabled | Native Buttons/Inputs über `disabled`; Links über `aria-disabled=true` und initial `tabindex=-1`. URL bleibt erhalten. |
| Loading | Bestehendes `setButtonLoading()` einschließlich DOM-Detach/Restore und Requestlocks. Inneres Icon wird ausgeblendet; bestehendes statisches `…` bleibt der Ladeindikator. Kein zweiter Spinner und keine neue Animation. |

Icon-only bleibt auch bei einem temporären Loadingtext geometrisch kompakt: sichtbare Ersatztexte werden innerhalb des Iconbuttons nicht dargestellt, `aria-label`/`aria-busy` bleiben zugänglich. Die Loading-`::after`-Anzeige besitzt ihre eigene Schriftgröße.

`renderButtonIcon(string $icon): string` ist der begrenzte Austauschpunkt für einen späteren lokalen SVG-Symbolsatz. Aktuell liefert er ausschließlich geprüfte PNG-Icon-Spans mit `aria-hidden=true`. Bei späterem SVG werden an dieser Stelle feste lokale Symbole eingesetzt und in derselben Iconbox dimensioniert; Symbolkatalog, Layout-/iframe-Einbau, Lizenznachweise und SVG-Regressionstests bleiben ein eigener Auftrag. B2 fügt weder `<use>` noch eine Iconinitialisierung hinzu.
### Warum eine schmale Capture-Sperre nötig ist

Ein `aria-disabled`-Link mit erhaltenem `href` ist nativ weiterhin aktivierbar. CSS `pointer-events` allein verhindert weder Enter noch programmatische native Klicks. Eine erst am Dokument delegierte jQuery-Sperre käme außerdem nach einem vorhandenen Inlinehandler.

`js/global.js` fängt deshalb Clicks in der Capturephase ab, sucht den umschließenden `.dw-btn--control` mit den vorhandenen jQuery-Konventionen und verhindert nur bei `:disabled`, `aria-disabled=true` oder `.is-loading` die weitere Ausführung. Das umfasst Klicks auf Kindicons und schützt vor Inlinehandler, Toolbarhandler und Navigation. Aktivierte Controls und alle nicht migrierten Controls sind davon unberührt. Es gibt keinen neuen Actiondispatcher und keine zweite Submit-/Auto-Save-Behandlung. Direkte Aufrufe fachlicher JavaScript-Funktionen bleiben unabhängig davon durch ihre bestehenden Request-/Serverprüfungen geschützt.

## Begrenzter Pilot

Ausschließlich sechs produktive Controls der internen Kontaktanfrage sind optisch migriert:

| Datei | Controls / Prüfung |
| --- | --- |
| `application/modules/contacts/forms/InquiryToolbar.php` | Filter und Zurücksetzen als Text+Icon; Suchbegriff leeren als Icon-only mit übersetztem `CLEAR`. `toolbar`, `rel=keyword`, Defaults und bestehende Aktionsklassen bleiben gleich. |
| `application/modules/contacts/forms/InquiryToolbarInline.php` | Anzeigen/Bearbeiten als kompakte Icon-only-Zeilenaktionen mit expliziten `TOOLBAR_VIEW`-/`TOOLBAR_EDIT`-Namen. `DEEC_List` ergänzt weiterhin Action/ID/Modul/Controller. |
| `application/modules/contacts/forms/Inquiry.php` | Status speichern als nativer Primary-Submit mit Save-PNG. Anfragecontroller, Statuswerte, Transaktion und bestehendes Status-Auto-Save unverändert. |

Filter-/Listentoolbar und native Statusbearbeitung gehören zu demselben Workflow und erfordern keinen Umbau der Views oder Controller. Readonly-, Disabled- und Loadingzustände werden zusätzlich in isolierten Fixtures ausgeübt. Varianten wie Danger/Ghost und Links sind in der gemeinsamen Schnittstelle implementiert und getestet, werden aber nicht durch zusätzliche fachliche Aktionen in den Pilot hineingezwungen.

## Baseline und tatsächlich ausgeführte Prüfungen

Vor den B2-Änderungen lagen fremde Dokument-/Rechnungsänderungen und der unversionierte Analysebericht im Arbeitsverzeichnis. B2 hat diese weder verworfen noch eigenmächtig mitgestaged. Der B2-Diff wird anhand der expliziten Dateiliste geprüft; zwischenzeitlich von anderer Arbeit erstellte lokale Commits gehören nicht zum B2-Commitbericht.

Sechs Legacyausgaben wurden vorab nach `/tmp` gesichert und nach dem Umbau byteweise verglichen: alter Iconbutton, Texttoolbarbutton, Bootstrapklassierter Submit sowie drei alte Helperaufrufe einschließlich Inlinehandler und Textspan. Der Vergleich ist unverändert. Die dauerhaften Tests prüfen zusätzlich die HTML-/Attributverträge, statt nur den aktuellen Renderer nachzubauen.

| Befehl / Prüfung | Befund |
| --- | --- |
| `php tests/ui/buttons.php` | PASS: 165 Prüfungen für neue Schnittstelle, Pilot, Legacy, einmaliges Escaping, `value="0"`, native Typen, Varianten, Größen, ungültige Konfiguration, DE/EN, Readonly und echte Berechtigungsabfragen im Memoryadapter. |
| `python3 tests/ui/buttons.py` | PASS: 169 Browserassertions, DE/EN jeweils bei 390/768/1280 px; echte Tab-/Enter-/Space-Eingaben, Fokus, AX-Namen, Icon-/Textabstände, Touchmaße, lange Labels, dynamischer AJAX-Zeilenaustausch und richtige Zielroute, Auto-Save/Opt-out, Loading-/Requestsperren, Node-Restaurierung, Linkschutz vor Inlinehandlern, native Submitterwerte/Validierung/Reset und Forced Colors. |
| `python3 tests/ui/notifications.py` | PASS vor und nach B2: je 1.985 bestehende Browserchecks bei 1280 und 390 px, einschließlich Mail-/Dokument-/Modal-/Auto-Save-Verträgen. |
| `php tests/security/permissions.php` | PASS vor und nach B2: 415 Berechtigungs-/ACL-/Kompatibilitätsfälle. |
| `php tests/security/tenant-access.php` | PASS: 269 Mandanten-/Objekt-/Legacy-/AJAX-/Sharingfälle, rein speicherbasiert. |
| `php tests/security/contact-inquiries.php` | PASS vor und nach B2: 46 Anfrage-/Konfigurations-/Renderingfälle. |
| `php tests/security/form-input-validation.php` | PASS vor und nach B2: 20 Formular-/AJAX-/Formatprüfungen. |
| `php tests/security/inquiry-dynamic-form.php` | PASS: 31 öffentliche DynamicForm-/Abhängigkeits-/Defaultprüfungen; keine optische Shopmigration. |
| `php tests/security/notifications.php` | Vor und nach B2: 370 allgemeine Rendering-/Flash-/Historieprüfungen bestehen, danach Abbruch im Sales-Teil: `Class "Sales_Service_DocumentRevisionService" not found`, `application/modules/sales/views/helpers/Finish.php:12`. Der eigenständige Test lädt die zwischenzeitlich vorhandene neue Dokumentabhängigkeit nicht. Kein durch B2 eingeführter Fehler; diese fremde Fixture-/Dokumentlogik wurde nicht geändert. |
| `php tests/security/notifications.php purchases` | PASS: 24 Purchases-Dokumenthelperprüfungen. |
| Syntax / Diff | `php -l` für alle sechs geänderten/neuen PHP-Dateien, Python-Syntaxprüfung und `git diff --check` bestanden. `node --check js/global.js` konnte mangels Node-Installation nicht laufen; Chromium hat das vollständige aktuelle `global.js` in den Browserprüfungen erfolgreich geparst und ausgeführt. |
| Visuelle Sichtung | Chromium-Screenshots der isolierten Fixture bei 390/1280 px geprüft. Keine vollständige manuelle Abnahme aller Mandantenlayouts. |

Alle AJAX-Aufrufe im neuen Browserrunner sind abgefangen; es gibt keine externen Downloads, Datenbank- oder SMTP-Verbindungen. Der Runner nutzt die vorhandene WebDriver-Hilfe aus `tests/site/accessibility-http.py`. ChromeDriver benötigt einen lokalen Socket und läuft in dieser Umgebung nach Sandboxfreigabe außerhalb der Sandbox. Der erste sandboxbeschränkte Versuch scheiterte mit `PermissionError: Operation not permitted`; die freigegebenen isolierten Durchläufe bestehen.

Nicht durchgeführt: produktive HTTP-/End-to-End-Tests, echte Status-/Dokument-/Mailänderungen, Screenreader-Tests auf realen Geräten, vollständige Modul-/Themeabnahme oder eine vollständige Accessibility-Konformitätsprüfung. Die automatischen Accessibilitychecks decken ihre aufgeführten Fälle ab. Die historische PNG-Unschärfe bei vergrößerten Icons bleibt bis zum separaten SVG-Schritt bestehen.

## Verbleibende Legacy-Wege und nächste Aufträge

- Andere Modul-/Positions-/Top-/Bottomtoolbars verwenden zwar die gemeinsame Ausgabe über `DEEC_Form`, bleiben ohne neue Metadaten optisch im Kompatibilitätsmodus. Technische ARIA-Fallbacks dieser unberührten Controls sind nicht pauschal korrigiert.
- Der Positionshelper `Button(...)` behält seine alten Klassen und Signatur. Neue PHP-Aufrufer verwenden `renderButton()`; seine acht vorhandenen Aufruferdateien sind später separat zu migrieren.
- MultiForm-Sonderausgabe, direktes HTML in Views/Partials, dynamische JS-Factorybuttons, `dw-button`, unimplementierte alte Modifier und alte Positionsgruppen-/Historyhelper bleiben gesonderte Inventarpunkte. Nicht bloß alle bisherigen Klassen durch `.dw-btn--control` ersetzen: Namen, Icons und Handler müssen pro Kontext geprüft werden.
- ZF1-Inputbuttons, öffentliches Bootstrap-/Shopfrontend und Drittanbieter-Widgets werden nicht umgestellt. Es wird kein ERP-Stylesheet ins Site-Layout eingeführt.
- Folgeschritt: Pilot im realen internen Anfrageworkflow manuell abnehmen; danach weitere kleine Gruppen anhand der bestehenden Inventarliste migrieren. Lokale SVGs erfordern einen eigenen Auftrag; keine Bibliothek für B2 nachinstallieren.
- Bestehenden Sales-Notification-Fixturefehler separat mit der Dokumentrevision zusammenführen. Der Analysebericht bleibt als vorhandene unversionierte Datei erhalten; bei späterem Teilen der Dokumentation seinen Versionsstatus beachten.

Keine SQL-Migration, keine Änderungen an gespeicherten HTML-/JSON-Inhalten, kein Deployment und kein Push gehören zu B2.
