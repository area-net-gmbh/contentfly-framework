---
id: 000-000-0029
title: Der CI-Job stirbt ohne Meldung
status: done
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
- [x] Ein fehlgeschlagener Schritt gibt aus, woran er gescheitert ist — mindestens die letzten Zeilen seines Logs.
- [x] Das gilt fuer jeden Schritt, dessen Ausgabe heute umgeleitet wird, nicht nur fuer `composer install`.
- [x] Die Loesung steht an einer Stelle (eine Hilfsfunktion oder ein `trap`), nicht als kopierte Zeile je Schritt.
- [x] Ein absichtlich herbeigefuehrter Fehlschlag wird durchgespielt, und die Meldung ist in der Ausgabe zu sehen.
- [x] Die Umleitung selbst bleibt: Eine Pipeline, die jeden `apt-get`-Fortschritt ausgibt, liest niemand.

## Verification
Einen Schritt kuenstlich scheitern lassen (etwa ein Paket, das es nicht gibt) und die Ausgabe
ansehen. Danach ein normaler Lauf, der nicht lauter geworden sein darf.

## Ergebnis

**`tools/ci/schritt.sh` ist die eine Stelle.** Eine gesourcete Funktion, kein `trap`:

```sh
. "$(dirname "$0")/schritt.sh"
schritt "gd konfigurieren" docker-php-ext-configure gd --with-freetype
```

Sie faengt stdout und stderr in eine Datei. Gelingt der Befehl, bleibt eine Zeile stehen und die
Datei wird geloescht. Scheitert er, kommen Name des Schritts, Exit-Code und die letzten 40 Zeilen
der Ausgabe dazu, und das Skript endet mit demselben Code. Gegen den `trap` sprach, dass er
dieselbe Buchfuehrung braeuchte — welcher Schritt, welches Log —, nur auf zwei Stellen verteilt.

**Der absichtliche Fehlschlag ist im echten Image durchgespielt**, `php:8.4-cli`, mit einem
Paketnamen, den es nicht gibt:

```
✗ Fehlgeschlagen: Systempakete fuer gd, ldap und den Composer-Entpacker
  Befehl:    apt-get install -y -q --no-install-recommends libpng-dev … paket-das-es-nicht-gibt
  Exit-Code: 100
  | E: Unable to locate package paket-das-es-nicht-gibt
```

**Der normale Lauf ist nicht lauter geworden:** 13 Zeilen vorher, 14 nachher — die eine mehr ist
`apt-get update`, das jetzt ein eigener benannter Schritt ist statt an den naechsten angehaengt.

## Was die Messung an der Aufgabenstellung korrigiert

**Die Skripte im Repo waren nicht so blind, wie dieser Task behauptet.** Ich habe den alten
Stand gegen denselben Fehler gefahren, im selben Image:

```
→ Systempakete fuer gd und den Composer-Entpacker
E: Unable to locate package paket-das-es-nicht-gibt
```

Die Meldung war da. `> /dev/null` nimmt nur stdout, und `apt-get` schreibt Fehler nach stderr —
`docker-php-ext-configure` und `docker-php-ext-install` ebenso, auch das nachgemessen
(`configure: error: Package requirements (zlib >= 1.2.11) were not met`). **Die Sichtbarkeit
hing also daran, dass diese Werkzeuge ihre Fehler zufaellig auf dem richtigen Kanal melden.**
Das ist Glueck, keine Eigenschaft der Pipeline — und genau das ist jetzt behoben.

**Der Schritt, der `013-005-0004` gekostet hat, stand nicht im Repo.** Er stand in einem
Wegwerf-Skript, mit dem ich den 8.4-Job lokal nachgestellt habe, und hatte die Form
`… > /tmp/i.log 2>&1` — die, bei der auch stderr mitverschwindet. Das aendert nichts am Befund,
aber es aendert, wo er sitzt: nicht in einer bestimmten Zeile, sondern in der Form.

