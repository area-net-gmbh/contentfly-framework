<!-- PURPOSE: Die VERENGUNG der Framework-Git-Konventionen durch dieses Projekt — vor allem die Scopes. Overlay, kein Fork. -->

# Git conventions — overlay

Die Baseline ist `.an_framework/docs/git-conventions.md`. **Nicht hierher kopieren.**
Bei Konflikt gewinnt diese Datei. `/commit` liest zuerst die Baseline, dann diese Datei.

## Scopes

Commit-Scopes sind fachliche Module *dieses* Projekts, keine Dateinamen.

<!-- z. B. authentication · data-model · infrastructure · ci · docs -->

## Branch names

<!-- z. B. feat/<id>-<slug>, aus der Work-Item-ID abgeleitet -->

## Integration branch

**`master` — und er ist geschützt**, seit **2026-09-18**, über das Ruleset `master-schutz`
(`000-000-0051`). Ein direkter Push wird abgelehnt; jede Änderung geht über einen Pull Request.
Gegenprobe am selben Tag: GitHub lehnt einen direkten Push ab mit *„Changes must be made through a
pull request"* und *„6 of 6 required status checks are expected"*.

> **Korrektur.** Bis 2026-09-18 stand hier „geschützt seit 2026-09-17". Das stimmte nicht: Die
> Entscheidungen waren getroffen und aufgeschrieben, geschaltet war nichts — und hätte es nicht
> sein können. Branch-Schutz **und** Rulesets werden in einem **privaten** Repository erst mit
> GitHub Team durchgesetzt. Wirksam wurde die Regel erst, als das Repository öffentlich wurde.
> Wer eine Schutzregel dokumentiert, prüft sie mit einem abgelehnten Push, nicht mit der Doku.

### Was die Regel verlangt

| | |
|---|---|
| Erforderliche Checks | **alle sieben** — `check: Vorlagen-Konfiguration`, `check: composer audit`, `check: PHPStan`, `check: Bezugsweg von aussen`, `test: PHP 8.3`, `test: PHP 8.4`, `test: PHP 8.5` (seit `000-000-0096`) |
| Freigabe | **keine** — bei dieser Teamgrösse wäre sie ein Hindernis ohne Nutzen, und GitHub lässt niemanden den eigenen Pull Request freigeben |
| Gilt für Administratoren | **ja** — die *Bypass list* des Rulesets ist leer |
| Force-Push und Löschen | **verboten** |
| Merge-Methode | **nur Merge** — Squash und Rebase sind abgeschaltet. Ein Merge-Commit hält einen Task als eine revertierbare Einheit zusammen, und eine Story behält ihre Commits je Task. |
| „Require branches to be up to date" | **aus** — sonst müsste jeder Branch vor dem Merge neu auf `master` gesetzt werden, auch wenn sich nichts überschneidet. Die Checks laufen ohnehin gegen den Merge-Stand. |
| Wo eingestellt | *Settings → Rulesets* — **nicht** unter *Branches*; zwei Regelwerke nebeneinander widersprechen sich irgendwann |

**Warum alle sechs und nicht nur die drei schnellen:** Die beiden teuersten Jobs sind genau die,
die in Epic `011` vier Defekte gefunden haben, welche lokal **alle** unsichtbar waren. Sie
wegzulassen hätte den Schutz auf das reduziert, was ohnehin jeder lokal sieht.

**Die Freigabe-Frage bleibt offen, nicht beantwortet für immer.** Sie wird nachgezogen, sobald
mehr als eine Person committet — dann ist sie wirksam statt blockierend.

### Gestapelte Pull Requests

**Einen gestapelten Pull Request erst mergen, wenn seine Basis `master` ist.** Am 2026-09-18
wurden #17–#20 — jeder auf den vorigen Branch aufgesetzt — in ihre Stapel-Basis gemergt statt
nach `master`. GitHub stellt die Basis erst um, wenn der Branch darunter **gelöscht** ist, nicht
schon, wenn er gemergt ist. Folge: Vier Sicherheitsfixes fehlten auf `master`, während die
Tickets, die die Lücken beschreiben, dort schon öffentlich lagen. Nachgeholt mit #21.

Deshalb: unteren PR mergen → **seinen Branch löschen** → prüfen, dass der nächste PR jetzt
`base: master` zeigt → erst dann mergen.

## Wie ein Work Item geschlossen wird — `/done` gilt hier NICHT vollständig

`.an_framework/commands/done.md` beschreibt zwei Wege und nennt den Unterschied ausdrücklich:

> „A team that integrates through a protected branch / Merge Request does **not** run `/done`; it
> pushes the branch and opens the MR instead."

**Mit `000-000-0051` gilt für dieses Projekt der zweite Weg.** Was das konkret heisst:

