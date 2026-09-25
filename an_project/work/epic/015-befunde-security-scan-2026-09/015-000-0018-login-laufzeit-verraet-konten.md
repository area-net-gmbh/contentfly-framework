---
id: 015-000-0018
title: Login-Laufzeit verrät existierende Konten
status: todo
depends_on: []
---

# Login-Laufzeit verrät existierende Konten

## Context
**Security-Scan 2026-09, LOW. Finding F29.**

In `AuthController::loginAction` werden unbekannte, deaktivierte und an einen Provider gebundene
Aliase vor jeder Hash-Prüfung abgewiesen (Zeilen 249-259). Nur existierende, aktive, lokale Konten
erreichen `isPass()` bzw. `password_verify` mit Argon2id (`Controller/AuthController.php:268`).
Das kostet absichtlich einige zehn Millisekunden. Die Antwortzeit verrät damit, ob ein Konto
existiert, auch wenn die Fehlertexte aus `015-000-0008` vereinheitlicht sind. Der Befund ist aus
dem Codepfad abgeleitet, nicht gemessen.

## Acceptance criteria
- [ ] Jeder Ablehnungspfad des Passwort-Logins führt ein `password_verify` gegen einen festen Dummy-Hash mit demselben Algorithmus und denselben Kosten aus.
- [ ] Der Dummy-Hash folgt `User::algorithm()` bzw. den aktuellen Hash-Optionen, damit er nach einer Änderung der Kosten nicht veraltet.

## Verification
Unit-Test mit einem Double für die Hash-Prüfung: Für einen unbekannten Alias wird genau eine
Prüfung ausgeführt, wie für ein bekanntes Konto mit falschem Passwort. Vor dem Fix ist es für den
unbekannten Alias keine.
