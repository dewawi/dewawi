# B3 – Lokale semantische SVG-Icons

Stand: 18.11.2026. Geprüfte Grundlage: lokaler B2-Commit `b27f55e6` (`add shared button presentation and inquiry pilot`), [B2-Bericht](buttons-foundation.md) und vollständige vorhandene [Bestandsaufnahme](buttons-icons-analysis.md). Letztere liegt weiterhin unversioniert vor und wird durch B3 weder verändert noch mitcommittet.

## Pilotstatus und Umfang

Für die sechs produktiven B2-Controls ist weiterhin keine fachliche/visuelle Freigabe dokumentiert. Deshalb beschränkt sich B3 gemäß Auftrag auf die isolierte SVG-Integration. Keine produktive Form, kein Layout und kein JavaScript-Aufrufer wird auf SVG umgestellt. Alle bestehenden PNG-Controls, öffentliches CMS/Shop-Frontend, gespeichertes HTML/JSON und Drittanbieter-Widgets bleiben unverändert.

Vor B3 bestehen die 165 PHP- und 169 Chromium-Prüfungen der B2-Baseline. Dabei wurden keine erkennbaren Funktions-/Layoutprobleme des Piloten gefunden. Diese Fixtureprüfung ersetzt die ausstehende reale Workflow-/Themeabnahme nicht. Der bereits in B2 dokumentierte unabhängige Sales-Notification-Fixturefehler ist weiterhin reproduzierbar.

Der isolierte B3-Pilot konstruiert dieselben echten `Contacts_Form_InquiryToolbar`, `InquiryToolbarInline`, `Inquiry` und die echte Listenausgabe. Nur die Testinstanzen erhalten zusätzlich `iconFormat=svg`: Filter, Zurücksetzen, Leeren, Anzeigen, Bearbeiten und Status speichern. Zusätzliche synthetische Controls prüfen Links, Disabled, Loading, native Submit/Reset, Größen, Varianten, lange Labels und den vollständigen Katalog. `global.js` läuft unverändert; sämtliche AJAX-Aufrufe werden abgefangen. Es gibt keine Datenbank-/SMTP-Verbindung oder echte Statusänderung.

## Architektur und Schnittstelle

Die bereits vorhandene `DEEC_Form::renderButtonHtml()` bleibt die einzige Präsentationsroutine; der bestehende View-Helper delegiert weiterhin dorthin. Keine neue Rendererklasse, JavaScript-Bibliothek, Initialisierung, MutationObserver, Abhängigkeit oder automatische Umstellung.

| Option / Methode | Vertrag |
| --- | --- |
| `iconFormat` | Neues optionales Top-level-Präsentationsmetadatum: `png` (Standard) oder `svg`. Wird durch Normalisierung, Elementablage und Button-/Submitausgabe weitergereicht; kein HTML-Attribut. |
| `icon` bei SVG | Expliziter semantischer Key aus dem Katalog oder `false` für Text ohne Icon. Fehlender Key, `null`, unbekannte Werte, URLs, Dateipfade, Arrays und SVG-/HTML-Fragmente werden abgewiesen. Keine Ableitung aus Text, Elementname oder Aktionsklasse. |
| `icon` bei PNG | Bestehender B2-Vertrag einschließlich geprüfter historischer Klassenableitung bleibt erhalten. Defaultausgabe und Legacy-Positionshelper werden nicht migriert. |
| `renderButtonIcon(string $icon, string $format = 'png')` | Bestehender geschützter Austauschpunkt: PNG-Span wie bisher oder dekoratives SVG mit festem lokalem `<use href="#dw-icon-…">`. Keine zweite Buttonausgabe. Im lokalen Code gibt es keine überschreibenden Implementierungen. |
| `renderButtonSymbols(): string` | Liefert ausschließlich das versionierte feste Asset `images/icons/dewawi.svg`, ohne benutzerbestimmten Pfad. Der Dokumentbesitzer gibt es genau einmal im Body aus. Keine automatische globale Deduplizierung im PHP-Prozess. |

Beispiel für einen später ausdrücklich freigegebenen Einbau; dies ist in B3 **kein** produktiver Layoutaufruf:

