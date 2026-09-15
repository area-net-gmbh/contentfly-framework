---
id: 000-000-0024
title: Ein Fehler im Bootstrap antwortet mit einer leeren 500
status: review
depends_on: []
---

# Ein Fehler im Bootstrap antwortet mit einer leeren 500

## Context
Gefunden mit `010-002-0002`, gehört aber nicht dorthin: Der Befund ist allgemein und betrifft
jede Ausnahme, die `lib/contentfly/bootstrap.php` wirft, bevor der Kernel steht.

Gemessen mit `APP_CACHE_DRIVER = 'apcu'` ohne die Erweiterung:

```
HTTP 500, Rumpf 0 Byte
Serverlog: RuntimeException: APP_CACHE_DRIVER = "apcu" verlangt die Erweiterung apcu; …
```

Die Meldung ist da — im **Log**. Der Aufrufer bekommt nichts. Das ist derselbe Zustand, gegen
den `000-000-0006` und `009-002-0004` angetreten sind: Damals fiel ein `TypeError` durch den
Kernel, weil `handleAllThrowables` auf `false` stand. Hier gibt es den Kernel noch gar nicht —
`$app` entsteht erst weiter unten, und ein `kernel.exception`-Listener kann nichts auffangen,
was vor seiner Registrierung passiert.

**Warum es zählt:** Eine Fehlkonfiguration ist der Normalfall beim Aufsetzen einer Instanz.
Wer eine leere 500 bekommt, sucht zuerst am falschen Ende — und auf einem Hoster ohne Zugriff
auf das Serverlog gar nicht.

## Acceptance criteria
- [x] Eine Ausnahme aus dem Bootstrap erreicht den Aufrufer als Antwort mit Rumpf, im selben
      Format wie die übrigen Fehlerantworten (`message`, `type`, `status`).
- [x] Der Rumpf nennt **nicht** mehr, als er darf: Dateipfade und Stacktraces nur bei
      `APP_DEBUG`, wie bei den anderen Fehlerantworten auch (`000-000-0018`).
- [x] Die Konsole ist nicht betroffen — dort ist eine geworfene Ausnahme mit Meldung auf
      `stderr` bereits das richtige Verhalten.
- [x] Ein Test deckt den Fall ab: eine Fehlkonfiguration erzeugen und die Antwort prüfen, nicht
      nur den Statuscode. Ein Test, der 500 gegen 500 prüft, wäre auch mit leerem Rumpf grün —
      genau diese Falle hat `009-002-0004` beschrieben.

## Verification
Eine unerfüllbare `APP_CACHE_DRIVER`-Einstellung setzen, `/api/config` rufen: Statuscode **und**
Rumpf prüfen. Gegenprobe mit `APP_DEBUG=1`: dann darf mehr drinstehen, aber der Rumpf bleibt
JSON.

## Ergebnis

**Nachgestellt, bevor etwas geändert wurde:** `APP_CACHE_DRIVER = 'apc'` (statt `apcu`, weil `apc`
auf jedem PHP scheitert, egal ob eine Erweiterung geladen ist), `GET /api/config` →
`HTTP 500`, Rumpf 0 Byte, Meldung nur im Log. Wie beschrieben.

**Der Fang sitzt in `Kernel\Start::web()`**, nicht im Bootstrap. Das ist die eine Stelle, die alles
umschliesst: die Prüfungen in `prepare()` und jede Zeile von `bootstrap.php`. Die Antwort hat das
Format der übrigen Fehlerantworten (`message`, `type`, `status`), bei `APP_DEBUG` zusätzlich
`debug` mit `file`, `line`, `trace`. Der Rumpf ist in beiden Modi JSON.

**Drei Entscheidungen, die im Code begründet stehen:**

- **Nur in einer Web-SAPI.** Unter `cli` wird weitergeworfen. Die Konsole bleibt damit
  ausdrücklich unberührt, und `StartTest`, der die Abbruchpfade genau so prüft, bleibt
  unverändert grün. Der eingebaute Server meldet `cli-server` und bekommt die Antwort.
- **Die Meldung geht zuerst ins Log** (`error_log()`). Eine gefangene Ausnahme schreibt PHP nicht
  mehr selbst dorthin; ohne diese Zeile hätte die Änderung die Meldung aus dem Log genommen.
  Nachgeprüft: Die Zeile `Contentfly cannot start: RuntimeException: APP_CACHE_DRIVER = "apc" …`
  steht im Serverlog.
- **Ohne `APP_DEBUG` nennt der Rumpf kein Verzeichnis.** Die Meldungen von `Start` nennen das
  Projektverzeichnis absichtlich, für den Betreiber im Log. In der Antwort werden Projekt- und
  Paketverzeichnis durch `<project>`/`<package>` ersetzt. Ein allgemeines Muster für „sieht aus
  wie ein Pfad" hätte auch Routen wie `/api/config` getroffen.

**`APP_DEBUG`, wenn die Konfiguration nie geladen wurde:** Scheitert schon `prepare()`, gibt es
keine Konfiguration. Dann zählt die Umgebungsvariable, die auch die Vorlage liest; ohne sie gilt
„aus". `Config\Factory` hat dafür `hasConfig()` bekommen, weil `getConfig()` ohne Default einen
undefinierten Schlüssel liest.

**Am Rand gefunden, nicht Teil dieses Tasks:** Im Debug-Modus baut der Bootstrap keine
Doctrine-Caches, dort scheitert `apc` beim Start also gar nicht. Die Anmeldebremse baut ihren
Pool zwar immer, aber erst bei Bedarf und damit innerhalb des Kernels, der die Ausnahme selbst
beantwortet. Nachgemessen ist nur der erste Teil (der Start läuft durch); der Test nimmt deshalb
für den Debug-Fall die fehlende Konfiguration.

**Test:** `tests/Unit/Kernel/StartupFailureResponseTest.php` startet den eingebauten Server auf
einem Scratch-Projekt und prüft Statuscode **und** Rumpf in drei Fällen: Fehlkonfiguration ohne
Debug, fehlende Konfiguration mit Debug (Datei, Zeile, Trace, Pfad bleibt), fehlende
Konfiguration ohne Debug (kein Verzeichnis im Rumpf). Er braucht keine Datenbank und liegt deshalb
in der `unit`-Suite. **Gegenprobe:** Mit `Start.php` von master sind alle drei rot
(„Until 000-000-0024 the body was empty").

**Verifiziert:** volle Suite `Tests: 531, Assertions: 1719, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0 Zeilen. Die Bruchstelle steht in `an_project/docs/breaking-changes.md`.

**Nachtrag, eigener Commit:** Die volle Suite lief vor dem Eintrag in `breaking-changes.md`. Mit
ihm wird `MigrationGuideTest` rot, weil das Register 101 Einträge hat und der Kopf von
`an_project/docs/migration.md` 100 nennt. Aufgefallen beim nächsten Task (`000-000-0031`), der
dieselbe Zahl nachziehen musste. Die Zahl ist nachgezogen; volle Suite danach
`Tests: 531, Assertions: 1719, Skipped: 3`, Deprecation-Gate 0.
