---
id: 000-000-0078
title: Übersetzung anlegen scheitert mit APP_LANGUAGES — EntityManager::clear() leert alles
status: todo
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
- [ ] `clearEM` leert nur, was es leeren soll (z. B. die Objekte der einen Klasse per `detach`) oder entfällt, wenn der frische Lesevorgang anders sichergestellt ist.
- [ ] Die beiden Tests in `MainLanguageApiTest` sind umgedreht: Die Übersetzung wird angelegt, und `code` kommt aus der Hauptsprache, wenn er nicht mitgeschickt wird.
- [ ] Ein Test belegt, dass ein Join auf eine übersetzbare Entity, der aus der Hauptsprache übernommen wird, auf die Übersetzung in der neuen Sprache zeigt (`000-000-0025`) — dafür braucht `Core\ExampleI18n::$related` `i18n_universal`.
- [ ] Nach weiteren `->clear(<Argument>)`-Aufrufen im Baum gesucht; jeder Treffer ist behoben oder begründet.

## Verification
`MainLanguageApiTest` grün mit den umgedrehten Erwartungen; die Suite bleibt grün.
