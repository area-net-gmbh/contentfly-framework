---
id: 000-000-0056
title: SonarQube Cloud als PR-Check einführen — Coverage und Datenflüsse dauerhaft erheben
status: todo
depends_on: []
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
3. Plan **Free** wählen (gilt für öffentliche Projekte).
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
- [ ] Die Coverage enthält **beide** Suiten: `tests/router.php` sammelt serverseitig, wenn `CONTENTFLY_COVERAGE_DIR` gesetzt ist; ohne die Variable ist das Verhalten unverändert (belegt durch die bestehende Suite).
- [ ] Die Gesamtzahl liegt vor, und die **drei am schwächsten abgedeckten Bereiche** sind benannt, je mit Entscheidung: Test nachziehen (Ticket) oder begründet nicht abdecken.
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
