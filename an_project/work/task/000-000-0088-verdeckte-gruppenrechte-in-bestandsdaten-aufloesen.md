---
id: 000-000-0088
title: Verdeckte Gruppenrechte in Bestandsdaten auflösen — und doppelte Einträge im Request ablehnen
status: done
depends_on: [000-000-0070]
---

# Verdeckte Gruppenrechte in Bestandsdaten auflösen — und doppelte Einträge im Request ablehnen

## Context
**Aus dem Review von `000-000-0070`.** Der Fix verhindert, dass neue `PIM\Tag`-Zeilen mit `ALL`
entstehen. **Bereits geschriebene bleiben stehen.** Für eine bestimmte Gruppe von Bestandsdaten
wirkt er damit nicht:

- `PermissionsType::toDatabase()` schrieb bis `0070` bei jedem Speichern **zuerst** die
  `PIM\Tag`-Zeile mit `ALL` und **danach** die angeforderten Zeilen.
- Eine Gruppe, deren Rechte `PIM\Tag` ausdrücklich einschränken, hat in der Datenbank deshalb
  **zwei** Zeilen für `PIM\Tag`: erst `ALL`, dann die Einschränkung.
- `Classes\Permission::is()` nimmt den **ersten** Treffer zum Entitätsnamen, und
  `Group::$permissions` ist ein `OneToMany` ohne `OrderBy`.
- **Die Einschränkung bleibt also wirkungslos, bis jemand die Rechte der Gruppe neu speichert.**
  Erst dann löscht `toDatabase()` alle Zeilen der Gruppe und schreibt nur noch die angeforderten.

Der Registereintrag aus `0070` beschreibt nur die umgekehrte Wirkung: dass Tag-Zugriff beim
nächsten Speichern **wegfallen** kann. Dass eine gewollte Einschränkung bis dahin **nicht greift**,
steht dort nicht.

**Die alte Zeile ist erkennbar.** Sie setzte weder `export` noch `extended`. `export` hat den
Default `0`, `extended` ist `nullable` und blieb `NULL`. Die Schleife für angeforderte Zeilen
setzt `extended` immer, auf `''` oder JSON. Kennzeichen der alten Zeile also:
`entityName = 'PIM\Tag'`, `readable = writable = deletable = 2`, `extended IS NULL`. Das gilt für
Daten, die dieser Code geschrieben hat; an einem Bestandsprojekt (UFP) zu prüfen, bevor darauf
gebaut wird.

**Zweitens, dieselbe Fehlerklasse vom Aufrufer ausgelöst:** `toDatabase()` prüft die Einträge des
Requests nicht. Kommt derselbe Entitätsname zweimal, entstehen zwei Zeilen, und die erste gewinnt
still. Fehlende Schlüssel (`name`, `readable`, …) werden ebenfalls nicht geprüft; was dann
passiert, ist nicht erhoben. **Wichtig für die Umsetzung:** `toDatabase()` löscht die bestehenden
Zeilen der Gruppe, **bevor** es die neuen schreibt. Eine Prüfung danach würde bei einem abgelehnten
Request die Rechte der Gruppe leeren.

`PermissionsType::toDatabase()` ist die einzige Stelle, die `Permission`-Zeilen anlegt.

## Was zu entscheiden ist
- **Wie die Bestandsdaten bereinigt werden.** Drei Wege, nicht gleichwertig:
  - **Console-Command** (Muster: `Command/TokenCleanupCommand.php`): findet je Gruppe doppelte
    Entitätsnamen und entfernt die alte `ALL`-Zeile. Das Projekt muss ihn einmal ausführen.
  - **SQL im Leitfaden:** gleiche Wirkung, kein Code, aber jedes Projekt tippt es selbst.
  - **`Permission::is()` eindeutig machen**, z. B. bei mehreren Treffern den restriktivsten Wert
    nehmen: Das wirkt ohne Zutun des Projekts, ändert aber die Semantik für jeden Fall mit
    Doppelzeilen, nicht nur für `PIM\Tag`.
- **Was mit Gruppen passiert, die nur die alte Zeile haben.** Sie haben kein ausdrückliches
  `PIM\Tag`. Die Zeile zu entfernen hiesse, ihren Tag-Zugriff ohne Ankündigung zu nehmen. Die
  Bereinigung sollte deshalb nur dort greifen, wo eine zweite `PIM\Tag`-Zeile den Willen zeigt.

