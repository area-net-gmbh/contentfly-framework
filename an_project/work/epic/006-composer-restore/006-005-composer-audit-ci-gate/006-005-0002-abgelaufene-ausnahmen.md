---
id: 006-005-0002
title: Eine Ausnahme, die nicht mehr greift, macht den Lauf rot
status: done
depends_on: [006-005-0001]
---

# Eine Ausnahme, die nicht mehr greift, macht den Lauf rot

## Context
`006-005-0001` nimmt fünf CVEs vom Gate aus. Das ist die richtige Entscheidung für einen Stack,
der bis Epic `009` auf Symfony 4.4 festliegt — und es ist zugleich die Stelle, an der solche
Gates sterben.

Der Story-Text sagt es selbst: **`--ignore` „mit Begründung und Ablaufdatum, nicht ein dauerhaft
abgeschaltetes Gate"**. Ohne eine Mechanik dahinter ist ein Ablaufdatum ein Kommentar, den
niemand liest.

Das Muster ist im Projekt bekannt. `008-005-0002` hat denselben Fehler an anderer Stelle
verhindert: eine grüne Suite, die nichts geprüft hat. Hier ist es ein grünes Gate, das nichts
mehr durchlässt, weil es alles ausnimmt.

## Umfang

### Der Fall, um den es geht
Epic `009` hebt Symfony auf 7.4. In diesem Moment verschwinden die fünf CVEs aus der Meldung —
und die `--ignore`-Liste nennt fünf Kennungen, die es nicht mehr gibt. Sie ist ab da eine
Erlaubnis ins Leere, die stillschweigend weiterwirkt: Träfe später eine dieser Kennungen
wieder zu, wäre sie schon ausgenommen.

Genauso beim Umgekehrten: Wird ein ausgenommenes Paket entfernt, bleibt seine Ausnahme stehen.

### Was zu bauen ist
Ein Vergleich zwischen **erwartet** und **tatsächlich**: Die Liste der ausgenommenen Kennungen
gegen die Liste der Kennungen, die `composer audit --locked` ohne Ausnahmen meldet.

- Eine Kennung, die ausgenommen ist und **nicht mehr gemeldet wird** → der Lauf wird rot, mit
  der Aufforderung, sie aus der Liste zu streichen.
- Eine Kennung, die gemeldet wird und **nicht ausgenommen ist** → das fängt bereits das Gate aus
  `006-005-0001`.

Damit hat jede Ausnahme ihr Ablaufdatum in der Sache statt im Kalender: Sie läuft ab, wenn ihr
Grund wegfällt. Ein Datum wäre die schwächere Lösung — es geht entweder zu früh los oder zu
spät, und in beiden Fällen liegt es an einer Schätzung von heute.

### Wo die Liste lebt
Sie ist bereits Teil des Aufrufs aus `006-005-0001`. Zu entscheiden und zu begründen ist, ob sie
dort bleibt oder in eine eigene Datei wandert, die beide Schritte lesen — Gate und Prüfung. Eine
Liste, die an zwei Stellen gepflegt werden muss, läuft auseinander; das ist derselbe Grund, aus
dem `006-004-0001` die Einordnungsregel **nicht** nach `architecture.md` kopiert hat.

`composer audit --format=json` liefert die gemeldeten Kennungen maschinenlesbar — dieselbe
Quelle, aus der der Ist-Stand in `006-005-0001` erhoben wurde.

## Abgrenzung
Keine Änderung am Gate selbst. Keine Ausnahme hinzufügen oder entfernen — die fünf stehen fest,
bis Epic `009` sie auflöst.

## Acceptance criteria
- [x] Eine ausgenommene Kennung, die nicht mehr gemeldet wird, lässt den Lauf fehlschlagen und
      nennt sie beim Namen.
- [x] Die Meldung sagt, was zu tun ist — Kennung streichen —, nicht nur, dass etwas nicht stimmt.
- [x] Die Ausnahmeliste steht an **einer** Stelle; wo sie liegt, ist begründet.
- [x] Auf dem Ist-Stand ist die Prüfung grün: Alle fünf Ausnahmen greifen noch.
- [x] Der Schritt läuft ohne `vendor/`, wie das Gate selbst.

## Verification
In beide Richtungen belegt, nach derselben Regel wie die Versandfalle aus `008-004-0004` und die
Überschneidungsprüfung aus `006-004-0003`:

1. **Sie schlägt an.** Eine sechste, frei erfundene Kennung in die Ausnahmeliste eintragen — sie
   wird von `composer audit` nie gemeldet, ist also per Definition abgelaufen. Der Lauf muss rot
   werden und genau diese Kennung nennen. Danach entfernen.
