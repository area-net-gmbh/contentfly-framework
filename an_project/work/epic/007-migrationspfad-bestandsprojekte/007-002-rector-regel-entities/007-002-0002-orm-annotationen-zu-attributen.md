---
id: 007-002-0002
title: ORM-Annotationen zu Attributen
status: review
depends_on: [007-002-0001]
---

# ORM-Annotationen zu Attributen

## Context
Die erste und grösste Hälfte, und die einzige, für die es **fertige Regeln** gibt.

**Nachgesehen am 2026-09-11:** `rector/rector` 1.2.10 bringt `rector-doctrine` in seinem eigenen
Vendor mit — `DoctrineSetList` liegt unter
`vendor/rector/rector/vendor/rector/rector-doctrine/src/Set/`. Es braucht **kein zusätzliches
Paket**. Dazu kommt die generische `AnnotationToAttributeRector` aus `rules/Php80/`, die eine
benannte Annotation in ihr Attribut überführt.

**Der Erfahrungswert liegt vor.** Epic `010` hat die Entities des Frameworks umgestellt — von
Annotationen auf Attribute, mitsamt dem, was dabei nicht glatt lief. Was dort auffiel, gehört
hier hineingelesen, statt es ein zweites Mal zu entdecken.

**Worauf besonders zu achten ist:** Verschachtelte Annotationen (`@ORM\JoinTable` mit
`joinColumns={@ORM\JoinColumn(…)}`) sind der Fall, an dem eine Umstellung erfahrungsgemäss
scheitert. Rector hat dafür `NestedAnnotationToAttributeRector` — ob sie greift, ist zu messen
und nicht anzunehmen.

## Acceptance criteria
- [x] `rector.php` trägt den ORM-Teil, mit einem Kommentar, welcher Satz benutzt wird und warum dieser.
- [x] Der Prüfstein aus `007-002-0001` kommt danach ohne eine einzige `@ORM\*`-Annotation heraus, und der Vergleich mit dem Sollzustand stimmt.
- [x] Verschachtelte Annotationen sind ausdrücklich geprüft — und wenn die Regel sie nicht kann, steht das als Grenze da, statt stillschweigend zu misslingen.
- [x] Kein `@PIM\*` wird dabei angefasst; das ist Sache der beiden folgenden Tasks.
- [x] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand. Zusätzlich: Ein Lauf gegen eine Kopie
von `lib/contentfly/Entity/` muss **nichts** vorschlagen — die Entities sind schon umgestellt,
und ein Vorschlag dort wäre ein Zeichen, dass die Regel mehr tut als sie soll.

## Ergebnis

**`DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES` steht in `rector.php`, und es reicht.** Nach dem
Lauf trägt der Prüfstein keine `@ORM\*`-Annotation mehr; die angewandten Regeln melden sich als
`AnnotationToAttributeRector` und `NestedAnnotationToAttributeRector`.

**Kein zusätzliches Paket.** `rector/rector` 1.2.10 liefert `rector-doctrine` in seinem eigenen
Vendor mit — ein `composer require rector/rector-doctrine` wäre nicht nur unnötig, es holte eine
zweite Fassung derselben Regeln in den Baum.

**Die anderen Sets bleiben draussen, und das steht im Kopf der Datei:**
`DOCTRINE_CODE_QUALITY` und `TYPED_COLLECTIONS` ändern Code, nicht Mapping. Das gehört einem
Projekt und nicht einer Migrationsregel.

## Der verschachtelte Fall, gemessen statt angenommen

Der Task hat ihn als Messung verlangt, weil Umstellungen erfahrungsgemäss genau dort scheitern.
Er scheitert nicht:

```
- * @ORM\JoinTable(
- *     name="fixture_rubrik_artikel",
- *     joinColumns={@ORM\JoinColumn(name="rubrik_id", referencedColumnName="id")},
- *     inverseJoinColumns={@ORM\JoinColumn(name="artikel_id", referencedColumnName="id")}
- * )
+ #[ORM\JoinTable(name: 'fixture_rubrik_artikel')]
+ #[ORM\JoinColumn(name: 'rubrik_id', referencedColumnName: 'id')]
+ #[ORM\InverseJoinColumn(name: 'artikel_id', referencedColumnName: 'id')]
```

Drei eigenständige Attribute, und `inverseJoinColumns` wird richtig zu `InverseJoinColumn` —
genau das, was der von Hand geschriebene Sollzustand vorhergesagt hatte.

**Kein `@PIM\*` wurde angefasst.** Die Regel geht an allen sieben gestrichenen und allen
gebliebenen PIM-Annotationen vorbei; das ist Sache der beiden folgenden Tasks.

## Eine Falle, und sie zeigt in die verkehrte Richtung

**Rector beendet einen `--dry-run` mit Exit 2, wenn er Änderungen gefunden hat, und mit 0, wenn
nichts zu tun war.** Mein erster Test schrieb `assertSame(0, …)` — er wäre also **genau dann
grün gewesen, wenn die Regel nicht greift**. Gefunden, weil der Lauf mit eingetragener Regel
sofort rot wurde; ohne die vorherige Fassung, die noch keine Regel hatte, wäre es nicht
aufgefallen.

Beide Richtungen sind jetzt festgehalten, mit dem Grund im Kommentar: Der Trockenlauf gegen den
Prüfstein **muss** 2 liefern, der Trockenlauf gegen die bereits umgestellten Entities **muss** 0
liefern.

## Der umgedrehte Test

`testDerLaufGehtDurch` hielt mit `0001` fest, dass Rector „Register rules or sets" warnt. Mit der
ersten eingetragenen Regel ist diese Zusicherung gegenstandslos — und eine Zusicherung, die
nicht mehr stimmt, ist ein Defekt, kein Altbestand. Der Test ist **umgedreht**: Rector muss jetzt
etwas vorschlagen, und die Warnung darf nicht mehr kommen.

## Die Gegenprobe, die ein Projekt vor Schaden bewahrt

`testGegenUmgestellteEntitiesTutDieRegelNichts` fährt die Regel gegen
`lib/contentfly/Entity/` — 22 Entities, seit Epic `010` auf Attributen. Sie schlägt dort
**nichts** vor. Ohne diese Prüfung wäre nicht ausgeschlossen, dass die Regel mehr tut als sie
soll; ein Projekt, das sie zweimal laufen liesse, bekäme beim zweiten Mal Schaden.

**Zahlen:** Volle Suite `OK (506 tests, 1320 assertions)` (vorher 503), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Der Prüfstein selbst ist nach allen
Läufen unverändert — die Tests arbeiten auf Kopien.