| `/done`-Schritt | hier |
|---|---|
| Status auf `done`, Häkchen im Elternteil, Changelog-Zeile | **bleibt nötig** — das ist die Buchführung, und die macht kein Merge von selbst |
| `git checkout <integration-branch>` + `git merge --no-ff` | **entfällt** — das macht GitHub beim Merge des Pull Requests |

Die Buchführung wird damit zum **letzten Commit auf dem Branch**, bevor der Pull Request gemergt
wird. Sie hier aufzuschreiben ist der Punkt: Sonst macht sie jeder anders, und der Status im
Backlog läuft der Historie hinterher.

### Bevor ein Branch gelöscht wird

```
git merge-base --is-ancestor <branch-tip> master
```

**Nicht aus Vorsicht, sondern aus Erfahrung:** Am 2026-09-17 ging ein Commit verloren, weil ein
Pull Request gemergt wurde, bevor ein Push gelandet war — und das Löschen des Branches nahm die
letzte Referenz mit. Wiederhergestellt per `git cherry-pick`, aber nur, weil es auffiel.

## Konflikte in der Buchführung

**Konflikte in `CHANGELOG.md`, `breaking-changes.md` und `migration.md` werden lokal aufgelöst und
mit der Unit-Suite geprüft, nicht im Web-Editor von GitHub.** Seit `000-000-0089`.

Dreimal hat eine Auflösung von Hand die Buchführung beschädigt: Beim Merge von `0068` ging der
Registereintrag aus `0067` verloren, bei #43 und #46 die aus `0079`–`0081`. Bei #52 liess der
Web-Editor die Registerzahl in `migration.md` auf 135 stehen, obwohl es 136 waren —
`MigrationGuideTest` wurde rot, und mit ihm die CI. Lokal läuft dieser Test vor dem Push; im
Web-Editor läuft nichts.

**Der CHANGELOG mergt per `union`** (`.gitattributes`). Seine Konflikte sind immer dieselben: Zwei
Branches hängen im selben Tagesabschnitt Zeilen an dieselbe Stelle, und die richtige Auflösung ist
„beide behalten“. Lokal belegt, am 2026-09-24:

- Zwei Branches mit je einer neuen Zeile im selben Tagesabschnitt: kein Konflikt, beide Zeilen da.
  Die Gegenprobe ohne das Attribut ergibt den Konflikt.
- Der echte Branch von `0088` in den Stand von `0089`: kein Konflikt. Beide legen einen Abschnitt
  `2026-09-24` an, er steht danach nur einmal da, mit den Zeilen beider Tasks.

Der Preis: Die Zeilen stehen in der Reihenfolge „unsere, dann ihre“, nicht zeitlich. Fügen beide
Seiten dieselbe Zeile an, steht sie zweimal da. Beides ist sichtbar und harmlos; eine verlorene
Zeile ist es nicht.

**Nicht für `breaking-changes.md` und `migration.md`.** Registereinträge sind mehrzeilige
Abschnitte, die `union` ineinanderschieben würde. Die Zahl in `migration.md` ist abgeleitet, über
sie wacht `MigrationGuideTest`.

**GitHub beachtet `union` ebenfalls**, belegt am 2026-09-25 (`0089`). *Update branch* auf #59
erzeugte `aad3fae7`, Committer `GitHub`, ohne Konflikt. Derselbe Merge ohne das Attribut ergibt
einen Konflikt im `CHANGELOG.md`, und mit dem Attribut ist das Ergebnis byte-gleich mit dem von
GitHub. Ein CHANGELOG-Konflikt sperrt einen Pull Request also nicht mehr. Für
`breaking-changes.md` und `migration.md` gilt die Regel oben unverändert: lokal auflösen.

## Commit language

<!-- de | en — die Baseline ist de. Nur ausfüllen, wenn dieses Projekt auf Englisch
     committet. /commit --de / --en überschreibt es für einen einzelnen Lauf. -->

## Ticket URL base

<!-- z. B. https://tracker.example/browse/ — oder leer lassen, wenn es keinen externen
     Tracker gibt. Reine Doku für Menschen: /commit liest diesen Abschnitt nicht, sondern
     bekommt die vollständige URL als Argument und schreibt sie als eigene `Ticket:`-Zeile,
     nie in `Ref:`. -->

## Deviations from the baseline

- **`/done` mergt hier nicht** — siehe *Wie ein Work Item geschlossen wird* oben. Die Buchführung
  bleibt, der lokale Merge entfällt.
- **Konflikte in der Buchführung werden lokal aufgelöst**, der CHANGELOG mergt per `union` — siehe
  *Konflikte in der Buchführung* oben.

<!-- Nicht ausgefüllt, weil dieses Projekt die Baseline dort nicht verengt: Scopes, Branch
     names, Commit language (de), Ticket URL base (kein externer Tracker). -->