## Acceptance criteria
- [x] Der Weg der Bereinigung ist entschieden und begründet: Command, SQL im Leitfaden oder eindeutiges `Permission::is()`.
- [x] Eine Bestandsgruppe mit alter `ALL`-Zeile **und** ausdrücklicher `PIM\Tag`-Einschränkung ist danach eingeschränkt, ohne dass ihre Rechte neu gespeichert werden. Belegt mit einem Integrationstest, der die zwei Zeilen per SQL anlegt, `ALL` zuerst.
- [x] Gruppen, die nur die alte `ALL`-Zeile haben, werden nicht angefasst. Oder die Entscheidung dagegen steht begründet im Register.
- [x] Ein `permissions`-Request mit doppeltem Entitätsnamen antwortet mit `400` `contentfly_general_invalid_params`, und die bestehenden Rechte der Gruppe bleiben **unverändert**. Geprüft wird vor dem `DELETE`.
- [x] Geklärt, was ein Eintrag ohne `name`, `readable`, `writable`, `deletable` oder `export` heute auslöst. Wenn es kein 400 ist, wird es eines, mit derselben Regel.
- [x] Der Registereintrag aus `0070` nennt die verdeckte Einschränkung bei Bestandsgruppen und den Weg zur Bereinigung. Die Zählung in `migration.md` ist nachgezogen, falls ein neuer Eintrag entsteht.

## Verification
Neue Integrationstests neben denen aus `0070` (`tests/Integration/Api/FieldTypeApiTest.php`).
Gegenprobe ohne Fix: der Bestandsdaten-Test und der Test für doppelte Namen sind rot. Danach die
volle Suite, PHPStan und das Deprecation-Gate.

## Ergebnis (2026-09-24)
**`Permission::is()` ist eindeutig, und `toDatabase()` prüft den Request, bevor es die Gruppe
anfasst.**

### Entscheidung: `is()` statt Command oder SQL
Den Ausschlag gab ein Befund, der im Ticket noch nicht stand: **Die Reihenfolge war nie die der
Einfügung.** Bei GUID-Ids liefert MySQL die Zeilen einer Gruppe nach der Id. Gemessen an 20 Gruppen
mit denselben zwei `PIM\Tag`-Zeilen: Die zuerst eingefügte kam in 5 Fällen zuerst. In Bestandsdaten
hat also je Gruppe der Zufall entschieden, welche Zeile galt. Die Aussage „Reihenfolge der
Einfügung“ aus `0070` ist im Register korrigiert.

Damit schieden Command und SQL als alleiniger Weg aus: Beide wirken erst, wenn ein Projekt sie
ausführt, und bis dahin bleibt es Zufall. `is()` löst mehrere Zeilen für dieselbe Entity jetzt je
Recht zur **restriktivsten** auf: `NONE` vor `OWN` vor `GROUP` vor `ALL`. Die Konstanten sind nicht
so geordnet (`GROUP` = 3, `ALL` = 2), deshalb eine eigene Rangfolge statt eines Zahlenvergleichs.
Ein unbekannter Wert zählt wie `ALL`, weil jeder Aufrufer ihn so behandelt.

`is()` ist die einzige Stelle, die Gruppenrechte liest, auch für den `permissions`-Block im Schema.
Eine Gruppe mit nur einer Zeile verhält sich wie bisher. Die SQL-Abfrage, die Gruppen mit
Doppelzeilen findet, steht im Registereintrag. Bereinigen ist damit möglich, aber nicht nötig.

### Eingabeprüfung
`validateEntries()` läuft vor dem ersten `flush()` und vor dem `DELETE`. Pflicht sind `name`
(nicht leerer String), `readable`, `writable`, `deletable`. Eine Entity darf nur einmal vorkommen,
und `permissions` muss eine Liste sein. Jede Ablehnung ist `400` `contentfly_general_invalid_params`.

**Abweichung vom Kriterium bei `export`:** Es wird **optional** mit Voreinstellung 0, statt 400 zu
liefern. `export` wirkt seit `000-000-0012` nicht mehr; Pflicht war es nur als fehlender
Array-Schlüssel. Die Werte der Rechte selbst prüft die Änderung nicht, das wäre eine eigene Frage.

### Erhoben am unveränderten Code
| Request | vorher |
|---|---|
| Eintrag ohne `name`/`readable`/`writable`/`deletable`/`export`, oder kein Objekt | 500, beim Update alle Rechte der Gruppe gelöscht, beim Insert eine Gruppe ohne Rechte |
| `permissions: null` oder String | 200, alle Rechte gelöscht, PHP-Warnung |
| dieselbe Entity zweimal | 200, zwei Zeilen |

### Belegt
Lokal gegen eine eigene Datenbank `contentfly_0088`, Aufbau mit `tools/ci/prepare-test-environment.sh`.
- Neu `tests/Unit/Security/PermissionResolutionTest.php` (5 Tests): beide Reihenfolgen, `GROUP`
  zwischen `OWN` und `ALL`, jedes Recht einzeln.
- In `FieldTypeApiTest` 13 neue Fälle. Die Bestandsdaten legt der Test per SQL an, `ALL` zuerst,
  und prüft diese Voraussetzung, statt sie anzunehmen.
- **Gegenprobe ohne Fix:** 12 der 13 Integrationsfälle und 3 der 5 Unit-Tests rot. Grün blieb
  jeweils nur, was vorher wie nachher gelten muss: Eine Gruppe mit nur der alten Zeile behält den
  Tag-Zugriff, eine einzelne Zeile gilt, wie sie ist.
- Volle Suite 817 Tests grün (3 übersprungen), PHPStan ohne Fehler, Deprecation-Gate 0 Meldungen.