```php
// Once per complete document, including an independent iframe document.
echo (new DEEC_Form())->renderButtonSymbols();

echo $this->getHelper('Button')->renderButton(
    ['tag' => 'a', 'text' => $this->translate('TOOLBAR_VIEW'),
     'icon' => 'view', 'iconFormat' => 'svg'],
    ['href' => $authorizedUrl]
);
```

Formkonfiguration: bestehende B2-Metadaten um `'iconFormat' => 'svg'` ergänzen; `icon` explizit setzen. Berechtigungen, Übersetzungen, `type`, `name`, `value`, `href`, `data-*`, `rel`, Klassen und Handler bleiben bei ihren bisherigen Aufrufern. Das Icon erteilt keine Berechtigung und löst keine Aktion aus. Direkte View-Aufrufer müssen Sichtbarkeit und erlaubte URLs selbst vorbereiten, wie bereits bei B2.

Die zentrale private Allowlist `DEEC_Form::BUTTON_SVG_ICONS` ist die einzige Laufzeitzuordnung semantischer Keys zu Symbol-IDs. AJAX-/Dialogfragmente enthalten nur die Controls mit `<use>`, niemals nochmals den Symbolblock. Jedes selbstständige iframe-Dokument benötigt seinen eigenen Block. Nachträglich eingefügte Controls funktionieren dadurch ohne JavaScript-Nachbearbeitung. Werden künftig nur Teilviews migriert, muss zuerst das zugehörige vollständige Dokument den Block erhalten. Ohne ihn können SVGs nicht erscheinen; keine stillen PNG-/URL-Fallbacks.

## Quelle, Lizenz und Katalog

