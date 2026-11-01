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
- `robots.txt`, Sitemap und Canonicals verwenden dieselbe validierte, mandantenbezogene Shop-URL; nicht indexierbare Websites bleiben zum Erkennen von `noindex` crawlbar und bewerben keine Sitemap.
- SEO-Titel, Descriptions und Open-Graph-Daten werden für öffentliche Seiten zentral erzeugt; Breadcrumbs verwenden für HTML und JSON-LD dieselbe aktuelle Pfadhierarchie.
- Ein explizit zu startender HTTP-Crawler prüft reale öffentliche Antworten, interne Links, Redirects, SEO-Metadaten, strukturierte Daten, Sitemap, Robots und interne Ressourcen ohne Formulare abzusenden.
- Öffentliche verwaltete Bilder verwenden tatsächliche Abmessungen, vorhandene feste Medienvarianten, kontextabhängiges Lazy Loading und eine explizite Priorisierung kritischer Hero- und Produktbilder.
- Pro Seite wird nur der erste tatsächlich sichtbare CMS-Medienblock beziehungsweise das initiale Slider- oder Produktbild hoch priorisiert; nachgelagerte Bilder decodieren asynchron.
- TLS, bekannte Alt-URL-Importe, Consent Management, moderne Bildformate, Asset-Modernisierung und mehrere Relaunch-Prüfungen benötigen weitere Umsetzungsphasen oder Infrastrukturzugriff.

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
| 11 | Umgesetzt | P1 | Vorhandene individuelle Metatitel und -Descriptions werden für Startseite, CMS-Seiten und Kategorien verwendet. Produkte und Tags nutzen sinnvolle Bestandsdaten als Fallback. Eine zentrale Ausgabe verhindert doppelte Elemente und doppelte Websitenamen; leere Werte werden ausgelassen. |
| 12 | Teilweise | P1 | Öffentliche Templates definieren eine Haupt-H1; bei mehreren Hero-Blöcken wird nur der erste als H1 ausgegeben. Vorhandene H1 in Legacy-HTML werden erkannt und nicht durch eine zusätzliche Template-H1 ergänzt. Mehrere bereits gespeicherte H1 innerhalb eines einzelnen Legacy-Inhalts werden bewusst nicht automatisch umgeschrieben und bleiben redaktionell zu prüfen. |
| 13 | Teilweise | P1 | Mega-Menü-Schaltflächen, `aria-expanded`, Escape-Behandlung und Fokusdarstellung sind teilweise vorhanden. Fokusrückgabe, gegebenenfalls Pfeiltastensteuerung sowie Browser-/Tastaturtests ergänzen. |
| 14 | Teilweise | P1 | Bootstrap-Collapse und mobile CSS-Regeln existieren, aber keine vollständige Regression für reale Menütiefen. Finale Navigation auf Telefon und Tablet testen und bei Bedarf als Akkordeon mit sauberer Fokussteuerung ausbauen. |
| 15 | Teilweise | P2 | Suche und Ergebnisformular besitzen Label, GET-Parameter und eine sinnvolle Ergebnisroute. Falls das Design nur ein Icon zeigt, eine zugängliche, fokussierte und per Escape schließbare Suchoberfläche ergänzen. |
| 16 | Umgesetzt | P1 | Suchparameter sind begrenzt, Datenbankabfragen parametrisiert, Ausgaben escaped und Ergebnislisten auf `noindex, follow` gesetzt. Regressionstests beibehalten. |
| 17 | Teilweise | P0 vor Relaunch | Leere CMS-Ziele werden teilweise ausgelassen; Legacy-Steuerelemente und Fallback-Navigation enthalten noch `href="#"`. Der neue HTTP-Crawler erkennt defekte interne Links und Ressourcen; verbleibende Bedienelemente sind zu bereinigen und der Crawl muss mit der freigegebenen Staging-URL ausgeführt werden. |
| 18 | Teilweise | P1 | Keine versionierte Referenz auf das bekannte Platzhalterbild gefunden. Reale Mandantenmedien wurden nicht geprüft; Alt-Texte und Bildmetadaten sind nicht überall vollständig. Medienbestand vor Livegang auditieren. |
| 19 | Umgesetzt | P1 | Ein gemeinsamer Renderer verwendet ausschließlich tatsächlich vorhandene, seitenverhältnisgleiche 300-/500-/800-/1000-Pixel-Varianten der bestehenden Medienverwaltung und ergänzt kontextbezogene `srcset`-/`sizes`-Angaben. Fehlen Varianten, bleibt das unveränderte Original ohne erfundene Quellen erhalten. |
| 20 | Offen | P2 | Die lokale GD-Installation kann WebP, aber kein AVIF erzeugen. Der vorhandene MediaManager besitzt keinen vollständigen Konvertierungs-, Umbenennungs-, Lösch- und Fallback-Lebenszyklus für abgeleitete Formate; ohne reale Medienmessung wurde deshalb keine unvollständige Pipeline ergänzt. Vorhandene WebP-Dateien können weiterhin sicher ausgeliefert werden. |
| 21 | Umgesetzt | P1 | Für sicher auflösbare Rasterbilder werden die tatsächlichen Originalmaße als `width` und `height` ausgegeben. Der Produktbildbereich reserviert zusätzlich adaptiv ein 4:3-Verhältnis statt einer starren 400-Pixel-Höhe; modale Bilder bleiben auf 80 Prozent der Viewporthöhe und maximal 800 Pixel begrenzt. |
| 22 | Umgesetzt | P1 | Inhalts-, Karten-, Kategorie-, Listen- und Galeriebilder laden lazy. Der sichtbare CMS-Hero, die erste Startseiten-Slide und das initiale Produktbild sind explizit ausgenommen. |
| 23 | Teilweise | P1 | Nur der erste CMS-Hero bzw. Bild/Text-Block, die erste Startseiten-Slide oder das initiale Produktbild verwendet `fetchpriority="high"` ohne Lazy Loading. Spätere Hero-/Galeriebilder bleiben lazy; reine Text-LCP-Seiten erzeugen keinen unnötigen Bild-Preload. Die tatsächliche LCP-Wirkung bleibt mit finalen Staging-Inhalten zu messen. |
| 24 | Teilweise | P1 Sicherheit | Das öffentliche Frontend verwendet den vollständigen jQuery-Build 3.7.1 und Bootstrap 4.6.2. AJAX, Navigation, Collapse, Tabs, Carousel und Modal sind im Chromium-Test abgedeckt. Bootstrap 4 ist seit 2023 EOL; die dokumentierte Bootstrap-5-Migration bleibt offen. Die älteren internen ERP-Abhängigkeiten wurden bewusst nicht verändert. |
| 25 | Umgesetzt | P1 Sicherheit | Alle drei externen öffentlichen Bibliotheksdateien besitzen feste Versions-URLs, kryptografisch gegen die ausgelieferten Dateien geprüfte SHA-384-SRI-Werte und `crossorigin="anonymous"`. Das lokale Shop-CSS und -JavaScript bleiben First Party. |
| 26 | Für aktuellen Umfang umgesetzt | P2 | Die drei abhängigen Scripts stehen in korrekter Reihenfolge am Dokumentende und werden vom Browser als nicht render-blockierend gemessen. `defer` wurde nicht ergänzt, weil es dort keinen belegten Rendergewinn bringt und kundenspezifisches nachfolgendes Analytics-Markup beziehungsweise vorhandene Inline-Scripts umordnen könnte. |
| 27 | Teilweise | P2 | Zwei nachweislich doppelte generische Header-Regeln wurden entfernt. Eine breitflächige CSS-Bereinigung oder neue Build-/Minifizierungsarchitektur erfolgte ohne reale Theme- und Nutzungsdaten bewusst nicht. Webserver-Caching und versionierte lokale Anwendungsassets bleiben offen. |
| 28 | Teilweise | P1 | Eine reproduzierbare lokale Chromium-Fixture vergleicht unoptimierte und responsive Bildausgabe, Ressourcenauswahl, Prioritäten und CLS. Mangels freigegebener Staging-URL und realer Medien sind keine belastbaren Feldwerte vorhanden; LCP und INP müssen weiterhin mit finalen Inhalten und realen Interaktionen gemessen werden. |
| 29 | Teilweise | P1 | `navbar-expand-xl` funktioniert in der lokalen Chromium-Fixture bei 500 und 1365 Pixeln einschließlich Collapse und Mega-Menü. Die endgültige Entscheidung bleibt mit realen mandantenbezogenen Menüpunkten und Textlängen auf Staging zu prüfen. |
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
| 40 | Umgesetzt | P1 | `Organization` wird aus vorhandenen Website-Daten erzeugt; `BreadcrumbList` basiert auf aktuellen öffentlichen Pfaden. Produkt-JSON-LD erscheint nur auf Produktdetails. Leere Felder sowie Offers ohne gültige URL, positiven realen Preis und Währung werden ausgelassen; Verfügbarkeit und Bewertungen werden nicht erfunden. |
| 41 | Umgesetzt | P1 | Visuelle Breadcrumbs und `BreadcrumbList` verwenden dieselbe Hierarchie für Kategorien, Unterkategorien, Produkte und tatsächlich verschachtelte CMS-Seiten. Fehlt ein aktueller öffentlicher Pfad, wird kein partielles JSON-LD ausgegeben. |
| 42 | Umgesetzt | P0 vor Relaunch | Die Sitemap verwendet ausschließlich die validierte kanonische Shop-URL und aktuell öffentliche, mandanten- und websitebezogene Entitätspfade. Duplikate, deaktivierte Features, gelöschte Ziele, Suche und historische Redirects bleiben ausgeschlossen. Nicht indexierbare oder falsch konfigurierte Websites liefern HTTP 404. |
| 43 | Umgesetzt | P1 | `robots.txt` wird pro Website dynamisch ausgeliefert. Alle öffentlichen Seiten bleiben crawlbar, damit Suchmaschinen vorhandenes `noindex` lesen können. Nur indexierbare Websites verweisen bei gültiger kanonischer URL auf die Sitemap. Robots bleibt ausdrücklich kein Zugriffsschutz. |
| 44 | Teilweise | P1 | Fehlende Routen und Entitäten liefern echte 404-Antworten; die Seite ist noch generisch. Eine hilfreiche öffentliche 404-Seite mit Navigation, Suche und Kontakt ergänzen, ohne den Statuscode zu verändern. |
| 45 | Teilweise | P0 | 403/404/500 werden explizit behandelt; bekannte automatisch gespeicherte Altpfade liefern 301, unbekannte Pfade weiterhin 404. Eine fachliche 410-Strategie und vollständige Statusprüfungen im Relaunch-Crawl fehlen. |
| 46 | Infrastruktur | P1 | Anwendungscache ist vorhanden; eine geprüfte statische Cache-Control-/Expires-Strategie ist nicht im Repository abgebildet. Versionierte Assets serverseitig langfristig cachen; Seitencache nur mit mandanten-, Consent- und Session-sicheren Schlüsseln. |
| 47 | Infrastruktur | P1 | Keine gzip-/Brotli-Konfiguration im Repository. Kompression am Webserver oder Proxy aktivieren und Content-Types, `Vary` sowie bereits komprimierte Formate prüfen. |
| 48 | Infrastruktur | P1 | HTTP/2-/HTTP/3-Unterstützung ist eine Server-/Proxy-Eigenschaft. HTTP/2 nach TLS prüfen; HTTP/3 nur bei unterstützter Infrastruktur und messbarem Nutzen aktivieren. |
| 49 | Teilweise | P0/P1 | Global werden `X-Content-Type-Options`, `Referrer-Policy` und eine konservative `Permissions-Policy` gesetzt. Interne Seiten erhalten zusätzlich SAMEORIGIN und CSP `frame-ancestors 'self'`. Öffentliche CSP-/Frame-Regeln erst nach Abhängigkeits- und Embedding-Audit festlegen. |
| 50 | Infrastruktur | P1 nach TLS | HSTS ist bewusst noch nicht gesetzt. Nach vollständiger HTTPS-Stabilisierung mit kurzer Laufzeit beginnen; `includeSubDomains` erst nach Subdomain-Audit verwenden. |
| 51 | Umgesetzt | P1 | Der zentrale View-Helper erzeugt `og:type`, Titel, Description, URL, optionales Bild, Websitename und Locale. Absolute URLs stammen ausschließlich aus der validierten Shop-Konfiguration; Bilder sind auf öffentliche Medienverzeichnisse des aktiven Shops beschränkt. |
| 52 | Teilweise | P2 | Ein klassisches Favicon existiert, moderne Icon-Größen und mandantenfähige Konfiguration fehlen. Icons je Website ermöglichen; Manifest nur bei tatsächlich gewünschtem App-Verhalten ergänzen. |
| 53 | Teilweise | P2 | Sprachfelder und Übersetzungen existieren, aber kein vollständiges öffentliches Locale-Routing und kein `hreflang`. URL-Eigentümerschaft pro Sprache zuerst entwerfen, anschließend gegenseitige Self-/Hreflang-Links ausgeben. |
| 54 | Für aktuellen Umfang umgesetzt | P2 | Es existiert kein Mechanismus für automatisch generierte Maschinenübersetzungs-URLs. Zukünftige Sprachen über redaktionelle Inhalte und stabile Locale-Pfade abbilden. |
| 55 | Teilweise | P1 | Semantik, Labels und Fokuszustände sind teilweise vorhanden; ein vollständiger WCAG-Audit fehlt. Automatisierte und manuelle Prüfungen für Tastatur, Screenreader, Kontrast und Fehlerzustände durchführen. |
| 56 | Teilweise | P1 | Verwaltete Produkt-, Kategorie-, Karten-, Logo- und Slide-Bilder verwenden vorhandene Titel bzw. fachliche Namen als Alt-Text; dekorative Hero-Bilder behalten `alt=""`. Legacy-WYSIWYG-Inhalte und reale Medienbestände bleiben redaktionell zu auditieren. |
| 57 | Teilweise | P2 | Zeichen-Icons besitzen teilweise Screenreader-Text, bleiben aber typografische Symbole. Durch wiederverwendbare SVG-Icons ersetzen und zugängliche Beschriftungen erhalten. |
| 58 | Teilweise | P1 | Strukturierte CMS-Blöcke existieren, gleichzeitig bleiben Legacy-WYSIWYG-Felder. Neue Inhalte nur über typisierte Komponenten pflegen und Bestandsinhalte schrittweise migrieren. |
| 59 | Umgesetzt | P1 | `DEEC_Site_Block` und Partials bilden wiederverwendbare Hero-, CTA-, Text-, Bild/Text-, Karten-, Kontakt- und Kategorieblöcke ab. Nur bei konkretem Wiederverwendungsbedarf erweitern. |
| 60 | Teilweise | P1 | Wichtige Blöcke besitzen getrennte Felder und Allowlists; generische HTML-/WYSIWYG-Felder bestehen weiterhin. Feldlimits und Medienauswahl ausbauen und Inhalte inkrementell migrieren. |
| 61 | Teilweise | P0 vor Relaunch | Ein begrenzter CLI-Crawler prüft reale HTTP-Antworten für Links, Status, Redirects, Titel, Descriptions, H1, Canonicals, Open Graph, JSON-LD, Sitemap, Robots und Ressourcen und kann Text oder JSON ausgeben. Vor dem Relaunch bleibt der ausdrücklich freizugebende Lauf gegen die echte Staging-Website samt fachlicher Auswertung offen. |
| 62 | Umgesetzt | P0 vor Relaunch | Redirects speichern keine weiteren Pfadziele, sondern die öffentliche Entität. Dadurch werden auch nach mehrfachen URL-Änderungen alle historischen Pfade direkt auf den aktuellen kanonischen Pfad aufgelöst; aktuelle URLs haben Vorrang und Schleifen werden verworfen. |
| 63 | Infrastruktur | P1 nach Relaunch | Search-Console-/Webmaster-Zustand liegt außerhalb des Repositories. Eigentum prüfen, Produktionssitemap einreichen und Indexierung sowie 404 nach dem Relaunch beobachten. |
| 64 | Teilweise | P0 vor Relaunch | Analytics ist konfigurierbar und jetzt umgebungs-/indexierungsabhängig, aber weder consent-gesteuert noch durch eine Conversion-Migrationsliste abgesichert. Bestehende Ereignisse inventarisieren und einzeln validieren. |
| 65 | Derzeit nicht anwendbar | P1 bei Einführung | Kein öffentlicher Partnerzugang vorhanden. Datenschutzgerechte Events für Start, Erfolg, Login, autorisierten Download und Anfrage definieren, ohne sensible Daten zu übertragen. |
| 66 | Teilweise | P0 | Production, Staging und Development sind als Anwendungsprofile vorhanden. Staging benötigt eine explizite, von Production abweichende Datenbankkonfiguration. Getrennte Hosts, Secrets, Mailziele und Deployments bleiben operative Aufgaben. |
| 67 | Offen | P0 | Nicht produktives Analytics wird unterdrückt, zentrale Mailfunktionen können aber weiterhin konfiguriertes SMTP verwenden. Eine verbindliche Nicht-Produktions-Mailstrategie mit Deaktivierung oder Allowlist/Test-Sink ergänzen. |
| 68 | Infrastruktur | P0 vor Relaunch | Kein vollständiger Backup-/Rollback-Ablauf im Repository dokumentiert. Anwendung, Datenbank, Medien und Redirects sichern und objektive Rollback-Kriterien erproben. |
| 69 | Infrastruktur | P1 vor Relaunch | Keine externe Überwachung für Erreichbarkeit, Zertifikatsablauf, 5xx, Formulare oder Login gefunden. Synthetische Checks und klare Alarmverantwortung einrichten. |
| 70 | Teilweise | P1 Entscheidung | Das öffentliche Frontend ist auf die letzten vorgesehenen 3.x-/4.x-Stände jQuery 3.7.1 und Bootstrap 4.6.2 aktualisiert. Bootstrap 4 bleibt EOL. Bootstrap 5 erfordert die gezielte Migration von `data-toggle`/`data-target`/`data-slide`, jQuery-Pluginaufrufen, Utility-Klassen und Komponentenmarkup; das interne ERP bleibt davon getrennt. |

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
- Indexierbare Websites liefern `Allow: /` und bei gültiger `shop.url` einen absoluten Sitemap-Verweis. Nicht indexierbare Websites bleiben mit `Allow: /` crawlbar, damit ihr Robots-Meta und der `X-Robots-Tag` erkannt werden; eine Sitemap wird dort nicht beworben.
- Sitemap, Robots-Verweis und HTML-Canonicals verwenden gemeinsam die validierte Shop-Basis-URL. Anfrage-Host, Zugangsdaten, Query und Fragment werden nicht als kanonische Basis übernommen.
- Die Sitemap liefert ausschließlich aktuelle öffentliche Slug-Ziele des aktiven Mandanten und Shops. Deaktivierte oder gelöschte Entitäten, nicht freigeschaltete Features, doppelte URLs, Suchseiten und historische Redirects werden ausgeschlossen.
- Die Sitemap selbst erhält `X-Robots-Tag: noindex, follow`. Nicht indexierbare Websites oder Websites ohne gültige kanonische Basisadresse erhalten am Sitemap-Endpunkt HTTP 404.
- `robots.txt` und `sitemap.xml` sind reservierte Systemrouten. Die Ausgabe ist ausschließlich lesend und verändert keine Inhalte oder Datenbankdaten.

