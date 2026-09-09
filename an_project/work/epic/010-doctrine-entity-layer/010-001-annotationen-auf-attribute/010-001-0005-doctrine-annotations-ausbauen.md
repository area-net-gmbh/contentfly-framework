---
id: 010-001-0005
title: doctrine/annotations aus dem Baum nehmen
status: todo
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
- [ ] `doctrine/annotations` steht nicht mehr in `composer.json`, und `composer.lock` bestätigt es.
- [ ] Weder `AnnotationRegistry` noch `AnnotationReader` kommen im eigenen Code noch vor — ausgenommen Erklärungen, die den abgelösten Weg beschreiben.
- [ ] Die zwei gegenstandslos gewordenen PHPStan-Muster sind entfernt, und der Lauf ist `[OK] No errors`.
- [ ] Über `Type::getAnnotationFile()` ist entschieden und begründet; fällt sie weg, steht sie als Bruchstelle in `an_project/docs/breaking-changes.md`.
- [ ] `composer audit` meldet ein abandoned Paket weniger — nur noch `doctrine/cache`.
- [ ] Die volle Suite ist grün und die Console startet; `appcms:install` läuft auf einer frischen Datenbank durch.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, die volle Suite und ein
vollständiger Durchlauf: frische Datenbank, `appcms:install`, Testserver, Suite. Dazu ein `grep`
über `lib`, `custom`, `bin` und `tests` als Nachweis, dass nichts übrig ist.
