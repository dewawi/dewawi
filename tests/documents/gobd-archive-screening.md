# Große registrierte Archive: Vorprüfung, Sammelnachweis und Paketfreigabe

Alle konkreten Profile, Firmen-/Belegidentitäten, Quellen und Ergebnisse bleiben in privaten Verzeichnissen außerhalb von Installation und Git-Worktree. `$PRIVATE_ISSUERS` bezeichnet das tatsächlich aktuelle Verzeichnis aus `DEWAWI_GOBD_ISSUER_REVIEW_DIR`, keine archivierte positive Kopie. Die folgenden Variablen sind vom Betreiber zu setzen; keine konkreten Werte in Repositorydateien oder Kommando-Beispielen versionieren. Die Werkzeuge ändern weder Geschäftsdaten noch PDF-Originale oder Archivregistrierungen. Es gibt keine SQL-Migration.

## Zweck und Grenzen

Der normale Ausgangsbelegexport bleibt streng: Ohne belastbaren Ausstellernachweis wird die betreffende PDF als Nichtlieferung ausgewiesen, während ihre CSV-Geschäftsdaten erhalten bleiben. Fehlende Konfiguration ist niemals eine automatische Ausstellerbestätigung.

Der neue Ablauf reduziert den Aufwand für große Bestände durch einen direkten read-only Scan registrierter Archive, einen wiederverwendbaren privaten Textcache und eine Erklärung für einen exakt benannten Dokument-/SHA-Umfang. Dafür ist **kein ZIP-Zwischenexport aller PDFs und keine PDF-Neuerstellung** erforderlich. Ein Sammelnachweis braucht tatsächliche unabhängige Ausstellerbelege und eine ausdrückliche fachliche Bestätigung. Textbefunde allein dürfen ihn nicht begründen. Fehlen solche Belege, bleibt die Zuordnung offen; die Software gibt keine erfundenen Bestätigungen aus.

Einzelnachweise aus dem bisherigen Verfahren bleiben gültig und werden exakt nach Mandant, Dokument und PDF-SHA wiederverwendet. Bestätigte Fremdaussteller haben immer Vorrang vor einem positiven Sammelnachweis. Änderungen an einer Scope-Datei oder ihrem Kontext machen die Erklärung ungültig.

## 1. Registrierte Archive lesend inventarisieren

Ein konsistenter Datenbank-/Dateisnapshot ist vorzuziehen. Die Konfiguration benötigt nur SELECT-Rechte. Der angegebene aktive DEWAWI-Benutzer benötigt weiterhin Administratorzugriff, Mandantenfreigabe und die bestehenden Leserechte aller acht Belegarten.

`$PRIVATE_WORK` muss ein vorhandenes, kanonisches Verzeichnis mit 0700 außerhalb von Installation und Worktree sein. Neue Dateien erhalten 0600; keine Symlinks. Das Werkzeug überschreibt keine Ausgabe.

```sh
umask 077
php tests/documents/gobd-archive-inventory.php \
  --config="$READ_ONLY_CONFIG" --base="$INSTALLATION" \
  --client="$CLIENT" --operator-user="$OPERATOR_USER" \
  --output="$PRIVATE_WORK/registered-archive.jsonl"
```

Ohne Datumsfilter werden alle registrierten PDF-Belege aller acht Arten berücksichtigt, einschließlich vorhandener gelöschter oder undatierter Datensätze. Optional **beide** Parameter `--from="$FROM" --to="$TO"` ergänzen. Einkaufsbelege bleiben Bestandteil der internen Vorprüfung, aber außerhalb der externen Ausgangsbeleglieferung und der automatisch vorbereiteten Verkaufssammlung.

Der Sammler verwendet die vorhandene Archiv-/Berechtigungsprüfung. Er prüft kanonische Pfade, Version, Größe und SHA. Gesperrte Registrierungen werden separat berichtet; unregistrierte Dateien oder Rekonstruktionen werden nicht als Originale hinzugefügt. Nur ein vollständiger JSONL-Bericht mit gültiger Schlusszeile wird im nächsten Schritt akzeptiert.

## 2. Privates Prüfprofil vorbereiten

```sh
python3 tests/documents/gobd-issuer-preflight.py \
  --action=prepare-profile --client="$CLIENT" \
  --inventory="$PRIVATE_WORK/registered-archive.jsonl" \
  --output="$PRIVATE_WORK/profile-template.json"
```

In einer privaten Kopie als `$PRIVATE_WORK/issuer-profile.json` aus belastbaren Betreiberunterlagen ergänzen:

- `expected_issuer.legal_name`: tatsächlicher erwarteter juristischer Name.
- `expected_issuer.identifiers`: unabhängige ausreichend eindeutige Kennzeichen, etwa USt-ID oder Registerangabe einschließlich nötiger Einordnung. Keine einzelne Ziffer oder bloße Kundennummer.
- `foreign_issuers`: bekannte andere juristische Aussteller mit demselben Aufbau, sofern vorhanden.

