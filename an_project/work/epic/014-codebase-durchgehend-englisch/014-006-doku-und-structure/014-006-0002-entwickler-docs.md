---
id: 014-006-0002
title: Entwickler-Docs auf die englischen Namen
status: todo
depends_on: [014-006-0001]
---

# Entwickler-Docs auf die englischen Namen

## Context
Die Docs, nach denen ein Entwickler arbeitet, nennen noch alte Namen: `dev-guide.md` (14 Treffer,
darunter das Registrieren eines Login-Providers mit `$app['anmeldeanbieter']->eintragen()`),
`technical.md` (14), `architecture.md`, `deployment.md` und `runbook.md`. Wer danach einen
Provider einbindet, schreibt Code, der nicht läuft.

Umfang: alle Dateien unter `an_project/docs/` ausser den Migrations-Docs (`014-006-0003`) und der
Übergabenotiz (`014-006-0004`), dazu `an_project/project-description.md`. Die Prosa bleibt deutsch.

## Acceptance criteria
- [ ] Jeder Code-Verweis in diesen Docs stimmt mit dem Code überein, auch Methoden, die nicht in der
  Tabelle des Epics stehen (etwa `Tokenhandler::timeoutGilt()`). Codebeispiele laufen gegen den
  heutigen Code.
- [ ] Deutsche Begriffe, die wie ein alter Klassenname aussehen („Anmeldebremse", „Zugangstoken"),
  sind ersetzt: durch den Klassennamen, wo die Klasse gemeint ist, sonst durch einen Begriff, den
  die Suche nach alten Namen nicht trifft.
- [ ] Die Suche nach den alten Namen findet in diesen Dateien nichts.
- [ ] Tests, die Docs lesen (`ContainerKeysTest` gegen `dev-guide.md`), sind grün.

## Verification
Suche mit der Liste der alten Namen. Skript, das Code-Verweise aus den Docs zieht und auflöst.
Codebeispiele stichprobenhaft gegen die Klassen gelesen. Volle Suite grün.
