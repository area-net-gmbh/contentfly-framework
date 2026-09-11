---
id: 007-002-0000
title: Die Rector-Regel für das Entity-Verzeichnis
status: review
depends_on: []
---

# Die Rector-Regel für das Entity-Verzeichnis

## Goal
Ein Bestandsprojekt lässt **einen Lauf** über sein `Entity/`-Verzeichnis laufen und hat danach
PHP-Attribute statt `@ORM\*`-Annotationen, und keine der mit Epic `012` gestrichenen
`@PIM\*`-Annotationen und `@PIM\Config`-Felder mehr. Das ersetzt hunderte Handgriffe pro Projekt.

## Ausgangslage

Zwei Hälften, und beide sind vorbereitet:

- **`@ORM\*` → Attribute.** `rector/rector` liegt im Baum; eine `rector.php` gibt es nicht.
  Rector bringt die ORM-Umstellung als fertigen Satz mit. Epic `010` hat die Entities des
  Frameworks bereits umgestellt — was dabei nicht glatt lief, ist der Erfahrungswert, auf dem
  diese Story aufsetzt.
- **Die gestrichenen `@PIM\*` raus.** `an_project/docs/pim-annotationen-migration.md` listet sie
  vollständig: 7 entfallene Annotationen, 14 entfallene Felder von `@PIM\Config`, die entfallene
  Plugin-Schnittstelle und die `FRONTEND_*`-Konfiguration. Die Datei nennt sich selbst Grundlage
  dieser Regel. Dafür gibt es keinen fertigen Rector-Satz; das ist der eigene Anteil.

**Warum das nicht optional ist:** Ein stehengebliebenes Feld ist kein geduldetes Relikt. Der
`AnnotationReader` bricht schon beim Einlesen ab, wenn eine Annotation ein Feld trägt, das ihre
Klasse nicht kennt — das Projekt startet dann gar nicht.

## Abnahme

Die Regel läuft gegen einen Satz Beispiel-Entities, der jede der gelisteten Annotationen und
jedes gelistete Feld mindestens einmal enthält, und das Ergebnis wird geprüft — nicht nur, dass
der Lauf durchgeht. Was die Regel **nicht** kann, steht dabei: Eine Automatik, deren Grenzen
niemand kennt, ist gefährlicher als gar keine.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 007-002-0001 — Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen
- [x] 007-002-0002 — ORM-Annotationen zu Attributen
- [x] 007-002-0003 — Die PIM-Annotationen — sieben entfernen, die übrigen zu Attributen
- [x] 007-002-0004 — Die gestrichenen Felder aus gebliebenen Annotationen entfernen
- [x] 007-002-0005 — Die Grenzen benennen und den Aufruf dokumentieren

`0001` liefert den Prüfstein, ohne den kein folgender Task verifizierbar ist. `0002` und `0003`
hängen nur daran und sind untereinander unabhängig. `0004` braucht beide — es arbeitet auf dem,
was sie übrig lassen. `0005` sammelt ein.

## Ergebnis

**Ein Bestandsprojekt bringt sein `Entity/`-Verzeichnis mit einem Werkzeug auf den neuen
Stand.** `rector.php` trägt vier Regeln: den Doctrine-Satz für `@ORM\*`, zwei konfigurierte
für die `@PIM\*`-Annotationen, und eine eigene für die Felder. Der Aufruf, was sie abdeckt und
was von Hand bleibt, steht in `an_project/docs/pim-annotationen-migration.md`, Abschnitt 7.

**Der Zuschnitt war an zwei Stellen falsch, und beide Korrekturen sind Gewinn:**

- **Die Story erwartete den `@PIM`-Teil als Eigenbau.** Für das Entfernen einer *ganzen*
  Annotation gibt es eine fertige, konfigurierbare Regel. Der Eigenbau ist eine einzige Klasse
  für die *Felder* — und dort gibt es wirklich nichts.
- **Sie erwartete die gebliebenen Annotationen als unangetastet.** Sie müssen mit auf Attribute,
  sonst ist die Migration ein **stiller Ausfall**: Der Metadatenleser liest seit `010-001-0003`
  nur noch Attribute, und ein `@PIM\Config(excludeFromSync=true)` im Docblock wirkt danach
  nicht mehr — ohne Fehlermeldung. Aufgefallen an einem Testfehlschlag, nachgetragen in Titel
  und Kriterien von `0003`.

**Der Fund, der die Anleitung ändert: zwei Läufe sind nötig.** Die Feldregel sieht Attribute,
und die entstehen erst im selben Lauf. Wer einmal läuft, hat keinen halb migrierten Baum,
sondern einen **kaputten** — `Unknown named parameter`, ein Fatal Error beim Laden. Gemessen:
erster Lauf ändert, zweiter ändert noch, dritter findet nichts mehr. Die Abbruchbedingung steht
als *laufen, bis ein Trockenlauf nichts mehr meldet*.

**Belegt statt behauptet:**

| | |
|---|---|
| Prüfstein | 3 Entities im Altstand, von Hand geschriebener Sollzustand daneben |
| Abdeckung | alle 7 entfallenen Annotationen, alle 14 entfallenen und alle 10 gebliebenen `Config`-Felder, die reduzierten `Checkbox`/`Radio`-Felder, die verschachtelte `@ORM\JoinTable` |
| Alias | eine eigene Datei mit `Anders` und `Abbildung` statt `PIM` und `ORM` |
| Einlesbarkeit | `newInstance()` auf jedem Attribut des Ergebnisses — 44, keiner wirft |
| Gegenprobe | gegen die 22 bereits umgestellten Entities des Frameworks schlägt die Regel nichts vor |

**Zwölf eigene Fehlgriffe unterwegs, und sie haben ein Muster.** Neun davon waren Prüfungen,
die grün waren, **weil sie nichts sahen** — ein Suchausdruck, der durch falsches Escaping nie
traf; ein Filter, der genau die Zeilen entfernte, die er prüfen sollte; drei Prüfungen, die an
einem Alias hingen; ein `array_merge`, das bei Zeichenketten-Schlüsseln ersetzt statt anhängt;
und eine Exit-Code-Erwartung, die genau dann grün war, wenn die Regel *nicht* greift. Jeder
dieser Zustände wurde danach durch absichtliche Verletzung geprüft.

Die anderen drei waren Sachfehler: ein Prüfstein, der Felder erfand, die keine Annotationsklasse
je hatte (von PHPStan gefangen); ein Sollzustand, der `::class` aus einer falschen Analogie
schloss (die Regel hatte recht, die Vorlage nicht); und eine Zahl, die ich messen liess, bevor
ich den letzten Test hinzufügte.

**Zahlen:** Die Suite wächst von 495 auf **514** Tests. PHPStan `[OK] No errors`, 0 Deprecations
bei 0 Ausnahmen, 0 Byte Postausgang.

**Ein Preis, der genannt gehört:** Die Suite braucht 49 s statt 35 s. Die Differenz sind die
Rector-Läufe, jeder ein eigener Prozess. Für heute vertretbar; wächst es weiter, gehören diese
Tests in eine eigene Suite, und das wäre eine eigene Entscheidung.
