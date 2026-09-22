---
id: 000-000-0078
title: Übersetzung anlegen scheitert mit APP_LANGUAGES — EntityManager::clear() leert alles
status: review
depends_on: []
---

# Übersetzung anlegen scheitert mit APP_LANGUAGES — EntityManager::clear() leert alles

## Context
**Gefunden in `000-000-0075` (2026-09-22).** Ist `APP_LANGUAGES` gesetzt, endet jedes
`/api/insert` einer Übersetzung mit `id` in 500 — auch wenn es keinen Datensatz in der Hauptsprache
gibt. `doInsert()` liest die Hauptsprache über `getSingle(…, clearEM: true)`, und das ruft
`$this->em->clear($entityFullName)`: das teilweise Leeren aus ORM 2. Seit ORM 3 (Epic `010`) nimmt
`clear()` kein Argument mehr, PHP verwirft es stillschweigend, und der **ganze** EntityManager wird
geleert — samt angemeldetem Benutzer. Beim `flush()` gilt `userCreated` dann als neue Entity.

Folge: Kein Projekt mit Sprachen kann einem bestehenden Datensatz eine Übersetzung hinzufügen, und
die Übernahme der `i18n_universal`-Felder aus der Hauptsprache ist nicht erreichbar. `getSingle()`
mit `compareToLang` benutzt denselben Schalter.

Festgehalten in `MainLanguageApiTest::testAddingATranslationFailsWhileLanguagesAreConfigured()` und
`…testItFailsWithoutARecordInTheMainLanguageAsWell()`.

## Acceptance criteria
- [x] `clearEM` leert nur, was es leeren soll (z. B. die Objekte der einen Klasse per `detach`) oder entfällt, wenn der frische Lesevorgang anders sichergestellt ist.
- [x] Die beiden Tests in `MainLanguageApiTest` sind umgedreht: Die Übersetzung wird angelegt, und `code` kommt aus der Hauptsprache, wenn er nicht mitgeschickt wird.
- [x] Ein Test belegt, dass ein Join auf eine übersetzbare Entity, der aus der Hauptsprache übernommen wird, auf die Übersetzung in der neuen Sprache zeigt (`000-000-0025`) — dafür braucht `Core\ExampleI18n::$related` `i18n_universal`.
- [x] Nach weiteren `->clear(<Argument>)`-Aufrufen im Baum gesucht; jeder Treffer ist behoben oder begründet.

## Verification
`MainLanguageApiTest` grün mit den umgedrehten Erwartungen; die Suite bleibt grün.

## Ergebnis (2026-09-22)
**Mit `APP_LANGUAGES` lässt sich eine Übersetzung wieder anlegen, und sie übernimmt die
`i18n_universal`-Felder aus der Hauptsprache.**

- `getSingle(…, clearEM: true)` löst jetzt nur noch die verwalteten Objekte **dieser** Entity vom
  EntityManager (`detach` über die Identity Map der Wurzelklasse) — das, was der ORM-2-Aufruf
  `clear($entityFullName)` tat. Der angemeldete Benutzer bleibt verwaltet.
- `Core\ExampleI18n::$related` ist `i18n_universal`. Damit belegt ein Test den Zweig aus
  `000-000-0025`: Der aus der Hauptsprache übernommene Join zeigt auf `(id, 'en')`, nicht auf die
  deutsche Zeile.
- `MainLanguageApiTest` umgedreht: Übernahme von `code`, mitgeschickter Wert gewinnt (und erreicht
  die Hauptsprache), Join in der neuen Sprache, ohne Datensatz in der Hauptsprache wird nichts
  übernommen, ohne `id` ebenso, die Hauptsprache selbst übernimmt nichts. **Gegenprobe:** ohne den
  Fix 4 von 7 rot.
- Weitere `->clear(<Argument>)`-Aufrufe: keine im Baum (`lib`, `custom`, `bin`, `plugins`).

Suite 785 Tests grün, PHPStan ohne Fehler.

