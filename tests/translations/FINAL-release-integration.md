# Final Release Integration Check

Stand: 19. November 2026. Geprüfter Produktstand: **`fd4b80df` auf `master`**.

**Empfehlung: GO für den gemeinsamen lokalen Entwicklungsstand.**

Die Translation- und Toolbar-/Button-Änderungen sind gemeinsam geprüft. Es bestehen
keine nachgewiesenen Produkt-Integrationsblocker. Die gefundenen drei Fixture-Probleme
wurden gezielt behoben; Produkt-PHP, JavaScript, CSS, Kataloge und SQL bleiben durch
diesen Abschlussauftrag unverändert. Alle nachfolgend genannten abschließenden
Prüfungen bestehen. Die Betriebs-/Browsergrenzen unten bleiben ausdrücklich bestehen.

Maschinenlesbarer Nachweis: [final-release-integration.json](final-release-integration.json).
Die Ergebnisse beschreiben ausgeführte lokale Prüfungen, keinen ausgeführten Push oder
Deployment und keinen Zugriff auf den Produktivmandanten.

## Git-Stand und Vollständigkeit

Das Arbeitsverzeichnis war beim Start sauber. Branch `master`, sechs lokale Commits
gegenüber dem **lokal gespeicherten** `origin/master`:

| Commit | Message |
| --- | --- |
| `87ac98c1` | `audit translation integration acceptance` |
| `22bc7ab1` | `fix translation acceptance failures` |
| `99e760c8` | `document svg permissions resolution` |
| `1910ef01` | `complete translation cleanup and quality gates` |
| `c196e6ad` | `verify translation release readiness` |
| `fd4b80df` | `unify standard delete buttons` |

Damit sind die in T6 noch parallel laufenden Toolbar-Arbeiten vollständig lokal
versioniert. Kein fremder Rest wurde übernommen. Der erneute strikte Translation-Audit
ist **bytegleich zum finalen `t6-report.json`**, SHA-256
`001384eefee9f243d7354a59e7453f13523ba7f7e55a995eead2136a623c9dcd`.
Der damals geprüfte gemeinsame Produktquellenstand entspricht also jetzt dem
committeten Stand. Ältere T6-Aussagen über laufende Arbeiten sind historische Hinweise.

Für diese Abschlussprüfung kommen ausschließlich die zwei unten genannten
Fixture-Korrekturen und ihre Dokumentation hinzu. Der Abschlusscommit ist im finalen
Antwortbericht genannt. Remote-Refs wurden nicht aktualisiert; der Ahead-Wert ist
kein behaupteter aktueller GitHub-Abgleich.

## Geprüfte Wechselwirkungen

- `js/global.js`: Der benannte delegierte Sprachwechselhandler gilt ausschließlich
  für `.dw-userbar__switchers #language`. Dokumentensprachen bleiben im separaten
  `.edit form`-Kontext. Native DE→EN→DE-Wechsel, Reload, genau ein Sprachrequest,
  Authentifizierung, Mandant und ungültige Eingaben werden erneut geprüft.
- Bestehende Toolbar-Delegation und neue SVG-Opt-ins: native Buttonattribute,
  Einzel-/Mehrfachauswahl, Kopf/Fuß/Inline, Bestätigung/Abbruch, Loading, Sperren,
  Fehler/Retry, Teilresultate, erneuerte AJAX-Zeilen und ausgeschlossene Kontexte.
  Die neue Buttonpräsentation führt keine zweite Aktionsbindung ein.
- Formulare/Auto-Save: dieselbe reale `global.js`-Logik mit kontrollierten Antworten;
  Schmutzzustand, Unsaved-/Pending-Anzeige, Lock-/Retryverhalten, Positionsfelder,
  Modal-/iframe-Elternvertrag, Fokus und unveränderte Requestziele.
- Übersetzungen: echte Modul-/Sprachkontexte, Javascript-Bridge, dynamische Fehlermeldungen,
  View-Helper und Modulwechsel; keine Katalog-/Schlüssel-/Platzhalterdefekte.
- SVG/CSS/Pfade: echte vollständige HTTP-/MVC-Dokumente, 14 Symbole/ein Symbolblock,
  auflösbare `use`-Referenzen, tatsächliche sichtbare Pixel, Hover/Disabled/Loading,
  Select-Popups, Dialoge und responsive Ansichten bei 320/390/768/1280 px.
  `css/ui.css`, `images/icons/dewawi.svg` und `DEEC_Form` sind gegenüber dem lokalen
  Upstream unverändert. Lokale Pfade sind traversierbar, Katalog/JS/CSS lesbar;
  die tatsächliche Produktions-FPM-Identität wurde nicht geprüft.
- Servergrenzen: bestehende Modul-/Objekt-/Mandanten-/Trash-/ACL-Negativfälle bleiben
  erhalten; keine neue Rechte- oder SaaS-Featureentscheidung in der Präsentation.

