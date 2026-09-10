---
id: 013-001-0002
title: Das Master-Passwort ersatzlos entfernen
status: done
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
- [x] `APP_MASTER_PASSWORD` kommt weder in `Classes/Config.php` noch in `AuthController` noch sonst im Baum vor.
- [x] Der Login prüft nur noch das Passwort des Benutzers — ein Zweig, keine Fallunterscheidung.
- [x] Ein Test weist nach, dass ein beliebiger Wert an dieser Stelle **nicht** mehr zum Login führt.
- [x] Die Bruchstelle steht in `an_project/docs/breaking-changes.md`, mit dem Hinweis für Bestandsprojekte.
- [x] Der Hinweis in `technical.md` unter *Master-Password neutralisiert* ist aufgelöst statt stehengelassen.

## Verification
Volle Suite. Dazu ein Test, der mit gesetztem Fremdwert einen Login versucht und ein 401
erwartet — er muss gegen den heutigen Stand **grün** sein, wenn `APP_MASTER_PASSWORD` nicht
gesetzt ist, und ist erst nach dem Ausbau eine echte Zusicherung. Deshalb zusätzlich ein `grep`
als Nachweis, dass die Konstante nirgends mehr existiert.

## Ergebnis

**`APP_MASTER_PASSWORD` existiert nicht mehr** — weder in `Classes/Config.php` noch im
`AuthController` noch sonst im Baum. Der Login prüft einen Zweig statt einer Fallunterscheidung.

### Entfernt, nicht abgeschaltet

Der Unterschied ist der ganze Punkt. Ein Schalter, der Vollzugriff gewährt, ist auch
ausgeschaltet eine Hintertür: Er kann versehentlich gesetzt werden, er steht in
Konfigurationsbeispielen, und er lädt dazu ein, ihn „nur kurz" zu benutzen.

**Dass das Problem bekannt war, ist aktenkundig.** `technical.md` hielt unter *Master-Password
neutralisiert* fest, dass das Kundenprojekt den Wert beim Bootstrap auf `null` setzte — „die
Framework-Hintertür wird unabhängig von Konfiguration und Umgebung inert gesetzt". Man hat sich
davor geschützt, statt ihn zu entfernen. Der Eintrag ist jetzt aufgelöst: Es gibt nichts mehr
zu neutralisieren.

### Ein Charakterisierungstest, der seine eigene Umkehr angekündigt hat

`UnenforcedPermissionApiTest::testDasMasterPasswortIstInDerVorlageNichtGesetzt` sicherte zu,
dass der Standardwert `null` ist — „die Hintertür ist zu, aber vorhanden" — und schrieb dazu:

> Wer 013-001 umsetzt, dreht diesen Test bewusst um.

Genau das ist geschehen. Er heisst jetzt `testDasMasterPasswortGibtEsNichtMehr` und prüft, dass
der Name im **Code** beider Dateien nicht mehr vorkommt — in Erklärungen darf er stehen, sie
beschreiben ja, was entfallen ist.

Der zweite Test hiess `…ScheitertSolangeKeinMasterPasswortGesetztIst`. Der Zusatz ist gestrichen:
Vorher galt die Zusicherung nur unter einer Bedingung, die eine Konfigurationszeile aufheben
konnte. **Jetzt gilt sie.**

### Nachweis

| Probe | Ergebnis |
|---|---|
| `APP_MASTER_PASSWORD` im Code | 0 Treffer; nur noch in drei Erklärungen |
| Volle Suite | `OK (285 tests, 700 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| A-2 in `technical.md` | durchgestrichen, mit Verweis auf diesen Task |
| Bruchstelle | steht in `breaking-changes.md` |
