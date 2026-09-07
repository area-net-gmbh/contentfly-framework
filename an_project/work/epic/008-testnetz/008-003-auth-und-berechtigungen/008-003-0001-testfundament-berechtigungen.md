---
id: 008-003-0001
title: Testfundament für Berechtigungen
status: todo
depends_on: []
---

# Testfundament für Berechtigungen

## Context
Jeder Test dieser Story braucht dasselbe: eine Gruppe mit bestimmten Rechten, einen Nicht-Admin
darin, und dessen Token. In `QueryApiTest` (Story `008-001`) steht davon eine Einwegfassung, die
nur `apiQueryEnabled` kennt. Sie wird hierher gehoben und um die eigentlichen
Entity-Berechtigungen erweitert.

Ohne diesen Task würde jeder der vier folgenden denselben Aufbau neu schreiben.

## Umfang

Ein Helfer, der in einem Aufruf anlegt und den Token liefert:

- eine **Gruppe** (`pim_group`) mit `apiQueryEnabled` und optionalen Sprachrechten
- einen **Nicht-Admin** (`pim_user`) darin, mit korrekt gehashtem Passwort
  (`sha256` aus Passwort und Salt, wie `User::setPass()` es tut)
- beliebig viele **Berechtigungszeilen** (`pim_permission`) mit `readable`, `writable`,
  `deletable`, `export` und `extended` je Entity

Die Stufen kommen aus `Areanet\PIM\Entity\Permission`:

| Konstante | Wert | Bedeutung |
|---|---|---|
| `NONE` | 0 | kein Zugriff |
| `OWN` | 1 | nur eigene Objekte |
| `ALL` | 2 | alles |
| `GROUP` | 3 | nur Objekte der eigenen Gruppe |

**Beachten:** Die Werte sind nicht aufsteigend geordnet — `GROUP` ist 3, `ALL` ist 2. Wer sie
als Rangfolge liest, irrt. Der Helfer nimmt deshalb die Konstanten, keine Zahlen.

Alles läuft über `pdo()` und meldet sich über `nachTestLoeschen()` zum Aufräumen an — in der
richtigen Reihenfolge, denn `pim_permission` und `pim_user` zeigen auf `pim_group`.

## Acceptance criteria
- [ ] Ein Helfer in `IntegrationTestCase` oder einer eigenen Basisklasse legt Gruppe, Benutzer
      und Berechtigungen an und liefert einen gültigen Token.
- [ ] Die Berechtigungsstufen werden als Konstanten übergeben, nicht als Zahlen.
- [ ] `QueryApiTest` nutzt den Helfer statt seiner Einwegfassung — **ohne dass eine seiner
      Zusicherungen sich ändert**.
- [ ] Alles Angelegte wird nach dem Test abgeräumt, in einer Reihenfolge, die die
      Fremdschlüssel respektiert.
- [ ] `tests/README.md` beschreibt den Helfer.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit
```
Erwartet: **109 Tests, 272 Assertions** — exakt die Zahlen von vorher. Eine Abweichung heißt,
dass beim Umzug von `QueryApiTest` eine Zusicherung verlorenging, und stoppt den Task.
Zusätzlich: `pim_group`, `pim_user` und `pim_permission` sind nach dem Lauf auf dem
Ausgangsstand (ein Benutzer: `admin`).
