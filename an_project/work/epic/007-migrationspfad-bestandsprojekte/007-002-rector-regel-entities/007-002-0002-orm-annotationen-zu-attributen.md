---
id: 007-002-0002
title: ORM-Annotationen zu Attributen
status: todo
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
- [ ] `rector.php` trägt den ORM-Teil, mit einem Kommentar, welcher Satz benutzt wird und warum dieser.
- [ ] Der Prüfstein aus `007-002-0001` kommt danach ohne eine einzige `@ORM\*`-Annotation heraus, und der Vergleich mit dem Sollzustand stimmt.
- [ ] Verschachtelte Annotationen sind ausdrücklich geprüft — und wenn die Regel sie nicht kann, steht das als Grenze da, statt stillschweigend zu misslingen.
- [ ] Kein `@PIM\*` wird dabei angefasst; das ist Sache der beiden folgenden Tasks.
- [ ] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand. Zusätzlich: Ein Lauf gegen eine Kopie
von `lib/contentfly/Entity/` muss **nichts** vorschlagen — die Entities sind schon umgestellt,
und ein Vorschlag dort wäre ein Zeichen, dass die Regel mehr tut als sie soll.