### 5.9 SEO-Metadaten und strukturierte Daten

- Ein zentraler öffentlicher View-Helper bereinigt und escaped Titel sowie Descriptions, verwendet vorhandene SEO-Felder und verhindert einen doppelt angehängten Websitenamen.
- Open Graph verwendet ausschließlich die validierte kanonische Shop-Basis-URL und aktuelle Slug-Pfade. Optionale Bilder werden nur aus den öffentlichen Verzeichnissen für Header-, Kategorie- und Produktmedien übernommen; externe, geschützte oder traversierende Pfade werden verworfen.
- Das öffentliche Layout gibt minimales `Organization`-JSON-LD aus vorhandenen Werten für Name, Website-URL und optionales Logo aus. Nicht vorhandene Kontakt-, Adress- oder Social-Daten werden nicht ergänzt.
- Visuelle Breadcrumbs und `BreadcrumbList` werden aus derselben Datenliste erzeugt. Kategorien, Produkte und vorhandene verschachtelte CMS-Seiten verwenden ausschließlich aktuelle öffentliche Slugs des aktiven Shops und Mandanten.
- Produkt-JSON-LD wurde aus Kategorieergebnislisten entfernt. Auf Produktdetails werden nur tatsächlich vorhandene Werte ausgegeben; ein Offer erfordert eine aktuelle öffentliche URL, einen positiven realen Preis und eine gültige Währung. Bewertungen und Verfügbarkeit werden nicht angenommen.
- Öffentliche CMS-Templates erkennen vorhandene H1 in Legacy-Inhalten. Der erste geeignete Hero kann die Haupt-H1 liefern; weitere Hero-Blöcke werden als H2 ausgegeben. Gespeicherte Inhalte werden nicht verändert.
- Die Änderung benötigt keine neuen Felder, Tabellen oder SQL-Migrationen. Bestehende Slugs, Redirects, Canonicals und Sitemap-Pfade bleiben unverändert.

