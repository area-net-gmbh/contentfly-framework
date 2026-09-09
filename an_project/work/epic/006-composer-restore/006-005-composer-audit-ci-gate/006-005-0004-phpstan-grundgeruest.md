---
id: 006-005-0004
title: PHPStan-Grundgerüst mit Deprecation-Regeln, zunächst nicht blockierend
status: done
depends_on: [006-005-0003]
---

# PHPStan-Grundgerüst mit Deprecation-Regeln, zunächst nicht blockierend

## Context
`tech-stack.md` nennt **zwei** Quellen für das „0 Deprecations"-Gate: das Deprecation-Log und
PHPStan. `006-005-0003` liefert die erste. Diese ist die zweite — und sie sieht anderes:

Das Laufzeit-Log zeigt nur, was ein Testlauf **tatsächlich ausgeführt** hat. Eine deprecated API
in einem Zweig, den die Suite nicht betritt, bleibt unsichtbar bis zum Upgrade. Statische
Analyse findet sie ohne Ausführung. Für den Zweck — der Sprung auf Symfony 8.4 LTS soll ein
reiner Constraint-Bump sein — ist das der wichtigere Teil.

**Der Ausgangspunkt ist nackt.** `phpstan/phpstan ^1.10` liegt seit `006-002` im
`require-dev`, aber es gibt **keine** `phpstan.neon` im Repo, keinen Level, keine Pfade, keine
Baseline. Und die Regeln, die deprecated APIs melden, sind ein eigenes Paket
(`phpstan/phpstan-deprecation-rules`), das nicht installiert ist — ohne es meldet PHPStan
Typfehler, aber keine Deprecations.

## Umfang

### Warum dieser Schritt zunächst nicht blockiert
Entschieden mit dem Schnitt der Story. Der Grund ist derselbe, aus dem `composer audit` erst
nach `006-002` bis `006-004` kommt: Ein Gate, das beim ersten Lauf eine unabsehbare Liste
ausspuckt, wird abgeschaltet statt abgearbeitet.

Der Codebestand ist ein Silex-2-Baum mit Symfony-4.4-Komponenten, Doctrine-Annotationen und
26 Dateien, die direkt an Silex hängen. Wie viele Treffer das gibt, weiss heute niemand — und
das herauszufinden ist der eigentliche Ertrag dieses Tasks.

**Nicht blockierend heisst nicht folgenlos.** Der Job läuft mit `allow_failure: true`, aber er
läuft, und seine Ausgabe ist die Liste, aus der Epic `009` seinen Aufwand ablesen kann. Wann er
scharf wird, gehört als Bedingung festgehalten, nicht als Absicht.

### Was anzulegen ist
- `phpstan/phpstan-deprecation-rules` in `require-dev`. Das Paket ist der Grund für den Task;
  ohne es prüft PHPStan das Falsche.
- Eine `phpstan.neon` (oder `.dist`) mit den Pfaden `lib/`, `custom/`, `bin/`, `tests/` und
  einem Level. **Der Level ist zu begründen**, nicht zu setzen: Für Deprecations genügt ein
  niedriger; ein hoher zieht Typfehler herein, die mit dem Zweck nichts zu tun haben und die
  Liste unlesbar machen.
- Ein CI-Job in der Stage `check`, mit `allow_failure: true`.

### Die Zahl gehört ins Ergebnis
Wie viele Deprecation-Treffer der erste Lauf liefert, und grob woher sie kommen — Silex,
Doctrine, eigener Code. Das ist die Ausgangsgrösse, gegen die Epic `009` seinen Fortschritt
misst, und ohne sie ist „deprecation-frei bauen" eine Absichtserklärung ohne Nullpunkt.

### Baseline: ja oder nein
Eine Baseline friert den Ist-Stand ein und macht den Job sofort grün — verlockend, und hier
wahrscheinlich falsch: Sie würde genau die Liste verstecken, deren Sichtbarkeit der Zweck dieses
Tasks ist. Die Entscheidung gehört ausgesprochen, mit der Bedingung, unter der eine Baseline
später doch richtig wird.

