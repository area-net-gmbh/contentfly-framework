---
id: 007-002-0004
title: Die gestrichenen Felder aus gebliebenen Annotationen entfernen
status: review
depends_on: [007-002-0002, 007-002-0003]
---

# Die gestrichenen Felder aus gebliebenen Annotationen entfernen

## Context
**Das ist der eigene Anteil dieser Story — und er ist schmaler und anders gelegen, als die Story
annahm.** Für das Entfernen einer ganzen Annotation gibt es eine fertige Regel (siehe
`007-002-0003`). Für das Entfernen eines **Feldes aus einer Annotation, die bleibt**, gibt es
keine. Nachgesehen am 2026-09-11 über alle konfigurierbaren Rector-Regeln.

**Betroffen sind drei Annotationen, die bleiben:**

| Annotation | entfallene Felder |
|---|---|
| `@PIM\Config` | 14, Liste in `pim-annotationen-migration.md` Abschnitt 3 |
| `@PIM\Checkbox` | `horizontalAlignment`, `columns` |
| `@PIM\Radio` | `horizontalAlignment`, `columns`, `select` |

**Der Unterschied zu `007-002-0003` ist der Grund für den eigenen Task:** Dort wird eine Zeile
gelöscht. Hier muss aus `@PIM\Config(hidden=true, label="x", readonly=false)` genau
`label="x"` übrig bleiben — mit korrekter Syntax, auch wenn das erste oder letzte Feld fällt,
auch bei mehrzeiligen Annotationen, auch wenn danach kein Feld mehr übrig ist und die Annotation
ohne Klammern dastehen muss.

**Die Fälle, an denen so etwas scheitert, gehören in den Prüfstein:** ein einziges Feld, das
fällt · das erste von mehreren · das letzte von mehreren · eine mehrzeilige Annotation · eine, in
der ein entfallenes und ein bleibendes Feld nebeneinander stehen.

**Wenn eine eigene Rector-Regel dafür zu teuer ist, ist das ein zulässiges Ergebnis** — dann
steht es als Grenze da und der Leitfaden sagt, was von Hand zu tun bleibt. Eine Automatik, die
Annotationen halb kaputt zurücklässt, wäre schlimmer als keine.

## Acceptance criteria
- [x] Die 14 `Config`-Felder und die fünf `Checkbox`/`Radio`-Felder werden entfernt — oder es steht begründet da, dass und warum nicht.
- [x] Die fünf genannten Grenzfälle sind im Prüfstein und einzeln geprüft.
- [x] Eine Annotation, aus der das letzte Feld fällt, bleibt syntaktisch gültig.
- [x] Kein bleibendes Feld verschwindet — geprüft an den 10 Feldern, die `@PIM\Config` behält.
- [x] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand, Fall für Fall. Danach muss der
Prüfstein von Doctrine **einlesbar** sein — eine syntaktisch gültige Datei mit einer kaputten
Annotation fällt beim Vergleich nicht auf, beim `AnnotationReader` schon.

## Ergebnis

**Es gibt keine fertige Regel, und das ist nachgesehen, nicht vermutet.**
`RemoveAnnotationRector` nimmt eine ganze Annotation, `ArgumentRemoverRector` arbeitet auf
Methodenaufrufen (`getNodeTypes()` nennt `MethodCall`, `StaticCall`, `ClassMethod`). Über alle
konfigurierbaren Regeln hinweg findet sich nichts, was ein **Feld aus einem Attribut** nimmt.

**`Migration\EntfalleneAttributfelderRector` ist die einzige eigene Regel dieser Story.** Sie
läuft auf `Class_` und `Property`, filtert die benannten Argumente eines Attributs und gibt den
Knoten nur zurück, wenn sich etwas geändert hat.

**Der Name kommt vollqualifiziert — gemessen, nicht angenommen.** Eine Sondierungsregel hat
`$attribut->name->toString()` ausgegeben: `Areanet\PIM\Classes\Annotations\Config`, auch wenn
die Datei `Anders\Config` schreibt. Damit hängt die Regel nicht am Alias, genauso wie die
beiden aus `007-002-0003`.

**Positionsgebundene Argumente bleiben unberührt.** Ein Argument ohne Namen zu entfernen
verschöbe die übrigen. Der Fall kommt bei den PIM-Annotationen nicht vor, aber eine Regel, die
ihn stillschweigend falsch behandelte, wäre eine Falle für den nächsten.

## Der Fund, der die Anleitung ändert: zwei Läufe sind nötig

**Die Regel griff im ersten Anlauf nicht.** Sie sieht Attribute — und die entstehen erst, wenn
`AnnotationToAttributeRector` im **selben** Lauf die Annotation umgeschrieben hat. Ein
Rector-Durchgang wendet die Regeln auf den Baum an, den er vorgefunden hat.

**Und das ist keine Unbequemlichkeit, sondern eine Falle.** Wer nur einmal läuft, hat keinen
halb migrierten Baum, sondern einen **kaputten**: Dort steht dann
`#[PIM\Config(label: 'Artikel')]`, und `Config::__construct()` hat kein `$label` —
„Unknown named parameter $label", ein Fatal Error beim Laden der Entity.

