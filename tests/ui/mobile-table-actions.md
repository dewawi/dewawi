# B11 – Mobile Kontakt- und Aufgabenaktionen

Stand: 19.11.2026. Lokale Grundlage: `AGENTS.md`, [B1](buttons-icons-analysis.md) bis [B10](list-buttons.md), insbesondere [B8](button-quality-audit.md). Ausgangs-HEAD `f337ba70`; Arbeitsverzeichnis zu Beginn sauber. Der zuletzt fremd geänderte Dokument-Recovery-Code ist inzwischen separat committed und bleibt unverändert. B10 liegt als `96549509` vor. Die aktuelle [Gesamtinventur](button-system-inventory.md) gehört ebenfalls zu B11.

## Reproduktion und Ursache

Die unveränderten echten `Contacts_Model_List_Contacts`-/`Tasks_Model_List_Tasks`-Ausgaben mit je zwei synthetischen Datensätzen reproduzieren in DE/EN bei 390 **und 320 px** die dokumentierten **207 px** Zeilenhöhe. Messung vor der CSSänderung: `/tmp/dewawi-b11/probe.log`, `layout-probe.json`, `candidates-min.log`.

| Bestandteil | Konkrete Ursache |
| --- | --- |
| `DEEC_List::{normalizeColumn,getTableClass}`, Modul-Listenklassen | Bestehende Klassen `dw-table--contacts-contact`, `dw-table--tasks-task`, `dw-col-actions`; dieselbe Klasse auf TH/TD. Kein fehlender oder neuer Renderer nötig. |
| `css/ui.css::.dw-col-actions` | 85 px **Inhaltsbreite**, zusätzlich 24 px horizontales Zellpadding. Zwei bestehende 44-px-SVG-Controls und 6 px Flexgap benötigen 94 px Inhalt. Die 85 px erlauben nur ein modernes Control je Zeile. |
| `.dw-row-actions` | Bereits `display:flex`, `flex-wrap:wrap`, `gap:6px`. Vier tatsächliche Controls, darunter drei moderne SVG-Buttons und unverändertes Legacy-Delete, ergeben die hohe Stapelung. |
| `.dw-table`, generische TH-/TD-Regeln | Festes Tabellenlayout, feste ID-/Checkbox-/Pinspalten und viele weitere Spalten. Mobil schrumpfen mehrere Spalten im alten Layout bis auf Breite null; `overflow-wrap:anywhere`/`word-break:break-word` erzeugen zeichenweise Umbrüche. Nur 94 px Aktionsbreite senkt die Testzeilen auf 171,75 px, löst diesen zweiten Engpass jedoch nicht. |
| Automatisches Tabellenlayout allein | Stellt Datenspalten wieder her, darf die bevorzugte Aktionsbreite aber auf ein Control zusammenschieben. Daher zusätzlich eine echte Mindestbreite für die Aktionsspalte nötig. |
| Tabellenüberschriften | Auch nach automatischem Layout bleiben bei beliebigem Wortumbruch extrem hohe Überschriften möglich. Normale Wortgrenzen in genau diesen mobilen Tabellen vermeiden vertikale Zeichenstapel. |

## Kleine, kontextbezogene Korrektur

Ausschließlich in `css/ui.css`, innerhalb des bestehenden Breakpoints **max-width:767px**:

1. Automatisches Tabellenlayout für Kontakt-/Tasks-**Index**tabellen im normalen Layout und im Plain-Indexlayout.
2. `width:94px; min-width:94px` für deren bestehende Actionszelle. Mit unverändertem Padding beträgt die Zellbreite mindestens 118 px; die 44-px-Controls stehen zu zweit nebeneinander und umbrechen weiter per bestehendem Flexlayout.
3. `overflow-wrap:normal; word-break:normal` ausschließlich für deren Tabellenkopf. Inhalte bleiben mehrzeilig, wenn normale Wortgrenzen das erlauben; kein Abschneiden oder Ellipsis.

Scope über bereits vorhandene Layout- und Tabellenklassen: `.dw-app-shell__inner.contacts.index`/`.tasks.index` plus konkrete Tabellenklasse, bzw. `.dw-popup-shell--contacts.dw-popup-shell--contact.dw-popup-shell--index` und entsprechender Tasks-Index. Kein `:has`, keine neue HTML-Klasse, kein Request-/Renderer-/Controllerumbau und keine globale Tabellenregel. Searchfragmente erben den Indexscope nach ihrem bestehenden Einfügen in dasselbe Dokument.

