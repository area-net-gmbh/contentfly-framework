---
id: 008-003-0001
title: Testfundament für Berechtigungen
status: review
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
- [x] Ein Helfer in `IntegrationTestCase` oder einer eigenen Basisklasse legt Gruppe, Benutzer
      und Berechtigungen an und liefert einen gültigen Token.
- [x] Die Berechtigungsstufen werden als Konstanten übergeben, nicht als Zahlen.
- [x] `QueryApiTest` nutzt den Helfer statt seiner Einwegfassung — **ohne dass eine seiner
      Zusicherungen sich ändert**.
- [x] Alles Angelegte wird nach dem Test abgeräumt, in einer Reihenfolge, die die
      Fremdschlüssel respektiert.
- [x] `tests/README.md` beschreibt den Helfer.

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

## Ergebnis

`IntegrationTestCase::testbenutzer()` legt in einem Aufruf Gruppe, Nicht-Admin und
Entity-Berechtigungen an und meldet den Benutzer an. Rückgabe: Token, Benutzer-Id, Gruppen-Id.

```php
[$token] = $this->testbenutzer(array(
    'PIM\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::OWN),
), array('apiQueryEnabled' => 'enabled'));
```

Fehlende Schlüssel sind `Permission::NONE`; über den zweiten Parameter lassen sich
Gruppenfelder setzen (`apiQueryEnabled`, `languages`). Auch `export` und `extended` sind
ansprechbar — die braucht `008-003-0005`.

`QueryApiTest` ist umgezogen und dabei von 196 auf **149 Zeilen** geschrumpft; seine
Einwegfassung kannte nur `apiQueryEnabled` und eine fest verdrahtete Permission-Zeile.

### Die Falle, die der Helfer entschärft
Die Konstanten in `Areanet\PIM\Entity\Permission` sind **nicht aufsteigend geordnet**:

| Konstante | Wert |
|---|---|
| `NONE` | 0 |
| `OWN` | **1** |
| `ALL` | **2** |
| `GROUP` | **3** |

Wer `3` schreibt und „mehr als ALL" meint, öffnet in Wahrheit auf Gruppenebene. Der Helfer
nimmt deshalb Konstanten, und `tests/README.md` sagt es ausdrücklich dazu.

## Verification
- [x] **109 Tests, 272 Assertions** — exakt die Zahlen von vorher. Beim Umzug von
      `QueryApiTest` ging keine Zusicherung verloren und kam keine hinzu.
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Nach den Läufen: `pim_user=1` (der Installations-Admin), `pim_group=0`,
      `pim_permission=0`, `pim_tag=0` — die Fremdschlüssel-Reihenfolge beim Aufräumen stimmt.
- [x] Ein Lauf ohne `CONTENTFLY_TEST_BASE_URL` überspringt weiterhin sauber.
