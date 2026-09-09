---
id: 009-002-0006
title: Die Einstiegspunkte und der Nachweis
status: review
depends_on: [009-002-0003, 009-002-0004, 009-002-0005]
---

# Die Einstiegspunkte und der Nachweis

## Context
Der letzte Task der Story, und der einzige, an dem die volle Suite wieder läuft. Bis hierher war
der Baum nicht lauffähig — das ist die Form des Schnitts, nicht sein Fehler.

Zusammenzuführen ist, was die vier Tasks davor gebaut haben: `index.php`, `bootstrap.php`,
`bootstrap-web.php` und `tests/router.php`. Der Bootstrap trägt heute Dinge, die mit dem Kernel
zusammenhängen und einzeln zu prüfen sind — die `AnnotationRegistry`-Falle, der SSL-Zwang, die
Ableitung von `WEB_ROOT` und die Produktionseinstellung aus `000-000-0018`.

**Der Nachweis ist das eigentliche Ergebnis.** `an_project/docs/technical.md`:

> Der Kernel-Tausch gilt als gelungen, wenn diese Suite ohne inhaltliche Änderung grün bleibt.

Eine Erwartung, die angepasst werden muss, ist ein Befund und braucht eine Begründung — keine
Anpassung. Der Stand vor dem Schnitt: **249 Tests, 616 Assertions, 0 übersprungen.**

## Acceptance criteria
- [x] Die vier Einstiegspunkte booten den neuen Kernel; die Reihenfolge im Bootstrap ist
      erhalten, wo sie Absicht war (`custom/app.php` vor `bindRoutes()`).
- [x] `tests/Unit/Kernel/KeineSilexTypenTest.php` hat eine **leere** Ausnahmeliste, und die
      Gegenprüfung — eine Ausnahme, die nichts mehr abdeckt — ist damit erfüllt.
- [x] Die Suite aus Epic `008` ist grün, ohne inhaltliche Änderung an einer Zusicherung. Muss
      eine geändert werden, steht die Begründung im Ergebnis, und zwar je Zusicherung.
- [x] `composer audit --locked` sauber; die Ausnahmeliste aus `006-005-0001` ist leer oder ihr
      Rest ist begründet.
- [x] Deprecation-Gate grün, und die Ausnahme für Silex ist aus
      `tools/ci/deprecations-ausnahmen.txt` verschwunden — sie hat keinen Anlass mehr.
- [x] Die Versandfalle bleibt stumm: Postausgang 0 Byte.

## Verification
Volle Suite gegen eine frisch installierte Wegwerf-Datenbank, `composer audit --locked`,
`sh tools/ci/deprecations-pruefen.sh` und `sh tools/ci/audit-ausnahmen-pruefen.sh`. Dazu ein
Durchgang durch `an_project/docs/runbook.md` von einem frischen Klon aus — die Anleitung war
schon einmal nicht durchführbar (`006-003-0003`), und ein Kernel-Wechsel ist der wahrscheinlichste
Anlass, dass sie es wieder wird.

## Ergebnis

**Die Suite ist grün: `OK (266 tests, 640 assertions)`, 0 übersprungen — und keine einzige
Zusicherung ist inhaltlich geändert worden.** Das ist die Abnahme, die `technical.md` verlangt.

### Die Zahl ist gewachsen, und das ist erklärbar

| | |
|---|---|
| Vor dem Schnitt | 249 |
| `ContainerTest` (`009-002-0002`) | +12 |
| `HookReihenfolgeTest` (`009-002-0004`) | +5 |
| **Nach dem Schnitt** | **266** |

249 + 12 + 5 = 266. **Kein bestehender Test ist verschwunden, keiner umgeschrieben.** Die
einzigen Änderungen an Testdateien betrafen zwei Importe: `RouteAndConsoleManagerTest` erwartet
jetzt `\RuntimeException` statt `Pimple\Exception\FrozenServiceException` — dasselbe Verhalten,
eigener Container —, und `KeineSilexTypenTest` hat seine Ausnahmeliste geleert.

### Null Deprecations, und das Gate hat es selbst eingefordert

Die letzte Ausnahme in `tools/ci/deprecations-ausnahmen.txt` betraf Silex'
`ExceptionListenerWrapper`. Nach dem Schnitt meldete das Gate von sich aus:

```
Die Meldung tritt nicht mehr auf — der Grund fuer die Ausnahme ist entfallen.
ZU TUN: die Zeile aus tools/ci/deprecations-ausnahmen.txt streichen.
```

Genau so war Regel 3 der Datei gemeint, und sie hat gegriffen. Der Stand jetzt:

```
0 protokollierte Zeile(n), 0 Paar(e) aus Datei und Meldung, 0 davon ausgenommen.
```

**Null Vorkommen, null Ausnahmen.** Das Ziel „0 Deprecations", das Epic `009` als Voraussetzung
für den späteren Sprung auf Symfony 8.4 LTS nennt, ist damit nicht behauptet, sondern gemessen.
Die Ausnahmedatei bleibt mit ihren Regeln stehen — die nächste Zeile darin ist wieder eine
Aussage und keine Gewohnheit.

Dasselbe bei `composer audit --locked`: „No security vulnerability advisories found", und
`config.audit.ignore` steht auf `{}`. Fünf ignorierte CVEs auf null.

### Der Wächter aus `009-001-0005` steht jetzt auf leer

Fünf Einträge, jeder eine Aufgabe für diese Story, alle gefallen: der Bootstrap mit seinen vier
`register()`-Aufrufen, die zwei Fugen `Kernel\Application` und `Kernel\Command`,
`InstallCommand::bootDoctrine()` und der Pimple-Einfriertest. Die Liste bleibt als leeres Array
stehen, nicht als gelöschte Mechanik: Der eine Test meldet eine neue Verwendung, der andere eine
unbenutzte Ausnahme. Zusammen halten sie den Zustand, den dieses Epic hergestellt hat.

### Die Einstiegspunkte

`index.php` und `tests/router.php` sind **unverändert** — sie zeigen auf
`lib/contentfly/bootstrap-web.php` beziehungsweise liefern statische Dateien aus, und beides
hängt nicht am Kernel. Der Bootstrap behält seine Reihenfolge, wo sie Absicht war:
`custom/app.php` wird gelesen, dann bindet `bindRoutes()`.

Smoke-Test aus dem Runbook, alles wie beschrieben:

| Aufruf | Antwort |
|---|---|
| `GET /` | 405, JSON, „Method Not Allowed (Allow: OPTIONS)" |
| `GET /gibtsnicht` | 405 |
| `OPTIONS /api/list` | 204 |
| `POST /api/v1/example/bootstrap` | 200, der Envelope der Vorlage |
| CORS-Header auf `/api/config` | Origin, Credentials, Headers, Methods — alle vier |

### Nachweis, vollständig durchgespielt

Frische Datenbank angelegt, `appcms:install` durchgelaufen, Testserver gestartet, Suite
gefahren — in dieser Reihenfolge, ohne Zwischenschritt von Hand.

| | |
|---|---|
| Volle Suite | `OK (266 tests, 640 assertions)`, 0 übersprungen |
| Deprecation-Gate | 0 Zeilen, 0 Paare, 0 Ausnahmen |
| `composer audit --locked` | „No security vulnerability advisories found" |
| `config.audit.ignore` | `{}` |
| Postausgang der Versandfalle | 0 Byte |
| `KeineSilexTypenTest` | grün mit leerer Ausnahmeliste |
