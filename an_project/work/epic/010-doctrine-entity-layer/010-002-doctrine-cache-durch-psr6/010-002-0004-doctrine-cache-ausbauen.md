---
id: 010-002-0004
title: doctrine/cache aus dem Baum nehmen
status: todo
depends_on: [010-002-0002, 010-002-0003]
---

# doctrine/cache aus dem Baum nehmen

## Context
Nach den drei vorigen Tasks benutzt niemand mehr `Doctrine\Common\Cache`. Was bleibt, ist der
Ausbau — und er ist der eigentliche Ertrag dieser Story: `doctrine/cache` ist das **zweite und
letzte** abandoned Paket, das Epic `009` übergeben hat.

Dazu gehört das PHPStan-Muster für die vier Cache-Klassen. **Regel 3 gilt:** Ein Muster, das
nichts mehr trifft, macht den Lauf rot — es muss also weg, nicht stehenbleiben.

Und `tools/ci/audit.sh` trägt den Vermerk „auf `fail` umstellen, sobald Epic 010 durch ist".
Ob der Schalter hier schon gezogen wird oder erst mit `010-005`, ist zu entscheiden: Nach
diesem Task ist die Liste leer, aber Epic `010` ist es nicht.

## Acceptance criteria
- [ ] `doctrine/cache` steht nicht mehr in `composer.json`, und `composer.lock` bestätigt es.
- [ ] `Doctrine\Common\Cache` kommt im eigenen Code nicht mehr vor — ausgenommen Erklärungen, die den abgelösten Weg beschreiben.
- [ ] Das PHPStan-Muster für die Cache-Klassen ist gestrichen, und der Lauf ist `[OK] No errors`.
- [ ] `composer audit --locked` meldet **null** abandoned Pakete.
- [ ] Über `--abandoned=fail` ist entschieden und begründet — hier gezogen oder mit Begründung an `010-005` gegeben.
- [ ] Volle Suite grün, Console startet, `appcms:install` läuft auf einer frischen Datenbank durch.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, die volle Suite und ein
vollständiger Durchlauf mit frischer Datenbank. Dazu ein `grep` über `lib`, `custom`, `bin` und
`tests` als Nachweis, dass keine Codestelle übrig ist.