Select-/Plain-Select, Edit/View, Historie, Inquiry, Artikel, Admin, Positionen und andere Module behalten ihr Layout. Ab 768 px bleiben sämtliche alten Tabellenregeln wirksam. Grobe Pointer behalten überall ihre vorhandenen 44-px-Controlgrößen; B11 führt dafür keine zusätzliche Desktop-Tabellenregel ein.

Keine Buttonklasse, kein SVG, keine Beschriftung, kein `href`, `type`, `name`, `value`, `data-*`, Handler, Checkbox-/Row-ID-/Auto-Save-/Loading-/Requestlockvertrag oder Berechtigungscheck geändert. Legacy-Delete wird nicht migriert oder umgestaltet; es fließt im vorhandenen Aktionscontainer mit. Keine Dokument-/Mail-/Zahlungs-/Positions-/Shop-/CMS-/Vendoraktion geändert.

## Ergebnis und Grenzen

Normale Fixturezeilen, beide Sprachen:

| Ansicht | B10 bei 320/390 px | B11 bei 320/390 px | 768 px | 1280 px |
| --- | --- | --- | --- | --- |
| Kontakte | 207 px | **115 px** | unverändert 115,22 px | unverändert 95 px |
| Aufgaben | 207 px | **115 px** | unverändert 95 px | unverändert 95 px |

Die 115 px sind ein gemessenes Ergebnis dieser Datensätze, **keine feste CSS-Zeilenhöhe**. Lange Kontakt-/Aufgabennamen dürfen die Zeile vergrößern; keine Inhalte werden für einen Messwert verborgen. Die bestehende horizontale Scrollfläche wird bewusst erhalten: Die Tabelle darf mobil ihre natürliche Mindestbreite einnehmen, das Dokument wächst dadurch nicht. Nach horizontalem Scrollen sind alle Aktionen vollständig erreichbar, ohne Überlappung oder reduzierte Touchflächen. Das Kopfumbrechen wurde nach Sichtprüfung zusätzlich abgesichert.

**Unabhängiger Altbefund bei 320 px:** Der gemeinsame Header ohne Flexwrap drückt `.dw-app-header__meta`/`.dw-userbar` über den Viewport; im deutschen Fixture reicht der Kopf bis x=334 px. Der gleiche Überlauf wurde vor B11 gemessen und stammt außerhalb der Tabelle. Der neue Test akzeptiert bei 320 px ausschließlich sichtbare Überläufer innerhalb dieses bestehenden Headers; kein pauschales Abschalten der Überlaufprüfung. Tabellen und alle übrigen Elemente müssen innerhalb des Viewports bleiben. Bei 390/768/1280 px besteht diese konkrete Ausnahme nicht. Globale Header-/Logoanpassung bleibt ein eigener B12-Kandidat, damit B11 keine allgemeine Layoutumstellung wird.