### 5.10 Automatisierte HTTP-Website-Prüfung

- `tests/site/crawl.py` startet ausschließlich mit einer ausdrücklich angegebenen `--url`. Der Crawler bleibt auf Schema, Host, Port und gegebenenfalls Unterpfad dieser Website begrenzt; externe Domains werden nicht verfolgt.
- Seiten werden per GET gelesen, interne Bilder, CSS, JavaScript und Downloads grundsätzlich per HEAD geprüft. Falls ein Server HEAD mit 405 oder 501 ablehnt, wird nur ein stark begrenzter GET-Abruf verwendet. Formulare und schreibende Requests werden nicht ausgeführt.
- Seitenzahl, Crawltiefe, Query-Varianten, Ressourcenanzahl, Redirects, Antwortgröße und Request-Timeout sind begrenzt. Trackingparameter werden für die Deduplizierung entfernt.
- Die Prüfung umfasst HTTP-Fehler, Soft-404, Redirect-Ketten und -Schleifen, ungültige oder externe Redirectziele, Titel, Descriptions, H1, Canonicals, Noindex, Open Graph, JSON-LD und aktuelle Breadcrumb-Pfade.
- Robots und XML-Sitemaps einschließlich Sitemap-Indizes werden geprüft. Doppelte, fremde, parametrisierte, suchbezogene, weiterleitende, nicht kanonische oder Noindex-URLs werden gemeldet.
- Interne Bilder und Assets werden auf Erreichbarkeit, Bild-Alt-Attribute und Mixed Content geprüft. Externe Ressourcen werden nur als Referenz analysiert und nicht heruntergeladen.
- Der Terminalbericht nennt jede URL und einen konkreten Befund. `--format=json` liefert dieselben Ergebnisse strukturiert. Mit `--paths` kann eine redaktionell geprüfte Liste zusätzlicher historischer Pfade einbezogen werden.
- Beispiel: `python3 tests/site/crawl.py --url <website-url> --indexable=yes --max-pages=500`. Für maschinelle Weiterverarbeitung zusätzlich `--format=json` verwenden.
- Soft-404 und möglicherweise informative Bilder mit leerem Alt-Text bleiben heuristische Hinweise. Historische URLs können nur geprüft werden, wenn sie verlinkt, in der Sitemap enthalten oder ausdrücklich über `--paths` übergeben werden.
- Der Crawler verändert keine Anwendungs- oder Kundendaten. Es wurden keine installationsspezifischen Ergebnisse eingecheckt und keine Datenbankänderungen benötigt.

