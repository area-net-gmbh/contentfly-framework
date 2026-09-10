---
id: 013-001-0002
title: Das Master-Passwort ersatzlos entfernen
status: todo
depends_on: []
---

# Das Master-Passwort ersatzlos entfernen

## Context
`APP_MASTER_PASSWORD` akzeptiert den Login **für jeden Benutzer** (`AuthController.php:81`).
Eine Konfigurationszeile, die Vollzugriff auf jedes Konto gewährt.

**Ersatzlos entfernen, nicht abschaltbar machen.** Ein Schalter, der Vollzugriff gewährt, ist
auch ausgeschaltet eine Hintertür: Er kann versehentlich gesetzt werden, er steht in
Konfigurationsbeispielen, und er lädt dazu ein, ihn „nur kurz" zu benutzen.

**Dass das Problem bekannt war, ist aktenkundig:** Das Kundenprojekt, aus dem dieses Framework
herausgeschnitten wurde, setzte `APP_MASTER_PASSWORD` beim Bootstrap ausdrücklich auf `null` —
festgehalten in `an_project/docs/technical.md` unter *Master-Password neutralisiert*. Man hat
sich davor geschützt, statt es zu entfernen.

Für Bestandsprojekte ein Breaking Change. Ein Projekt, das es gesetzt hat, verliert einen
Zugang, den es nicht hätte haben dürfen.

## Acceptance criteria
- [ ] `APP_MASTER_PASSWORD` kommt weder in `Classes/Config.php` noch in `AuthController` noch sonst im Baum vor.
- [ ] Der Login prüft nur noch das Passwort des Benutzers — ein Zweig, keine Fallunterscheidung.
- [ ] Ein Test weist nach, dass ein beliebiger Wert an dieser Stelle **nicht** mehr zum Login führt.
- [ ] Die Bruchstelle steht in `an_project/docs/breaking-changes.md`, mit dem Hinweis für Bestandsprojekte.
- [ ] Der Hinweis in `technical.md` unter *Master-Password neutralisiert* ist aufgelöst statt stehengelassen.

## Verification
Volle Suite. Dazu ein Test, der mit gesetztem Fremdwert einen Login versucht und ein 401
erwartet — er muss gegen den heutigen Stand **grün** sein, wenn `APP_MASTER_PASSWORD` nicht
gesetzt ist, und ist erst nach dem Ausbau eine echte Zusicherung. Deshalb zusätzlich ein `grep`
als Nachweis, dass die Konstante nirgends mehr existiert.
