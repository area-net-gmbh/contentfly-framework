---
id: 006-005-0003
title: Das „0 Deprecations"-Gate aus dem Laufzeit-Log
status: todo
depends_on: [006-005-0002]
---

# Das „0 Deprecations"-Gate aus dem Laufzeit-Log

## Context
`an_project/docs/tech-stack.md` macht es zur Pflicht, nicht zur Kür:

> **Pflicht dazu — deprecation-frei bauen:** Der spätere Sprung auf Symfony 8.4 LTS ist nur dann
> ein reiner Constraint-Bump, wenn keine in 8.0 entfernten APIs benutzt werden. Deprecation-Log
> und PHPStan gehören deshalb als Gate in die CI („0 Deprecations"), **von Tag 1 an — nicht erst
> vor dem Upgrade**.

Die Quelle liegt bereit. `tools/ci/prepare-test-environment.sh` startet den Testserver mit
`log_errors=On`, und der Kommentar dort sagt wörtlich: *„Das ist die Quelle, aus der das
‚0 Deprecations'-Gate aus 006-005 später liest."* Dieser Task liest.

**Die Ausgangslage ist besser als erwartet.** Aus den Joblaufen von `000-000-0021`, beide
vollständig in Docker gefahren:

| Image | Deprecations im Serverlog |
|---|---|
| `php:8.3-cli` (Pflicht-Job) | **0** |
| `php:8.4-cli` (`allow_failure`) | **2** |

Das Gate kann auf 8.3 **sofort** scharf sein, ohne dass vorher etwas abzuarbeiten wäre. Genau
das meint „von Tag 1 an".

## Umfang

### Was auf 8.4 steht
```
Deprecated: Silex\Application::run(): Implicitly marking parameter $request as nullable …
Deprecated: Areanet\PIM\Classes\Config::__construct(): Implicitly marking parameter $config as nullable …
```

Die erste kommt aus Silex und fällt mit Epic `009`. **Die zweite liegt im eigenen Code.**
`008-005-0001` hielt fest, es sei „eine einzige Deprecation aus Silex selbst" — das stimmt seit
diesem Befund nicht mehr, und die eigene ist die interessantere: Sie ist mit einer Typangabe zu
beheben, unabhängig von Silex und unabhängig von Epic `009`.

Ob sie hier behoben wird oder ein eigenes Ticket bekommt, ist zu entscheiden. Sie zu beheben
ist eine Zeile; sie stehen zu lassen heisst, das 8.4-Bild bleibt bei zwei statt bei einer.

### Der Schritt
Nach dem Testlauf wird das Serverlog auf `Deprecated:` durchsucht. Auf 8.3 macht ein Treffer
den Job rot; auf 8.4 wird gemeldet, aber nicht abgebrochen — der Job trägt dort ohnehin
`allow_failure: true`, und die Frühwarnung ist sein Zweck.

**Die Zahl gehört in die Ausgabe, nicht nur der Vergleich.** Ein Gate, das „0 erwartet, 2
gefunden" sagt und die zwei Zeilen zeigt, ist brauchbar; eines, das nur „failed" sagt, schickt
den nächsten ins Log.

### Zwei Fallen, die das Gate wertlos machen würden
- **Ein leeres Log ist kein Beweis.** Findet der Schritt die Datei nicht — falscher Pfad,
  Server nie gestartet —, meldet er null Deprecations und ist grün. Das ist derselbe stille
  Durchwinker, gegen den `008-005-0002` den Umgebungswächter gebaut hat. Der Schritt muss
  belegen, dass er eine echte Quelle gelesen hat.
- **`display_errors=Off` schreibt nicht ins Log, `log_errors=On` schon.** Beide stehen bereits
  im Testserver-Aufruf, und beide sind Voraussetzung: Ohne das erste landen Deprecations im
  Antwortstrom und verfälschen Statuscodes (`008-005-0001`), ohne das zweite verschwinden sie
  spurlos.

## Abgrenzung
Kein PHPStan — das ist `006-005-0004`. Keine Änderung an der Testsuite und keine Behebung der
Silex-Deprecation; die fällt mit Epic `009`.

## Acceptance criteria
- [ ] Nach dem Testlauf wird das Serverlog auf Deprecations geprüft.
- [ ] Auf `php:8.3-cli` macht ein Treffer den Job rot; die Ausgabe nennt Anzahl **und** Zeilen.
- [ ] Auf `php:8.4-cli` wird gemeldet, ohne den Lauf zu erzwingen.
- [ ] Ein fehlendes oder unlesbares Log macht den Schritt **rot**, nicht grün.
- [ ] Über die eigene Deprecation in `Areanet\PIM\Classes\Config::__construct()` ist entschieden:
      hier behoben oder als Ticket festgehalten.
- [ ] Der Schritt liegt in `tools/ci/` und ist lokal nachspielbar.

## Verification
Der vollständige Job in Docker, auf beiden Images — so wie `000-000-0021` es vorgemacht hat:

| Lauf | erwartet |
|---|---|
| `php:8.3-cli`, unveränderter Stand | 0 Deprecations, Schritt grün |
| `php:8.4-cli`, unveränderter Stand | 2 (bzw. 1 nach Behebung) gemeldet, Lauf nicht abgebrochen |
| `php:8.3-cli`, Deprecation künstlich ausgelöst | **rot**, Ausgabe nennt die Zeile |
| Logpfad auf eine nicht existierende Datei gezeigt | **rot**, nicht grün |

Die letzten beiden sind die eigentliche Abnahme. Ein Gate, das nur im Gutfall vorgeführt wurde,
ist nicht vorgeführt.