### 5.11 Responsive öffentliche Bilder

- Der gemeinsame `SiteImage`-Helper löst ausschließlich Medien auf, die durch die bestehende mandantenbezogene Publikationsprüfung des aktiven Shops freigegeben sind. Externe URLs, Traversal, fehlende Dateien, geschützte Speicherbereiche und fremde Mandanten werden nicht ausgegeben.
- Die bereits aktive MediaManager-Verarbeitung erzeugt bei Uploads seitenverhältnisgetreue Varianten mit 300, 500, 800 und 1000 Pixeln. Der Renderer referenziert nur wirklich vorhandene Varianten mit passendem Seitenverhältnis und erzeugt beim Seitenabruf keine Dateien.
- Tatsächliche Originalmaße werden pro Request zwischengespeichert und als `width`/`height` ausgegeben. Vorhandene Originaldateien und gespeicherte Medienpfade bleiben unverändert; Bestandsbilder ohne Varianten funktionieren weiterhin mit dem Original.
- CMS-Hero, erste Startseiten-Slide und initiales Produktbild werden nicht lazy geladen und mit hoher Fetch-Priorität versehen. Nachgelagerte CMS-, Karten-, Kategorie-, Listen-, Galerie- und Anfragebilder verwenden `loading="lazy"`.
- Aussagekräftige vorhandene Alt-Texte werden übernommen, leere Medientitel fallen bei Produkten und Kategorien auf deren fachlichen Titel zurück. Das dekorative CMS-Hero-Bild verwendet weiterhin `alt=""`; alle Attribute werden escaped.
- Die zentrale Ausgabe ist in CMS-Hero, Bild/Text- und Kartenblöcken, Kategorien, Produktlisten und -details, Galerien, Anfragekarten, Startseiten-Slides sowie dem Shop-Logo eingebunden. Bestehende Klassen, Bootstrap-Carousels, Modal-IDs und JavaScript-Ereignisse bleiben erhalten.
- Es wurden keine Datenbankfelder, SQL-Migrationen oder Laufzeit-Konvertierungen ergänzt. WebP-/AVIF-Derivate und endgültige Core-Web-Vitals-Messungen bleiben getrennte Folgethemen.

