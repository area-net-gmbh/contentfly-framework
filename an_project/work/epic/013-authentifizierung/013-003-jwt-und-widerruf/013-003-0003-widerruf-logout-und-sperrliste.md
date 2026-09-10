---
id: 013-003-0003
title: Widerruf: Logout und die Sperrliste
status: done
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
- [x] `GET /auth/logout` entzieht das Refresh-Token und setzt die `jti` des vorgezeigten Access-Tokens auf die Sperrliste.
- [x] Ein gesperrter `jti` wird abgewiesen, **obwohl das Token noch gültig ist** — nachgewiesen mit einem Token, dessen `exp` noch in der Zukunft liegt.
- [x] Die Sperrliste liegt dauerhaft, nicht im Cache. Ein Neustart oder ein geleerter Cache hebt keinen Widerruf auf.
- [x] Einträge verfallen mit dem Token, und das Aufräumen hängt an `appcms:token:cleanup` — kein zweiter Aufräumweg.
- [x] Dass eine Benutzersperrung schon ohne Liste sofort wirkt, ist gemessen und im Ergebnis benannt.
- [x] Logout mit einem opaquen Token verhält sich unverändert.

## Verification
Integrationstest: mit JWT anmelden, `/api/schema` öffnen, abmelden, mit demselben — noch nicht
abgelaufenen — Access-JWT erneut anfragen und eine Abweisung erwarten. Dazu ein Test, der das
entzogene Refresh-Token einlösen will, und einer für das Aufräumen. Volle Suite.

## Ergebnis

**Ein abgemeldetes Access-JWT gilt nicht mehr, obwohl sein `exp` noch in der Zukunft liegt.**
Das ist der Kern der Story, und er ist am laufenden System nachgemessen.

### Zwei Hälften, und beide sind nötig

| | |
|---|---|
| Die `jti` auf die Sperrliste, bis zum `exp` | sonst gilt das Token in der Hand des Clients weiter |
| Das Refresh-Token löschen | sonst holt sich der Inhaber gleich ein neues, und die Sperre war umsonst |

### Der Client muss sein Refresh-Token mitschicken

Das Access-JWT sagt nicht, zu welcher Refresh-Zeile es gehört. Die Verbindung stünde sonst als
**sechster Claim** darin, und der Claim-Satz aus `013-003-0001` ist absichtlich klein. Die
Alternative — alle Refresh-Zeilen des Benutzers löschen — meldete ihn auf allen seinen Geräten
ab, was beim Abmelden an einem davon niemand erwartet.

Ohne mitgeschicktes Refresh-Token wird nur das Access-JWT gesperrt; die Refresh-Zeile verfällt
über ihr eigenes Zeitlimit. Das ist die dokumentierte Lage, kein Versehen, und ein eigener Test
hält sie fest.

**Nur das eigene:** Ein mitgeschicktes Refresh-Token wird gelöscht, wenn es dem angemeldeten
Benutzer gehört. Ohne die Prüfung wäre `logout` ein Endpunkt, mit dem ein beliebiger
angemeldeter Benutzer fremde Sitzungen beenden könnte.

### Die Sperrung brauchte die Liste nicht — gemessen

Der Story-Text nannte die Benutzersperrung als Anwendungsfall. Sie ist es seit `013-002-0001`
nicht mehr, und der Test belegt es doppelt: Ein gesperrter Benutzer kommt mit seinem noch
gültigen Access-JWT nicht durch, **und die Sperrliste ist dabei leer**. Der `Benutzerlader` weist
ihn ab, weil der JWT-Zweig sein `UserBadge` ohne eigenen Lader zurückgibt.

Der Test steht da, damit die Zusicherung nicht unbelegt bleibt — und damit es auffällt, wenn
jemand den Ladeweg umbaut und die Sperrung dabei mit abschaltet.

### Der Preis: ein Lesezugriff je Request

Der JWT-Zweig liest jetzt die Sperrliste. Das ist **ein Lesezugriff auf eine kleine Tabelle mit
Unique-Index** gegen den Schreibzugriff auf `pim_token`, der der Grund für den ganzen Umbau war.
Der Sliding-Expiration-Write bleibt weg.

Der Doppelgänger im Test unterscheidet deshalb jetzt nach Tabelle, statt bei jedem Zugriff zu
werfen: Sonst mässe `testDerJwtZweigFasstDieTokentabelleNichtAn` nicht mehr, was er zu messen
behauptet.

### Die Liste bleibt klein

Ein Eintrag verfällt mit dem Token, das er sperrt, und `appcms:token:cleanup` räumt ihn weg —
**kein zweiter Aufräumweg**. Das Kundenprojekt hatte eine solche Liste ohne Verfall, und sie
musste unbegrenzt wachsen.

Sie liegt in der Datenbank und nicht im Cache: Ein geleerter Cache darf keinen Widerruf
aufheben, und der Cache ist genau das, was man leert, wenn etwas klemmt.

### Zwei Funde unterwegs

**Eine neue Entity ohne `modified` bricht die Installation.** `Classes/Events/LoadMetadata` hängt
an jede Entity, die kein Tree ist, einen `modified_index`. Fehlt die Spalte, endet
`appcms:install` mit „There is no column with name "modified" on table "pim_revoked_token"" —
einer Meldung, die den Grund nicht nennt. Die Spalte steht jetzt in `RevokedToken`, mit einem
Kommentar, der sagt warum. Dass der Listener eine Annahme über alle Entities trifft, die
nirgends festgehalten ist, gehört in einen eigenen Task.

**Die Anmeldebremse hat die Suite abgeschossen.** Die neuen Refresh-Tests erzeugen viele
Fehlversuche, alle von 127.0.0.1 — beim ersten Lauf waren **87 Tests rot**, sämtlich mit „Zu
viele Anmeldeversuche". Die Suite ist kein realistischer Client. `IntegrationTestCase::tearDown()`
leert den Speicher der Bremse jetzt zwischen den Verfahren. Das schwächt nichts ab:
`AnmeldebremseApiTest` misst innerhalb *eines* Verfahrens, und was dazwischen weggeräumt wird,
hat dort nie eine Aussage getragen.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (398 tests, 991 assertions)`, 0 übersprungen (vorher 387) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Abgemeldetes, noch gültiges JWT | öffnet nichts mehr |
| Gesperrter Benutzer, leere Sperrliste | kommt nicht durch |
| Gegenstandsloser Sperreintrag | von `appcms:token:cleanup` entfernt |
