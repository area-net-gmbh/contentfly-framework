---
id: 000-000-0114
title: Base::setCreated(null) und setModified(null) sind stillschweigend wirkungslos — ein Leeren über die API fällt aus
status: todo
depends_on: []
---

# Base::setCreated(null) und setModified(null) sind stillschweigend wirkungslos — ein Leeren über die API fällt aus

## Context

Gefunden am 2026-11-27 beim Umsetzen von `016-001-0001`, und zwar auf dem unangenehmen Weg: Der
erste Testansatz dort wollte ein `datetime`-Feld leeren, um den Zweig für ungültige Datumswerte
zu erreichen, und **hat stattdessen „jetzt" gemessen und es leer genannt.**

`Areanet\PIM\Entity\Base::setCreated()` und `setModified()` haben beide diese Form:

```php
public function setCreated($created): void
{
    if($created instanceof \Datetime) {
        $this->created = $created;
    } else if($created !== null) {
        $this->created = new \Datetime($created);
    }
}
```

Ein Zweig für `\DateTime`, ein Zweig für alles andere **das nicht `null` ist** — und **keiner für
`null` selbst.** Ein `setCreated(null)` fällt damit durch beide Zweige hindurch und tut nichts.
Das Feld behält, was der Konstruktor hineingeschrieben hat: `new \DateTime()`
(`lib/contentfly/Entity/Base.php:76-80`).

**Das ist kein theoretischer Pfad.** `DatetimeType::toDatabase()` ruft im Leer-Zweig
ausdrücklich `$object->$setter(null)`, um ein Feld zu löschen — das ist der Weg, den der Type für
„Wert weg" vorsieht. Für `created` und `modified` tut dieser Aufruf nichts, und zwar ohne jede
Meldung. Beide Spalten sind `nullable: true`
(`lib/contentfly/Entity/Base.php:37`, `:43`), der leere Zustand existiert in der Datenbank also
— er ist über den Setter nur nicht herstellbar.

**Warum das bisher niemandem auffiel:** Es gibt keinen Test, der ein `datetime`-Feld über die API
leert. `016-001-0001` hat den Mangel umgangen statt behoben — sein Test setzt das Feld per
Reflection, mit der Begründung im Code
(`tests/Unit/Types/DatetimeTypeTest.php`, Helfer `readEmpty()`). Diese Umgehung ist der Grund,
warum es diesen Task gibt: Ein Test, der um einen Produktivcode-Mangel herumarbeitet, ist ein
Hinweis, kein Abschluss.

**Die acht übrigen Setter in `Base` tragen das Muster nicht** — nachgesehen am 2026-11-27 über
`setId`, `setUser`, `setUserCreated`, `setViews`, `setDisableModifiedTime`, `setIsIntern`,
`setUsers`, `setGroups`. Das ist ein Zwischenstand, keine Zusage: Teil dieses Tasks ist, den
Umfang zu belegen (siehe unten).

## Was zu klären ist, bevor etwas geändert wird

Die naheliegende Korrektur — ein `else`-Zweig, der `null` zuweist — ist **nicht ohne Weiteres
richtig**, und das ist der Kern dieses Tasks:

1. **Verlässt sich etwas darauf, dass `null` verschluckt wird?** `created` und `modified` sind
   universelle Felder, die das Framework selbst pflegt. Wenn irgendein Schreibweg
   `setModified(null)` aufruft und sich darauf verlässt, dass der Zeitstempel dabei **erhalten**
   bleibt, dreht ein `else`-Zweig dieses Verhalten. Zu prüfen sind insbesondere
   `DatetimeType::toDatabase()`, die Schreibwege in `Areanet\PIM\Classes\Api` und
   `$disableModifiedTime`.
2. **Soll ein universelles Feld überhaupt leerbar sein?** `created`/`modified` sind nicht
   fachliche Daten, sondern Buchführung. Es ist vertretbar, dass sie *nicht* geleert werden
   können — dann ist die Behebung **nicht** der `else`-Zweig, sondern ein **lautes Ablehnen**
   statt des stillen Verschluckens. Still ist die eine Variante, die sicher falsch ist.