### 5.12 Ladeverhalten und Core-Web-Vitals-Laborprüfung

- `RenderPageBlocks` kennzeichnet ausschließlich den ersten tatsächlich ausgegebenen Hero- oder Bild/Text-Block als möglichen LCP-Medienblock. Weitere CMS-Hero-Bilder bleiben lazy und erhalten keine hohe Fetch-Priorität. Slider und Produktdetail behalten jeweils genau ein priorisiertes initiales Bild.
- Nachgelagerte Bilder verwenden zusätzlich `decoding="async"`. Kritische Bilder behalten normales Decoding, werden nicht lazy geladen und erhalten keine pauschalen Preloads; dadurch entstehen keine durch unpassende Preload-Quellen verursachten Doppeldownloads.
- Der Produktbildbereich reserviert responsiv ein 4:3-Verhältnis. Die bisher starre 400-Pixel-Höhe entfällt auf modernen Browsern, sodass Mobilgeräte weniger leere Fläche rendern, ohne Bilder abzuschneiden. Modale Galerieflächen verwenden 80 Viewport-Prozent bis maximal 800 Pixel.
- Die Shop-Scripts bleiben in ihrer bestehenden Reihenfolge am Ende des Dokuments. Ein pauschales `defer` wäre wegen Bootstrap/jQuery, nachfolgendem kundenspezifischem Analytics-Markup und nicht vollständig inventarisierten Inline-Abhängigkeiten nicht ausreichend abgesichert. Bootstrap- und jQuery-Modernisierung bleibt Phase 3.3.
- Der Website-Crawler prüft nun positive Bilddimensionen, vollständige `srcset`-/`sizes`-Paare, widersprüchliches `loading="lazy"` plus `fetchpriority="high"` sowie mehrere hoch priorisierte Bilder. Diese Prüfung kann später unverändert gegen eine ausdrücklich freigegebene Staging-Website laufen.

Die lokale Medienablage enthält in diesem Stand keine realen Websitebilder oder Varianten, sondern nur die Verzeichnisplatzhalterdatei. Deshalb wurde keine scheinpräzise Bestandskompressionsrate angegeben. Die lokale GD-Erweiterung meldet JPEG-, PNG- und WebP-Unterstützung, aber keine AVIF-Unterstützung. Der ältere MediaManager kann WebP-Derivate nicht konsistent über Erzeugung, Auswahl, Umbenennung, Kopieren und Löschen verwalten. Eine isolierte Konvertierungsfunktion hätte verwaiste Dateien oder kollidierende Namen riskiert und wurde bewusst nicht eingebaut. Vorhandene Originale und WebP-Dateien bleiben unverändert und sicher auslieferbar.

`tests/site/performance-http.py` verwendet ausschließlich den vorhandenen Chromium, Python-Standardbibliothek, lokale synthetische Bilder und das reale `shop.css`. Der mobile Headless-Viewport beträgt technisch 500 Pixel. Im Vergleich der repräsentativen unoptimierten mit der responsiven Ausgabe ergaben sich:

| Laborwert | Unoptimiert mobil | Optimiert mobil | Optimiert Desktop |
|---|---:|---:|---:|
| ausgewähltes Hero-Bild | 1200 px | 500 px | 1200 px |
| gemessene Bildbytes | 3.512.926 | 1.503.417 | 3.512.926 |
| hoch priorisierte Bilder | 2 | 1 | 1 |
| CLS | 0 | 0 | 0 |

