---
id: 011-002-0004
title: Den Weg von aussen fahren, ins Runbook schreiben und als Gate festnageln
status: todo
depends_on: [011-002-0003]
---

# Den Weg von aussen fahren, ins Runbook schreiben und als Gate festnageln

## Context
Das Ziel der Story ist erst eingelöst, wenn ein Projekt **ohne Kenntnis dieses
Arbeitsverzeichnisses** installiert werden kann. Bis hierher ist das gebaut, aber nicht gefahren —
und ein Weg, den niemand fährt, verrottet. Entschieden am 2026-09-16: echter Durchlauf **und**
CI-Gate, nicht nur ein Datum in einem Dokument.

**Der Lauf muss das `path`-Repository dieses Repos umgehen.** Läuft er im Baum, beweist er nichts:
`composer install` fände das Paket lokal und niemand merkte, dass es von aussen nicht beziehbar ist.

## Acceptance criteria
- [ ] Ein Durchlauf von aussen ist einmal von Hand gefahren: frisches Verzeichnis ausserhalb des Repos, Skeleton-Dateien hinein, `composer install` gegen das Paket-Repository, `appcms:install`, ein API-Aufruf antwortet.
- [ ] Der Lauf bezieht das Paket nachweislich **nicht** über `path` — belegt an `composer.lock` (`source`/`dist` zeigen auf das Paket-Repo).
- [ ] `runbook.md` beschreibt genau diesen Weg als „So startet ein neues Projekt", inklusive der Zugangsdaten, die ein Entwicklerrechner dafür braucht.
- [ ] Ein Gate im Actions-Workflow wiederholt ihn bei jedem Lauf und wird rot, sobald das Paket nicht mehr beziehbar ist.
- [ ] `migration.md` Phase 2 nennt den Bezugsweg für ein Bestandsprojekt — was in dessen `composer.json` steht und wie ein Update ankommt.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Das Gate läuft grün. Gegenprobe: Mit einer Constraint auf eine Version, die es nicht gibt, wird es
rot — und die Meldung sagt, woran es lag.