## Tatsächlich ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| Vorher-Reproduktion | Zwei reale Listenmodelle × DE/EN × 320/390/768/1280 px, 207 px mobil bestätigt. Kandidatenvergleich zeigt: nur Breite 171,75 px; Auto ohne Mindestbreite weiter 207 px; beide zusammen 115 px. Kein produktiver Datenzugriff. |
| `php tests/ui/mobile-list-actions.php` | PASS: **256** zusätzliche Checks, eingebundene B2-/B5-/B10-Baselines unverändert. Reale Tabellen-/Zell-/Full-/Plain-Scopeklassen, native Typen und Rowtargets. Optionaler Renderaufruf liefert reale Modelle mit langen synthetischen Daten. |
| `python3 tests/ui/list-buttons.py --mobile` | PASS: **822** Assertions. DE/EN × 320/390/768/1280 px; Zwei-Spalten-Anordnung, 44-px-Ziele, ≤116 px normale mobile Zeilen, kompakter Tabellenkopf, keine kollabierten Spalten, Fokus/Tab/Enter/Space/Hover, AX-Namen, Loading/Disabled, Row-ID statt fremder Auswahl, gemeinsamer Kopierschutz, Fehler/Retry, AJAX-SVG-/Footerersatz. Zusätzlich Plain-Index, negative Select-/Edit-/Items-/Adminkontexte, Readonly, Kontakt-View-only und lange/ungetrennte Namen in Full/Plain bei allen vier Breiten mit Zellcontainment und Scroll-Erreichbarkeit. |
| `php tests/ui/{button-icons,inquiry-buttons,toolbar-buttons,tasks-toolbar,remaining-toolbars,apply-buttons,list-buttons}.php` | PASS: 645 / 697 / 2.259 / 1.428 / 27.105 / 1.459 / 7.374 Checks zuzüglich eingebundener Baselines. |
| `python3 tests/ui/list-buttons.py` | PASS: **1.470** B10-Assertions für neun Listen, DE/EN, 390/768/1280 px. Nach der letzten Kopfkorrektur erneut ausgeführt. |
| `python3 tests/ui/toolbar-buttons.py`; `--tasks`; `--remaining` | PASS: **1.000 / 524 / 3.247** bestehende Assertions. Kontaktprofil (**500**) und Tasks (**524**) nach der letzten Kopfkorrektur zusätzlich erneut geprüft. |
| `python3 tests/ui/buttons.py`; `--svg --audit`; `inquiry-buttons.py`; `apply-buttons.py` | PASS: **169 / 204 / 796 / 302** bestehende Assertions, darunter lokale CSP, Forced Colors, Zoom-/Touch-/AX-Proben und Auswahl-/Modalverträge. |
| `python3 tests/ui/notifications.py` | PASS: **1.985** Checks je 390/1280 px; vorhandene Dokument-/Mail-/Positions-/Auto-Saveabläufe mit abgefangenem I/O. |
| `php tests/security/{permissions,admin-access,tenant-access,bootstrap-auth}.php`; `php tests/lists/summary.php` | PASS: **415 / 68 / 269 / 29** Fälle bzw. zentrale Listenfooter-/Summenprüfung. Keine Sicherheitslogik geändert. |
| `php tests/security/notifications.php` | Bekannter unabhängiger Fehler: **370** erfolgreiche Checks, dann fehlende `Sales_Service_DocumentRevisionService` in `sales/views/helpers/Finish.php:12`; Fixtureabbruch `notifications.php:211`. Nicht als bestanden gewertet. |
| Inventurwerkzeuge | `python3 tests/ui/button-inventory.py --json` und `php tests/ui/button-inventory.php` tatsächlich ausgeführt; Ergebnisse in der [Gesamtinventur](button-system-inventory.md). |
| Syntax / Diff | PHP-Lint der drei geänderten/neuen PHP-Testdateien, Python-AST beider Pythondateien, Berichtlinks und `git diff --check` bestanden. |

Der bestehende B10-Browserrunner bekommt einen optionalen `--mobile`-Modus; sein Standardumfang bleibt bei 1.470 Checks. Keine zweite Browser-/HTML-Renderarchitektur. `toolbarPage()` akzeptiert optional synthetische Listendaten, damit lange Werte durch die wirklichen Modelle/Partials laufen. Alle bisherigen Aufrufe bleiben unverändert. Neue PHP-/Browserproben benötigen weder Appbootstrap noch produktive Session/DB. `tests/lists/footer-http.py` bleibt wegen Produktionsbootstrap/-session ausgeschlossen. Keine neue komplette authentifizierte ERP-/SaaS-/Crossbrowserabnahme behauptet.

Logs/Metriken: `/tmp/dewawi-b11/`. Neue Fokus-/Retrybilder: `/tmp/dewawi-b11-<contacts|tasks>-<320|390|768|1280>.png`. Kontakte/390 und Tasks/320 wurden nach der Korrektur repräsentativ gesichtet. Manuelle Gesamtfreigabe mit realistischen Datendichten, horizontalem Wischen, Touchgerät, Safari/Firefox, NVDA/VoiceOver und realen Rollen-/Mandanten-/SaaS-Einstellungen bleibt offen. Die B8-Übersetzungs-/Disclosurebefunde werden nicht als gelöst ausgegeben.

Laufzeitänderung nur `css/ui.css`; Tests/Inventur/Dokumentation gesondert nachvollziehbar. Keine Datenbankmigration, gespeicherten Inhaltsänderungen oder produktiven Eingriffe. Lokaler B11-Commit; kein Push, Merge oder Deployment. Keine automatische Fortsetzung mit B12.
