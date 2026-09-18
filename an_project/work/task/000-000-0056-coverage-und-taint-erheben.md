---
id: 000-000-0056
title: SonarQube Cloud als PR-Check einführen — Coverage und Datenflüsse dauerhaft erheben
status: in-progress
depends_on: [000-000-0065]
---

# SonarQube Cloud als PR-Check einführen — Coverage und Datenflüsse dauerhaft erheben

## Context
**Umgeplant am 2026-09-18.** Der erste Zuschnitt sah eine **einmalige** Erhebung vor. Seit das
Repository öffentlich ist, gibt es SonarQube Cloud (ehemals SonarCloud) laut Anbieter ohne
Lizenzkosten — und damit dieselben zwei Erhebungen **bei jedem Pull Request** statt einmal.
Empfohlen im Sicherheitsnachweis vom 2026-09-18, Abschnitt 5.

**Die Frage bleibt dieselbe: Wo sehen 680 Tests und PHPStan *nicht* hin?**

- **Coverage** ist nie erhoben worden. „Gut getestet" ist eine Vermutung.
- **Datenflüsse**: PHPStan findet Typfehler, keine Taint-Pfade. Die Injections aus `000-000-0062`
  und `000-000-0063` und der Upload aus `000-000-0038` waren genau solche Pfade — alle **von
  Hand** gefunden.

## Warum SonarQube Cloud und nicht Gitar
Gefragt am 2026-09-18. **Gitar** ist ein KI-Code-Review-Agent, den Sonar im Mai 2026 übernommen
hat. Er prüft Pull Requests mit generativer KI auf Logik und Absicht, schreibt Korrekturen und
iteriert, bis die CI grün ist. Sonar selbst nennt ihn eine **Ergänzung** zu SonarQube.

| | SonarQube Cloud | Gitar |
|---|---|---|
| Art | deterministische statische Analyse | KI-Review, generativ |
| Taint-Analyse | ja | nein |
| Coverage | ja | nein |
| Reproduzierbares Gate | ja | nein — das Urteil kann beim nächsten Lauf anders ausfallen |
| Einbindung | Pipeline-Job | GitHub-App |
| Kosten, öffentliches Repo | kostenlos (OSS-Plan) | laut Produktseite $20–40 je Benutzer und Monat; eine OSS-Variante war nicht zu belegen |

**Dieser Task braucht Coverage, Taint und ein Gate, das bei gleichem Code gleich urteilt.** Ein
erforderlicher Check, der nicht reproduzierbar ist, trainiert das Ignorieren. Gitar kann später
als **zusätzlicher, nicht erforderlicher** Reviewer Sinn ergeben — ein eigenes Ticket, sobald mehr
als eine Person committet.

