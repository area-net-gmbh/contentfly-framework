---
id: 000-000-0028
title: LoadMetadata verlangt eine modified-Spalte von jeder Entity
status: todo
depends_on: []
---

# LoadMetadata verlangt eine modified-Spalte von jeder Entity

## Context
`Classes/Events/LoadMetadata` haengt an **jede** Entity, die kein `BaseTree` oder `BaseI18nTree`
ist, einen Index `modified_index` auf die Spalte `modified`. Fehlt die Spalte, scheitert schon
die Installation:

```
Die Installation ist fehlgeschlagen: There is no column with name "modified" on table "pim_revoked_token".
```

**Die Meldung nennt den Grund nicht.** Sie sagt, was fehlt, aber nicht, wer es verlangt — und der
Listener steht an einer Stelle, an der niemand sucht, der gerade eine neue Entity geschrieben
hat. Gefunden bei `013-003-0003`, beim Anlegen von `RevokedToken`; die Spalte steht dort jetzt
mit einem Kommentar, der sagt warum.

**Die Annahme ist nirgends festgehalten.** Ein Projekt, das eine eigene Entity anlegt, die nicht
von `Base` erbt, laeuft in dasselbe — und `an_project/docs/dev-guide.md` erwaehnt es nicht.

**Drei Wege stehen offen, und die Wahl gehoert begruendet:**

1. Der Listener ueberspringt Entities ohne `modified`-Feld. Am wenigsten ueberraschend, aendert
   aber stillschweigend, welche Tabellen den Index bekommen.
2. Der Listener wirft mit einer Meldung, die ihn selbst benennt. Ehrlicher, bricht aber die
   Installation weiterhin ab.
3. Die Anforderung wird dokumentiert und bleibt. Billig, und verschiebt das Problem auf den
   naechsten, der eine Entity schreibt.

## Acceptance criteria
- [ ] Der Weg ist gewaehlt und im Code begruendet, nicht nur umgesetzt.
- [ ] Eine Entity ohne `modified` fuehrt entweder zu einer Meldung, die `LoadMetadata` benennt, oder gar nicht mehr zum Abbruch — und ein Test haelt fest, welches von beidem gilt.
- [ ] Die Anforderung steht in `an_project/docs/dev-guide.md` bei dem, was eine neue Entity braucht.
- [ ] `RevokedToken` ist nachgezogen: Bleibt die Spalte, sagt der Kommentar warum; faellt die Anforderung, faellt die Spalte mit.
- [ ] Die volle Suite bleibt gruen, und `appcms:install` laeuft durch.

## Verification
Eine Wegwerf-Entity ohne `modified` anlegen und `appcms:install` laufen lassen — vorher und
nachher. Volle Suite, PHPStan.