Die mobile Bildübertragung sank in dieser synthetischen Fixture um 2.009.509 Bytes beziehungsweise rund 57,2 Prozent. Das ist ein Laborvergleich der Ressourcenauswahl und keine Prognose für konkrete Kundenbilder. Chromium stellt im verwendeten Headless-Dump LCP-Einträge nicht zuverlässig für alle Läufe bereit; deshalb wird kein LCP-Zeitwert behauptet. Der Headless-Modus lud außerdem das weit entfernte Lazy-Bild innerhalb des Messfensters, sodass daraus keine reale Lazy-Loading-Ersparnis abgeleitet wird. INP ist ohne repräsentative reale Benutzerinteraktion nicht messbar. Feldwerte für LCP, CLS und INP bleiben nach Einbau finaler Inhalte auf einer freigegebenen Staging-Website zu erheben.

### 5.13 Öffentliche Frontend-Abhängigkeiten

- Das öffentliche Site-Layout lädt den vollständigen jQuery-Build 3.7.1 mit AJAX-Unterstützung und Bootstrap 4.6.2 CSS sowie Bundle einschließlich Popper. jQuery Migrate, der Slim-Build und zusätzliche Bibliothekskopien werden nicht geladen. Das interne ERP behält seine vorhandenen Legacy-Abhängigkeiten.
- Die versionsgenauen Dateien wurden direkt von `code.jquery.com` beziehungsweise `cdn.jsdelivr.net` geladen. Ihre SHA-384-Werte wurden lokal aus den ausgelieferten Bytes berechnet; alle drei externen Tags verwenden diese SRI-Werte und `crossorigin="anonymous"`.
- Eine lokale Kopie würde dieselben drei Dateien zusätzlich im Repository pflegen, ohne Requests oder Bytes zu reduzieren. Deshalb bleiben die geprüften CDNs bestehen. Ausfallsicherheit und Datenschutz sind gegen den Vorteil der zentralen, unveränderlichen Dateien abzuwägen; ein späteres Self-Hosting muss Cache-Header, Updates und Integritätsprüfung gemeinsam regeln.
- Die Reihenfolge jQuery, Bootstrap-Bundle und `shops.js` bleibt am Dokumentende erhalten. Chromium klassifiziert diese drei Ressourcen dort als nicht render-blockierend. `defer` wurde nicht ergänzt, da nachfolgende kundenspezifische Analytics- und vorhandene Inline-Scripts ohne vollständige Entkopplung ihre Ausführungsreihenfolge ändern könnten.
- In `shop.css` wurden nur die beiden generischen `header`-/`header.home`-Regeln entfernt, die von den identischen spezifischen `.dw-site-header`-Regeln vollständig abgedeckt waren. Themes, Klassen, Breakpoints und Komponentenlayouts bleiben unverändert.

Die reproduzierbare lokale Chromium-Fixture `tests/site/frontend-assets-http.py` lädt die echten, hashverifizierten Bibliotheksdateien über einen lokalen HTTP-Server. Sie prüft Start- und CMS-Seite, Desktop- und Mobilnavigation, Mega-Menü samt Escape, Suche, Produktliste, Collapse, Tabs, Modal, Carousel, Warenkorb-AJAX, Anfrageformular und Checkout. Es traten keine Konsolen-, JavaScript- oder Ressourcenfehler auf. Die Assetmessung umfasst ausschließlich die initialen öffentlichen CSS-/JS-Dateien ohne Bilder und optionale Theme-Datei:

Ein separater JavaScript-Parser ist in der lokalen Umgebung nicht installiert. `shops.js` wurde deshalb nicht nur statisch gelesen, sondern mit beiden aktualisierten Bibliotheken in Chromium vollständig geparst und in den genannten Interaktionen ausgeführt.

| Laborwert | Vorher: jQuery 3.5.1 / Bootstrap 4.5.2 | Nachher: jQuery 3.7.1 / Bootstrap 4.6.2 |
|---|---:|---:|
| initiale CSS-/JS-Requests | 5 | 5 |
| render-blockierende Ressourcen | 2 CSS | 2 CSS |
| nicht render-blockierende Scripts am Dokumentende | 3 | 3 |
| rohe Assetbytes | 357.342 | 359.713 |
| lokal gzip-komprimierte Assetbytes | 82.974 | 83.193 |

Die Aktualisierung erhöht diese Laborgröße damit um 2.371 rohe beziehungsweise 219 gzip-komprimierte Bytes. Sie wird als Kompatibilitäts- und Sicherheitswartung eingeordnet, nicht als gemessene Byte-Einsparung. Reale CDN-Header, TLS-Verbindungen, Cachetreffer, optionale Theme-Dateien und kundenspezifisches Analytics sind in diesen Bytewerten nicht enthalten. LCP, CLS und INP wurden in dieser Abhängigkeits-Fixture nicht ermittelt.

