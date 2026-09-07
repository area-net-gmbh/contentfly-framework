---
id: 006-003-0000
title: vendor/ aus Git lösen und den Build nachziehen
status: todo
depends_on: [006-002-0000]
---

# vendor/ aus Git lösen und den Build nachziehen

## Goal
Die eingefrorenen Vendor-Bäume verlassen die Versionskontrolle. Was heute als 11 208 committete
Dateien im Repo liegt, entsteht künftig beim Build. `an_project/docs/deployment.md` beschreibt
diesen Zustand bereits — diese Story stellt ihn her.

## Umfang

### A — Beide Bäume aus dem Index
| Baum | Dateien im Index |
|---|---|
| `vendor/` | 5 239 |
| `custom/vendor/` | 5 969 |

Entfernt wird über `git rm -r --cached`, damit die Dateien lokal liegen bleiben und eine
laufende Umgebung nicht mitten im Umbau zerfällt. Erst danach greifen die Ignore-Regeln.

### B — Die `.gitignore`-Falle
Die heutige Regel lautet sinngemäß „`composer.lock` überall ignorieren, **außer**
`custom/composer.lock`":

```
composer.lock
!custom/composer.lock
```

Der Kommentar darüber begründet das mit „vendor-interne Locks sind Rauschen". Mit dem neuen
Root-Manifest aus `006-002` fällt der **Root-Lock durch genau diese Regel** — er würde
stillschweigend nicht committet, und `composer audit --locked` (Story `006-005`) liefe gegen
nichts. Die Regel ist so umzustellen, dass beide Anwendungs-Locks committet werden und nur die
Locks *innerhalb* der Vendor-Bäume ignoriert bleiben.

Dazu kommen die Ignore-Einträge für `vendor/` und `custom/vendor/` selbst.

### C — Build und Dokumentation nachziehen
- `composer install --no-dev --optimize-autoloader` als der Befehl, der den Baum im
  Deployment-Artefakt erzeugt — so steht es in `an_project/docs/deployment.md`.
- `an_project/docs/runbook.md` bekommt den Schritt „Abhängigkeiten installieren" **vor** der
  Installation der Datenbank. Heute steht dort noch, Composer sei erst nach Epic `006` nutzbar
  und der Baum liege eingefroren im Repo — das ist ab hier überholt.
- Der Hinweis in `an_project/docs/technical.md`, `vendor/`-in-Git sei eine bewusste
  PHP-8-Notlösung, wird auf den neuen Stand gebracht.

### D — Was dabei sichtbar wird
Nach dem Ausbau ist ein frischer Checkout **ohne `composer install` nicht lauffähig**. Das ist
gewollt und der eigentliche Punkt der Story, muss aber an jeder Stelle stehen, an der bisher
„einfach auschecken und loslegen" stand — Runbook, README, `tests/README.md`.

## Fertig, wenn
- `vendor/` und `custom/vendor/` sind aus dem Git-Index entfernt und werden ignoriert.
- Die `composer.lock`-Regel in `.gitignore` lässt **beide** Anwendungs-Locks durch; der Root-Lock
  ist nachweislich committet (`git ls-files composer.lock` liefert einen Treffer).
- `composer install --no-dev --optimize-autoloader` erzeugt aus dem committeten Stand einen
  vollständigen Baum.
- `runbook.md`, `deployment.md` und `technical.md` beschreiben den neuen Ablauf; kein Dokument
  behauptet mehr, der Vendor-Baum liege im Repo.
- Ein frischer Checkout plus `composer install` führt zum selben Ergebnis wie der bisherige
  Checkout — geprüft an der Testsuite.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
