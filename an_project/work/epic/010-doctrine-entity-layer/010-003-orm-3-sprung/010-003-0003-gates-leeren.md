---
id: 010-003-0003
title: Die Gates auf den neuen Stand bringen
status: todo
depends_on: [010-003-0002]
---

# Die Gates auf den neuen Stand bringen

## Context
Nach dem Sprung sind die verbliebenen PHPStan-Muster für ORM-2-APIs gegenstandslos, und die
Liste der abandoned Pakete ist leer.

- **PHPStan:** die Muster für die entfallenen Console-Commands, `createHelperSet()`,
  `EntityManager::create()` und die Lexer-Konstanten. **Regel 3:** Was nichts mehr trifft, macht
  den Lauf rot — die Muster müssen weg, nicht stehenbleiben.
- **`composer audit`:** `tools/ci/audit.sh` steht auf `--abandoned=ignore` mit dem Vermerk „auf
  `fail` umstellen, sobald Epic 010 durch ist". `010-002-0004` hat die Entscheidung hierher
  gegeben, weil `doctrine/cache` bis zum ORM-Sprung im Baum blieb. Jetzt ist sie fällig.
- **Die Dokumente:** `tech-stack.md` nennt ORM 2.20 als Stand, `deployment.md` die Zahl der
  Ausnahmen.

Was an Doctrine-Befunden übrig bleibt, verlässt das Epic **mit benanntem Auflöser** — nicht
stillschweigend.

## Acceptance criteria
- [ ] `phpstan.neon.dist` enthält kein Muster mehr, das nichts trifft; der Lauf ist `[OK] No errors`.
- [ ] `composer audit --locked` meldet **null** abandoned Pakete, und `tools/ci/audit.sh` steht auf `--abandoned=fail`.
- [ ] Ein Gegentest belegt, dass `fail` greift: ein künstlich abandoned Paket macht den Lauf rot.
- [ ] `tech-stack.md` und `deployment.md` beschreiben den erreichten Stand, mit gemessenen Zahlen.
- [ ] Die Suite ist grün, und der PHP-8.4-Lauf ist auf dem neuen Stand nachgefahren — er ist seit `009-004-0002` die offene Frage zum blockierenden Job.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, volle Suite. Für den
Gegentest zum `fail`-Schalter genügt ein Lauf mit einer Datei, die ein abandoned Paket
vortäuscht — er muss rot sein, sonst ist das Gate nur eine Behauptung.