## Voraussetzung: die Lizenz
Der kostenlose OSS-Plan gilt für öffentliche Projekte **mit Open-Source-Lizenz**. Die Angaben im
Repo widersprachen sich (Dual-Lizenz in `LICENSE`, „proprietär" in beiden `composer.json`).
Entschieden am 2026-09-18: **MIT** — umgesetzt in `000-000-0065`, deshalb `depends_on`.

## Die drei Stellen, an denen ein naiver Einbau falsch misst

**1. Die Coverage der Integrationstests entsteht in einem anderen Prozess.** 364 der 680 Tests
schicken HTTP an den Testserver (`php -S … tests/router.php`); der ausgeführte Framework-Code
läuft **dort**, nicht in PHPUnit. `phpunit --coverage-clover` sähe nur die Unit-Tests — Sonar
würde eine Zahl anzeigen, die **systematisch zu niedrig** ist, und genau die Bereiche als
ungetestet melden, die die Integration-Suite am gründlichsten prüft (Rechte, Auth, Upload).
→ Serverseitig sammeln: PCOV im Testserver, `tests/router.php` schreibt je Request einen
Teilbericht, wenn `CONTENTFLY_COVERAGE_DIR` gesetzt ist; `phpcov merge` führt Unit- und
Server-Anteil zu **einem** Clover-Bericht zusammen. Ohne die Variable tut der Router nichts
anders als heute.

**2. Das Standard-Quality-Gate verlangt 80 % Coverage auf neuem Code.** Der erste Zuschnitt
dieses Tasks hat davor gewarnt, und die Warnung gilt: **Eine Coverage-Schwelle erzeugt Tests,
die Zeilen berühren statt zu prüfen** — das ist die Eigenschaft, gegen die alle bestehenden
Gates gebaut sind. → Ein eigenes Gate `Contentfly` statt „Sonar way", **ohne**
Coverage-Bedingung. Coverage wird angezeigt, nicht erzwungen.

**3. Sonar meldet sein Ergebnis über die eigene GitHub-App.** Deren Check-Name gehört dem
Anbieter und kann sich ändern. → Die Pipeline wartet auf das Gate (`sonar.qualitygate.wait=true`)
und wird selbst rot. Erforderlicher Check im Ruleset ist dann **unser** Job-Name
`analyse: SonarQube Cloud`, genau wie die sechs bestehenden.

## Entscheidungen (vorgeschlagen, beim Umsetzen bestätigen)

| Frage | Vorschlag | Grund |
|---|---|---|
| Analyse-Art | **CI-basiert** (GitHub Actions), *Automatic Analysis* aus | Automatic Analysis kennt keine Coverage |
| Ort | eigener Job `analyse: SonarQube Cloud` in `pipeline.yml`, `needs: [test]` | eine Pipeline, eine Wahrheit; läuft nur, wenn die Suite grün ist |
| Coverage-Treiber | **PCOV**, nur in diesem Job | schnell, nur Zeilenabdeckung; die sechs bestehenden Jobs bleiben ohne Treiber — so wie `phpunit.xml.dist` es begründet |
| Quality Gate `Contentfly` (neuer Code) | 0 neue Vulnerabilities · Security Rating A · 100 % Security Hotspots geprüft · Reliability Rating A | Sicherheit blockiert, Stil nicht |
| Coverage-Schwelle | **keine** | siehe Stelle 2 |
| Forks und Dependabot | Job übersprungen (`if:` wie bei `bezugsweg`) | beide bekommen kein `SONAR_TOKEN`; ein Dependabot-PR ändert nur den Lock |
| Fremde Action | `SonarSource/sonarqube-scan-action`, **auf Commit-SHA gepinnt** | zweite fremde Action im Baum; `pipeline.yml` verlangt, dass sie ihre Laufzeit selbst erklärt — beim Einbau prüfen |
| Einführung | **Phase 1:** 2–4 Wochen nicht erforderlich, Befunde sichten · **Phase 2:** siebter erforderlicher Check im Ruleset | der erste Lauf bringt die Altlasten des Bestandscodes |

## Ihre Schritte — vor dem Umsetzen
Alles unter **sonarcloud.io** und in den GitHub-Einstellungen; im Repo nicht sichtbar. Die
Beschriftungen können beim Anbieter leicht abweichen.

1. **sonarcloud.io** → **Log in** → **With GitHub**.
2. **Import an organization** → Organisation `area-net-gmbh` wählen → die SonarQube-Cloud-App
   installieren, Zugriff **nur** auf `contentfly-framework` (*Only select repositories*).
3. Plan **Free** bzw. den **OSS-Plan** wählen (öffentliches Projekt mit Open-Source-Lizenz — erst nach dem Merge von `000-000-0065`).
4. **Analyze new project** → `contentfly-framework` → **Set up**.
5. Bei der Analyse-Methode **With GitHub Actions** wählen. Den angezeigten **Token** kopieren.
   Prüfen unter *Administration → Analysis Method*, dass **Automatic Analysis aus** ist.
6. GitHub: **Settings → Secrets and variables → Actions → New repository secret**
   → Name `SONAR_TOKEN`, Wert = Token aus Schritt 5.
7. SonarQube Cloud: **Quality Gates → Create** → Name `Contentfly` → Bedingungen auf *New Code*:
   *Vulnerabilities* is greater than 0 · *Security Rating* worse than A ·
   *Security Hotspots Reviewed* less than 100 % · *Reliability Rating* worse than A.
   Dann **Projekt → Administration → Quality Gate → `Contentfly`**.
8. **Projekt → Administration → New Code** → *Previous version*.
9. Mir **Organization Key** und **Project Key** nennen (stehen unter *Information*) — sie
   gehören in `sonar-project.properties`.

## Acceptance criteria
- [ ] `sonar-project.properties` im Repo: Quellen `lib`, `custom`, `bin`; Tests `tests`; Coverage-Pfad; Ausschlüsse (`vendor`, generierte Proxies) begründet.
- [ ] Der Job `analyse: SonarQube Cloud` läuft bei jedem PR und Push auf `master`, nicht im Zeitplan-Lauf, nicht bei Forks und Dependabot; er wird rot, wenn das Gate rot ist.
- [x] Die Coverage enthält **beide** Suiten: `tests/router.php` sammelt serverseitig, wenn `CONTENTFLY_COVERAGE_DIR` gesetzt ist; ohne die Variable ist das Verhalten unverändert (belegt durch die bestehende Suite).
- [x] Die Gesamtzahl liegt vor, und die **drei am schwächsten abgedeckten Bereiche** sind benannt, je mit Entscheidung: Test nachziehen (Ticket) oder begründet nicht abdecken.
- [ ] Die Befunde des ersten Laufs sind eingeordnet — Vulnerabilities und Security Hotspots **einzeln** als „echt" (eigenes Ticket) oder „falsch positiv" (mit Begründung, in Sonar markiert). Dieser Task behebt keine Befunde.
- [ ] `deployment.md` (Gate-Tabelle) und der Sicherheitsnachweis kennen den neuen Check; `git.md` nennt ihn nach Phase 2 als siebten erforderlichen.

## Verification
**Gegenprobe statt Sichtprüfung:** In einem Wegwerf-PR die SQL-Injection aus `000-000-0062`
versuchsweise zurückbauen (`t.lang = '$lang'`). Sonar muss sie als Vulnerability melden und der
Job rot werden. Meldet er nichts, erkennt die Taint-Analyse diesen Pfad nicht — dann ist das der
wichtigste Befund dieses Tasks, und Psalm (`--taint-analysis`) ist die Alternative.

Dazu: Die Coverage-Zahl mit und ohne serverseitigen Anteil vergleichen — der Unterschied zeigt,
dass Stelle 1 wirklich greift.

## Abgrenzung
**PHPStan über Level 0 zu heben ist ein eigener Task.** Sonar ersetzt das nicht — beide sehen
Verschiedenes.

## Stand — Teil 1: die Messung (2026-09-18)
**Unabhängig von SonarQube Cloud umgesetzt, weil es die Voraussetzung ist:** Ohne richtige
Coverage zeigt auch Sonar eine falsche Zahl.

**Die erste Coverage-Zahl dieses Projekts:** 60,2 % der Zeilen, 59,1 % der Methoden, 129 Dateien —
Unit- und Integration-Suite zusammen.

**Stelle 1 bestätigt, und deutlicher als erwartet.** Nur der PHPUnit-Prozess: **19,5 %**. Mit dem
Anteil des Testservers: **60,2 %**. Ein naiver Einbau hätte ein Drittel der Wahrheit gemeldet.

**Ein Befund, den erst die Messung sichtbar gemacht hat — und der über Coverage hinausgeht:**
`<source>` in `phpunit.xml.dist` schloss `vendor` aus. Ein Ausschluss durchläuft das Verzeichnis und
folgt Symlinks; `vendor/areanet/contentfly` zeigt auf `lib/contentfly`. **Aus 122 Framework-Dateien
wurden 0** — „Quellcode" hiess für PHPUnit nur `custom/Classes`. Das verbog zweierlei: Coverage
hätte nur die Vorlage gemessen, und **`restrictDeprecations/Notices/Warnings` meldeten nur Probleme
aus `custom/Classes`** — `failOnWarning` hat den Framework-Code nie gesehen. Ausschluss entfernt
(keines der eingeschlossenen Verzeichnisse enthält `vendor`); die Suite bleibt grün, es kam also
nichts Verstecktes zum Vorschein. Das war nicht garantiert.

**Umgesetzt:**
- `tests/router.php` sammelt je Request einen Teilbericht, nur wenn `CONTENTFLY_COVERAGE_DIR`
  gesetzt und PCOV geladen ist. Welcher Code zählt, liest er aus `<source>` in `phpunit.xml.dist`.
  Ohne Variable unverändert — belegt durch die volle Suite (699 grün).
- `phpunit/phpcov` 9 als Entwicklungsabhängigkeit, zum Zusammenführen.
- `runbook.md`: der lokale Coverage-Lauf.

**Die drei schwächsten Bereiche — alle drei mit Ticket:**

| Bereich | Quote | offen | Entscheidung |
|---|---|---|---|
| `Classes/Types` | 34 % | 541 Zeilen | Test nachziehen → `000-000-0067`. Keine Entity von Framework oder Vorlage benutzt Multifile, Checkbox, Radio, Onejoin — Projekte schon. |
| `Classes/File` (Bildverarbeitung) | 23 % | 280 Zeilen | Test nachziehen → `000-000-0068`. Verarbeitet **hochgeladene** Dateien; `ImageMagick.php` 0 %. |
| `Api.php` | 59 % | 514 Zeilen | Einordnen → `000-000-0069`. Grösste absolute Lücke; zuerst zeilengenau aufschlüsseln. |

Am besten abgedeckt: `Classes/Security` mit **90,5 %**.

**Kosten der Messung:** Die Suite läuft mit PCOV rund 4 statt 1 Minute; ~700 Teilberichte, zusammen
~200 MB, zusammengeführt in 3 Sekunden. Für einen eigenen Pipeline-Job vertretbar, für die sechs
bestehenden nicht — dort bleibt kein Treiber.

**Offen — Teil 2, wartet auf Organization Key und Project Key:** `sonar-project.properties`, der Job
`analyse: SonarQube Cloud`, das Quality Gate, die Gegenprobe mit der SQL-Injection, die Einordnung
der ersten Befunde, die Doku.
