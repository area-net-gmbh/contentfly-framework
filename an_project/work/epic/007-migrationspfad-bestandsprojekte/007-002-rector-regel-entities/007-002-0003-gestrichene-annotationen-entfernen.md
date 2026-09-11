---
id: 007-002-0003
title: Die sieben gestrichenen PIM-Annotationen entfernen
status: todo
depends_on: [007-002-0001]
---

# Die sieben gestrichenen PIM-Annotationen entfernen

## Context
Mit Epic `012` ist der Teil der `@PIM`-Annotationen weggefallen, der Eingabemasken beschrieb.
Sieben Annotationen sind ersatzlos zu löschen; die Liste steht vollständig in
`an_project/docs/pim-annotationen-migration.md`, Abschnitt 1:

`@PIM\Rte` · `@PIM\Textarea` · `@PIM\Datetime` · `@PIM\Time` · `@PIM\Password` ·
`@PIM\MatrixChooser` · `@PIM\EntitySelector`

**Warum das nicht optional ist:** Ein stehengebliebenes Feld ist kein geduldetes Relikt.
`Doctrine\Common\Annotations\Annotation::__get()` wirft eine `BadMethodCallException`, und der
`AnnotationReader` bricht schon beim Einlesen ab. Das Projekt startet dann gar nicht.

**Die Story hat hier zu viel eigenen Anteil erwartet.** Sie sagte, für den `@PIM`-Teil gebe es
keinen fertigen Rector-Satz. Für das Entfernen einer **ganzen Annotation** gibt es einen:
`RemoveAnnotationRector` aus `rules/DeadCode/Rector/ClassLike/` ist konfigurierbar und nimmt die
Namen entgegen. Nachgesehen am 2026-09-11. Der eigene Anteil liegt bei den **Feldern** und damit
in `007-002-0004`.

**Mitentfernt gehören drei Type-Klassen** aus der Konfiguration: `RteType`, `PasswordType`,
`EntitySelectorType`. Ein Projekt, das eine davon in `APP_SYSTEM_TYPES` oder `APP_CUSTOM_TYPES`
aufführt, bricht beim Start mit `contentfly_type_class_not_found`. **Das ist keine
Entity-Änderung** und deshalb hier nur zu benennen, nicht zu automatisieren — die Regel läuft
über `Entity/`, nicht über die Konfiguration.

## Acceptance criteria
- [ ] `rector.php` entfernt die sieben Annotationen, konfiguriert aus der Liste und nicht aus dem Gedächtnis.
- [ ] Der Prüfstein kommt ohne sie heraus, und die **fünf gebliebenen** Annotationen sind unangetastet — beides geprüft, nicht nur das erste.
- [ ] Dass die drei Type-Klassen aus der Konfiguration zu streichen sind, steht dort, wo der Aufruf beschrieben ist; es wird nicht automatisiert und der Grund dafür steht dabei.
- [ ] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand. Der Gegentest ist der wichtigere:
Die gebliebenen Annotationen müssen Zeichen für Zeichen dieselben sein.
