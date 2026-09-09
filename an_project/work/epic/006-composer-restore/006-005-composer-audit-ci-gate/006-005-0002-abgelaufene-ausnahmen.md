---
id: 006-005-0002
title: Eine Ausnahme, die nicht mehr greift, macht den Lauf rot
status: todo
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
- [ ] Eine ausgenommene Kennung, die nicht mehr gemeldet wird, lässt den Lauf fehlschlagen und
      nennt sie beim Namen.
- [ ] Die Meldung sagt, was zu tun ist — Kennung streichen —, nicht nur, dass etwas nicht stimmt.
- [ ] Die Ausnahmeliste steht an **einer** Stelle; wo sie liegt, ist begründet.
- [ ] Auf dem Ist-Stand ist die Prüfung grün: Alle fünf Ausnahmen greifen noch.
- [ ] Der Schritt läuft ohne `vendor/`, wie das Gate selbst.

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
