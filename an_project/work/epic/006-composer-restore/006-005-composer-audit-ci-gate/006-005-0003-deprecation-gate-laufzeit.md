---
id: 006-005-0003
title: Das „0 Deprecations"-Gate aus dem Laufzeit-Log
status: review
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
- [x] Nach dem Testlauf wird das Serverlog auf Deprecations geprüft.
- [x] Auf `php:8.3-cli` macht ein Treffer den Job rot; die Ausgabe nennt Anzahl **und** Zeilen.
- [x] Auf `php:8.4-cli` wird gemeldet, ohne den Lauf zu erzwingen.
- [x] Ein fehlendes oder unlesbares Log macht den Schritt **rot**, nicht grün.
- [x] Über die eigene Deprecation in `Areanet\PIM\Classes\Config::__construct()` ist entschieden:
      hier behoben oder als Ticket festgehalten.
- [x] Der Schritt liegt in `tools/ci/` und ist lokal nachspielbar.

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

## Ergebnis
**Das Gate steht, ist blockierend und auf PHP 8.3 grün** — aber nicht so, wie dieser Task es
beschrieben hat. Seine Prämisse war falsch, und die Korrektur ist das Wichtigste an diesem
Ergebnis.

### Die Zahlen im Context dieses Tasks waren falsch
Er sagte: 0 Deprecations auf PHP 8.3, 2 auf 8.4. Beide Zahlen stammen aus `000-000-0021`, und
sie sind **an der falschen Datei gemessen**. Die Deprecations des Testservers stehen in
`/tmp/testserver.log`, nicht auf stdout des Jobs — durchsucht hatte ich das Job-Log, und darin
stehen nur die Meldungen des PHP-CLI.

Die echten Zahlen, aus der Quelle, aus der das Gate liest:

| Image | protokollierte Zeilen | Paare aus Datei und Meldung |
|---|---|---|
| `php:8.3-cli` | **148** | **4** |
| `php:8.4-cli` | **779** | **50** |

Die Paare sind der brauchbare Schlüssel: 148 Zeilen sind 148 Requests, in denen dieselben vier
Stellen ansprangen. Die Zeilennummer taugt nicht dafür — sie verschiebt sich bei jeder
Codeänderung oberhalb der Fundstelle.

Der Vermerk steht auch im Skript, weil die Falle wiederkehrt: *„Die Deprecations stehen in
dieser Datei, nicht im Job-Log auf stdout."*

### Damit ändert sich der Zuschnitt: Ausnahmeliste statt Nullpunkt
Ein blockierendes Gate gegen vier bestehende Meldungen wäre ab dem ersten Lauf rot — und würde
abgeschaltet statt abgearbeitet. Also dasselbe Muster wie beim Audit-Gate, auf deine
Entscheidung hin:

`tools/ci/deprecations-ausnahmen.txt` führt die vier bekannten Paare, jedes mit Begründung und
auflösendem Ticket. Eine **fünfte** Meldung macht den Lauf sofort rot, und eine Ausnahme, die
nicht mehr greift, ebenfalls — beide Richtungen, wie in `006-005-0002`.

| Herkunft | Paare | Was |
|---|---|---|
| `vendor/silex/silex` | 1 | `ReflectionParameter::getClass()`, deprecated seit PHP 8.0 — fällt mit Epic `009` |
| eigener Code | 3 | `null` an `strtolower()`, `explode()`, `method_exists()` — festgehalten in `000-000-0023` |

Die drei eigenen sind **echte Defekte**, keine Kosmetik: Seit PHP 8.1 ist `null` an einem
`string`-Parameter deprecated, später wird daraus ein `TypeError`. Der Code läuft nur, weil PHP
`null` noch stillschweigend zu `""` macht.

### Der Job musste umgebaut werden, damit das Gate überhaupt läuft
GitLab bricht die `script`-Liste beim ersten Fehlschlag ab. Da die Suite heute mit 7 bekannten
Failures endet, wäre der Deprecation-Schritt **nie ausgeführt worden** — das Gate wäre genau
dann blind gewesen, wenn am meisten passiert ist.

Der Exit-Code von PHPUnit wird deshalb aufgehoben und erst am Ende wirksam:

```yaml
- set +e; $PHPUNIT; PHPUNIT_EXIT=$?; set -e
- sh tools/ci/deprecations-pruefen.sh
- exit $PHPUNIT_EXIT
```

### Keine Fallunterscheidung im Skript
Das Skript prüft immer dasselbe und schlägt immer fehl. Der Unterschied zwischen „blockiert" und
„nur Frühwarnung" steht in `.gitlab-ci.yml`: `test:php8.4` trägt `allow_failure: true`,
`test:php8.3` nicht. Eine Version-Abfrage im Skript wäre dieselbe Aussage an einer zweiten
Stelle — und die zweite läuft irgendwann von der ersten weg.

### Was 8.4 als Frühwarnung liefert
**46 Paare ohne Ausnahme**, und das ist die Ausgangsgrösse für Epic `009`:

| Herkunft | Paare |
|---|---|
| `symfony/http-foundation` | 15 |
| `symfony/http-kernel` | 12 |
| `symfony/debug` | 12 |
| `symfony/routing` · `silex/silex` | je 2 |
| **eigener Code** | **3** |

Die drei eigenen sind `Api.php`, `Config.php` und `ContentflyQuoteStrategy.php` — genau die
Dateien aus `000-000-0022`. Die 43 aus `vendor/` verschwinden mit dem Kernel-Tausch.

### Verification — fünf Richtungen
| Lauf | Exit | Beleg |
|---|---|---|
| voller Job auf `php:8.3-cli` | **0** | `148 protokollierte Zeile(n), 4 Paar(e) …, 4 davon ausgenommen` |
| voller Job auf `php:8.4-cli` | **1** | `779 … 50 Paar(e) …, 4 davon ausgenommen`, 46 gemeldet (Job trägt `allow_failure`) |
| neue, nicht ausgenommene Meldung ins Log | **1** | nennt Datei und Meldung, sagt was zu tun ist |
| eine Ausnahme greift nicht mehr | **1** | nennt sie und fordert die Streichung |
| Logdatei fehlt | **1** | `✗ Serverlog nicht gefunden` |

Die letzte ist die wichtigste. Ohne sie wäre „null Deprecations gefunden" dasselbe Ergebnis wie
„Datei nie gelesen" — der stille Durchwinker, gegen den `008-005-0002` den Umgebungswächter
gebaut hat.

### Zwei Tickets, die dabei entstanden sind
- **`000-000-0022`** — sieben implizit nullable Parameter im eigenen Code. Der Task-Text nannte
  eine (aus `Config::__construct()`); ein `grep` über den Baum fand sieben. Eine von sieben zu
  beheben wäre willkürlich gewesen und hätte ein falsches Bild erzeugt.
- **`000-000-0023`** — die drei `null`-Übergaben aus der Ausnahmeliste. Ausdrücklich mit der
  Auflage, sie **nicht** wegzucasten: Ein `(string)$wert` bringt die Meldung zum Verschwinden
  und versteckt die Frage, warum dort `null` ankommt.