3. **Gilt die Antwort für alle `datetime`-Felder oder nur für diese zwei?** Ein Projekt-Entity mit
   eigenem `datetime`-Feld bringt seinen eigenen Setter mit. Wenn `DatetimeType::toDatabase()`
   mit `null` löschen soll, muss die Erwartung an einen Setter irgendwo stehen — sonst hat jedes
   Projekt dieselbe Falle neu.
4. **Ist die `nullable: true`-Zusage im Mapping überhaupt gewollt?** Beide Spalten tragen
   zusätzlich `options: ['default' => 'CURRENT_TIMESTAMP']`. Wenn der leere Zustand fachlich nie
   vorkommen soll, ist das Mapping die zu korrigierende Stelle und nicht der Setter.

Diese vier Punkte sind zu beantworten und das Ergebnis festzuhalten — Punkt 2 ist eine
Verhaltensentscheidung und gehört, wenn sie bindend wird, als ADR nach
`an_project/docs/adr/`.

## Acceptance criteria

- [ ] Beantwortet und im Task festgehalten: ob ein Schreibweg sich heute darauf verlässt, dass
      `setCreated(null)`/`setModified(null)` nichts tut — **mit der Codestelle**, nicht als
      Vermutung. Findet sich keiner, ist auch das belegt (wonach gesucht wurde).
- [ ] Entschieden und begründet, welches der beiden Verhalten gilt: `null` zuweisen, oder das
      Leeren **laut** ablehnen. Das stille Verschlucken entfällt in jedem Fall.
- [ ] Umgesetzt für `setCreated` **und** `setModified` — sie tragen dieselbe Form und dürfen
      nicht auseinanderlaufen.
- [ ] Der Umfang ist belegt: ob ausser diesen zwei Settern weitere im Baum `null` still
      verschlucken (`lib/`, `custom/`, `plugins/`). Treffer werden benannt — mitgezogen nur,
      wenn sie zur Entscheidung passen, sonst mit eigenem Task.
- [ ] Festgehalten, was `DatetimeType::toDatabase()` von einem Setter erwarten darf, und wo diese
      Erwartung für Projekt-Entities nachlesbar ist (`dev-guide.md` oder
      `pim-annotationen-migration.md`) — sonst tritt jedes Projekt in dieselbe Falle.
- [ ] Ein Test deckt das Leeren eines `datetime`-Feldes **über den Setter** ab und fällt gegen den
      heutigen Stand rot. **Dazu:** die Reflection-Umgehung in
      `tests/Unit/Types/DatetimeTypeTest.php::readEmpty()` ist zu prüfen — wird das Feld über den
      Setter leerbar, gehört der Helfer auf den Setter umgestellt und der erklärende Kommentar
      dort angepasst. Bleibt es bei einem lauten Ablehnen, bleibt die Umgehung richtig und der
      Kommentar ist auf die neue Begründung zu ziehen.
- [ ] Entschieden, ob die Verhaltensänderung nach `breaking-changes.md` und in den Kopf von
      `migration.md` gehört. Ein Bestandsprojekt, dessen Code heute `setModified(null)` aufruft
      und sich auf das Verschlucken verlässt, merkt die Änderung sonst erst an den Daten.
- [ ] Suite und PHPStan grün.

## Verification

```sh
./vendor/bin/phpunit --testsuite unit            # muss immer grün sein
./vendor/bin/phpunit                             # beide Suiten
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Dazu die Gegenprobe: der neue Test gegen den heutigen Stand — rot, und zwar mit dem Zeitstempel
aus dem Konstruktor statt eines leeren Werts. Genau diese Verwechslung hat den Befund zutage
gebracht; ohne die Gegenprobe belegt der Test nicht, dass sie nicht mehr möglich ist.
