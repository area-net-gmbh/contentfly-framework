---
id: 000-000-0026
title: Der tote Authentifizierungsschalter in BaseControllerProvider
status: review
depends_on: []
---

# Der tote Authentifizierungsschalter in BaseControllerProvider

## Context
`BaseControllerProvider::isAuthRequiredForPath()` und die Konstante `LOGIN_PATH` ruft **niemand**
— weder im Framework noch in `custom/` noch in `plugins/`.

**Das ist nicht durch Epic `013` tot geworden.** `checkToken()` hat die Methode nie benutzt; sie
stand schon vorher unbenutzt da. Aufgefallen ist sie bei `013-002-0004`, als `checkToken()`
entfiel und die Nachbarschaft durchgesehen wurde — und sie blieb bewusst stehen, weil ein Task
nicht fremdes Totholz mitnimmt, das er nicht selbst erzeugt hat.

Die Methode sieht beim Lesen aus wie eine Stelle, an der man steuert, welche Pfade eine Anmeldung
brauchen. Das tut sie nicht: Diese Entscheidung faellt je Route ueber `isSecure` im
`RouteManager` beziehungsweise ueber den `$checkAuth`-Hook der Provider.

**Zu klaeren, bevor etwas entfernt wird:** Beide sind `protected` bzw. `const` auf einer Klasse,
von der ein Projekt eigene Controller-Provider ableitet. Ein Bestandsprojekt koennte sie
ueberschrieben oder aufgerufen haben — nachsehen laesst sich das hier nicht, also gehoert die
Entfernung in `breaking-changes.md`.

## Acceptance criteria
- [x] Nachgemessen und festgehalten, dass beide im ganzen Baum keinen Aufrufer haben.
- [x] Entfernt — oder, falls es einen Grund zum Behalten gibt, ist dieser im Code benannt statt im Kopf.
- [x] Die Entfernung steht in `an_project/docs/breaking-changes.md`, mit dem Hinweis fuer Projekte, die von `BaseControllerProvider` ableiten.
- [x] Die volle Suite bleibt gruen.

## Verification
`grep` ueber `lib/`, `custom/`, `plugins/` und `tests/`. Volle Suite, PHPStan.

## Ergebnis

**Beide sind weg.** `LOGIN_PATH` und `isAuthRequiredForPath()` hatten im ganzen Baum keinen
einzigen Aufrufer.

### Nachgemessen, nicht angenommen

```
grep -rn "isAuthRequiredForPath\|LOGIN_PATH" --include='*.php' lib/ custom/ plugins/ tests/ bin/
```

Vorher: drei Treffer, alle in `BaseControllerProvider.php` selbst — die Konstante, die
Methodensignatur, und die eine Zeile im Rumpf, die die Konstante liest. Nachher: keiner.

### Warum das mehr ist als Aufräumen

Die Methode **sah aus wie ein Schalter**, an dem man steuert, welche Pfade eine Anmeldung
brauchen. Das tut sie nicht, und das ist der Punkt: Diese Entscheidung fällt je Route über
`isSecure` im `RouteManager` beziehungsweise über den `$checkAuth`-Hook des Providers.

Eine Methode, die einen Schalter vortäuscht, den es woanders gibt, ist gefährlicher als gar
keine. Wer sie beim Lesen findet und für die zuständige Stelle hält, ändert sie — und wundert
sich, dass nichts passiert.

### Was das für ein Bestandsprojekt heisst

Beide sassen auf einer Klasse, von der ein Projekt eigene Controller-Provider ableitet.
Nachsehen lässt sich von hier aus nicht, ob jemand sie benutzt, also steht die Entfernung in
`breaking-changes.md` — mit dem Unterschied, auf den es ankommt:

| Fall | Folge |
|---|---|
| überschrieben | kein Fehler. Sie wurde nie gerufen, auch vorher nicht |
| aufgerufen | `Error`. Der Aufruf kann ersatzlos weg, sein Rückgabewert steuerte nichts |

### Nachweis

| Probe | Ergebnis |
|---|---|
| `grep` über fünf Verzeichnisse | 0 Treffer |
| Volle Suite | `OK (471 tests, 1150 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |

Die Suite ist nicht gewachsen, und das ist richtig so: Entfernter Code bekommt keinen Test.
Belegt ist er durch den `grep` und dadurch, dass 471 bestehende Tests weiterlaufen.