Bootstrap 4.6.2 ist die letzte 4.x-Version und seit Januar 2023 EOL. Eine spätere Bootstrap-5-Migration muss mindestens alle `data-toggle`, `data-target`, `data-slide`, `data-ride` und `data-parent` Attribute auf die neue Datenattributsyntax umstellen, direkte jQuery-Aufrufe wie `.collapse()`, `.tab()`, `.modal()` und `.carousel()` ersetzen, `ml-*`-/`mr-*`-Utilities sowie Input-Groups und Navbars prüfen und sämtliche öffentlichen Komponenten erneut im Browser testen. Diese Migration darf nicht mit den älteren internen ERP-Bibliotheken gekoppelt werden.

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
| Reale lokale HTTP-Crawler-, SEO-, Redirect-, Sitemap-, Ressourcen-, Bildperformance- und Begrenzungsfälle | 34 Prüfungen bestanden |
| SEO-Metadaten, Open Graph, Breadcrumbs, strukturierte Daten und Überschriften | 46 Prüfungen bestanden |
| Technische HTML5-/Indexierungs-/Header-Baseline | 40 Prüfungen bestanden |
| Robots, Sitemap, kanonische Basis-URL, Routing und Isolation | 31 Prüfungen bestanden |
| Slug-Normalisierung, Speicherwege, Hierarchie, Kollisionen, Redirect-Erzeugung und Kompatibilität | 67 Prüfungen bestanden |
| Site-CMS-Struktur, Assets, SRI, Bildpriorisierung und responsive Medienflächen | 165 Prüfungen bestanden |
| Dateizugriff, Medienvarianten, LCP-Attribute und Mandantenisolation | 327 Prüfungen bestanden |
| Isolierte Chromium-Bildprioritäts-, Doppeldownload-, CLS- und Übertragungsmessung | 4 Prüfungen bestanden; LCP-/INP-Grenzen ausdrücklich dokumentiert |
| Reale jQuery-/Bootstrap-Dateien in Chromium: öffentliche Komponenten, Navigation, AJAX, Suche und Checkout | 41 Prüfungen bei 500 und 1365 Pixeln bestanden; keine Browser-/Ressourcenfehler |
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
- Open-Graph-URL, Breadcrumb-JSON-LD und Organization-Markup werden bei fehlender oder ungültiger `shop.url` ebenfalls ausgelassen. Externe Bild-URLs und Medien außerhalb der ausdrücklich öffentlichen Bildverzeichnisse werden nicht übernommen.
- Legacy-WYSIWYG-Inhalte können bereits mehrere eigene H1 enthalten. Die Templates fügen in diesem Fall keine weitere H1 hinzu, verändern bestehende Kundendaten aber nicht; der Bestand bleibt im Relaunch-Crawl redaktionell zu bereinigen.
- Bereits vorhandene Medien ohne die vom MediaManager erzeugten Varianten bleiben als Original erreichbar und erhalten weiterhin reale Maße und Ladeattribute, aber kein künstliches `srcset`. Ein kontrollierter Bestands-Backfill wurde nicht durchgeführt.
- SVG-Dateien und Formate ohne zuverlässig lesbare Rastermaße werden sicher ausgegeben, jedoch ohne erfundene `width`-/`height`-Werte. Moderne WebP-/AVIF-Konvertierung und reale LCP-/CLS-Messungen bleiben offen.
- Die lokale Chromium-Fixture bewertet Ressourcenauswahl und synthetisches CLS, ersetzt aber weder Lighthouse auf der finalen Website noch reale Chrome-UX-Felddaten. Ihr Headless-Dump liefert LCP nicht zuverlässig in allen Läufen und lädt Lazy-Bilder innerhalb des Messfensters; diese Grenzen sind Teil des Reports.
- Bootstrap 4.6.2 ist trotz des Updates EOL und erhält keine regulären Sicherheitskorrekturen. Die getestete 4.x-Aktualisierung reduziert das aktuelle Kompatibilitätsrisiko, ersetzt aber nicht die separat geplante Bootstrap-5-Migration.
- Die drei öffentlichen Bibliotheksdateien bleiben von zwei externen CDN-Ursprüngen abhängig. SRI verhindert die Ausführung abweichender Bytes, bietet jedoch keinen Verfügbarkeits-Fallback. Ein ungeprüfter lokaler Fallback wurde bewusst nicht ergänzt.
- Kundenspezifisches Analytics kann weiterhin Inline-Abhängigkeiten besitzen. Deshalb bleiben die Scripts am Dokumentende ohne `defer`; eine spätere Entkopplung muss alle konfigurierten Analytics-Ausgaben und Consent-Gating gemeinsam prüfen.
- Eine spätere WebP-/AVIF-Pipeline muss Erzeugung, Original-Fallback, Umbenennung, Kopieren, Löschen, Backups und kontrollierten Bestands-Backfill gemeinsam lösen. Einzelne Konvertierungsdateien ohne diesen Lebenszyklus dürfen nicht ergänzt werden.
- Der Crawler erzeugt keine Aussage über unbekannte historische URLs. Eine vollständige fachliche Alt-URL-Liste muss vor dem Relaunch über `--paths` zusätzlich geprüft werden. Heuristische Soft-404- und Alt-Text-Hinweise benötigen eine manuelle Bewertung.
- Es erfolgten kein Push, kein Deployment und keine produktive Datenbankänderung.

## 9. Empfohlene nächste Phasen

### Phase 2 – URL-, SEO- und Relaunch-Basis

- Bekannte historische URLs inventarisieren und einen geprüften Import- oder kleinen manuellen Zuordnungsweg ergänzen.
- Legacy-Inhalte mit mehreren gespeicherten H1 redaktionell bereinigen und die hilfreiche 404-Seite vervollständigen.
- Den vorhandenen Crawler mit ausdrücklich freigegebener Staging-URL und vollständiger Alt-URL-Liste ausführen, JSON-Report archivieren und Befunde abarbeiten.

### Phase 3 – Performance und Assets

- Die implementierte responsive Bildausgabe, Einzel-LCP-Priorisierung und adaptive Produktbildfläche anhand realer Staging-Inhalte nachmessen und verbleibende Legacy-WYSIWYG-Bilder auditieren.
- WebP-/AVIF-Derivate sowie einen gegebenenfalls erforderlichen kontrollierten Backfill für ältere Medien separat bewerten.
- Die bestehende CDN-/SRI-Entscheidung im Betrieb auf Verfügbarkeit und Datenschutz prüfen; Self-Hosting nur mit versionierten Cache- und Updateprozessen umsetzen.
- Bootstrap 5 als getrennte öffentliche Migration mit Datenattribut-, Utility-, Komponenten- und Browseraudit planen; interne ERP-Abhängigkeiten nicht gleichzeitig umstellen.
- Lokales CSS/JS anhand realer Staging-Nutzung weiter messen, kontrolliert bereinigen, minifizieren und versioniert cachen.
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

Die Modernisierungsschritte werden in fachlich getrennten lokalen Commits geführt. Dateien mit mehreren Themen wurden bei den früheren Phasen hunkgenau zugeordnet; die folgenden Phasen bauen jeweils auf dem dokumentierten Vorgängerstand auf.

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

