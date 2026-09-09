---
id: 006-005-0004
title: PHPStan-Grundgerüst mit Deprecation-Regeln, zunächst nicht blockierend
status: todo
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
- [ ] `phpstan/phpstan-deprecation-rules` liegt in `require-dev`, der Lock ist nachgezogen.
- [ ] Eine PHPStan-Konfiguration liegt im Repo; Pfade und Level sind begründet.
- [ ] Ein CI-Job in der Stage `check` führt PHPStan aus, mit `allow_failure: true`.
- [ ] Die Bedingung, unter der der Job blockierend wird, ist benannt.
- [ ] Über eine Baseline ist entschieden und begründet.
- [ ] Die Zahl der Deprecation-Treffer des ersten Laufs steht im Ergebnis, mit grober Herkunft.
- [ ] Die Suite bleibt unverändert bei ihren bekannten Failures — ein neues `require-dev`-Paket
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