Mandant und Kontextfingerprint sind bereits gebunden. Die leere Vorlage nimmt keinen Firmennamen aus `clientid` als juristisch bestätigt an. Unterschiedliche historische Namen können in getrennten, fachlich begründeten Profil-Läufen geprüft werden; Textcache und vorhandene Nachweise bleiben wiederverwendbar. Ein Profil selbst ist keine Freigabe.

## 3. Vorprüfen und Auffälligkeiten priorisieren

```sh
python3 tests/documents/gobd-issuer-preflight.py \
  --action=scan --client="$CLIENT" \
  --inventory="$PRIVATE_WORK/registered-archive.jsonl" \
  --profile="$PRIVATE_WORK/issuer-profile.json" \
  --cache="$PRIVATE_WORK/issuer-text-cache.sqlite" \
  --issuer-review="$PRIVATE_ISSUERS/$CLIENT.json" \
  --output="$PRIVATE_WORK/archive-screening.json"
```

`--issuer-review` beim ersten Lauf weglassen, wenn noch keine Datei existiert. `--profile` kann fehlen; ohne belastbare vorhandene Einzel-/Scope-Nachweise entstehen dann ausschließlich unklare Fälle, keine automatische Freigabe. Das Cacheziel muss eine neue Datei oder ein von diesem Werkzeug angelegter Cache sein.

Der Bericht enthält eigene Zusammenfassungen sowie Verweise und SHA-Werte für:

- `archive-screening.json.results.jsonl`: alle konkreten Fälle, Identitäten, PDF-SHA und Befunde.
- `archive-screening.json.priority.csv`: zu klärende Fälle, zuerst ungültige Archive/bestätigte Fremdaussteller, dann widersprüchliche Textbefunde und unklare Fälle.

| Einstufung | Aussage |
| --- | --- |
| `confirmed_foreign_issuer` | Bereits ausdrücklich bestätigte Fremdausstellerentscheidung, an diese Datei gebunden. Gesperrt. |
| `invalid_archive` | Pfad-/Integritäts-/Registrierungsproblem oder nicht freigegebene Archivrekonstruktion. Kein Sammelkandidat. |
| `contradictory_issuer_evidence` | Name und unabhängiges Kennzeichen eines anderen Ausstellers in derselben seitenbezogenen Fußregion gefunden. Ein priorisierter Verdacht, keine automatische juristische Feststellung. |
| `unclear` | Kein ausreichend zuordenbarer Befund, Scan ohne Text, fehlendes Profil oder fehlgeschlagene Texterkennung. Manuell klären. |
| `unobtrusive_candidate` | Erwarteter Name und unabhängiges Kennzeichen in derselben Fußregion gefunden, keine entsprechende Gegenanzeige. **Nur Prüfkandidat**, keine juristische oder manuelle Bestätigung. |
| `confirmed_same_issuer` | Ein tatsächlich vorhandener Einzel- oder Scope-Nachweis wurde SHA-/identitätsgenau wiederverwendet. Die Nachweismethode bleibt im Bericht unterscheidbar. |

Empfängerangaben und Layout können Fehlalarme oder fehlende Treffer verursachen. Das Verfahren entscheidet niemals allein aus Kundennamen, Dateiname oder Mandant. Der Cache speichert ausschließlich Texterkennung nach PDF-SHA und Werkzeugversion. Profil und Entscheidungen werden bei jedem Lauf neu beurteilt; der Cache ist keine Freigabedatenbank. Vorhandene bestätigte Erklärungen ersparen eine erneute Texterkennung. Bei Wiederholung denselben Cache und **neue Ausgabe-Dateinamen** verwenden.

## 4. Einen benannten Sammelumfang vorbereiten und tatsächlich bestätigen

```sh
python3 tests/documents/gobd-issuer-preflight.py \
  --action=prepare-batch --client="$CLIENT" \
  --screening-report="$PRIVATE_WORK/archive-screening.json" \
  --issuer-review="$PRIVATE_ISSUERS/$CLIENT.json" \
  --scope-output="$PRIVATE_WORK/issuer-scope.json" \
  --output="$PRIVATE_WORK/issuer-review-pending.json"
```

Ohne bestehende Erklärungsdatei `--issuer-review` weglassen. Bekannte Entscheidungen und ältere Scopes bleiben erhalten. Nur neue unauffällige Verkaufskandidaten werden aufgenommen; ungeklärte, auffällige, ungültige oder schon bestätigte Fälle werden nicht automatisch in den neuen Umfang gemischt. Die Scope-Datei nennt jede Identität und jede PDF-SHA. Ist kein neuer geeigneter Umfang vorhanden, wird keine leere Freigabe erzeugt.

Die neue Erklärung unter `batches` ist zunächst ausdrücklich `unresolved`, mit leeren Prüfer-/Zeit-/Basis-/Ausstellerfeldern und unbestätigten Merkmalen. Eine zuständige Fachperson darf sie erst nach tatsächlicher Prüfung der unabhängigen Herkunftsnachweise für **genau diesen gesamten benannten Umfang** ergänzen:

- `decision=confirmed_same_issuer`.
- Tatsächliche `reviewed_by`, `reviewed_at` mit Zeitzonenoffset, `basis` und `actual_issuer`.
- `scope_reviewed=true`, `issuer_compared_with_tenant=true` und `independent_issuer_evidence_confirmed=true` ausschließlich, wenn diese Aussagen tatsächlich belegt sind.