**Gemessen am Prüfstein:** erster Lauf ändert, zweiter Lauf ändert noch, dritter findet nichts
mehr. Die Abbruchbedingung steht deshalb nicht als „zweimal" im Kopf von `rector.php`, sondern
als **laufen, bis ein Trockenlauf nichts mehr meldet** — und `testZweiLaeufeSindNoetigUndDerDritteFindetNichtsMehr()`
hält den Fixpunkt als Zusicherung fest.

## Die fünf Grenzfälle, einzeln geprüft

| Fall | Ergebnis |
|---|---|
| ein einziges Feld, und es fällt | `#[PIM\Config]` — **ohne Klammern**, syntaktisch gültig |
| das erste von mehreren | `hide` weg, `isFilterable` bleibt |
| das letzte von mehreren | `readonly` weg, `encoded` bleibt |
| mehrzeilig | `viewMode` und `sort` weg, `labelProperty` und `unique` bleiben |
| entfallen und bleibend nebeneinander | beides richtig getrennt |

**Und die Gegenprobe, die wichtiger ist:** Alle zehn gebliebenen `Config`-Felder stehen nach dem
Lauf noch da, und `group` steht an `Checkbox` **und** `Radio`. Eine Regel, die zu viel entfernt,
fällt beim Test auf das Entfernen nicht auf.

## Einlesbar, und zwar wirklich

`testJedesAttributDesErgebnissesLaesstSichInstanziieren()` ruft `newInstance()` auf **jedem**
Attribut des Ergebnisses: **44 Attribute, 0 Fehler.** Das prüft etwas, das kein Textvergleich
prüfen kann — ein Attribut mit einem unbekannten Feld ist syntaktisch einwandfrei und wirft erst
beim Laden. PHPStan deckt die andere Seite ab: Es prüft den **Sollzustand** gegen die
Konstruktoren, dieser Test prüft, was die **Regel** erzeugt.

## Eine Korrektur am Sollzustand, nicht an der Regel

`#[PIM\Virtualjoin(targetEntity: …)]` bleibt eine **Zeichenkette** und wird nicht zu `::class`.
Mein handgeschriebener Sollzustand hatte `::class` aus der Analogie zu `@ORM\ManyToMany`
geschlossen, wo der Doctrine-Satz das Feld als Klassenverweis kennt. Nachgesehen in
`Classes/Types/VirtualjoinType`: Der Wert landet als `$schema['accept']` direkt im Schema, also
als Zeichenkette. Beide Formen ergäben zur Laufzeit denselben Wert — **die Regel hatte recht,
die Vorlage nicht**, und die Vorlage ist korrigiert.

## Der Vergleich mit dem Sollzustand

`testDasErgebnisEntsprichtDemSollzustand()` vergleicht **je Element**, nicht je Zeile: Der
Schlüssel ist der Name der Klasse oder Eigenschaft, die Attribute daran werden sortiert. Die
Reihenfolge der Attribute trägt keine Aussage — Doctrine liest sie als Liste, und Rector setzt
`#[ORM\Table]` vor `#[ORM\Entity]`, wo die Vorlage es umgekehrt hat. Ein Vergleich, der darauf
besteht, wäre rot wegen einer Nichtaussage. Dass ein Attribut am **richtigen** Element hängt,
prüft er trotzdem.

## Wo die Regel liegt, und warum

Im **Paket**, unter `lib/contentfly/Migration/`: Das Framework schuldet seinen Benutzern den
Migrationsweg, und ein Projekt bekommt die Regel damit mit `composer require`. Sie wird
ausschliesslich von einer `rector.php` geladen und nie zur Laufzeit — deshalb steht
`rector/rector` im `suggest` des Pakets und nicht im `require`, mit derselben Begründung wie
`symfony/ldap` seit `013-005-0004`.

**Zahlen:** Volle Suite `OK (514 tests, 1610 assertions)` (vorher 509), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang.

> **Richtiggestellt mit `007-002-0005`:** Hier stand `513 tests, 1609 assertions`. Ich hatte
> gemessen, *bevor* ich `testJedesAttributDesErgebnissesLaesstSichInstanziieren()` ergänzt habe
> — der Test gehört zu diesem Task, die Zahl war also um eins zu niedrig. Eine Zahl, die nicht
> mehr stimmt, ist derselbe Defekt wie ein Kommentar, der nicht mehr stimmt. PHPStan `[OK] No errors`. `composer validate` auf dem
Paket-Manifest.

**Ein Preis, der genannt gehört:** Die Suite braucht jetzt 55 s statt 35 s. Die zwanzig Sekunden
sind die Rector-Läufe in diesem Testfall — jeder startet einen eigenen Prozess. Für heute ist
das vertretbar; wächst es weiter, gehören diese Tests in eine eigene Suite, und das wäre eine
eigene Entscheidung.
