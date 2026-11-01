# DEWAWI – Technischer Modernisierungsbericht

Stand: 1. November 2026

## 1. Zweck und Geltungsbereich

Dieser Bericht bewertet die 70 Anforderungen zur technischen Modernisierung der öffentlichen DEWAWI-Websites und dokumentiert die bereits umgesetzte technische Basis. Die Bewertung bezieht sich auf das gesamte mandantenfähige DEWAWI-System und enthält bewusst keine installationsspezifischen Domains, Zugangsdaten oder Serverwerte.

Geprüft wurden der PHP-/Zend-Framework-1-Code, öffentliche Shop- und CMS-Templates, Konfigurationen, Installations- und Migrationsdateien, vorhandene Sicherheitsmechanismen sowie automatisierte Tests. Nicht geprüft oder verändert wurden produktive Server, DNS, Zertifikate, externe Tracking-Konten, Webmaster-Werkzeuge, reale Kundendaten und produktive Datenbanken.

## 2. Ergebnisübersicht

- Die HTML-Ausgabe wurde auf HTML5 umgestellt und veraltete Content-Type-Metaelemente wurden entfernt.
- Die Suchmaschinenfreigabe wird mandantenbezogen über `shop.indexable` gesteuert. Neue Shops sind standardmäßig nicht indexierbar.
- Entwicklungs- und Staging-Umgebungen bleiben unabhängig von der Shop-Einstellung nicht indexierbar.
- Nicht indexierbare Websites erhalten Robots-Meta und `X-Robots-Tag`; Canonical-Ausgabe und öffentlich konfiguriertes Analytics-Markup werden unterdrückt.
- Neue und ausdrücklich geänderte öffentliche Slugs werden zentral normalisiert und vor dem Speichern auf Pfadkonflikte sowie reservierte Systemrouten geprüft.
- Interne DEWAWI-Seiten erhalten grundlegende Sicherheits- und Noindex-Header.
- Eine eigene Staging-Konfiguration wurde vorbereitet; Staging darf nicht stillschweigend die Produktionsdatenbank verwenden.
- Automatische historische Pfade werden mandanten- und websitebezogen gespeichert und per HTTP 301 auf die jeweils aktuelle öffentliche Entitäts-URL weitergeleitet.
- `robots.txt`, Sitemap und Canonicals verwenden dieselbe validierte, mandantenbezogene Shop-URL; nicht indexierbare Websites sperren Crawling zusätzlich dynamisch.
- TLS, bekannte Alt-URL-Importe, Consent Management, responsive Bilder, Asset-Modernisierung und mehrere Relaunch-Prüfungen benötigen weitere Umsetzungsphasen oder Infrastrukturzugriff.

## 3. Status- und Prioritätsdefinitionen

| Kennzeichnung | Bedeutung |
|---|---|
| Umgesetzt | Die Anforderung ist für den derzeit vorhandenen Funktionsumfang im Repository umgesetzt. |
| Teilweise | Eine geeignete Grundlage existiert, die Anforderung ist aber noch nicht durchgängig abgeschlossen. |
| Offen | Im Repository wurde keine ausreichende Umsetzung gefunden. |
| Infrastruktur | Umsetzung oder Verifikation benötigt Server-, Deployment- oder externen Systemzugriff. |
| Derzeit nicht anwendbar | Die genannte Funktion existiert noch nicht; die Anforderung wird bei ihrer Einführung verbindlich. |

Prioritäten: P0 = Sicherheits-/Relaunch-Blocker, P1 = nächste Umsetzungsphase, P2 = geplante Qualitätsverbesserung, P3 = spätere Optimierung.

## 4. Bewertung aller 70 Anforderungen