Eine Textauswertung oder unauffällige Stichprobe allein bestätigt nicht sämtliche Dateien. Fehlt ein tragfähiger Nachweis für den ganzen Umfang, den Scope nicht bestätigen; Auffälligkeiten über die bestehenden dokumentbezogenen Erklärungen klären. Keine historische Einzelvollprüfung oder Papierprüfung behaupten. Die Software erzeugt diese positiven Angaben nicht selbst.

Die tatsächlich bestätigte Erklärung nach Sicherung der bisherigen Datei kontrolliert unter `$DEWAWI_GOBD_ISSUER_REVIEW_DIR/$CLIENT.json` bereitstellen. Die referenzierte Scope-Datei unverändert mit 0600 dauerhaft aufbewahren und sichern; ihr absoluter privater Pfad und SHA sind Teil der Erklärung. `scope_attested` bleibt im Export ausdrücklich von `manually_verified` als Einzelvollprüfung getrennt. Bestehende Original-, Recovery- und historische Versionsprüfungen bleiben unverändert vorgeschaltet.

## 5. Export und separate Freigabe der konkreten Übergabe

Den normalen GoBD-Export in `/admin/export` mit PDFs erstellen. Die bestehende [private Konfiguration und Paketprüfung](gobd-package.md) bleibt erforderlich. ZIP, Liefernachweis und die exakt verwendete Erklärungs-/Scope-Version unverändert privat sichern. Fachliche CSVs, README, Metadaten und Strukturinformationen bleiben erhalten; bestätigte Fremdaussteller-PDFs werden weiterhin nicht geliefert. Ungeklärte Aussteller erscheinen als dokumentierte Lieferlücken.

Zuerst den finalen technischen Preflight durchführen:

```sh
python3 tests/documents/gobd-package.py \
  --action=preflight --client="$CLIENT" \
  --zip="$PRIVATE_WORK/final-export.zip" \
  --verification="$PRIVATE_WORK/delivery-verification.json" \
  --issuer-review="$PRIVATE_WORK/issuer-review-used.json" \
  --output="$PRIVATE_WORK/final-preflight.json"

python3 tests/documents/gobd-issuer-preflight.py \
  --action=prepare-release --client="$CLIENT" \
  --preflight="$PRIVATE_WORK/final-preflight.json" \
  --output="$PRIVATE_WORK/package-release-pending.json"
```

Auch diese Vorlage ist unbestätigt. Die Fachperson beurteilt die vollständige konkrete Lieferung, alle im gebundenen Preflight genannten Fehlstellen/Unsicherheiten und die tatsächliche Übergabefähigkeit. Nur nach dieser gesonderten Entscheidung in einer privaten Kopie `package-release.json` die tatsächlichen Angaben `reviewed_by`, `reviewed_at`, `basis`, `approved_for_delivery=true` und `limitations_acknowledged=true` ergänzen. Scope- und Paketfreigabe sind verschiedene Handlungen; das Vorliegen einer Ausstellererklärung erteilt keine Paketfreigabe.

```sh
python3 tests/documents/gobd-issuer-preflight.py \
  --action=verify-release --client="$CLIENT" \
  --zip="$PRIVATE_WORK/final-export.zip" \
  --verification="$PRIVATE_WORK/delivery-verification.json" \
  --issuer-review="$PRIVATE_WORK/issuer-review-used.json" \
  --preflight="$PRIVATE_WORK/final-preflight.json" \
  --current-issuer-review="$PRIVATE_ISSUERS/$CLIENT.json" \
  --release="$PRIVATE_WORK/package-release.json" \
  --output="$PRIVATE_WORK/package-release-verified.json"
```

Die Prüfung wiederholt den technischen Preflight, bindet Paket-SHA und ursprünglichen Prüfbericht und sperrt bestätigte Fremdaussteller, falsche Zuordnungen oder geänderte Bytes. Zusätzlich wird der aktuelle Entscheidungsstand aus `--current-issuer-review` geprüft. Eine inzwischen bestätigte Fremdausstellerentscheidung oder fehlende aktuelle Bestätigung sperrt die Freigabe auch dann, wenn die unveränderte ursprüngliche Erklärungsdatei noch positiv war. Erforderlichenfalls neu exportieren; die aktuelle Datei darf nicht durch eine alte Kopie ersetzt werden. Es werden weder Pakete geändert noch offene PDF-Aussteller nachträglich positiv umetikettiert. Eine bewusste fachliche Freigabe einer unvollständigen Lieferung verschweigt deren Fehlstellen nicht.

Exitcodes: `1` für Vorprüfung/Vorlagen mit fachlicher Prüferfordernis, `2` für gesperrte/fehlerhafte Vorgänge, `0` ausschließlich für die erfolgreich nachgeprüfte ausdrückliche Paketfreigabe. Das Ergebnis ist keine rechtliche Konformitätsbestätigung. Alle positiven Erklärungen, Quellen und Entscheidungen bleiben in der Verantwortung der zuständigen Fachperson.
