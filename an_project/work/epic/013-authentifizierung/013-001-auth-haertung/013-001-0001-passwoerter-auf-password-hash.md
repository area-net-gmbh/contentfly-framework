---
id: 013-001-0001
title: Passwörter auf password_hash, mit Umschlüsselung beim Login
status: done
depends_on: []
---

# Passwörter auf password_hash, mit Umschlüsselung beim Login

## Context
`User::setPass()` und `isPass()` rechnen `hash('sha256', $pass.$salt)`. Der Salt ist in Ordnung
— 64 Hex je Benutzer, im Konstruktor erzeugt —, aber **SHA-256 hat keinen Arbeitsfaktor**. Eine
GPU prüft Milliarden Kandidaten pro Sekunde; ein Passwort aus einer Wortliste fällt in Sekunden.

Umgestellt wird auf `password_hash()` mit Argon2id, Rückfall bcrypt. Beide bringen ihren Salt
selbst mit und tragen die Parameter im Hash — der eigene `salt` wird für neue Hashes nicht mehr
gebraucht, **bleibt aber**, solange Bestandsdaten mit ihm geprüft werden.

**Bestandsdaten wandern beim Login mit.** Passt der alte SHA-256-Hash, wird das Passwort sofort
neu gehasht und gespeichert. Kein Zwangs-Reset, keine Migration im Voraus — und nach dem ersten
Login jedes Benutzers ist der alte Hash weg.

**Die Spalte muss mit.** `pass` ist heute `varchar(100)`; ein Argon2id-Hash ist rund 96 Zeichen.
Es passt knapp und ist trotzdem zu eng: PHP darf Algorithmus und Parameter wechseln, und die
Länge ist keine Zusicherung. Auf 255.

## Acceptance criteria
- [x] `setPass()` schreibt einen `password_hash()`-Hash; `isPass()` prüft mit `password_verify()`.
- [x] Ein Passwort im alten SHA-256-Format wird beim Login **noch** akzeptiert und dabei umgeschlüsselt — mit einem Test, der den alten Hash fest hinterlegt, nicht neu berechnet.
- [x] Nach dem Umschlüsseln ist der alte Hash in der Datenbank ersetzt, nicht daneben abgelegt.
- [x] Die Spalte `pass` fasst 255 Zeichen; der Datenbankvergleich zeigt genau diese eine Änderung.
- [x] Der Vergleich ist zeitkonstant — `password_verify()` ist es, das alte `==` war es nicht.
- [x] `AuthApiTest` bleibt grün, und ein Login mit falschem Passwort scheitert weiterhin mit derselben Meldung.

## Verification
`./vendor/bin/phpunit --filter AuthApiTest`, dann die volle Suite. Der Umschlüsselungs-Nachweis
gehört in einen Test: einen Benutzer mit fest hinterlegtem SHA-256-Hash anlegen, einloggen, und
danach in der Datenbank prüfen, dass dort ein `password_hash`-Hash steht.

## Ergebnis

**Passwörter werden mit Argon2id gehasht, alte SHA-256-Hashes wandern beim Login mit.** Die
Suite wächst von 282 auf **285**.

Ein frisch gesetztes Passwort sieht so aus:

```
$argon2id$v=19$m=65536,t=4,p=1$…
```

### Das Verfahren wird zur Laufzeit entschieden, nicht als Konstante

Mein erster Entwurf hatte `private const VERFAHREN = PASSWORD_ARGON2ID;`. **Das wäre ein
Rückfall gewesen, der die Anwendung umbringt:** `PASSWORD_ARGON2ID` gibt es nur, wenn PHP mit
libargon2 gebaut wurde — als Klassenkonstante würde `User` auf einem Build ohne libargon2 gar
nicht mehr laden. Der Kommentar versprach einen Rückfall, den der Code nicht hatte.

Jetzt entscheidet eine private Methode: Argon2id, sonst `PASSWORD_DEFAULT` (heute bcrypt). Weil
das Verfahren im Hash steht, laufen beide Formen nebeneinander, und `password_needs_rehash()`
holt einen bcrypt-Hash später nach, wenn Argon2id verfügbar wird.

Gemessen: lokal, in `php:8.3-cli` und in `php:8.4-cli` ist Argon2id vorhanden.

### Umgeschlüsselt wird beim Login, nicht in `isPass()`

**Eine Prüfung darf nichts schreiben.** Stünde die Umschlüsselung in `isPass()`, hätte jeder
Aufruf eine Nebenwirkung — auch der aus `Api::doUpdate()`, wo nur das bisherige Passwort
bestätigt wird.

Der Weg über den LoginManager ist ausgenommen: Dort prüft ein Fremdsystem, und `getPass()` steht
in keinem Zusammenhang mit dem eingegebenen Wort.

### Ein Befund über die Testsuite

Der erste Anlauf des Umschlüsselungs-Tests war rot — die Vorbedingung fand bereits einen
Argon2id-Hash. Der Grund: **`testbenutzer()` meldet den Benutzer selbst an**, um den Token zu
liefern, und dabei wurde der Hash schon ersetzt.

Das heisst zweierlei. Der Test musste den alten Hash ausdrücklich wiederherstellen, um überhaupt
etwas zuzusichern. Und: **Jeder Test, der den Helfer benutzt, läuft über den Altformat-Zweig** —
er ist breit abgedeckt, nur war das bis eben nirgends festgehalten.

### Drei Tests, und warum der zweite der wichtigste ist

| Test | Zusicherung |
|---|---|
| `…WirdBeimLoginUmgeschluesselt` | Altes Format meldet sich an, Hash ist danach ersetzt |
| `…GehtDieAnmeldungWeiterhin` | Ein **zweiter** Login mit dem umgeschlüsselten Hash geht |
| `…ScheitertAuchNachDemUmschluesseln` | Ein falsches Passwort scheitert weiterhin |

Ohne den zweiten wäre ein Benutzer nach genau einem erfolgreichen Login ausgesperrt gewesen —
und der Test darüber wäre trotzdem grün geblieben.

### Der Vergleich ist jetzt zeitkonstant

Auch im Altformat-Zweig: `hash_equals()` statt `==`. Beim neuen Format erledigt
`password_verify()` das von sich aus.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `AuthApiTest` | `OK (13 tests, 26 assertions)` |
| Volle Suite | `OK (285 tests, 699 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| **Schemavergleich** | **genau eine Änderung**: `pim_user.pass` von `varchar(100)` auf `varchar(255)` |
| Argon2id verfügbar | lokal, `php:8.3-cli`, `php:8.4-cli` |
