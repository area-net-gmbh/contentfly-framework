---
id: 012-004-0001
title: Session-Verwendungen aufspüren und bewerten
status: review
depends_on: []
---

# Session-Verwendungen aufspüren und bewerten

## Context
Die PHP-Session existiert wegen der Admin-Oberfläche. Bevor sie fällt, muss klar sein, ob irgendwo API-Zustand darin liegt — sonst verschwindet stillschweigend Verhalten.

## Acceptance criteria
- [x] Alle Fundstellen von `$app['session']`, `$_SESSION` und `Auth::init()` sind erfasst.
- [x] Jede ist eingestuft: UI-Anmeldung oder API-Zustand.
- [x] Für jede API-Fundstelle ist entschieden, wohin der Zustand stattdessen gehört.
- [x] Die Sonderbehandlung in `custom/app.php`, die für `/api/v1/*` die Session früh schließt, ist als gegenstandslos markiert.

## Ergebnis — die Session hat genau einen Zweck

Fünf Fundstellen, und nur drei davon lesen oder schreiben überhaupt etwas:

| Ort | Was | Einstufung |
|---|---|---|
| `Classes/Auth.php:32` | `init()` liest `auth.userid` aus der Session und lädt den Benutzer | **UI-Anmeldung** — der einzige Grund, warum überhaupt eine Session existiert |
| `Classes/Auth.php:77` | `login()` schreibt `auth.userid` | **UI-Anmeldung**, und: **ohne Aufrufer** |
| `Classes/Auth.php:85` | `logout()` entfernt `auth.userid` | dito, **ohne Aufrufer** |
| `bootstrap.php:58` | `SessionServiceProvider` registrieren | Voraussetzung der drei obigen |
| `bootstrap.php:4-5`, `:287` | `session.cookie_httponly`, `session.use_only_cookies`, `session.cookie_secure` | Härtung einer Session, die es danach nicht mehr gibt |

**Kein API-Zustand liegt in der Session.** Der Token-Weg (`BaseControllerProvider::checkToken()`)
schlägt in `pim_token` nach und setzt `auth.user`/`auth.token` im Container — nicht in der Session.

## Der eigentliche Befund: `Auth` ist fast vollständig tot

- `Auth::login()` und `Auth::logout()` haben **keinen einzigen Aufrufer**. Der `AuthController`
  bringt seine eigene Anmeldung mit und stellt Tokens aus; er ruft `Auth` nie an.
- `Auth::getLoginProvider()` wird nur von `login()` gebraucht — also ebenfalls tot. Es ist die
  zweite, abweichende Kopie der Provider-Auflösung aus dem `AuthController` (Befund aus dem
  Auth-Review, siehe `013-001`).
- `Auth::$token` mit `getToken()`/`setToken()`: kein Aufrufer. Alle Treffer auf `getToken()` im
  Baum gehören zur **Entity** `Token`, nicht zu `Auth`.
- Übrig bleiben `getUser()` und `setUser()` — dünne Hüllen um `$app['auth.user']`.

`$app['auth']` selbst wird außerhalb von `bootstrap.php` nirgends benutzt.

## Was daraus folgt

`init()` ist der Grund, warum bei **jedem** Request eine PHP-Session startet — auch bei einem
reinen API-Aufruf. Fällt es weg, fällt die Session weg, und mit ihr der exklusive Lock auf der
Session-Datei, der gleichzeitige Aufrufe desselben Nutzers serialisiert hat.

`getUser()`/`setUser()` bleiben: Sie sind für Bestandsprojekte die dokumentierte Art, an den
angemeldeten Benutzer zu kommen. Der Rest der Klasse wird entfernt — als Bruch für Epic `007`
zu vermerken.

## Verification
Die Liste wird gegen `grep -rn "session" lib custom` gegengelesen — keine Fundstelle bleibt unbewertet.