Kuratiert sind **14 Geometrien / 15 semantische Keys** aus [Lucide 0.468.0](https://github.com/lucide-icons/lucide/tree/0.468.0/icons), fest auf Commit `f12b0de177fbc2a6795e99be065887e72b237123` geprüft. Bewusst fest versionierter Geometriesatz, keine Behauptung, dies sei die neueste Lucide-Version. Alle benötigten Dateien sind lokal: kein CDN, Netzwerkzugriff oder npm-Paket zur Laufzeit. Das Sprite umfasst 2.836 Bytes und enthält ausschließlich geprüfte geometrische Elemente.

Lucide steht unter ISC; der unveränderte [Upstream-Lizenztext](../../images/icons/LICENSE-lucide) benennt zusätzlich Feather/MIT. Der [Feather-MIT-Hinweis](../../images/icons/LICENSE-feather) aus v4.29.2 liegt ebenfalls bei. Nutzung, Veränderung und kommerzielle Weitergabe sind mit Erhalt der Copyright-/Lizenzhinweise gestattet. Beide Dateien bei Weitergabe mitliefern. Herkunft, Anpassung und SHA-256 der 14 unveränderten Ursprungsdateien stehen im [Assetmanifest](../../images/icons/README.md). Es werden keine Fonts, Skripte oder fremden SVG-Inhalte nachgeladen.

| Semantischer Key | Symbol-ID | Lucide-Datei | Bedeutung / Textregel |
| --- | --- | --- | --- |
| `add` | `dw-icon-add` | plus.svg | Neu anlegen; Text bei Toolbar-/Formaktionen bevorzugen. |
| `edit` | `dw-icon-edit` | pencil.svg | Bearbeiten; Icon-only in klar zugeordneten Tabellenzeilen möglich. |
| `save` | `dw-icon-save` | save.svg | Speichern; beschrifteter nativer Submit im Formular. |
| `delete` | `dw-icon-delete` | trash-2.svg | Löschen; Danger, bestehende Bestätigung und Serverprüfung beibehalten. |
| `copy` | `dw-icon-copy` | copy.svg | Kopieren; nicht als Bearbeiten oder Duplikat-Speicherstatus verwenden. |
| `view` | `dw-icon-view` | eye.svg | Anzeigen; echter Link bei Navigation, Button bei bestehender JS-Aktion. |
| `download` | `dw-icon-download` | download.svg | Datei herunterladen; kein automatischer Ersatz aller PDF-/Exportaktionen. |
| `upload` | `dw-icon-upload` | upload.svg | Datei hochladen; nativen Datei-/Formularablauf beibehalten. |
| `send` | `dw-icon-send` | send.svg | Senden; Empfänger-/Bestätigungsablauf nicht aus dem Icon ableiten. |
| `search` | `dw-icon-search` | search.svg | Suche ausführen; keine implizite Migration alter Filtercontrols. |
| `close` | `dw-icon-close` | x.svg | Dialog/Ansicht schließen; Icon-only mit übersetztem Namen möglich. |
| `clear` | Alias `dw-icon-close` | x.svg | Feld leeren; übersetztes `CLEAR`, bestehendes `rel`-Feldziel erhalten. Keine doppelte Geometrie. |
| `filter` | `dw-icon-filter` | filter.svg | Bestehendes Filterpanel; ausdrücklich anderer Zweck als Suchen. |
| `reset` | `dw-icon-reset` | rotate-ccw.svg | Filter zurücksetzen; nicht automatisch mit `type=reset` gleichsetzen. |
| `apply` | `dw-icon-apply` | check.svg | Bestätigen/Übernehmen; synthetischer nativer Submitter im Test. |

Die elf häufigen Aktionen des Auftrags sind enthalten. Filter/Reset werden für den bestehenden Pilot benötigt; Apply verifiziert den vorhandenen B2-Submittertest. `clear` teilt ausdrücklich die Schließen-Geometrie. Technische Namen wie `email`, `get`, `pdf`, `cancel`, `settings` werden nicht automatisch umgebogen; ihre spätere semantische Auswahl muss pro Aktion erfolgen.

## Styling, Zustände und Zugänglichkeit

Die vorhandene `.dw-btn--control`-Grundlage bleibt maßgeblich: `sm/md` 32/36 px mit 16-px-Iconbox, `touch` und Viewport ≤ 767 px/grober Pointer mindestens 44 px mit 20-px-Iconbox; Text+Icon-Abstand 8 px. Die Flexausrichtung zentriert die Icons, ihre Box schrumpft nicht. Lange Labels dürfen umbrechen. Variablen für Default/Primary/Danger/Ghost, Hover, Active, sichtbaren Tastaturfokus, Disabled und Loading werden übernommen.

Nur `svg.dw-btn__icon` innerhalb eines aktivierten Controls bekommt `fill:none`, `stroke:currentColor`, Strichstärke 2 sowie runde Linienenden/-verbindungen. Keine PNG-Hintergründe auf SVG, keine fest eingebrannten Iconfarben, Inline-Styles oder leeren Platzhalter. `icon=false` erzeugt überhaupt keine Iconbox. `.dw-icon-symbols` beansprucht mit 0×0 und absoluter Position keinen Layoutplatz.

Text+Icon ist Standard für Toolbar, Formular und Modalaktionen, insbesondere für Speichern/Senden/Löschen. Icon-only bleibt auf eindeutig zugeordnete Tabellenaktionen, Feld-Leeren und Dialog-Schließen beschränkt; ein nicht leerer übersetzter zugänglicher Name ist Pflicht. Bei schmaler Ansicht werden Labels nicht pauschal entfernt. Ein Tooltip allein ersetzt den Namen nicht. Andere Aktionsgruppen bleiben bewusst Legacy.

Jedes sichtbare SVG trägt `aria-hidden=true` und `focusable=false`, hat weder Titel noch eigenen Tabstopp. Beschriftungen/`aria-label`/`aria-labelledby` gehören zum Control und werden wie bei B2 übersetzt/escaped. Native Links und `button`/`submit`/`reset` behalten ihre Semantik. Die existierende Capture-Sperre schützt auch Klicks auf SVG-/`use`-Kinder bei Disabled/Loading; kein neuer Eventpfad. `setButtonLoading()` blendet dasselbe Iconelement aus und stellt es einschließlich seiner DOM-Identität wieder her.

## CSP, Browser und Sicherheitsgrenzen

Der aktuelle interne `SecurityHeaders`-Plugin setzt `frame-ancestors 'self'`; es erfolgt keine Änderung oder Lockerung. Der lokale Symbolblock enthält keine Skripte, Styleattribute, Events, `foreignObject`, Bilder, Animation, externen Referenzen oder DTD. Die Laufzeitschnittstelle akzeptiert nur feste Keys und setzt daraus lokale Fragmentreferenzen zusammen. Datenbank-/Benutzereingaben werden nicht als SVG-Markup interpretiert.

Ein zusätzlicher isolierter localhost-HTTP-Test verwendet:

```text
default-src 'none'; style-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'none'
```

Unter dieser Policy sind auch Skripte, Inline-Styles und Bilder nicht freigegeben. Der vollständige Katalog erscheint mit tatsächlicher Geometrie, sichtbarem Stroke/currentColor und korrekten Icongrößen bei 390/1280 px; ein separates iframe löst seinen eigenen Symbolblock auf. Es werden nur die lokale CSS-Datei und das Test-iframe angefordert, kein Iconasset. Die Interaktionstests laufen separat mit unverändertem vollständigem `global.js` und abgefangenem AJAX; sie behaupten nicht, die gesamte Legacyanwendung sei mit der strikten Testpolicy kompatibel.

Die internen Layouts enthalten aktuell kein `<base>`-Element. Lokale Fragmentreferenzen müssen beim späteren Layout-/iframe-Einbau weiter im eigenen Dokument aufgelöst werden; keine externen Sprite-URLs ergänzen. Der B3-Befund umfasst Chromium, keine vollständige Safari-/Firefox- oder Screenreader-Matrix.

## Tatsächlich ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| B2 vor Änderung: `php tests/ui/buttons.php`, `python3 tests/ui/buttons.py` | PASS: 165 PHP- / 169 Browserchecks. |
| `php tests/ui/button-icons.php` | PASS: zusätzlich 645 SVG-Checks; führt vorher auch die unveränderten 165 B2-Assertions aus. Prüft XML-Element-/Attribut-/Geometrie-Allowlist, 14 eindeutige nicht leere Symbole, alle 15 Mappings, feindliche/ungültige Konfiguration, keine Leericons, Legacy, native Werte/Typen, Links, DE/EN, Rechte, Readonly und unerlaubte Ziele. |
| `python3 tests/ui/buttons.py --svg` | PASS: 194 Browserassertions. DE/EN jeweils 390/768/1280 px; echte Geometrie/currentColor, Namen im AX-Baum ohne doppelte Grafiknamen, Fokus/Tab/Enter/Space, Größen, Überlauf, lange Texte, native Submitter/Required/Reset, Link-/Kindklickschutz, AJAX-Zeilenaustausch, Auto-Save/Opt-out, Loading-/Requestsperren und DOM-Restaurierung. Zusätzlich Forced Colors, strikte CSP und iframe bei 390/1280 px. |
| `php tests/ui/buttons.php`, `python3 tests/ui/buttons.py` nach Änderung | PASS: weiterhin 165 / 169. Gerendertes komplettes deutsches PNG-Fixture-JSON vor/nach B3 zusätzlich bytegleich (`cmp`, temporäre Dateien unter `/tmp`). |
| `python3 tests/ui/notifications.py` | PASS: je 1.985 bestehende Browserchecks bei 1280 und 390 px für Dialoge, Dokumente, Bestätigungen, Auto-Save und weitere unveränderte JS-Verträge. |
| `php tests/security/permissions.php` | PASS: 415 Fälle. |
| `php tests/security/tenant-access.php` | PASS: 269 speicherbasierte Mandanten-/Objekt-/Sharingfälle. Iconrendering hat keinen Mandanten-/Datenspeicherzugriff. |
| `php tests/security/contact-inquiries.php` | PASS: 46 Fälle. |
| `php tests/security/form-input-validation.php` | PASS: 20 Fälle. |
| `php tests/security/inquiry-dynamic-form.php` | PASS: 31 Fälle. |
| `php tests/security/notifications.php purchases` | PASS: 24 Fälle. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler: erst 370 Checks erfolgreich, danach `Class "Sales_Service_DocumentRevisionService" not found` in `application/modules/sales/views/helpers/Finish.php:12`; Abbruch der Sales-Helper-Fixture in `tests/security/notifications.php:211`. Bereits im B2-Bericht dokumentiert; keine betreffende Datei durch B3 geändert. |
| Syntax / Diff | `php -l` für die drei geänderten/neuen PHP-Dateien; Python-Syntaxprüfung ohne erzeugten Bytecode; `git diff --check`. |
| Visuell | Isolierte Chromium-Screenshots bei 390/1280 px gesichtet: vollständige klare Icons, Touchflächen, umbrochene Labels und Zustandsfarben. Dateien `/tmp/dewawi-b3-icons-390.png` und `…-1280.png`; keine fachliche oder reale Themefreigabe. |

Die Browserrunner verwenden den vorhandenen lokalen ChromeDriver; der zusätzliche CSP-Server dient nur der temporären statischen Fixture auf 127.0.0.1. Tests benötigen keine produktive Anwendung und greifen nicht auf produktive Daten zu. Nicht ausgeführt: produktive E2E-Aktionen, physische Mobilgeräte, reale Screenreader, vollständige Modul-/Theme-/Browserabnahme oder SQL-Tests (kein SQL geändert).

## Geänderte Dateien und weitere Schritte

| Datei | Änderung |
| --- | --- |
| `library/DEEC/Form.php` | Explizites Formatmetadatum, feste semantische Allowlist, SVG-Ausgabe am bestehenden Iconslot, fester Symbolblock-Leser. |
| `css/ui.css` | SVG-spezifischer Stroke/currentColor und nicht sichtbarer Symbolcontainer; bestehende PNG-Regeln unverändert. |
| `images/icons/dewawi.svg`, `README.md`, `LICENSE-lucide`, `LICENSE-feather` | 14 lokale Symbole, Pin/Hashes, Herkunft und beide Lizenzhinweise. |
| `tests/ui/buttons.php`, `buttons.py` | Optionaler SVG-Modus der bestehenden echten Pilot-Fixture und Browsermatrix; PNG bleibt Standard. |
| `tests/ui/button-icons.php` | Zusätzliche Katalog-/Sicherheits-/Kompatibilitätstests mit vorhandener Memory-Fixture. |
| `tests/ui/button-icons.md`, `buttons-foundation.md` | B3-Vertrag, Katalog, Ergebnisse und Verlinkung. |

| Risiko / offen | Umgang / konkreter Folgeauftrag |
| --- | --- |
| B2-/SVG-Freigabe ausstehend | Reale Anfrage-Toolbar, Tabelle und Statusformular mit DE/EN, Desktop/Touch und verwendeten Themes abnehmen. Erst danach produktive Opt-ins beauftragen. |
| Symbolblock fehlt / mehrfach vorhanden | Bei freigegebenem Pilot zuerst genau einmal in die tatsächlich verwendeten internen Vollseiten-/plain-Layouts einbauen; AJAXfragmente und fremde Dokumente getrennt prüfen. Eindeutige IDs und iframe-Einbau testen. B3 baut den Block bewusst nur in Fixtures ein. |
| Neue Icons / uneindeutige Aktionen | Fachliche Bedeutung und übersetzten Namen festlegen; nur nötige Geometrie aus geprüftem Pin ergänzen, Allowlist/Manifest/Lizenzen und alle Tests aktualisieren. Keine automatische Text-/Namensheuristik, Alias nur bewusst. |
| Legacy-/PNG-Migration | Nach Pilotfreigabe kleine zusammenhängende Modulgruppen migrieren. Alle 23 PNG-Keys und URLs bleiben verfügbar; insbesondere `email/get/pdf/cancel` vor Umstellung fachlich zuordnen. MultiForm, Positionshelper, JS-generiertes HTML, Inputbuttons und direkte Partials weiterhin einzeln bearbeiten. |
| Public Frontend / Drittanbieter | Keine ERP-Symbole/CSS ungeprüft ins Site-Layout laden; Shop/CMS und Widgets bleiben eigene Aufgaben. |
| Browser / A11y | Vor produktiver Erweiterung unterstützte Safari-/Firefox-Versionen und Screenreader prüfen. Namen, Fokus, 44-px-Touchflächen und Zustände erhalten. |
| Bekannter Notification-Testfehler | Separater Auftrag für die Sales-Fixture-Abhängigkeit; kein Iconproblem und kein Anlass für Änderung der Dokumentlogik in B3. |

Keine Datenbankänderung, Migration, Datenkorrektur, gespeicherte HTML-Änderung oder manuelle Datenbanknacharbeit erforderlich. Kein Deployment und kein Push. Der B3-Commit umfasst ausschließlich die explizit geprüften eigenen Dateien; der vorhandene unversionierte Analysebericht bleibt geschützt.