| Nr. | Status | Priorität | Befund und nächste Maßnahme |
|---:|---|---|---|
| 1 | Infrastruktur | P0 | Im Repository ist keine vollständige TLS- und HTTP-zu-HTTPS-Konfiguration enthalten. Zertifikate pro öffentlicher Domain einrichten, HTTP am Webserver oder Proxy per 301 auf HTTPS umleiten und anschließend einen Mixed-Content-Crawl durchführen. HSTS erst nach erfolgreicher Stabilisierung aktivieren. |
| 2 | Umgesetzt | P0 | `shop.indexable` steuert die Freigabe pro Shop. Development und Staging bleiben immer ausgeschlossen. Neue Shops starten mit `indexable = 0`; die Migration lässt bestehende Shops aus Kompatibilitätsgründen zunächst freigegeben. Noindex ist kein Zugriffsschutz; bei Bedarf zusätzlich Authentifizierung einsetzen. |
| 3 | Umgesetzt | P0 | Nicht indexierbare Websites geben keine Canonical-URL aus. Vor dem Livegang muss `shop.url` die endgültige HTTPS-Produktionsadresse enthalten; anschließend alle Seitentypen prüfen. |
| 4 | Umgesetzt | P1 | Bootstrap, Anwendungslayouts, Login, Fehlerausgabe und Installer verwenden HTML5. Fremde Demo-/Vendor-Dateien wurden nicht verändert. |
| 5 | Umgesetzt | P1 | Veraltete Content-Type-Metaelemente wurden aus den anwendungseigenen Dokumenten entfernt; `<meta charset="UTF-8">` bleibt erhalten. |
| 6 | Umgesetzt | P0 vor Relaunch | Neue und ausdrücklich geänderte öffentliche Slugs werden zentral nach `ae/oe/ue/ss` normalisiert. Vollständige verschachtelte Pfade werden je Mandant und Website auf Kollisionen und reservierte Systemrouten geprüft. Bestehende Slugs bleiben ohne ausdrückliche URL-Änderung unverändert; es gab keine Massenmigration. |
| 7 | Teilweise | P1 | Generierte öffentliche Pfade verwenden aktuell keine abschließenden Schrägstriche. Diese Strategie für neue URLs beibehalten und nach einem Routen-Audit eine serverseitige 301-Normalisierung ergänzen, ohne Dateien, Startseite oder Legacy-Routen zu beschädigen. |
| 8 | Infrastruktur | P0 vor Relaunch | Die kanonische Domain kommt mandantenbezogen aus `shop.url`; eine globale Host-Weiterleitung existiert nicht. Pro öffentlicher Website eine Hauptdomain festlegen und alternative Hosts nach DNS-/TLS-Prüfung per 301 umleiten. |
| 9 | Teilweise | P0 vor Relaunch | Ausdrückliche Änderungen öffentlicher Seiten-, Kategorie-, Produkt- und Tag-Pfade erzeugen automatisch mandanten- und websitebezogene 301-Weiterleitungen. Ziele werden bei jedem Abruf aus der aktuellen öffentlichen Entität ermittelt. Ein fachlich geprüfter Import bekannter Alt-URLs und eine manuelle Admin-Zuordnung bleiben offen. |
| 10 | Offen | P0 vor Relaunch | Historische Doppelungen wurden nicht vollständig inventarisiert. Jede bekannte Alt-URL einzeln der fachlich passenden neuen Seite zuordnen und aus Navigation sowie Sitemap entfernen; die neue Redirect-Tabelle kann anschließend als technische Grundlage dienen. |
| 11 | Teilweise | P1 | Seiten und Kategorien unterstützen `metatitle` und `metadescription`; einzelne Entitäten verwenden nur Fallbacks. Metadatenfelder und Fallback-Regeln pro öffentlichem Seitentyp vervollständigen und auf Duplikate prüfen. |
| 12 | Teilweise | P1 | CMS-Blöcke und Views enthalten semantische Überschriften, jedoch erlauben Legacy-WYSIWYG-Felder beliebige H1–H6. Pro Template eine H1-Quelle definieren, Komponentenebenen begrenzen und gerenderte Seiten automatisiert prüfen. |
| 13 | Teilweise | P1 | Mega-Menü-Schaltflächen, `aria-expanded`, Escape-Behandlung und Fokusdarstellung sind teilweise vorhanden. Fokusrückgabe, gegebenenfalls Pfeiltastensteuerung sowie Browser-/Tastaturtests ergänzen. |
| 14 | Teilweise | P1 | Bootstrap-Collapse und mobile CSS-Regeln existieren, aber keine vollständige Regression für reale Menütiefen. Finale Navigation auf Telefon und Tablet testen und bei Bedarf als Akkordeon mit sauberer Fokussteuerung ausbauen. |
| 15 | Teilweise | P2 | Suche und Ergebnisformular besitzen Label, GET-Parameter und eine sinnvolle Ergebnisroute. Falls das Design nur ein Icon zeigt, eine zugängliche, fokussierte und per Escape schließbare Suchoberfläche ergänzen. |
| 16 | Umgesetzt | P1 | Suchparameter sind begrenzt, Datenbankabfragen parametrisiert, Ausgaben escaped und Ergebnislisten auf `noindex, follow` gesetzt. Regressionstests beibehalten. |
| 17 | Teilweise | P0 vor Relaunch | Leere CMS-Ziele werden teilweise ausgelassen; Legacy-Steuerelemente und Fallback-Navigation enthalten noch `href="#"`. Bedienelemente als Buttons auszeichnen und vor Livegang einen vollständigen internen Link-Crawl ausführen. |
| 18 | Teilweise | P1 | Keine versionierte Referenz auf das bekannte Platzhalterbild gefunden. Reale Mandantenmedien wurden nicht geprüft; Alt-Texte und Bildmetadaten sind nicht überall vollständig. Medienbestand vor Livegang auditieren. |
| 19 | Offen | P1 | Öffentliche Templates erzeugen noch keine systematischen `srcset`-/`sizes`-Angaben. Medienvarianten und einen wiederverwendbaren responsiven Bild-Renderer ergänzen. |
| 20 | Offen | P2 | Es existiert keine WebP-/AVIF-Derivatpipeline. Nach Klärung von Medienspeicherung und Backup-Regeln moderne Derivate mit Fallback-Ausgabe ergänzen. |
| 21 | Teilweise | P1 | Einzelne feste Assets reservieren Abmessungen, die meisten Inhalts- und Produktbilder nicht. Bildmaße speichern/auslesen und `width`/`height` oder `aspect-ratio` ausgeben. |
| 22 | Offen | P1 | Lazy Loading wird in öffentlichen Inhaltsbildern nicht durchgängig eingesetzt. Unterhalb des sichtbaren Bereichs `loading="lazy"` ergänzen; LCP-/Hero-Bilder explizit ausnehmen. |
| 23 | Offen | P1 | Hero-/Slide-Ausgabe setzt keine LCP-Priorisierung oder responsiven Quellen. Pro Seitentyp ein LCP-Bild definieren, früh ausgeben, korrekt dimensionieren und mit realen Inhalten messen. |
| 24 | Teilweise | P1 Sicherheit | Öffentliche Seiten verwenden Bootstrap 4.5.2, jQuery 3.5.1 und ein externes Bootstrap-Bundle; das ERP besitzt tiefere Legacy-Abhängigkeiten. Sicherheits-/Kompatibilitätsinventar erstellen und öffentliche Assets inkrementell aktualisieren. |
| 25 | Offen | P1 Sicherheit | Externe CDN-Tags besitzen weder SRI noch `crossorigin`. Bevorzugt geprüfte Assets selbst hosten oder exakte Integritätswerte und Ausfalltests ergänzen. |
| 26 | Offen | P2 | Nicht kritische Scripts werden noch nicht systematisch mit `defer` geladen. Abhängigkeitsreihenfolge und Inline-Code zuerst prüfen, dann kompatible Scripts verzögert laden. |
| 27 | Offen | P2 | Keine dokumentierte CSS-Bereinigung, Minifizierung oder cache-gebundene Asset-Versionierung. Nutzung messen, öffentliche und interne Bundles getrennt optimieren und reproduzierbare Builds vorsehen. |
| 28 | Infrastruktur | P1 | Es liegen keine Messungen mit finalen Medien und Inhalten vor. Mobile LCP-, CLS- und INP-Werte auf Staging messen und Reports als Relaunch-Nachweis archivieren. |
| 29 | Offen | P1 | `navbar-expand-xl` ist fest gesetzt; eine Prüfung mit finalen Menüpunkten fehlt. Breakpoint anhand realer Breiten testen und gegebenenfalls mandantenbezogen anpassen. |
| 30 | Teilweise | P1 | Footerlinks werden als Liste ausgegeben, aber nicht vollständig als beschriftete Navigation. `<nav aria-label="Footer">` ergänzen und Erreichbarkeit aller rechtlichen sowie Consent-Ziele je Website prüfen. |
| 31 | Offen | P0 vor Tracking | Ein vollständiges Consent-System mit Zustimmen, Ablehnen und späterem Ändern existiert nicht. Vor optionalem Tracking eine mandantenfähige Einwilligungsverwaltung implementieren. |
| 32 | Teilweise | P0 vor Tracking | Öffentliches Analytics-Markup wird bei nicht indexierbaren Websites unterdrückt; internes Analytics läuft nur in Production. In indexierbarer Produktion fehlt weiterhin Consent-Gating. Dienste klassifizieren und optionale Tags erst nach Einwilligung laden. |
| 33 | Teilweise | P0 | Öffentliche Kontakt-, Checkout- und Anfragewege besitzen serverseitige Validierung, CSRF-Schutz, Mandantenbezug und sichere Fehlerbehandlung; Spam-/Rate-Limit-Schutz ist nicht überall einheitlich. Alle schreibenden Formulare inventarisieren und standardisieren. |
| 34 | Derzeit nicht anwendbar | P0 bei Einführung | Ein öffentlicher Partnerzugang wurde nicht gefunden. Partnerauthentifizierung getrennt von ERP-Benutzern entwerfen und vorhandene sichere Session-Komponenten wiederverwenden. |
| 35 | Derzeit nicht anwendbar | P1 bei Einführung | Kein öffentlicher Partnerkonto-Lebenszyklus vorhanden. Bei Einführung einmalige, ablaufende Reset-/Verifikationstokens und nicht verratende Antworten implementieren. |
| 36 | Teilweise | P0 | Vorhandene geschützte Dateien werden über authentifizierte, mandanten- und berechtigungsgeprüfte Controller ausgeliefert. Dieses Muster für jeden zukünftigen Partnerdownload wiederverwenden und negativ testen. |
| 37 | Teilweise | P0 | Direkter Zugriff auf vorhandene geschützte Dateien wird verhindert; ein partnerspezifischer Speicher existiert nicht. Partnerdokumente außerhalb statischer Auslieferung halten und nur nach serverseitiger Autorisierung senden. |
| 38 | Teilweise | P2 | Kontaktanfragen speichern bereits einen Quellpfad. Für eine spätere Registrierung ein validiertes First-Party-Quellfeld wiederverwenden; keine beliebigen externen URLs übernehmen. |
| 39 | Offen | P0 bei Einführung | Kein Partner-Marketing-Consent-Datensatz vorhanden. Registrierung und freiwillige Marketingeinwilligung getrennt mit Textversion, Zeitpunkt und Quelle speichern. |
| 40 | Teilweise | P1 | Produkt-JSON-LD existiert in einzelnen Ansichten. `Organization` und `BreadcrumbList` fehlen; vorhandenes Product-Markup muss semantisch geprüft werden. Nur vollständige, passende strukturierte Daten ausgeben. |
| 41 | Teilweise | P1 | Visuelle Breadcrumbs existieren; `BreadcrumbList` fehlt. Dieselben aufgelösten Breadcrumb-Daten für HTML und JSON-LD verwenden. |
| 42 | Umgesetzt | P0 vor Relaunch | Die Sitemap verwendet ausschließlich die validierte kanonische Shop-URL und aktuell öffentliche, mandanten- und websitebezogene Entitätspfade. Duplikate, deaktivierte Features, gelöschte Ziele, Suche und historische Redirects bleiben ausgeschlossen. Nicht indexierbare oder falsch konfigurierte Websites liefern HTTP 404. |
| 43 | Umgesetzt | P1 | `robots.txt` wird pro Website dynamisch ausgeliefert. Indexierbare Websites erlauben Crawling und verweisen bei gültiger kanonischer URL auf die Sitemap; nicht indexierbare Umgebungen liefern `Disallow: /`. CSS-/JS-Pfade werden in freigegebenen Websites nicht blockiert. Robots bleibt ausdrücklich kein Zugriffsschutz. |
| 44 | Teilweise | P1 | Fehlende Routen und Entitäten liefern echte 404-Antworten; die Seite ist noch generisch. Eine hilfreiche öffentliche 404-Seite mit Navigation, Suche und Kontakt ergänzen, ohne den Statuscode zu verändern. |
| 45 | Teilweise | P0 | 403/404/500 werden explizit behandelt; bekannte automatisch gespeicherte Altpfade liefern 301, unbekannte Pfade weiterhin 404. Eine fachliche 410-Strategie und vollständige Statusprüfungen im Relaunch-Crawl fehlen. |
| 46 | Infrastruktur | P1 | Anwendungscache ist vorhanden; eine geprüfte statische Cache-Control-/Expires-Strategie ist nicht im Repository abgebildet. Versionierte Assets serverseitig langfristig cachen; Seitencache nur mit mandanten-, Consent- und Session-sicheren Schlüsseln. |
| 47 | Infrastruktur | P1 | Keine gzip-/Brotli-Konfiguration im Repository. Kompression am Webserver oder Proxy aktivieren und Content-Types, `Vary` sowie bereits komprimierte Formate prüfen. |
| 48 | Infrastruktur | P1 | HTTP/2-/HTTP/3-Unterstützung ist eine Server-/Proxy-Eigenschaft. HTTP/2 nach TLS prüfen; HTTP/3 nur bei unterstützter Infrastruktur und messbarem Nutzen aktivieren. |
| 49 | Teilweise | P0/P1 | Global werden `X-Content-Type-Options`, `Referrer-Policy` und eine konservative `Permissions-Policy` gesetzt. Interne Seiten erhalten zusätzlich SAMEORIGIN und CSP `frame-ancestors 'self'`. Öffentliche CSP-/Frame-Regeln erst nach Abhängigkeits- und Embedding-Audit festlegen. |
| 50 | Infrastruktur | P1 nach TLS | HSTS ist bewusst noch nicht gesetzt. Nach vollständiger HTTPS-Stabilisierung mit kurzer Laufzeit beginnen; `includeSubDomains` erst nach Subdomain-Audit verwenden. |
| 51 | Offen | P1 | Keine allgemeine Open-Graph-/Social-Metadaten-Unterstützung gefunden. Escapte Felder/Fallbacks je Entität und validierte absolute Bild-URLs ergänzen. |
| 52 | Teilweise | P2 | Ein klassisches Favicon existiert, moderne Icon-Größen und mandantenfähige Konfiguration fehlen. Icons je Website ermöglichen; Manifest nur bei tatsächlich gewünschtem App-Verhalten ergänzen. |
| 53 | Teilweise | P2 | Sprachfelder und Übersetzungen existieren, aber kein vollständiges öffentliches Locale-Routing und kein `hreflang`. URL-Eigentümerschaft pro Sprache zuerst entwerfen, anschließend gegenseitige Self-/Hreflang-Links ausgeben. |
| 54 | Für aktuellen Umfang umgesetzt | P2 | Es existiert kein Mechanismus für automatisch generierte Maschinenübersetzungs-URLs. Zukünftige Sprachen über redaktionelle Inhalte und stabile Locale-Pfade abbilden. |
| 55 | Teilweise | P1 | Semantik, Labels und Fokuszustände sind teilweise vorhanden; ein vollständiger WCAG-Audit fehlt. Automatisierte und manuelle Prüfungen für Tastatur, Screenreader, Kontrast und Fehlerzustände durchführen. |
| 56 | Teilweise | P1 | Manche Bilder verwenden beschreibende Titel oder leere Alt-Texte für Dekoration, andere Werte sind uneinheitlich. Dekorativ/informativ strukturiert kennzeichnen und reale Inhalte auditieren. |
| 57 | Teilweise | P2 | Zeichen-Icons besitzen teilweise Screenreader-Text, bleiben aber typografische Symbole. Durch wiederverwendbare SVG-Icons ersetzen und zugängliche Beschriftungen erhalten. |
| 58 | Teilweise | P1 | Strukturierte CMS-Blöcke existieren, gleichzeitig bleiben Legacy-WYSIWYG-Felder. Neue Inhalte nur über typisierte Komponenten pflegen und Bestandsinhalte schrittweise migrieren. |
| 59 | Umgesetzt | P1 | `DEEC_Site_Block` und Partials bilden wiederverwendbare Hero-, CTA-, Text-, Bild/Text-, Karten-, Kontakt- und Kategorieblöcke ab. Nur bei konkretem Wiederverwendungsbedarf erweitern. |
| 60 | Teilweise | P1 | Wichtige Blöcke besitzen getrennte Felder und Allowlists; generische HTML-/WYSIWYG-Felder bestehen weiterhin. Feldlimits und Medienauswahl ausbauen und Inhalte inkrementell migrieren. |
| 61 | Offen | P0 vor Relaunch | Einzeltests existieren, aber kein vollständiger Crawl für Links, Status, Titel, H1, Canonicals und Redirect-Ketten. Einen sicheren Staging-Crawl als reproduzierbaren Relaunch-Report implementieren. |
| 62 | Umgesetzt | P0 vor Relaunch | Redirects speichern keine weiteren Pfadziele, sondern die öffentliche Entität. Dadurch werden auch nach mehrfachen URL-Änderungen alle historischen Pfade direkt auf den aktuellen kanonischen Pfad aufgelöst; aktuelle URLs haben Vorrang und Schleifen werden verworfen. |
| 63 | Infrastruktur | P1 nach Relaunch | Search-Console-/Webmaster-Zustand liegt außerhalb des Repositories. Eigentum prüfen, Produktionssitemap einreichen und Indexierung sowie 404 nach dem Relaunch beobachten. |
| 64 | Teilweise | P0 vor Relaunch | Analytics ist konfigurierbar und jetzt umgebungs-/indexierungsabhängig, aber weder consent-gesteuert noch durch eine Conversion-Migrationsliste abgesichert. Bestehende Ereignisse inventarisieren und einzeln validieren. |
| 65 | Derzeit nicht anwendbar | P1 bei Einführung | Kein öffentlicher Partnerzugang vorhanden. Datenschutzgerechte Events für Start, Erfolg, Login, autorisierten Download und Anfrage definieren, ohne sensible Daten zu übertragen. |
| 66 | Teilweise | P0 | Production, Staging und Development sind als Anwendungsprofile vorhanden. Staging benötigt eine explizite, von Production abweichende Datenbankkonfiguration. Getrennte Hosts, Secrets, Mailziele und Deployments bleiben operative Aufgaben. |
| 67 | Offen | P0 | Nicht produktives Analytics wird unterdrückt, zentrale Mailfunktionen können aber weiterhin konfiguriertes SMTP verwenden. Eine verbindliche Nicht-Produktions-Mailstrategie mit Deaktivierung oder Allowlist/Test-Sink ergänzen. |
| 68 | Infrastruktur | P0 vor Relaunch | Kein vollständiger Backup-/Rollback-Ablauf im Repository dokumentiert. Anwendung, Datenbank, Medien und Redirects sichern und objektive Rollback-Kriterien erproben. |
| 69 | Infrastruktur | P1 vor Relaunch | Keine externe Überwachung für Erreichbarkeit, Zertifikatsablauf, 5xx, Formulare oder Login gefunden. Synthetische Checks und klare Alarmverantwortung einrichten. |
| 70 | Teilweise | P1 Entscheidung | Öffentliche Seiten bleiben an Bootstrap 4 und jQuery 3 gebunden; das ERP besitzt zusätzliche ältere jQuery-Abhängigkeiten. Öffentlichen Bereich inkrementell modernisieren und keinen vollständigen Backend-Rewrite mit dem Relaunch koppeln. |