**Drei Stellen im Repo schwiegen wirklich, und alle drei sind behoben:**

1. **Der Composer-Bootstrap**, dreimal woertlich gleich in der `.gitlab-ci.yml`. Der zweite
   Schritt trug `--quiet` und sagte im Fehlerfall nichts. Er steht jetzt in
   `tools/ci/install-composer.sh` — einmal, und ueber `schritt`.
2. **`php -r "copy('https://getcomposer.org/installer', …)"`** — `copy()` gibt bei einem
   Fehlschlag `false` zurueck, und `php -r` endet trotzdem mit 0. Ein Netzwerkfehler lief
   durch, und erst der naechste Schritt scheiterte an einer Datei, die es nicht gibt oder die
   eine HTML-Fehlerseite enthaelt; die Meldung handelte dann von PHP-Syntax statt von einem
   Download. Derselbe Befund eine Zeile frueher. Der Ersatz prueft Ladefehler, Inhalt und
   Schreibfehler einzeln und sagt jeweils, was war.
3. **`composer audit … 2>/dev/null`** in `audit-ausnahmen-pruefen.sh`. Die Pruefung meldete
   lautstark, dass sie keine Daten bekommen hat — und warf im selben Atemzug den Grund weg, den
   composer genannt hatte. Der wird jetzt mit ausgegeben.

## Der Waechter

`tests/Unit/Ci/CiSchritteTest.php`, vier Tests. Zwei fahren die Funktion selbst: einen
scheiternden Schritt (Meldung sichtbar, Exit-Code durchgereicht) und einen gelungenen (Ausgabe
bleibt weg — sonst waere „alles ausgeben" eine gueltige Loesung). Zwei halten die Regel: kein
Schritt in `tools/ci/` schweigt an der Funktion vorbei, und jede eingetragene Ausnahme trifft
noch zu. Eine Ausnahme, die gegenstandslos geworden ist, macht den Lauf rot — dieselbe Regel wie
bei den Gates aus `006-005`.

**Beide Haelften sind durch absichtliche Verletzung geprueft:** eine eingeschmuggelte Zeile
`irgendein-befehl > /dev/null` und eine gegenstandslos gemachte Ausnahme; beide wurden gemeldet,
danach wurde zurueckgesetzt.

**Und der Waechter hatte selbst eine Luecke.** Mein erster Entwurf suchte nach `/dev/null`,
`--quiet`, `--silent` und `-qq` — also gerade nicht nach der Form, die den Vorfall ausgeloest
hat. Eine Zeile `composer install … > /tmp/i.log 2>&1` waere durchgelaufen. Aufgefallen ist es
erst beim Nachmessen des alten Stands. Er erkennt jetzt auch `2>&1` und `2>` in eine Datei;
nachgeprueft mit genau dieser Zeile.

**Nicht geloest, ausdruecklich benannt:** Der Composer-Installer wird weiterhin ohne Pruefsumme
ausgefuehrt. Das war vorher so und ist eine eigene Frage — sie beantwortet man nicht nebenbei in
einem Task ueber Fehlermeldungen. Der Hinweis steht im Kopf von `install-composer.sh`.

**Der Waechter deckt `tools/ci/*.sh` ab, nicht die `.gitlab-ci.yml`.** Das ist kein Versehen:
Der Kopf der YAML sagt selbst, dass die Schritte in die Skripte gehoeren, und mit diesem Task
ist der letzte Rest dort herausgezogen.

**Zahlen:** Volle Suite `OK (475 tests, 1162 assertions)` (vorher 471), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Dazu vier Laeufe in `php:8.4-cli`: der
alte Stand mit kaputtem Paket, der neue mit kaputtem Paket, der alte mit fehlenden Bibliotheken,
und ein normaler Lauf beider Skripte von Null (Exit 0, 21 Zeilen Ausgabe).
