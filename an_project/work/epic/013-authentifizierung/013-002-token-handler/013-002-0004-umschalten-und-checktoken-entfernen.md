---
id: 013-002-0004
title: Umschalten und checkToken() entfernen
status: done
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
- [x] Alle fünf Aufrufer gehen über den neuen Mechanismus; `checkToken()` existiert nicht mehr.
- [x] `$app['auth.user']` und `$app['auth.token']` werden weiterhin gesetzt, mit denselben Werten wie bisher.
- [x] Der Sonderfall in `CustomControllerProvider` ist erhalten — oder ausdrücklich begründet aufgelöst, nicht beiläufig verloren.
- [x] Ein Bestandsclient mit `appcms-token` funktioniert unverändert; derselbe Token als `Authorization: Bearer` ebenfalls.
- [x] Das Testnetz aus Epic `008` bleibt grün, auf PHP 8.3 **und** 8.4.
- [x] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Dazu je ein Request
über HTTP für jede Tokenquelle gegen eine geschützte Route, und einer ohne Token.

## Ergebnis

**Der Schalter ist umgelegt.** Alle fünf Provider rufen `anmelden()`, `checkToken()` existiert
nicht mehr. Was 75 Zeilen in einem Rumpf waren, steht auf vier Klassen verteilt, jede für sich
prüfbar.

### Die Schnittstelle nach aussen ist geblieben

Ein `bool`, und im Erfolgsfall stehen `$app['auth.user']` und `$app['auth.token']` wie bisher.
Das war die Bedingung: Das Contentfly-eigene Berechtigungsmodell liest sie an Dutzenden Stellen,
und es durch Symfony-Rollen zu ersetzen ist ausdrücklich nicht Teil dieser Story. Ein Projekt,
das einen eigenen Provider ableitet, ersetzt genau einen Methodennamen.

Der Sonderfall in `CustomControllerProvider` — `!$this->anmelden(...) && !$app['auth.user']` — ist
**erhalten**, nicht beiläufig aufgelöst. Er lässt einen Request durch, den ein Projekt-Hook
vorher schon angemeldet hat.

### Drei Konstanten, die nichts mehr steuerten

`TOKEN_HEADER_KEY`, `TOKEN_HEADER_KEY_ALT` und `TOKEN_REQUEST_KEY` standen in
`BaseControllerProvider`, weil `checkToken()` sie las. Die Werte sind nach `Tokenquellen`
gewandert, zu dem Code, der sie benutzt. Sie stehen zu lassen wäre schlimmer gewesen als sie zu
entfernen: Drei öffentliche Konstanten ohne Wirkung sehen beim nächsten Lesen aus wie die Stelle,
an der man die Tokenquellen ändert.

### Ein Test hat einen zweiten Fund freigelegt

`SystemControllerApiTest::testJedeAnmeldungLegtEineZeileAn…` prüft am Ende im **Quelltext**, dass
der träge Löschzweig existiert — er zeigte auf `BaseControllerProvider.php` und wurde rot, weil
der Zweig nach `Tokenhandler.php` umgezogen ist. Beim Nachziehen fiel im selben Test auf:

```php
$zeile->execute(array('t' => $frisch));   // der Klartext-Token
```

**Diese Abfrage fand seit `013-001-0004` nichts.** Dort steht nur noch der Hash; `fetch()` lieferte
`false`, `$gefunden['referrer']` war deshalb `null`, und die Zusicherung darunter galt, ohne etwas
zu prüfen. Jetzt wird über den Hash gesucht, und ein `assertIsArray` sorgt dafür, dass ein
Fehlschlag als Fehlschlag ankommt statt als stillschweigend erfüllte Bedingung.

### Fünf Quellen, am laufenden System gemessen

`TokenquellenTest` misst dieselben fünf Quellen ohne HTTP. Hier geht es darum, dass sie
**verdrahtet** sind: je ein Request gegen `/api/schema` beziehungsweise `/api/count`.

| Quelle | Antwort |
|---|---|
| `Authorization: Bearer` | 200 |
| `appcms-token` | 200 |
| `X-XSRF-TOKEN` | 200 |
| `_token` im Query-String | 200 |
| `_token` im Rumpf | 200 |
| gar kein Token | nicht 200 |
| erfundener Token, über drei Quellen | nicht 200 |

### `$app['auth.token']` kann null werden

Im JWT-Zweig gibt es keine Zeile in `pim_token`. Gelesen wird der Schlüssel nur beim Abmelden,
und dort steht jetzt eine Prüfung. Sie steht schon hier und nicht erst in `013-003`, weil die
**Möglichkeit** mit dieser Story entsteht. Was Abmelden bei einem zustandslosen Token bedeutet,
entscheidet jene Story.

### Nachweis

| Probe | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Volle Suite | `OK (362 tests, 920 assertions)` | `OK (362 tests, 920 assertions)` |
| Deprecations | 0 | 0 |
| Postausgang | 0 Byte | 0 Byte |

Dazu PHPStan `[OK] No errors` und `composer audit --locked` ohne Advisories. Sechs Bruchstellen
stehen in `an_project/docs/breaking-changes.md`; der Auth-Abschnitt in `technical.md` ist
nachgezogen.

## Offene Beobachtung

`BaseControllerProvider::isAuthRequiredForPath()` und `LOGIN_PATH` ruft niemand — auch vor
diesem Task nicht, `checkToken()` hat sie nie benutzt. Sie stehen deshalb noch da: Sie sind
nicht durch diese Änderung tot geworden, und den Task um fremdes Totholz zu erweitern wäre die
falsche Reihenfolge. Gehört in einen eigenen Task.