## Abgrenzung
**Keine Behebung der gefundenen Treffer.** Was hier entsteht, ist die Liste, nicht ihre
Abarbeitung; die gehört zu Epic `009` und `010`. Wer hier anfängt zu reparieren, bläst die
Story auf und vermischt Messung mit Umbau.

Keine Änderung an `006-005-0003`; das Laufzeit-Gate steht.

## Acceptance criteria
- [x] `phpstan/phpstan-deprecation-rules` liegt in `require-dev`, der Lock ist nachgezogen.
- [x] Eine PHPStan-Konfiguration liegt im Repo; Pfade und Level sind begründet.
- [x] Ein CI-Job in der Stage `check` führt PHPStan aus, mit `allow_failure: true`.
- [x] Die Bedingung, unter der der Job blockierend wird, ist benannt.
- [x] Über eine Baseline ist entschieden und begründet.
- [x] Die Zahl der Deprecation-Treffer des ersten Laufs steht im Ergebnis, mit grober Herkunft.
- [x] Die Suite bleibt unverändert bei ihren bekannten Failures — ein neues `require-dev`-Paket
      darf nichts verschieben.

## Verification
```sh
composer install
./vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress
```

Dazu der vollständige Joblauf in Docker auf `php:8.3-cli`, wie in `000-000-0021`: Der neue Job
läuft, seine Ausgabe ist lesbar, und der Pflicht-Job der Suite bleibt bei **249 Tests, 7
bekannten Failures, 0 übersprungen**.

Gegenprobe, dass die Regeln greifen: eine Datei mit einem Aufruf auf eine als `@deprecated`
markierte Methode anlegen, PHPStan muss sie melden. Danach entfernen. Ohne diesen Nachweis ist
nicht belegt, dass die Deprecation-Regeln aktiv sind — ein Lauf mit null Treffern sähe genauso
aus wie einer ohne die Regeln.

## Ergebnis
**47 Treffer, und sie liegen alle im eigenen Code.** Das ist die Zahl, um die es in diesem Task
ging — die Ausgangsgrösse, gegen die Epic `009` seinen Fortschritt messen kann.

### Die Liste
Keine einzige Meldung stammt aus `vendor/` (das ist ausgeschlossen). Alle 47 sind **Aufrufe aus
unserem Code in deprecated APIs** von Doctrine und Symfony:

| Datei | Treffer |
|---|---|
| `lib/contentfly/bootstrap.php` | 12 |
| `bin/console.php` | 8 |
| `lib/contentfly/Classes/ORM/Query/Mysql/FindInSet.php` | 4 |
| `lib/contentfly/Classes/ORM/Spatial/Distance.php` | 4 |
| `lib/contentfly/Classes/Manager/TypeManager.php` | 3 |
| `lib/contentfly/Classes/ORM/Spatial/PointStr.php` | 3 |
| `lib/contentfly/bootstrap-web.php` | 3 |
| übrige (8 Dateien) | 10 |

Nach der deprecated API, in die gerufen wird:

| API | Treffer | fällt mit |
|---|---|---|
| `Doctrine\ORM\Query\Lexer` | 11 | Epic `010` |
| `Doctrine\Common\Cache\*` (Apc, Apcu, Filesystem, Memcached) | 11 | Epic `010` |
| `Doctrine\ORM\Tools\Console\*` und `DBAL\Tools\Console\*` | 8 | Epic `010` |
| `Doctrine\Common\Annotations\AnnotationRegistry` | 4 | Epic `010` |
| `Symfony\Component\Debug\*`, `EventDispatcher\Event`, `HttpKernel\Event\*` | 3 | Epic `009` |
| übrige | 10 | — |

