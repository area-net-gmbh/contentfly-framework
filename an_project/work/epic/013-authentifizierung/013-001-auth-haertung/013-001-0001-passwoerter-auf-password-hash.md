---
id: 013-001-0001
title: Passwörter auf password_hash, mit Umschlüsselung beim Login
status: todo
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
- [ ] `setPass()` schreibt einen `password_hash()`-Hash; `isPass()` prüft mit `password_verify()`.
- [ ] Ein Passwort im alten SHA-256-Format wird beim Login **noch** akzeptiert und dabei umgeschlüsselt — mit einem Test, der den alten Hash fest hinterlegt, nicht neu berechnet.
- [ ] Nach dem Umschlüsseln ist der alte Hash in der Datenbank ersetzt, nicht daneben abgelegt.
- [ ] Die Spalte `pass` fasst 255 Zeichen; der Datenbankvergleich zeigt genau diese eine Änderung.
- [ ] Der Vergleich ist zeitkonstant — `password_verify()` ist es, das alte `==` war es nicht.
- [ ] `AuthApiTest` bleibt grün, und ein Login mit falschem Passwort scheitert weiterhin mit derselben Meldung.

## Verification
`./vendor/bin/phpunit --filter AuthApiTest`, dann die volle Suite. Der Umschlüsselungs-Nachweis
gehört in einen Test: einen Benutzer mit fest hinterlegtem SHA-256-Hash anlegen, einloggen, und
danach in der Datenbank prüfen, dass dort ein `password_hash`-Hash steht.
