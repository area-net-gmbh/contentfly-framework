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

**`master` — und er ist geschützt** (`000-000-0051`, 2026-09-17). Ein direkter Push wird
abgelehnt; jede Änderung geht über einen Pull Request.

### Was die Regel verlangt

| | |
|---|---|
| Erforderliche Checks | **alle sechs** — `check: Vorlagen-Konfiguration`, `check: composer audit`, `check: PHPStan`, `check: Bezugsweg von aussen`, `test: PHP 8.3`, `test: PHP 8.4` |
| Freigabe | **keine** — bei dieser Teamgrösse wäre sie ein Hindernis ohne Nutzen, und GitHub lässt niemanden den eigenen Pull Request freigeben |
| Gilt für Administratoren | **ja** — „Do not allow bypassing the above settings" |
| Force-Push und Löschen | **verboten** |

**Warum alle sechs und nicht nur die drei schnellen:** Die beiden teuersten Jobs sind genau die,
die in Epic `011` vier Defekte gefunden haben, welche lokal **alle** unsichtbar waren. Sie
wegzulassen hätte den Schutz auf das reduziert, was ohnehin jeder lokal sieht.

**Die Freigabe-Frage bleibt offen, nicht beantwortet für immer.** Sie wird nachgezogen, sobald
mehr als eine Person committet — dann ist sie wirksam statt blockierend.

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

<!-- Nicht ausgefüllt, weil dieses Projekt die Baseline dort nicht verengt: Scopes, Branch
     names, Commit language (de), Ticket URL base (kein externer Tracker). -->
