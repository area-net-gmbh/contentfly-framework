---
id: 000-000-0106
title: composer.lock gegen Format-Rückstufung sichern — das Versions-Gefälle schliessen
status: review
depends_on: []
---

# composer.lock gegen Format-Rückstufung sichern — das Versions-Gefälle schliessen

## Context
**Viermal derselbe Handgriff: `000-000-0084`, `000-000-0091`, `000-000-0098`, `000-000-0105`.**
Jedes Release zieht den Lock mit `composer update areanet/contentfly --no-install` nach, und
jedes Mal schreibt Composer dieselben vier Zeilen um:

```
-    "stability-flags": {},          +    "stability-flags": [],
-    "platform": {},                 +    "platform": [],
-    "platform-dev": {},             +    "platform-dev": [],
-    "plugin-api-version": "2.9.0"   +    "plugin-api-version": "2.6.0"
```

Jedes Mal hat jemand sie von Hand zurückgesetzt. Ein Handgriff, der sich viermal wiederholt, ist
kein Zufall mehr, sondern eine fehlende Prüfung.

**Die Ursache ist ein Versions-Gefälle, kein Composer-Fehler** (gemessen 2026-09-29):

| | |
|---|---|
| Lock im Repository | `plugin-api-version: 2.9.0` — geschrieben von Composer ~2.9.x |
| Lokal installiert | **Composer 2.6.6** (2023-12-08) |
| In der Pipeline | `tools/ci/install-composer.sh` holt `getcomposer.org/installer` **ohne Versionspin** — also jeweils die neueste |

Die CI schreibt 2.9er-Locks, die Entwicklermaschine 2.6er. Wer lokal den Lock anfasst, stuft ihn
zurück; wer es nicht merkt, committet die Rückstufung.

**Warum das mehr ist als Kosmetik.** Heute sind die vier Zeilen funktional gleichwertig, und der
Schaden ist Diff-Rauschen. Aber:

- Ein Lock, den zwei Composer-Versionen abwechselnd umschreiben, macht **jeden** Lock-Diff
  unlesbar — und genau dort will man sehen, ob sich eine Abhängigkeit geändert hat.
- Der ungepinnte Installer in der CI bedeutet, dass auch **die CI-Version wandert**. Ein künftiges
  Composer-Release, das am Format etwas ändert, trifft alle Branches gleichzeitig, und niemand
  hat es entschieden.

## Acceptance criteria
- [x] Ein Prüfskript unter `tools/ci/` meldet eine Format-Rückstufung im committeten `composer.lock` und macht den Lauf rot. Geprüft werden mindestens die vier bekannten Zeilen; welche Form die richtige ist, steht an **einer** Stelle im Skript, nicht verteilt.
- [x] Das Skript läuft lokal ohne Sondervariablen — wie `check-template-config.sh`, damit es vor dem Commit benutzbar ist und nicht erst in der Pipeline meldet.
- [x] Es ist als Job in `pipeline.yml` eingehängt; ob er ein **erforderlicher** Check im Ruleset wird, ist entschieden und begründet (Vorschlag: ja — er ist schnell, deterministisch und hat keine Altlast).
- [x] **Zur Composer-Version in der CI ist entschieden**: pinnen oder bewusst ungepinnt lassen. Die Entscheidung steht in `deployment.md` mit Begründung, nicht nur im Skript.
- [x] Die Meldung sagt, was zu tun ist — nicht „Lock falsch", sondern welche Zeile welche Form braucht und dass die lokale Composer-Version zu alt ist.
- [x] `deployment.md` (Gate-Tabelle) und `git.md` kennen den neuen Check, falls er erforderlich wird.

## Verification
**Gegenprobe statt Sichtprüfung:** Im Arbeitsbaum die vier Zeilen auf die 2.6er-Form setzen —
das Skript muss rot werden und die vier Zeilen einzeln nennen. Zurücksetzen, Skript grün.

Dazu ein echter Lauf: `composer update areanet/contentfly --no-install` mit der lokalen Composer
2.6.6, danach das Skript. Es muss dieselbe Rückstufung melden, die bei den vier Releases von Hand
gefunden wurde.

## Abgrenzung
**Die lokale Composer-Version zu aktualisieren ist nicht Teil dieses Tasks** — das ist eine
Entscheidung je Arbeitsplatz und lässt sich nicht erzwingen. Der Task sorgt dafür, dass eine
veraltete Version auffällt, bevor ihr Ergebnis in `master` landet.

## Ergebnis (2026-09-29)
**`tools/check-lock-format.sh`** prüft die vier Zeilen im committeten Lock. Die erwartete Form
steht an **einer** Stelle im Skript (`ERWARTUNGEN` und `MIN_PLUGIN_API`) — ändert eine künftige
Composer-Version das Format bewusst, wird dort eine Zeile angepasst.

**`plugin-api-version` wird als „oder neuer" geprüft, nicht auf Gleichheit.** Das ist der Kern der
Entscheidung gegen einen Versionspin: Vorwärtsbewegung ist erlaubt, nur die Rückstufung fällt auf
— und genau die ist viermal aufgetreten.

**Gemessen, beide Richtungen:**
- guter Lock → Exit 0
- die vier Zeilen auf die 2.6er-Form gesetzt → Exit 1, jede Zeile **einzeln** genannt, mit der
  erwarteten Form daneben, dazu die lokal installierte Composer-Version (`2.6.6 2023-12-08`) und
  zwei Wege zurück
- danach zurückgesetzt → wieder Exit 0, `git diff` auf dem Lock leer

**Job `check: Lock-Format`** in `pipeline.yml`, ohne Container und ohne `install-composer.sh`: Er
liest eine Datei und soll auch dann etwas sagen können, wenn Composer selbst das Problem ist. Er
läuft **auch im Zeitplan-Lauf** mit, weil es dort um den Lock geht (`000-000-0055`).

**Zur Composer-Version in der CI entschieden: nicht pinnen.** Begründung in `deployment.md` —
ein Pin fröre das Format ein und veraltete still, wie die offene Prüfsummen-Frage im selben
Installer-Skript zeigt.

**Erforderlicher Check: entschieden ja, noch nicht geschaltet.** Das ist eine Änderung am Ruleset
`master-schutz` und damit an den Repository-Einstellungen, nicht am Code; in `git.md` steht, wer
sie wo vornimmt. Bis dahin läuft der Job mit und meldet, blockiert aber nicht.
