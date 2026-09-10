---
id: 010-001-0005
title: doctrine/annotations aus dem Baum nehmen
status: done
depends_on: [010-001-0004]
---

# doctrine/annotations aus dem Baum nehmen

## Context
Nach `0003` und `0004` liest niemand mehr Annotationen. Was bleibt, ist die Mechanik drumherum
— und die ist ersatzlos zu entfernen, nicht stehenzulassen:

- **`AnnotationRegistry`** im Bootstrap (`registerLoader('class_exists')`) und im `TypeManager`
  (drei `registerFile()`-Aufrufe).
- **`doctrine/annotations`** aus `composer.json`. Damit fällt eines der beiden abandoned Pakete,
  die Epic `009` übergeben hat.
- **Zwei PHPStan-Muster** aus `phpstan.neon.dist` — die für `registerFile`/`registerLoader` und
  die für `newDefaultAnnotationDriver()`. **Regel 3 gilt:** Ein Muster, das nichts mehr trifft,
  macht den Lauf rot. Sie müssen also weg, sonst bleibt die Pipeline stehen.

**Eine Entscheidung über die öffentliche Oberfläche liegt hier:** `Type::getAnnotationFile()`
existiert allein, um der `AnnotationRegistry` einen Dateipfad zu nennen. Ohne Registry hat die
Methode keinen Zweck mehr. 21 Type-Klassen deklarieren sie, fünf liefern einen Namen. Sie ist
aber Teil der Klasse `Type`, von der Projekte über `CustomType` ableiten — sie zu streichen ist
eine Bruchstelle und gehört nach `breaking-changes.md`.

## Acceptance criteria
- [x] `doctrine/annotations` steht nicht mehr in `composer.json`, und `composer.lock` bestätigt es.
- [x] Weder `AnnotationRegistry` noch `AnnotationReader` kommen im eigenen Code noch vor — ausgenommen Erklärungen, die den abgelösten Weg beschreiben.
- [x] Die zwei gegenstandslos gewordenen PHPStan-Muster sind entfernt, und der Lauf ist `[OK] No errors`.
- [x] Über `Type::getAnnotationFile()` ist entschieden und begründet; fällt sie weg, steht sie als Bruchstelle in `an_project/docs/breaking-changes.md`.
- [x] `composer audit` meldet ein abandoned Paket weniger — nur noch `doctrine/cache`.
- [x] Die volle Suite ist grün und die Console startet; `appcms:install` läuft auf einer frischen Datenbank durch.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, die volle Suite und ein
vollständiger Durchlauf: frische Datenbank, `appcms:install`, Testserver, Suite. Dazu ein `grep`
über `lib`, `custom`, `bin` und `tests` als Nachweis, dass nichts übrig ist.

## Ergebnis

**`doctrine/annotations` ist aus dem Baum.** 42 Pakete auf 41, `composer audit` meldet nur noch
**ein** abandoned Paket statt zweier — `doctrine/cache`, und das fällt mit `010-002`.

Die drei verbliebenen Nennungen in `composer.lock` sind `conflict`- und
`require-dev`-Angaben **fremder** Pakete, kein installiertes Paket. Nachgezählt, nicht überflogen.

### Ein Nebeneffekt, der nicht im Task stand

`doctrine/lexer` ist von **2.1.1 auf 3.0.1** gesprungen. `doctrine/annotations` hatte ihn auf
2.x gehalten; ohne es löst Composer die Obergrenze des ORM auf. Das ist kein zweiter Umbau,
sondern der Wegfall einer Klammer — die Suite ist grün, und die eigenen DQL-Funktionen unter
`Classes/ORM/Query` und `Classes/ORM/Spatial` benutzen `Lexer::T_*`, was `010-003` ohnehin auf
`TokenType` ziehen muss.

### Entschieden: `Type::getAnnotationFile()` fällt weg

Die Methode nannte dem `DocParser` den Dateipfad einer Annotationsklasse. Ohne Registry hat sie
keinen Gegenstand. Sie stand als **abstrakte** Methode in `Classes/Type.php`, war also in allen
21 `Type`-Klassen zu implementieren — fünf lieferten einen Namen, sechzehn `null`.

**Sie zu entfernen bricht nichts beim Laden**, und das ist der Kern der Entscheidung: Eine
abstrakte Methode zu **entfernen** ist für Ableitungen harmlos; ein eigener `CustomType`, der
sie implementiert, behält sie als zusätzliche, nie gerufene Methode. Umgekehrt wäre es ein
Bruch. Was verloren geht, ist die **Wirkung** — eine eigene Attributklasse muss autoladbar
sein. Unter `Custom\` (auf `custom/`) und `Plugins\` (auf `plugins/`) ist sie das, geprüft im
`autoload`-Block des Manifests. Als Bruchstelle in `breaking-changes.md`, zusammen mit den
beiden anderen aus dieser Story.

### Regel 3, zum zweiten Mal in dieser Story

Das Muster für `registerFile|registerLoader` traf nach dem Ausbau nichts mehr und ist
gestrichen. Damit ist der **ganze** `doctrine/annotations`-Block aus `phpstan.neon.dist`
verschwunden; übrig bleiben die Muster für `doctrine/cache` und `doctrine/orm`.

### Ein Test umgedreht, mit Begründung

`TypeManagerTest::testEinTypOhneAnnotationsdateiWirdOhneRegistrierungAbgelegt` sicherte zu, dass
`getAnnotationFile() === null` **kein** `registerFile()` auslöst. Der Gegenstand ist weg; der
Test heisst jetzt `testEinTypBrauchtKeineAnnotationsdateiMehr` und prüft, dass die Methode nicht
mehr existiert und das Ablegen trotzdem funktioniert.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `doctrine/annotations` in `composer.json` | 0 Treffer |
| als installiertes Paket im Lock | nein; 42 → 41 Pakete |
| `AnnotationRegistry`/`AnnotationReader` im eigenen Code | 0 Codestellen (nur noch Erklärungen, die den abgelösten Weg beschreiben) |
| `composer audit --locked` | keine Advisories, **1** abandoned statt 2 |
| PHPStan | `[OK] No errors` |
| Volle Suite | `OK (267 tests, 640 assertions)`, 0 übersprungen |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| Console ohne Installation | startet, listet die 4 Basis-Commands |
| `appcms:install` auf frischer Datenbank | läuft durch; danach 21 Commands, Doctrine eingeschlossen |

**Nicht behoben und älter als diese Story:** `orm:validate-schema` meldet weiterhin, dass Schema
und Mapping nicht deckungsgleich sind — der fehlende `modified_index` und die ungültige
Zuordnung in `BaseI18nTree`. Beide sind aus `009-005-0003` mit benanntem Auflöser hierher
übergeben und gehören `010-005`.