## Gefundene und behobene Fixture-Probleme

| Datei | Nachweis / Ursache | Gezielte Korrektur und Ergebnis |
| --- | --- | --- |
| `tests/install/import-db.php` | Kopierte Installer-Templates verwenden seit T5 `installerText`; im isolierten Testroot fehlen Bibliothek und Kataloge. Exit 255; Probe bestätigt fehlendes `library/DEEC/Translate.php`. | Read-only-Verknüpfungen zu `library` und `languages` im Wegwerf-Verzeichnis ergänzen. Keine Produktdatei geändert. |
| Dieselbe Datei | Danach läuft die Frischinstallation, aber der minimale Upgrade-Test besitzt keine `invoice`-Elterntabelle für den bereits vorhandenen `invoicecorrection`-FK. | `invoice` aus der getesteten frischen Struktur vor dem vollständigen unveränderten Upgrade bereitstellen. Keine SQL-Anweisung überspringen. Ganzer Installer-/Upgrade-/Fehlerlauf und 66 UOM-Prüfungen bestehen. |
| `tests/ui/notifications.html` | Der künstlich leere iframe evaluiert `applyPosition` mit jQuery und `pushMessages`, jedoch ohne die jetzt benötigte `Dewawi`-Übersetzungsbridge. Fehlerpfad wirft `ReferenceError`. Reale vollständige iframe-Seiten besitzen die Bridge bereits. | Dasselbe tatsächliche `Dewawi`-Objekt im Test-iframe bereitstellen. Alle Assertions erhalten; je 1.985 Prüfungen bei 1280/390 px bestanden. |

Es handelt sich ausschließlich um Testkontext-Reparaturen. Keine neue Infrastruktur,
Features, allgemeinen Refactorings oder produktiven Fallbacks. Ein zunächst falsch
angegebener Pfad für den Kontakt-Löschtest wurde zum vorhandenen
`tests/contacts/contact-deletion.php` korrigiert; dessen 16 Prüfungen bestehen.

## Tatsächlich ausgeführte Tests

| Prüfung | Abschließendes Ergebnis |
| --- | --- |
| Ursprüngliche Translation-Abnahme | **235/235**: 149 HTTP/Chromium + 72 PDF + 14 lokale SMTP/MIME |
| Erweiterter nativer Sprachwechsel, Requestanzahl, CSRF und Passwortdialog | **12/12** |
| Strikter Audit / Runtime | **0 Defekte**, **28/28** Modul-/Sprachkontexte, 157 Runtime-Prüfungen; Audit bytegleich zu T6 |
| Regulärer Validator `--stage T6 --browser` | **PASS**, alle eingebundenen Prüfungen grün, keine Baseline-Ausnahmen; einschließlich 30 Audit-Tests, 86 Renderprüfungen, 33 Offline-Browserprüfungen und 139 PDF-Markierungsprüfungen |
| PHP-Lint für lokale Commitänderungen plus korrigierte Installer-Fixture | **110 Dateien bestanden** |
| PHP Button-/Toolbarregressionen, zwölf Runner | **Alle PASS**: Buttons 165, Icons 651, Inquiry 697, Toolbar 2.451, Tasks 1.488, weitere Toolbars 27.681, Apply 1.459, List 7.586, Mobile 256, Filter 3.070, Dashboard 3.620, Delete/Apply 1.738 |
| Zusätzliche PHP-Sicherheit | Permissions 415, Tenant 269, Admin 68, Bootstrap/Auth 29, Kontaktlöschen 16: **alle PASS** |
| Gemeinsame Delete-Events/Präsentation, DE/EN | Chromium **1.184**, Firefox **1.256** Prüfungen, je 72 responsive Profile: **PASS** |
| Vollständige Dokument-/SVG-Pixelprobe, DE/EN | Chromium **420**, Firefox **388**: **PASS** |
| Vollständige Original-MVC-/HTTP-Seiten, DE/EN | **852 PASS** |
| Toolbar-Browser | **1.108 PASS** |
| Modal-/iframe-Apply mit echtem Elternvertrag | **400 PASS** in Chromium |
| Auto-Save, Notifications, Dokument-/Positions-/Kontakt-/Listen-/Modalaktionen | **1.985 PASS bei 1280 px und 1.985 PASS bei 390 px** |
| Isolierter Apache-/Tenant-/Trash-HTTP | **60 PASS**, Root- und Unterpfadinstallation |
| Reale Frischinstallation, aktive Upgrade-SQL und sichere Fehlerbehandlung | **PASS** auf temporärer MariaDB 10.11.14; zusätzlich **66 UOM-Prüfungen PASS** |

Überlappende Runnerzahlen sind keine addierbare Anzahl unterschiedlicher Anwendungsfälle.
Absichtliche SQL-/Rollback-Fehler werden in den negativen Fixtures protokolliert und
auf sichere Antworten geprüft; sie sind kein verbliebener Suite-Abbruch.

