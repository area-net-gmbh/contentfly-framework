---
id: 013-003-0003
title: Widerruf: Logout und die Sperrliste
status: todo
depends_on: [013-003-0001, 013-003-0002]
---

# Widerruf: Logout und die Sperrliste

## Context
Der schwierige Teil der Story. Ein JWT gilt bis zum Ablauf — Logout und Leak wirken nicht,
solange niemand nachschaut.

**Der Hauptteil des Widerrufs sitzt im Refresh-Modell:** Logout löscht die Refresh-Zeile, und
damit ist der Zugang spätestens mit dem Access-Token zu. Das ist der billige Teil, denn dort
liegt ohnehin Zustand.

**Für das Restfenster braucht es die Sperrliste** — die `jti` des Access-Tokens, bis dieses
abläuft. Sie bleibt klein, weil Einträge mit dem Token verfallen. Das Kundenprojekt hatte genau
diese Liste gebaut, aber **ohne** das Refresh-Modell daneben, weshalb sie unbegrenzt wachsen
musste.

**Wofür sie NICHT gebraucht wird, ist nachgemessen:** Eine Benutzersperrung wirkt seit
`013-002-0001` sofort. Der JWT-Zweig gibt sein `UserBadge` ohne eigenen Lader zurück, also lädt
der `Benutzerlader` den Benutzer aus `pim_user` — und weist einen gesperrten mit derselben
Ausnahme ab wie einen unbekannten. Die Sperrliste ist deshalb für den **einzelnen** Token da:
Logout und Leak. Wer sie für die Sperrung baute, baute etwas, das schon steht.

**Sie gehört nicht in den Cache.** Ein geleerter Cache darf keinen Widerruf aufheben — und der
Cache ist genau das, was man leert, wenn etwas klemmt.

## Acceptance criteria
- [ ] `GET /auth/logout` entzieht das Refresh-Token und setzt die `jti` des vorgezeigten Access-Tokens auf die Sperrliste.
- [ ] Ein gesperrter `jti` wird abgewiesen, **obwohl das Token noch gültig ist** — nachgewiesen mit einem Token, dessen `exp` noch in der Zukunft liegt.
- [ ] Die Sperrliste liegt dauerhaft, nicht im Cache. Ein Neustart oder ein geleerter Cache hebt keinen Widerruf auf.
- [ ] Einträge verfallen mit dem Token, und das Aufräumen hängt an `appcms:token:cleanup` — kein zweiter Aufräumweg.
- [ ] Dass eine Benutzersperrung schon ohne Liste sofort wirkt, ist gemessen und im Ergebnis benannt.
- [ ] Logout mit einem opaquen Token verhält sich unverändert.

## Verification
Integrationstest: mit JWT anmelden, `/api/schema` öffnen, abmelden, mit demselben — noch nicht
abgelaufenen — Access-JWT erneut anfragen und eine Abweisung erwarten. Dazu ein Test, der das
entzogene Refresh-Token einlösen will, und einer für das Aufräumen. Volle Suite.