## 5. Bereits implementierte Änderungen

### 5.1 HTML5-Grundlage

- Zend-View-Doctype von XHTML Strict auf HTML5 umgestellt.
- Anwendungslayouts, Login, Fehlerseite und Installer auf `<!doctype html>` und ein einfaches `lang`-Attribut umgestellt.
- Redundante Content-Type-Metaelemente entfernt und UTF-8-Charset explizit gesetzt.
- Interne Layouts, Login, Fehlerausgabe und Installer mit Robots-Noindex versehen.

### 5.2 Mandantenbezogene Indexierungssteuerung

- Neue Datenbankspalte `shop.indexable` angelegt.
- Neue Shops und Neuinstallationen verwenden standardmäßig `0`.
- Bestehende Shops erhalten bei der Migration zunächst `1`, damit veröffentlichte Websites nicht unbeabsichtigt deindexiert werden; danach wird der Spaltenstandard auf `0` gesetzt.
- Einstellung im Shop-Formular unter „Suchmaschinen-Indexierung“ verfügbar.
- Die effektive Freigabe entsteht aus `APPLICATION_ENV === production` und `shop.indexable = 1`.
- Fehlt die Spalte in einer noch nicht migrierten Installation, bleibt das bisherige Produktionsverhalten aus Kompatibilitätsgründen erhalten.

