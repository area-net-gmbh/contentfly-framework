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

<!-- master | main | develop — das PR-Ziel. Baseline ist `master`. /commit und /done
     vergleichen HEAD dagegen; /commit warnt, bevor direkt darauf committet wird, und /done
     mergt den Task-Branch lokal in diesen Branch. -->

## Commit language

<!-- de | en — die Baseline ist de. Nur ausfüllen, wenn dieses Projekt auf Englisch
     committet. /commit --de / --en überschreibt es für einen einzelnen Lauf. -->

## Ticket URL base

<!-- z. B. https://tracker.example/browse/ — oder leer lassen, wenn es keinen externen
     Tracker gibt. Reine Doku für Menschen: /commit liest diesen Abschnitt nicht, sondern
     bekommt die vollständige URL als Argument und schreibt sie als eigene `Ticket:`-Zeile,
     nie in `Ref:`. -->

## Deviations from the baseline

<!-- Leer lassen, wenn es keine gibt. -->
