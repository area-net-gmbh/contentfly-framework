---
id: 013-002-0004
title: Umschalten und checkToken() entfernen
status: todo
depends_on: [013-002-0002, 013-002-0003]
---

# Umschalten und checkToken() entfernen

## Context
Fünf Provider rufen `checkToken()`: Api, Auth, File, System und Custom. Der letzte mit einer
Zusatzbedingung — `!$this->checkToken(...) && !$app['auth.user']`. Dieser Task legt den Schalter
um und entfernt den alten Weg; ein toter Zweig, den man stehen lässt, sieht beim nächsten Lesen
wie eine vorhandene Sicherung aus.

Was **nicht** angefasst wird: `$app['auth.user']` und `$app['auth.token']`. Das
Contentfly-Berechtigungsmodell liest sie an vielen Stellen, und es durch Symfony-Rollen zu
ersetzen ist ausdrücklich nicht Teil dieser Story.

## Acceptance criteria
- [ ] Alle fünf Aufrufer gehen über den neuen Mechanismus; `checkToken()` existiert nicht mehr.
- [ ] `$app['auth.user']` und `$app['auth.token']` werden weiterhin gesetzt, mit denselben Werten wie bisher.
- [ ] Der Sonderfall in `CustomControllerProvider` ist erhalten — oder ausdrücklich begründet aufgelöst, nicht beiläufig verloren.
- [ ] Ein Bestandsclient mit `appcms-token` funktioniert unverändert; derselbe Token als `Authorization: Bearer` ebenfalls.
- [ ] Das Testnetz aus Epic `008` bleibt grün, auf PHP 8.3 **und** 8.4.
- [ ] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Dazu je ein Request
über HTTP für jede Tokenquelle gegen eine geschützte Route, und einer ohne Token.