Hauptaufrufe: vorhandene `audit.py`, `validate.py`, `acceptance.py --extended`,
`acceptance-pdf.php`, `acceptance-mail.py`; `tests/ui/button-release-audit.py`
mit `--standard-actions` und `--document-icons` jeweils für Chromium/Firefox;
`button-page-integration.py`, `toolbar-buttons.py`, `apply-buttons.py`,
`notifications.py`, `tests/security/tenant-access-http.py`,
`tests/install/import-db.py` und die im JSON einzeln aufgeführten PHP-Runner.
Alle Daten/Requests sind synthetisch und isoliert. Vollständige Aufrufe, Exitcodes,
Ausgaben und Grenzen stehen im maschinenlesbaren Bericht. Screenshots und Rohmessungen
liegen im dort genannten `/tmp/dewawi-final-release-*`-Verzeichnis.

## SQL-Versionierung und Deployment-Schritte

**Keine SQL-Datei wurde in den sechs lokalen Ausgangscommits oder in diesem Auftrag
geändert.** Höchste offizielle Zielmigration ist weiterhin ausschließlich
`install/config/update-1.1.7-1.1.8.sql`; frische Struktur:
`install/config/structure.sql`. Entwicklungskonfiguration bleibt 1.1.7. Keine 1.1.9-
oder Feature-Migration angelegt. Historische Upgrades bleiben erhalten;
`contactperson-name-review.sql` ist eine lesende Diagnose, keine neue Migration.
Die aktive Upgrade-SQL wurde mit dem bestehenden SQL-Parser vollständig in der
isolierten Fixture ausgeführt, einschließlich der späteren Dokument-FKs.

Erforderliche Schritte durch den Deployment-Verantwortlichen:

1. Nach eigenem Remote-Abgleich den vollständig geprüften Commitstand zusammenhängend
   ausliefern: PHP, Views, Kataloge, JavaScript und die zugehörigen Assets. Bestehende
   installationsspezifische Konfiguration sowie `files`, `media`, `templates` erhalten.
2. Den tatsächlichen PHP-/FPM-Benutzer auf Lesbarkeit und Verzeichnistraversierung
   von `images/icons/dewawi.svg` prüfen. Git verwaltet Eigentümer/Gruppe und alle
   erforderlichen Unix-Leserechte nicht vollständig. Der frühere SVG-Rechtefehler
   darf nicht durch einen gemischten/fehlenden Dateistand wiederkehren.
3. Backup-/Restorebereitschaft und Ziel-Schema klären. Für diese Integrationscommits
   entsteht kein neuer SQL-Schritt. Falls das bestehende 1.1.7→1.1.8-Upgrade noch
   aussteht, nur die freigegebene Datei mit geprüftem Ausführungsplan verwenden.
   Bereits vollständig/teilweise ausgeführte `ALTER`-Anweisungen nicht blind
   wiederholen; kein Neuinstaller und keine `structure.sql` über eine bestehende DB.
4. PHP-/Asset-Caches über den etablierten Deploymentablauf aktualisieren und mit
   einem isolierten Testmandanten Sprachwechsel/Reload, genau eine Aktion pro Event,
   SVGs, Modal-Apply und AJAX/Auto-Save prüfen. Externe SMTP-/TLS-Zustellung nur mit
   ausdrücklich freigegebenem internem Testempfänger separat abnehmen.

## Offene Restrisiken und Grenzen

- Externe SMTP-/TLS-/Providerzustellung, kundeneigene Templates/Erweiterungen und
  der tatsächliche Server-/Schema-/Cachezustand wurden nicht geprüft.
- Native Enter-/Fokusprüfung in allen 72 Headless-Firefox-Deleteprofilen und der
  vollständige Firefox-Apply-Elternworkflow bleiben **NOT TESTED**. Chromium-
  Tastatur und Modal-Apply sowie Firefox-Pointer-/SVG-Prüfungen bestehen.
- Safari/WebKit, Screenreader, echte Touchhardware und die native Betriebssystem-
  Bestätigungsdialogoberfläche sind nicht abgenommen.
- Historische bewusst ausgeschlossene B17-Kontexte bleiben eigene Folgeaufgaben:
  Index-Apply-Elternvertrag und Admin-Inline-`media-delete`-Doppelattribute. Keine
  pauschale Erweiterung auf diese Kontexte durch die aktuellen SVG-Opt-ins.

Diese Grenzen sind keine neu nachgewiesenen Produkt-Integrationsfehler. Die Empfehlung
GO gilt für den dokumentierten gemeinsamen lokalen Stand und ersetzt die genannten
Betriebsschritte nicht. **Kein Push, Merge oder Deployment durchgeführt. Keine
produktiven Datenänderungen und keine Kunden-E-Mails.**
