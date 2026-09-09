---
id: 009-004-0003
title: Die Bruchstellen des Kernel-Wechsels festhalten
status: todo
depends_on: []
---

# Die Bruchstellen des Kernel-Wechsels festhalten

## Context
`an_project/docs/breaking-changes.md` ist die Grundlage für den Migrationsleitfaden aus Epic
`007`. Der Kernel-Wechsel hat mehrere Stellen erzeugt, an denen ein Bestandsprojekt bricht —
und keine davon steht bisher dort.

Bekannt aus den Stories:

- **`getSilexApplication()` gibt es nicht mehr.** Sie war an `Knp\Command\Command` geerbt und
  mit dem Paket weg. Ein Projekt-Command, der sie ruft, bekommt einen Fehler. Ersatz:
  `anwendung()`.
- **`$app['request']` ist entfallen.** Der Schlüssel war eine Falle — der Container merkt sich
  Factory-Ergebnisse, hätte also ab dem ersten Zugriff denselben Request geliefert.
- **`$app['controllers_factory']` ist entfallen.** Ein Projekt, das einen eigenen
  Controller-Provider schreibt, benutzt jetzt `Kernel\Routing\Routensammlung`.
- **`connect()` hat einen Rückgabetyp.** Ein eigener Provider muss eine `RouteCollection`
  liefern.
- **Der Container ist nicht mehr Pimple.** `share()`, `protect()`, `raw()`, `factory()` und
  `register()` gibt es nicht; `extend()` wirft eine `\RuntimeException` statt Pimples
  `FrozenServiceException`.
- **`Classes\Event` erbt von den Contracts.** Ein Projekt, das davon ableitet und die alte
  Basisklasse type-hinted, bricht.
- **`dispatch()` hat die umgekehrte Argumentreihenfolge.** Ein Projekt, das eigene Ereignisse
  verteilt, muss sie drehen.
- **Eine `unique`-Verletzung antwortet mit 409 statt 500** (`009-003-0002`).

## Acceptance criteria
- [ ] Jede Bruchstelle steht in `breaking-changes.md`, im dortigen Format: was war, was ist,
      wer betroffen ist, was zu tun ist.
- [ ] Die Liste ist **vollständig** — gegen die Ergebnisse der vier Stories geprüft, nicht aus
      dem Gedächtnis geschrieben.
- [ ] Was sich für ein Projekt **nicht** ändert, steht ebenfalls da: `$app['schlüssel']`,
      `before()`/`after()`/`error()`, der `RouteManager`, `CustomCommand`. Eine Liste nur der
      Brüche liest sich, als bräche alles.
- [ ] Epic `007` wird nicht vorweggenommen: Hier steht, **was** bricht, nicht die Rector-Regel,
      die es repariert.

## Verification
Die Einträge gegen die Ergebnisteile von `009-001` bis `009-003` gelesen — jede dort genannte
Änderung an einer öffentlichen Oberfläche taucht auf oder ist ausdrücklich als nicht relevant
eingestuft.
