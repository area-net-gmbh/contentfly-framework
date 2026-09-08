---
id: 006-002-0006
title: Testerwartungen an den neuen Stack anpassen
status: todo
depends_on: [006-002-0003]
---

# Testerwartungen an den neuen Stack anpassen

## Context
`006-002-0003` hat den Lock erzeugt und die Anwendung gegen Doctrine 2.20 / Symfony 4.4 zum
Laufen gebracht. Von 247 Tests bleiben **44 Failures und 7 Errors** — und der Grossteil davon
sind **Verbesserungen**, keine Brüche.

Beispiel aus dem Protokoll:

```
4) WriteApiTest::testDeleteOhneTokenLoeschtNichts
   Heute 500 statt 401 — siehe 000-000-0006
   Failed asserting that 401 is identical to 500.
```

Der Testkommentar sagt es selbst: Die Erwartung `500` hält einen **Fehler** fest, mit Verweis
auf `000-000-0006`. Symfony 4.4 liefert jetzt `401`. **Der Sprung behebt den Befund.**

`an_project/docs/technical.md` ist an dieser Stelle eindeutig:

> Eine Testanpassung ist ein **Verhaltenswechsel** und braucht eine Begründung — sie ist kein
> Wartungsschritt.

Genau deshalb ist das ein eigener Task und nicht Beiwerk von `0003`: 44 Begründungen sind
Arbeit, und sie einzeln zu prüfen ist der Sinn der Regel.

## Umfang

### Die Statuscode-Änderungen — 32 Fälle
| Erwartet → jetzt | Anzahl |
|---|---|
| `500` → `401` | 17 |
| `500` → `403` | 8 |
| `403` → `401` | 4 |
| `500` → `404` | 3 |

Alle in dieselbe Richtung: Wo Symfony 3.4 eine Exception in einen `500` verwandelte, liefert
4.4 den gemeinten Code. **Das ist die Behebung von `000-000-0006`** — jedenfalls in Teilen; ob
der Task damit ganz erledigt ist, gehört dort geprüft und vermerkt.

Je Test ist zu entscheiden:
- Der neue Code ist **richtig** → Erwartung anpassen, Kommentar umschreiben (der Verweis auf
  `000-000-0006` wird zur Erfolgsmeldung statt zur Klage).
- Der neue Code ist **falsch** → Befund, eigener Task.

Ein pauschales Suchen-und-Ersetzen von `500` auf `401` verfehlt den Zweck: Es gibt Tests, die
`500` aus einem anderen Grund erwarten (etwa `SystemControllerApiTest`, wo eine nackte
`\Exception` fliegt — die bleibt ein `500`).

### Die Redirect-Änderungen — 5 Fälle
`302` statt `200`, `405`, `301` oder `500`. Symfony 4.4 routet anders, vermutlich beim
Trailing Slash. **Zu klären, bevor angepasst wird:** Ein Redirect, wo vorher eine Antwort kam,
kann auch ein Fehler sein — etwa wenn `APP_FORCE_SSL` greift, wo es nicht soll.

### Die 7 Errors — `contentfly_general_plugin_not_found`
Alle aus derselben Quelle. **Nicht verstanden, als `0003` schloss** — und deshalb hier
ausdrücklich als *offene Ursache* geführt, nicht als bekannte Grösse. Erst die Ursache, dann
die Entscheidung.

Ein Verdacht, der zu prüfen wäre: `PluginManagerTest` legt Plugins zur Laufzeit unter
`plugins/` an, und der `TypeManager` registriert deren Annotationen über
`AnnotationRegistry::registerFile()`. `006-002-0003` hat an genau dieser Stelle einen
`registerLoader()` ergänzt. Ob das zusammenhängt, ist zu prüfen — nicht anzunehmen.

## Abgrenzung
- Kein Entfernen von `vendor/` aus Git — das ist `006-003`.
- Keine Änderung an `composer.json` oder `composer.lock`. Stellt sich heraus, dass ein
  Constraint falsch ist, ist das ein Befund für `0003`, kein Nebenbei-Fix hier.
- Keine neuen Tests. Hier werden **Erwartungen** nachgezogen, nicht Abdeckung ergänzt.

## Acceptance criteria
- [ ] Alle 44 Failures sind aufgelöst: je Test entweder Erwartung angepasst **mit Begründung
      im Testkommentar**, oder als Befund mit eigenem Task festgehalten.
- [ ] Die 7 Errors sind auf ihre Ursache zurückgeführt; die Ursache steht im Ergebnis.
- [ ] Kein pauschales Ersetzen — für jede geänderte Erwartung ist erkennbar, **warum** der
      neue Wert der richtige ist.
- [ ] Wo ein Test bisher `000-000-0006` als offenen Befund zitierte und der Sprung ihn behebt,
      sagt der Kommentar das jetzt.
- [ ] In `000-000-0006` ist vermerkt, welche Teile durch den Stack-Wechsel erledigt sind — und
      welche nicht.
- [ ] Die Suite läuft gegen den **neuen** Baum vollständig grün.

## Verification
Mehrere Läufe gegen den neuen Baum, mit `CI=true` (dann greift der Wächter aus `008-005-0002`).

Und die Gegenprobe, die verhindert, dass hier stillschweigend Abdeckung verlorengeht: Die
**Zahl der Assertions** darf nicht sinken. 603 waren es vor dem Wechsel; wer eine Erwartung
lockert statt sie anzupassen, sieht es daran.