**Das Bild ist eindeutig: Der Aufwand liegt bei Doctrine, nicht bei Symfony.** Rund 34 der 47
Treffer hängen an Doctrine-APIs — das ist Epic `010`, nicht `009`. Wer den Kernel-Tausch plant,
sollte diese Zahl kennen, bevor er die Reihenfolge der beiden Epics festlegt.

### Level 0, und der Beweis dass es reicht
Die Deprecation-Regeln greifen unabhängig vom Level. Belegt statt behauptet: **Dieselbe
Konfiguration ohne den `rules.neon`-Include liefert 0 Treffer.** Alle 47 kommen also aus den
Deprecation-Regeln, keiner aus der Typprüfung.

Ein höherer Level zöge Typfehler herein, die mit dem Zweck nichts zu tun haben — auf einem
Silex-2-Baum mit Doctrine-Annotationen viele — und in denen die Deprecations untergingen. Wer
PHPStan als echte statische Analyse will, hebt den Level in einem eigenen Ticket.

Die Gegenprobe aus dem Task-Text (eine Datei mit einem `@deprecated`-Aufruf anlegen) war damit
entbehrlich: Sie sollte ausschliessen, dass ein Lauf mit **null** Treffern nur wie Erfolg
aussieht. Der Lauf hat 47, und der Nullvergleich ohne Regeln zeigt dasselbe schärfer.

### Keine Baseline
Sie friert den Ist-Stand ein und macht den Job sofort grün — und versteckte damit genau die
Liste, deren Sichtbarkeit der Zweck dieses Tasks ist. Die Bedingungen, unter denen sie später
richtig wird, stehen in `phpstan.neon.dist`: wenn die Liste auf null steht (dann ist sie leer
und überflüssig) oder wenn jemand den Level anhebt (dann friert sie die dabei entstehenden
Typfehler ein, ohne die Deprecations mitzunehmen).

### Wann der Job blockierend wird
Sobald die Treffer auf null stehen. Solange die Liste von Doctrine- und Symfony-APIs dominiert
wird, hinge das Gate an Epic `009`/`010` — und ein Gate, das eine andere Story erst grün machen
muss, wird abgeschaltet. Bis dahin `allow_failure: true`.

### Eine Falle, die der Task nicht kannte: das Speicherlimit
Der erste Lauf brach ab:

```
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

Das ist der PHP-Standard von 128M. Was daran gefährlich ist, zeigte sich erst beim Nachmessen:
Mit zu wenig Speicher **scheitert PHPStan nicht sichtbar**, sondern meldet

```
[ERROR] Found 1 error
⚠️  Result is incomplete because of severe errors. ⚠️
```

— also **weniger** Treffer. In einem nicht blockierenden Job sähe das aus wie Fortschritt.

Nachgemessen, und die Cache-Wärme entscheidet:

| Speicher | Ergebnis-Cache warm | Ergebnis-Cache **kalt** |
|---|---|---|
| 128M | läuft durch, 47 Treffer | **unvollständig** |
| 512M | 47 | **47** |

In der CI ist der Cache immer kalt. Der Job läuft deshalb mit `--memory-limit=512M`, und die
Begründung steht als Kommentar daneben — sonst kürzt sie jemand als Übervorsicht weg.

### Verification
| Prüfung | Ergebnis |
|---|---|
| `phpstan analyse` lokal | 47 Treffer |
| derselbe Aufruf im Job-Image `php:8.3-cli` | **47 Treffer**, Exit 1 |
| dieselbe Konfiguration ohne die Deprecation-Regeln | **0 Treffer** |
| volle Testsuite nach dem neuen `require-dev`-Paket | **249 Tests / 598 Assertions, 7 Failures, 0 übersprungen** |

Die letzte Zeile ist die Absicherung gegen den unbeabsichtigten Nebeneffekt: Ein neues
`require-dev`-Paket darf nichts verschieben, und es hat nichts verschoben.
