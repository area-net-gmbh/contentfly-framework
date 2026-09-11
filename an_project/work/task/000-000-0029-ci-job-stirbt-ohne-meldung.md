---
id: 000-000-0029
title: Der CI-Job stirbt ohne Meldung
status: todo
depends_on: []
---

# Der CI-Job stirbt ohne Meldung

## Context
Gefunden bei `013-005-0004`. Der PHP-8.4-Lauf brach mit **Exit 2 und null Zeilen Ausgabe** ab.
Keine Fehlermeldung, kein Hinweis, nichts — nur ein roter Job.

Die Ursache war ein echter Fehler (`composer install` verlangte `ext-ldap`, das im Image fehlte),
und sie stand woertlich in der Composer-Ausgabe. **Nur sah sie niemand**, weil der Schritt so
aussieht:

```sh
composer install --no-interaction --no-progress --prefer-dist > /tmp/i.log 2>&1
```

Zusammen mit `set -eu` am Dateikopf heisst das: Der erste Fehlschlag beendet das Skript, und
alles, was er zu sagen hatte, liegt in einer Datei, die nie jemand ausgibt.

Sichtbar wurde der Fehler erst, als die Schritte von Hand einzeln und ohne Umleitung gefahren
wurden — das kostete mehrere Anlaeufe und die Vermutung, Docker selbst sei kaputt.

**Betroffen ist das Werkzeug, nicht das Framework.** `tools/ci/prepare-test-environment.sh` macht
es an einer Stelle richtig vor: Wenn der Testserver nicht antwortet, gibt es das Serverlog aus,
bevor es aufgibt.

## Acceptance criteria
- [ ] Ein fehlgeschlagener Schritt gibt aus, woran er gescheitert ist — mindestens die letzten Zeilen seines Logs.
- [ ] Das gilt fuer jeden Schritt, dessen Ausgabe heute umgeleitet wird, nicht nur fuer `composer install`.
- [ ] Die Loesung steht an einer Stelle (eine Hilfsfunktion oder ein `trap`), nicht als kopierte Zeile je Schritt.
- [ ] Ein absichtlich herbeigefuehrter Fehlschlag wird durchgespielt, und die Meldung ist in der Ausgabe zu sehen.
- [ ] Die Umleitung selbst bleibt: Eine Pipeline, die jeden `apt-get`-Fortschritt ausgibt, liest niemand.

## Verification
Einen Schritt kuenstlich scheitern lassen (etwa ein Paket, das es nicht gibt) und die Ausgabe
ansehen. Danach ein normaler Lauf, der nicht lauter geworden sein darf.