2. **Sie winkt nicht durch.** Auf dem unveränderten Ist-Stand grün, und zwar als bestandene
   Prüfung — nicht, weil sie mangels Datenquelle nichts zu vergleichen hatte. Die zweite Hälfte
   ist die wichtigere: Findet der Schritt keine Meldungen, weil der Aufruf scheitert, sähe das
   aus wie Erfolg.

Gefahren wird lokal in Docker mit demselben Image wie die Pipeline.

## Ergebnis
**Die Ausnahmeliste kann nicht mehr einschlafen.**
`tools/ci/audit-ausnahmen-pruefen.sh` läuft im selben Job direkt hinter dem Gate und macht den
Lauf rot, sobald eine eingetragene Kennung nicht mehr gemeldet wird.

### Wie verglichen wird
`composer audit --format=json` trennt selbst zwischen `advisories` (nicht ausgenommen — das
fängt bereits `006-005-0001`) und `ignored-advisories` (ausgenommen, greift also noch). Der
Vergleich läuft gegen die zweite Liste:

| Fall | Ergebnis |
|---|---|
| Kennung in `composer.json`, aber **nicht** in `ignored-advisories` | abgelaufen → rot |
| Kennung in `advisories`, nicht ausgenommen | fängt das Gate selbst |

Eingesammelt werden **beide** Schreibweisen, die Composer je Meldung führt: die CVE-Kennung und
die composer-eigene `advisoryId` (`PKSA-…`). Damit darf die Ausnahmeliste jede von beiden
enthalten, ohne dass die Prüfung fälschlich anschlägt.

**Die Liste bleibt nur in `composer.json`.** Eine zweite Kopie im Skript müsste mitgepflegt
werden und liefe auseinander — derselbe Grund, aus dem `006-004-0001` die Einordnungsregel nicht
nach `architecture.md` kopiert hat.

**PHP statt `jq`** für den Vergleich: `jq` liegt in keinem der CI-Images, PHP per Definition in
jedem.

### Ablauf in der Sache, nicht im Kalender
Eine Ausnahme fällt, wenn ihr Grund wegfällt — nicht an einem Datum. Ein Ablaufdatum wäre die
schwächere Lösung: Es greift entweder zu früh (die CVE ist noch offen) oder zu spät (Epic `009`
kam schneller), und beides hängt an einer Schätzung von heute. Hebt Epic `009` Symfony auf 7.4,
verschwinden die fünf Meldungen, und derselbe Lauf, der sie überflüssig macht, fordert ihre
Streichung ein.

### Vier Richtungen, gemessen in `php:8.3-cli`
| Lauf | Exit | Beleg |
|---|---|---|
| Ist-Stand | **0** | `5 Ausnahme(n) eingetragen, 5 greifen noch.` |
| sechste, erfundene Kennung eingetragen | **1** | nennt `CVE-1999-00000` und sagt, dass sie zu streichen ist |
| `composer` durch eine Attrappe ersetzt, die nichts ausgibt | **1** | `✗ composer audit hat nichts geliefert.` |
| Composer 2.7.7 | **1** | `✗ Composer 2.7.7 ist zu alt für dieses Gate (nötig: >= 2.8.0)` |

Die dritte Zeile ist die wichtigste. Liefert der Aufruf nichts, sähe „keine abgelaufene
Ausnahme gefunden" aus wie Erfolg — genau der stille Durchwinker, gegen den `008-005-0002` den
Umgebungswächter gebaut hat. Die Prüfung bricht deshalb ab, statt eine leere Datenlage als
Ergebnis auszugeben.

### Eine Korrektur an `006-005-0001`
Beim Bauen dieser Prüfung ist aufgefallen, dass die Begründung der Versionsschranke aus dem
Vorgänger-Task **falsch war**. Sie lautete: `config.audit.ignore` gebe es erst ab Composer 2.7,
ältere Fassungen übergingen den Block stillschweigend.

Nachgemessen über vier Fassungen stimmt das nicht:

| Composer | `config.audit.ignore` | `--abandoned` |
|---|---|---|
| 2.6.6 | **beachtet** (`advisories: 0`) | fehlt |
| 2.7.0 | beachtet | fehlt |
| 2.7.7 | beachtet | fehlt |
| 2.8.0 | beachtet | **vorhanden** |

Die Schranke hängt an `--abandoned`, nicht an der Ausnahmeliste — und sie stand mit **2.7.0 zu
niedrig**: Eine 2.7.x wäre durch die Prüfung gekommen und danach an `--abandoned` gescheitert,
mit genau der Meldung, die die Schranke verhindern sollte.

Korrigiert auf **2.8.0**, samt Begründung im Skript und einer Korrekturnotiz im Ergebnis von
`006-005-0001`. Der Fehler kam daher, dass ich die Schranke aus dem Verhalten meines eigenen
Skripts abgelesen habe, statt Composer selbst zu befragen — die Absage im Testlauf stammte von
meiner Versionsprüfung, nicht von Composer.
