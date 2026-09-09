---
id: 009-003-0002
title: Die restlichen eigenen Deprecations beheben
status: todo
depends_on: []
---

# Die restlichen eigenen Deprecations beheben

## Context
Was nach `009-003-0001` an eigenen Deprecations übrig bleibt, ist klein und mechanisch:

| Meldung | Fundstellen |
|---|---|
| `Application::add()` → `addCommand()`, seit Symfony 7.4 | 3 |

Dazu die Level-0-Meldungen, die keine Deprecations sind — PHPStan zählt heute **sieben**
gewöhnliche Fehler mit, darunter `Doctrine\Common\Cache\ApcuCache does not have a constructor
and must be instantiated without any parameters`. Die sind zu prüfen: Ein Level-0-Fehler ist
selten Rauschen, sondern meist ein echter Fund.

## Acceptance criteria
- [ ] Die drei `add()`-Aufrufe benutzen `addCommand()`.
- [ ] Jede der sieben Level-0-Meldungen ist angesehen und entweder behoben oder mit Begründung
      als Nicht-Fund festgehalten. Keine wird stillschweigend übergangen.
- [ ] Die Suite bleibt grün, `php bin/console.php list` unverändert.

## Verification
PHPStan meldet keine eigene Deprecation und keinen Level-0-Fehler mehr, der nicht begründet ist.
Volle Suite grün.
