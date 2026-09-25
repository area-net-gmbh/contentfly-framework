---
id: 015-000-0008
title: Login-Fehlertexte verraten, ob ein Konto existiert
status: todo
depends_on: []
---

# Login-Fehlertexte verraten, ob ein Konto existiert

## Context
**Security-Scan 2026-09, MEDIUM. Finding F11.**

Die `$reject`-Closure in `AuthController::loginAction` (`Controller/AuthController.php:250`) gibt
ihren Text als `detail` der Antwort aus (`BaseController::renderError` → `Envelope::fault`). Der
Text hängt vom Grund ab:
- `Invalid user name.`: Das Konto existiert nicht.
- `The user is deactivated.`
- `The user can only be authenticated through their login provider.`
- `Invalid user name and/or password.`: Das Konto existiert, ist aktiv und lokal, das Passwort ist falsch.

Das widerspricht dem Code-Kommentar, der einheitliche Texte behauptet. Ohne Anmeldung lässt sich
eine Namensliste abfragen, und das Ergebnis dient als Zielliste für Password-Spraying. Die
Drossel (20/min, 200/h pro IP) begrenzt nur die Rate.

Den Laufzeitunterschied als zweiten Kanal behandelt `015-000-0018`.

## Acceptance criteria
- [ ] Jeder Login-Fehlschlag liefert dasselbe `detail` und denselben Status.
- [ ] Der genaue Grund steht nur im serverseitigen Log.

## Verification
Test: Login mit unbekanntem Alias, mit deaktiviertem Konto, mit Provider-Konto und mit falschem
Passwort. Vor dem Fix unterscheiden sich die `detail`-Texte, nach dem Fix sind Antwortkörper und
Status in allen vier Fällen gleich.