### 5.3 Verhalten nicht indexierbarer Websites

- Robots-Meta: `noindex, nofollow`.
- HTTP-Header: `X-Robots-Tag: noindex, nofollow`.
- Keine Canonical-Ausgabe.
- Keine Sitemap-Ausgabe; der Sitemap-Endpunkt antwortet mit HTTP 404.
- Kein öffentlich im Shop konfiguriertes Analytics-Markup.
- Die Regel enthält keine fest eingetragenen Hosts und ist für alle Mandanten wiederverwendbar.

### 5.4 Sicherheitsheader

- Global: `X-Content-Type-Options: nosniff`.
- Global: `Referrer-Policy: strict-origin-when-cross-origin`.
- Global: `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- Intern: `X-Frame-Options: SAMEORIGIN`.
- Intern: `Content-Security-Policy: frame-ancestors 'self'`.
- Intern: `X-Robots-Tag: noindex, nofollow`.

Eine umfassende Script-/Style-CSP ist noch nicht aktiviert, weil Inline-Code, externe Assets und dynamisches Analytics zuerst inventarisiert werden müssen. Öffentliche Frame-Regeln bleiben offen, bis erlaubte Einbettungsszenarien je Mandant geklärt sind.

### 5.5 Staging-Trennung

- Eigenes `staging`-Anwendungsprofil ergänzt.
- Staging benötigt eine explizite Datenbanksektion.
- Eine mit Production identische Staging-Datenbankkonfiguration beendet den Start mit einem Fehler.
- Der bisherige Development-Fallback auf eine vorhandene Production-Sektion bleibt aus Rückwärtskompatibilität erhalten.

### 5.6 Einheitliche Slug-Erzeugung

- `DEEC_Filter::slug()` transliteriert deutsche Umlaute und `ß` verbindlich zu `ae`, `oe`, `ue` und `ss`, vereinheitlicht Trennzeichen und entfernt unerwünschte Sonderzeichen.
- Die zentralen Slug-Speicherwege normalisieren neue oder ausdrücklich geänderte Werte und verwenden bei leerem Ergebnis weiterhin die Entitäts-ID als deterministischen Fallback.
- Seiten, Kategorien, Tags, Menüpunkte, Artikel und Artikelimporte verwenden den zentralen Speicherweg; die separate Artikel-Normalisierung wurde entfernt.
- Kollisionen werden anhand des vollständigen öffentlichen Pfads innerhalb derselben Website erkannt. Verschachtelte Seiten, Kategorien und Tags sowie Artikel unter Shopkategorien werden berücksichtigt.
- Die Prüfung ist mandanten- und websitebezogen. Gleiche Pfade in unterschiedlichen Websites bleiben zulässig; fremde Website-IDs werden abgewiesen.
- Reservierte Such-, Sitemap-, Kontakt-, Anfrage-, Warenkorb-, Checkout-, Feed- und Produkt-Routen können nicht als neue öffentliche Pfade gespeichert werden.
- Bestehende Slug-Werte werden bei Titel-, Preis- oder anderen sachfremden Änderungen nicht normalisiert oder ersetzt. Es wurden keine vorhandenen Slug-Datensätze migriert.

### 5.7 Automatische öffentliche Weiterleitungen

- `slugredirect` speichert ausschließlich relative historische Pfade, deren SHA-256-Prüfwert und die zugehörige öffentliche Entität. Mandant und Website sind Bestandteil der eindeutigen Zuordnung; Domains werden nicht gespeichert.
- Bei einer ausdrücklichen Slug- oder Elternänderung werden der bisherige und der neue vollständige Pfad vor dem Schreiben berechnet. Das gilt auch für verschachtelte Seiten, Kategorien, Tags und Produkte unter einer verschobenen Kategorie.
- Slug-Änderung und Redirect-Speicherung laufen in derselben bestehenden Formular-, Tag- oder Artikelimport-Transaktion. Ein Pfadkonflikt bricht die gesamte Änderung mit einer übersetzbaren Meldung ab.
- Der öffentliche Resolver prüft zuerst aktuelle Slugs. Nur ein nicht mehr aktueller GET-/HEAD-Pfad kann anschließend eine 301-Antwort erhalten. POST und andere schreibende Requests werden nicht umgeleitet.
- Das Ziel wird bei jedem Abruf aus der aktuellen kanonischen URL der gespeicherten Entität gebildet. Mehrfache Änderungen erzeugen deshalb keine Redirect-Ketten. Gelöschte, deaktivierte, mandantenfremde oder durch ein deaktiviertes Website-Feature nicht öffentliche Ziele werden nicht weitergeleitet.
- Reservierte Systemrouten, Dateipfade und statische Ressourcen sind ausgeschlossen. Unbekannte Pfade bleiben echte 404-Antworten; es gibt keinen Startseiten-Fallback.
- Canonicals und Sitemap verwenden weiterhin ausschließlich die bestehende Slug-Tabelle. Historische Pfade erscheinen dort nicht.
- Bekannte historische URLs aus der Zeit vor dieser Funktion werden nicht automatisch erraten. Ein geprüfter Alt-URL-Import und eine kleine manuelle Admin-Zuordnung sind bewusst noch nicht enthalten.

### 5.8 Dynamische Robots- und Sitemap-Ausgabe

- `/robots.txt` ist eine dynamische, mandantenbezogene Route. GET und HEAD sind öffentlich; schreibende Requests oder andere Actions bleiben gesperrt.
- Indexierbare Websites liefern `Allow: /` und bei gültiger `shop.url` einen absoluten Sitemap-Verweis. Nicht indexierbare Websites liefern `Disallow: /`; ihr bestehendes Robots-Meta und der `X-Robots-Tag` bleiben zusätzlich aktiv.
- Sitemap, Robots-Verweis und HTML-Canonicals verwenden gemeinsam die validierte Shop-Basis-URL. Anfrage-Host, Zugangsdaten, Query und Fragment werden nicht als kanonische Basis übernommen.
- Die Sitemap liefert ausschließlich aktuelle öffentliche Slug-Ziele des aktiven Mandanten und Shops. Deaktivierte oder gelöschte Entitäten, nicht freigeschaltete Features, doppelte URLs, Suchseiten und historische Redirects werden ausgeschlossen.
- Die Sitemap selbst erhält `X-Robots-Tag: noindex, follow`. Nicht indexierbare Websites oder Websites ohne gültige kanonische Basisadresse erhalten am Sitemap-Endpunkt HTTP 404.
- `robots.txt` und `sitemap.xml` sind reservierte Systemrouten. Die Ausgabe ist ausschließlich lesend und verändert keine Inhalte oder Datenbankdaten.

## 6. Datenbank- und Deployment-Hinweise

`structure.sql` und die weiterhin aktuelle Migration `update-1.1.7-1.1.8.sql` enthalten dieselbe vollständige Definition von `slugredirect`. Phase 2.3 hat beide Dateien ausschließlich geprüft und nicht verändert. Es wurde keine weitere SQL-Datei oder Versionsnummer angelegt und keine reale Bestandsdatenbank verändert.

Empfohlene Reihenfolge:

1. Vollständiges Datenbank-Backup erstellen.
2. Prüfen, ob die vorgesehene Migration in der Zielinstallation bereits ausgeführt wurde.
3. Falls sie bereits ausgeführt wurde, die nachträglich ergänzten Schemaanweisungen einschließlich `slugredirect` kontrolliert und separat freigegeben anwenden; die Anwendung benötigt die Tabelle vor der ersten öffentlichen URL-Änderung.
4. Für jede Entwicklungs-/Testwebsite die Indexierung im Shop-Datensatz deaktivieren.
5. Anwendungscode deployen und Robots-Meta, `X-Robots-Tag`, Canonical, Redirects und Analytics-Ausgabe prüfen.
6. Indexierung einer Produktionswebsite erst nach TLS-, Canonical-, Redirect- und Content-Freigabe aktivieren.

Wichtig: `noindex` verhindert Suchmaschinenindexierung, aber weder Zugriff noch Datenabfluss. Nicht öffentliche Umgebungen sollten zusätzlich durch Authentifizierung, Netzwerkregeln oder einen vorgeschalteten Zugriffsschutz abgesichert werden.

## 7. Durchgeführte Prüfungen

| Prüfung | Ergebnis |
|---|---|
| PHP-Syntax der geänderten PHP-/PHTML-Dateien | Bestanden |
| Technische HTML5-/Indexierungs-/Header-Baseline | 40 Prüfungen bestanden |
| Robots, Sitemap, kanonische Basis-URL, Routing und Isolation | 30 Prüfungen bestanden |
| Slug-Normalisierung, Speicherwege, Hierarchie, Kollisionen, Redirect-Erzeugung und Kompatibilität | 67 Prüfungen bestanden |
| Site-CMS-Struktur | 144 Prüfungen bestanden |
| Öffentliche Suche, Redirect-Auflösung und Statusverhalten | 101 Prüfungen bestanden |
| Manuelle Shopzuordnung und Varianten | 26 Prüfungen bestanden |
| Browsermeldungen, Übersetzungen und Benachrichtigungen | 370 Prüfungen bestanden; zusätzlich 60 Sales- und 24 Purchases-Helper-Prüfungen |
| Sicherheitskonfiguration und Umgebungswahl | 22 Prüfungen bestanden |
| Shop-Anfragen | 39 Prüfungen bestanden |
| Kontaktanfragen | 45 Prüfungen bestanden |
| Kontaktpersonen | 66 Prüfungen bestanden |
| Kontaktrollen | 32 Prüfungen bestanden |
| Berechtigungen | 408 Prüfungen bestanden |
| Mandantenzugriff | 268 Prüfungen bestanden |
| Mandantenwechsel | 53 Prüfungen bestanden |
| HTTP-Sessions | 25 Prüfungen bestanden |
| HTTP-Konfiguration | 8.479 Prüfungen bestanden |
| HTTP-Fehlerbehandlung | 140 Prüfungen bestanden |
| Isolierter MariaDB-Installations-/Migrationstest | Bestanden; einschließlich 66 UOM-Prüfungen |
| `git diff --check` | Bestanden |
| Lagerplanungs-Regressionsprüfung | Fehlgeschlagen: bestehende Prüfung erwartet den aktuellen Lagerbestand als frühesten Liefertermin. Der Lagerplanungsbereich wurde durch diese Arbeiten nicht verändert. |

Der Lagerplanungstest enthält feste Planungsdaten ab dem 1. November 2026 und vergleicht zugleich mit dem aktuellen Systemdatum. Am 1. November wird eine feste Reservierung deshalb als heutiges Ereignis verrechnet, während die Prüfung weiterhin den unveränderten Anfangsbestand als sofort verfügbar erwartet. Im Lagerplanungsbereich existiert kein Diff aus dieser Modernisierung; der Fehler ist unabhängig zu korrigieren, indem der Test seine Bezugszeit kontrolliert.

## 8. Kompatibilitätsrisiken und offene Punkte

- Bestehende Shops werden bei der Schema-Migration absichtlich nicht automatisch auf Noindex gestellt. Die gewünschte Einstellung muss je Installation geprüft werden.
- Wird der Redirect-Code vor der ergänzten Datenbankmigration bereitgestellt, schlagen Redirect-Prüfungen bei öffentlichen URL-Änderungen wegen der fehlenden Tabelle fehl. Migration und Deployment müssen deshalb koordiniert werden.
- Das Hinzufügen von Headern kann kundenspezifische Einbettungen beeinflussen; die Frame-Beschränkung gilt derzeit nur für interne Seiten.
- Öffentliches Analytics ist in einer indexierbaren Produktionswebsite weiterhin nicht consent-gesteuert.
- TLS, Host-Redirects, HSTS, Kompression, Protokollversionen und Caching sind nicht allein durch den PHP-Code abgeschlossen.
- Bestehende Slugs und URLs wurden bewusst nicht migriert oder aus Titeln neu erzeugt. Redirects entstehen erst bei einer ausdrücklichen Slug- oder Elternänderung innerhalb derselben Website; ein Websitewechsel erzeugt keine domainübergreifende Weiterleitung.
- Vorhandene historische URLs werden nicht automatisch importiert. Dafür sind vor dem Relaunch eine fachliche Zuordnung und ein eigener sicherer Import- oder Verwaltungsweg erforderlich.
- Eine ungültige oder fehlende `shop.url` unterdrückt Canonical und Sitemap-Verweis; der Sitemap-Endpunkt liefert dann bewusst 404. Die Produktionsadresse muss vor Indexierungsfreigabe geprüft werden.
- Es erfolgten kein Push, kein Deployment und keine produktive Datenbankänderung.

## 9. Empfohlene nächste Phasen

### Phase 2 – URL-, SEO- und Relaunch-Basis

- Bekannte historische URLs inventarisieren und einen geprüften Import- oder kleinen manuellen Zuordnungsweg ergänzen.
- Metadaten, Überschriftenhierarchie, Breadcrumb-JSON-LD und 404-Seite vervollständigen.
- Vollständigen Staging-Crawl als Release-Prüfung erstellen.

### Phase 3 – Performance und Assets

- Responsive Bilder, feste Dimensionen, Lazy Loading und LCP-Priorisierung ergänzen.
- CDN-/SRI-Entscheidung und unterstützte Frontend-Versionen festlegen.
- CSS/JS messen, modernisieren, minifizieren und versioniert cachen.
- Core Web Vitals mit realen Inhalten messen.

### Phase 4 – Consent, Tracking und Formulare

- Mandantenfähiges Consent Management implementieren.
- Tracking und Conversions erst nach passender Einwilligung laden.
- Öffentliche Formulare auf einheitliche CSRF-, Spam-, Rate-Limit- und Fehlerbehandlung prüfen.
- Nicht produktiven Mailversand sicher deaktivieren oder auf einen Test-Sink begrenzen.

### Phase 5 – Partnerzugang

- Authentifizierung, Registrierung, Verifikation, Reset und Brute-Force-Schutz konzipieren.
- Dokumente ausschließlich serverseitig autorisiert ausliefern.
- Marketingeinwilligung getrennt und revisionsfähig speichern.
- Datenschutzgerechte Conversion-Ereignisse definieren.

### Phase 6 – Infrastruktur und Livegang

- TLS, HTTPS-Redirects, Canonical-Host, Kompression, Caching und HTTP/2 prüfen.
- HSTS kontrolliert einführen.
- Backups und Rollback proben.
- Monitoring, Webmaster-Werkzeuge, Sitemap und Conversion-Messung verifizieren.

## 10. Lokale Commits

Die vorherigen Änderungen wurden hunkgenau in vier fachliche Einheiten getrennt. Dateien mit mehreren Themen wurden nicht pauschal einem einzelnen Commit zugeordnet. Die Slug-Vereinheitlichung aus Phase 2.1 wird als eigener fünfter lokaler Commit geführt.

### Commit 1: `modernize html document structure`

Inhalt:

- HTML5-Doctype und Charset-Ausgabe.
- Entfernung alter Content-Type-Metaelemente.
- HTML5-Anpassungen für interne Layouts, Login, Fehlerseite und Installer.

Dateien:

- `application/Bootstrap.php` – Doctype-Hunk.
- `configs/application.ini` – Doctype-Hunk.
- `application/layouts/scripts/layout.phtml`
- `application/layouts/scripts/plain.phtml`
- `application/layouts/scripts/site.phtml` – Entfernung des alten Content-Type-Metaelements.
- `application/modules/users/views/scripts/user/login.phtml`
- `application/views/scripts/error/error.phtml`
- `install/templates/header.php`

### Commit 2: `improve application security baseline`

Inhalt:

- Explizites Staging-Profil.
- Pflicht für eine eigene Staging-Datenbanksektion.
- Schutz gegen identische Production-/Staging-Datenbankkonfiguration.
- Zentrale grundlegende Security-Header.
- Internes Robots-Noindex und Frame-Schutz.
- Analytics-Unterdrückung außerhalb Production.
- Erweiterte Konfigurationstests.

Dateien:

- `application/Bootstrap.php` – Registrierung des Security-Header-Plugins.
- `application/layouts/scripts/layout.phtml`
- `application/layouts/scripts/plain.phtml`
- `application/modules/users/views/scripts/user/login.phtml`
- `application/plugins/Analytics.php`
- `application/plugins/SecurityHeaders.php`
- `application/views/scripts/error/error.phtml`
- `configs/application.ini` – Staging-Hunk.
- `index.php`
- `install/templates/header.php`
- `tests/security/configuration.php`

### Commit 3: `manage site indexing per shop`

Inhalt:

- Datenbankfeld und Admin-Einstellung für `shop.indexable`.
- Umgebungsabhängige effektive Indexierbarkeit.
- Robots-Meta/Header, Canonical-, Sitemap- und Analytics-Steuerung.
- Installations-/Migrations- und Baseline-Tests.

Dateien:

- `application/Bootstrap.php` – Übergabe der Laufzeitumgebung an den Site-Resolver.
- `application/layouts/scripts/site.phtml`
- `application/modules/admin/forms/Shop.php`
- `application/modules/shops/controllers/Action.php`
- `application/modules/shops/controllers/SitemapController.php`
- `application/plugins/SecurityHeaders.php` – öffentliche Indexierungssteuerung.
- `install/config/structure.sql`
- `install/config/update-1.1.7-1.1.8.sql`
- `languages/de_DE/admin/admin.php`
- `languages/en_US/admin/admin.php`
- `library/DEEC/Site/Context.php`
- `library/DEEC/Site/Resolver.php`
- `tests/install/import-db.php`
- `tests/site/technical-baseline.php`

### Commit 4: `document technical modernization status`

Inhalt:

- Vollständige Bewertung aller 70 Anforderungen.
- Testergebnisse, Risiken, Deployment-Reihenfolge und Folgephasen.
- Keine installationsspezifischen Domains oder Zugangsdaten.

Datei:

- `TECHNICAL_MODERNIZATION.md`

### Commit 5: `standardize public site slugs`

Inhalt:

- Zentrale Slug-Normalisierung mit verbindlicher deutscher Transliteration.
- Mandanten- und websitebezogene Prüfung vollständiger Pfade, Kollisionen und reservierter Routen.
- Transaktionale Formular-, Tag- und Artikelimport-Speicherwege mit übersetzbaren Fehlermeldungen.
- Kompatibilitätsschutz für bestehende Slugs, Zuordnungen, Canonicals und Sitemap-Pfade.
- Automatisierte Slug- und Benachrichtigungsregressionen.

### Commit 6: `add automatic site redirects`

Inhalt:

- Mandanten- und websitebezogene Speicherung historischer öffentlicher Pfade.
- Automatische Redirect-Erzeugung für ausdrückliche Slug-, Eltern- und Kategoriepfadänderungen einschließlich abhängiger Seiten und Produkte.
- Direkte Auflösung auf die aktuelle öffentliche Entitäts-URL ohne Redirect-Ketten.
- HTTP 301 für GET/HEAD mit Vorrang aktueller URLs sowie Schutz vor Schleifen, Systemrouten, statischen Pfaden und nicht öffentlichen Zielen.
- Schemaergänzung in Neuinstallation und weiterhin aktueller Migration `update-1.1.7-1.1.8.sql` sowie automatisierte Routing-, ACL-, Kollisions- und MariaDB-Migrationstests.

### Commit 7: `improve site robots and sitemap`

Inhalt:

- Dynamische, indexierungsabhängige `robots.txt` pro öffentlicher Website.
- Kanonische Sitemap-Ausgabe ohne Host-Header-Fallback, historische Redirects oder nicht öffentliche Entitäten.
- Gemeinsame validierte Basis-URL für Canonicals, Robots-Sitemap-Verweis und XML-Sitemap.
- Lesebeschränkung auf GET/HEAD, reservierte SEO-Routen und mandanten-/websitebezogene ACL-Prüfung.
- Automatisierte Robots-, Sitemap-, Routing-, Feature-, Shop-, Mandanten- und SQL-Schema-Regressionen.

Wegen der Schemaänderungen in Commit 3 und Commit 6 darf der Gesamtstand nicht ohne abgestimmte Deployment-Migration ausgerollt werden.

## 11. Abschlussstatus

Die technische Basis für HTML5, mandantenbezogene Indexierungssteuerung, dynamische Robots-/Sitemap-Ausgabe, grundlegende Response-Header, eine sicherere Staging-Trennung, einheitliche Slug-Erzeugung und automatische interne 301-Weiterleitungen ist vorbereitet und getestet. Der Gesamtauftrag mit 70 Punkten ist damit nicht vollständig abgeschlossen: Die offenen P0-Themen TLS-/Host-Redirects, historischer Alt-URL-Import, Consent, Relaunch-Crawl, produktive Infrastruktur und Deployment-Prozesse bleiben vor einem Livegang verbindlich zu bearbeiten.
