---
id: 009-003-0003
title: Das Gate scharfstellen und den Rest an Epic 010 übergeben
status: todo
depends_on: [009-003-0001, 009-003-0002]
---

# Das Gate scharfstellen und den Rest an Epic 010 übergeben

## Context
Der Abschluss. Was dann noch in PHPStan steht, kommt **ausschliesslich** aus Doctrine:

| Quelle | Fundstellen | Zuständig |
|---|---|---|
| `doctrine/orm` (entfallende Klassen, `EnsureProductionSettingsCommand`) | 19 | Epic `010` |
| `doctrine/cache` (abandoned, fällt in 2.0) | 7 | Epic `010` |
| `doctrine/annotations` (abandoned, Attribute statt Annotationen) | 4 | Epic `010` |

Die Laufzeit-Hälfte des Gates steht schon: `tools/ci/deprecations-ausnahmen.txt` ist mit
`009-002-0006` auf **null Einträge** gegangen, und das Serverlog zeigt **null Deprecations**.
Das Gate hat die Streichung der letzten Ausnahme selbst eingefordert.

Zu entscheiden bleibt die statische Hälfte, und die Konfiguration hat die Bedingung selbst
formuliert:

> **WANN DER JOB BLOCKIEREND WIRD.** Sobald `vendor/`-fremde Treffer auf null stehen. Solange
> die Liste von Silex und den Symfony-4.4-Komponenten dominiert wird, hinge das Gate an Epic
> 009 — und ein Gate, das eine andere Story erst grün machen muss, wird abgeschaltet.

Silex ist weg. Die Bedingung ist zu prüfen: Stehen die eigenen Treffer auf null, wenn man die
Doctrine-Meldungen ausnimmt? Und wenn ja — wie werden sie ausgenommen, ohne das Gate zu
entwerten? Eine Baseline würde alles einfrieren, auch das, was morgen dazukommt.

## Acceptance criteria
- [ ] Es ist entschieden und begründet, ob der PHPStan-Job blockierend wird, und mit welchem
      Level. Beides steht in `phpstan.neon.dist`, wo die bisherigen Entscheidungen stehen.
- [ ] Wenn ausgenommen wird, dann **benannt** — je Meldung mit Grund und aufl��sendem Epic, nach
      derselben Regel wie die Deprecation- und Audit-Ausnahmen aus `006-005`. Keine Baseline,
      solange sie mehr einfrieren würde als die benannten Fälle.
- [ ] Der PHPStan-Lauf braucht mehr Speicher als die Vorgabe: Mit 128 MB stürzt er ab
      (`PHPStan process crashed because it reached configured PHP memory limit`). Der Aufruf in
      `.gitlab-ci.yml` trägt das, statt es dem Zufall der Runner-Konfiguration zu überlassen.
- [ ] `tools/ci/deprecations-ausnahmen.txt` ist leer und bleibt es — der Zustand aus
      `009-002-0006` ist bestätigt, nicht angenommen.
- [ ] Was an Epic `010` übergeht, steht dort oder in seinem Epic-Text, nicht nur hier.

## Verification
`./vendor/bin/phpstan analyse --memory-limit=1G` mit dem entschiedenen Level und den
entschiedenen Ausnahmen. `sh tools/ci/deprecations-pruefen.sh` gegen ein frisches Serverlog.
Beide Gates in der `.gitlab-ci.yml` so, wie sie laufen sollen.