### Commit 8: `fix site robots noindex handling`

Inhalt:

- Nicht indexierbare Websites bleiben crawlbar, damit Robots-Meta und `X-Robots-Tag` ausgewertet werden können.
- Keine Sitemap-Werbung und weiterhin HTTP 404 am Sitemap-Endpunkt nicht indexierbarer Websites.
- Aktualisierte Robots-, Sitemap- und Baseline-Regressionen.

### Commit 9: `improve site seo metadata and structured data`

Inhalt:

- Zentrale, escaped SEO-Titel, Descriptions und Open-Graph-Ausgabe mit validierten Shop- und Medien-URLs.
- Gemeinsame visuelle und strukturierte Breadcrumb-Hierarchie einschließlich verschachtelter CMS-Seiten.
- Minimales Organization-Markup und fachlich begrenztes Product-Markup ohne erfundene Angaben.
- Eindeutige Template-H1 mit Hero-Abstufung und Kompatibilitätsbehandlung für vorhandene Legacy-H1.
- Automatisierte SEO-, Mandanten-, URL-, JSON-LD- und Überschriftenregressionen ohne Datenbankänderung.

### Commit 10: `add site seo crawl checks`

Inhalt:

- Explizit zu startender und auf eine freigegebene Website begrenzter HTTP-Crawler ohne externe Abhängigkeiten.
- Prüfungen für Statuscodes, Redirects, Soft-404, interne Links, Canonicals, SEO-Metadaten, Open Graph, JSON-LD, Breadcrumbs, Robots, Sitemap und interne Ressourcen.
- Begrenzungen für Requests, Seiten, Tiefe, Query-Varianten, Ressourcen, Redirects und Antwortgrößen sowie optionale historische Pfadlisten.
- Lesbarer Terminalbericht und strukturierter JSON-Bericht mit URL und konkreter Beschreibung je Befund.
- Isolierte reale HTTP-Fixtures für Erfolgs-, Fehler-, Noindex-, Mandanten-, Timeout- und Begrenzungsfälle.

### Commit 11: `optimize responsive site images`

Inhalt:

- Gemeinsame sichere Ausgabe verwalteter öffentlicher Bilder mit tatsächlichen Abmessungen und escaped Attributen.
- Wiederverwendung vorhandener 300-/500-/800-/1000-Pixel-Varianten für `srcset` und kontextbezogene `sizes` ohne Laufzeit-Generierung.
- Lazy Loading für nachgelagerte Bilder sowie Priorisierung von CMS-Hero, erster Startseiten-Slide und initialem Produktbild.
- Einbindung in CMS-Blöcke, Kategorien, Produkte, Galerien, Anfragekarten, Slides und Shop-Logo.
- Automatisierte Medien-, Varianten-, Template-, Pfad- und Mandantenregressionen ohne Datenbankänderung.

### Commit 12: `improve site loading performance`

Inhalt:

- Begrenzung hoher Bildpriorität auf den ersten sichtbaren CMS-Medienblock beziehungsweise das initiale Slider- oder Produktbild.
- Asynchrones Decoding nachgelagerter Bilder und adaptive, layoutstabile Produkt- und Modalbildflächen.
- Erweiterung des Website-Crawlers um Bilddimensionen, responsive Größen und widersprüchliche oder mehrfache LCP-Priorisierung.
- Reproduzierbare lokale Chromium-Fixture für Ressourcenauswahl, übertragene Bildbytes und CLS mit ausdrücklich dokumentierten LCP-/INP-Grenzen.
- Dokumentierte Entscheidung gegen eine unvollständige WebP-/AVIF-Pipeline ohne sicheren Medienlebenszyklus und reale Bestandsmessung.

### Commit 13: `update site frontend dependencies`

Inhalt:

- Aktualisierung ausschließlich des öffentlichen Frontends auf den vollständigen jQuery-Build 3.7.1 und Bootstrap 4.6.2 einschließlich Bundle.
- Versionsgenaue CDN-URLs mit lokal verifizierten SHA-384-SRI-Werten und anonymem CORS ohne Migrate, Slim-Build oder doppelte Bibliotheken.
- Beibehaltung der getesteten Abhängigkeitsreihenfolge am Dokumentende und dokumentierte Entscheidung gegen eine riskante pauschale `defer`-Umstellung.
- Entfernung zweier vollständig redundanter generischer Header-Regeln ohne Änderung der spezifischen Shop- oder Theme-Selektoren.
- Reproduzierbare Chromium-Regression für öffentliche Komponenten, AJAX, Navigation, Suche, Checkout, Breakpoints, Browserfehler und Assetmessung.

Wegen der Schemaänderungen in Commit 3 und Commit 6 darf der Gesamtstand nicht ohne abgestimmte Deployment-Migration ausgerollt werden.

## 11. Abschlussstatus

Die technische Basis für HTML5, mandantenbezogene Indexierungssteuerung, dynamische Robots-/Sitemap-Ausgabe, zentrale SEO-/Open-Graph-Metadaten, Breadcrumb- und Organization-Strukturdaten, einen begrenzten realen HTTP-/SEO-Crawler, sichere responsive Bildausgabe mit kontrollierter LCP-Priorisierung, aktualisierte und integritätsgeschützte öffentliche Frontend-Abhängigkeiten, grundlegende Response-Header, eine sicherere Staging-Trennung, einheitliche Slug-Erzeugung und automatische interne 301-Weiterleitungen ist vorbereitet und getestet. Der Gesamtauftrag mit 70 Punkten ist damit nicht vollständig abgeschlossen: Die offenen P0-Themen TLS-/Host-Redirects, historischer Alt-URL-Import, Consent, tatsächlicher Staging-Crawl, produktive Infrastruktur und Deployment-Prozesse bleiben vor einem Livegang verbindlich zu bearbeiten; Bootstrap 5 bleibt eine getrennte technische Migration.
