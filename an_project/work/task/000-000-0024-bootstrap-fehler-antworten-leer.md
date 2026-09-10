---
id: 000-000-0024
title: Ein Fehler im Bootstrap antwortet mit einer leeren 500
status: todo
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
- [ ] Eine Ausnahme aus dem Bootstrap erreicht den Aufrufer als Antwort mit Rumpf, im selben
      Format wie die übrigen Fehlerantworten (`message`, `type`, `status`).
- [ ] Der Rumpf nennt **nicht** mehr, als er darf: Dateipfade und Stacktraces nur bei
      `APP_DEBUG`, wie bei den anderen Fehlerantworten auch (`000-000-0018`).
- [ ] Die Konsole ist nicht betroffen — dort ist eine geworfene Ausnahme mit Meldung auf
      `stderr` bereits das richtige Verhalten.
- [ ] Ein Test deckt den Fall ab: eine Fehlkonfiguration erzeugen und die Antwort prüfen, nicht
      nur den Statuscode. Ein Test, der 500 gegen 500 prüft, wäre auch mit leerem Rumpf grün —
      genau diese Falle hat `009-002-0004` beschrieben.

## Verification
Eine unerfüllbare `APP_CACHE_DRIVER`-Einstellung setzen, `/api/config` rufen: Statuscode **und**
Rumpf prüfen. Gegenprobe mit `APP_DEBUG=1`: dann darf mehr drinstehen, aber der Rumpf bleibt
JSON.
