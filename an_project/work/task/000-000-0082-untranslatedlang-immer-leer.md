---
id: 000-000-0082
title: untranslatedLang liefert leer, sobald die Entity auf eine übersetzbare joint
status: todo
depends_on: []
---

# untranslatedLang liefert leer, sobald die Entity auf eine übersetzbare joint

## Context
**Gefunden in `000-000-0075` (2026-09-22).** `/api/list` mit `untranslatedLang` bindet `:lang` an
die Ausgangssprache. Die Join-Schleife weiter unten in `getList()` bindet **denselben** Parameter
neu, auf die Sprache des Requests, sobald die Entity auf eine übersetzbare Entity joint
(`setParameter('lang', $lang)`). Die Abfrage sucht dann Datensätze in der Zielsprache, die keine
Fassung in der Zielsprache haben — nie einen.

`Core\ExampleI18n` ist die einzige übersetzbare Entity der Vorlage und joint über `related` auf
sich selbst; der funktionierende Weg ist damit nicht mehr zeigbar.

Festgehalten in `ReadPathApiTest::testUntranslatedLangFindsNothingWhileTheEntityJoinsATranslatableOne()`.

## Acceptance criteria
- [ ] Der Join benutzt einen eigenen Parameter; `:lang` der Liste bleibt unangetastet.
- [ ] Entschieden, in welcher Sprache der Join bei `untranslatedLang` gelesen wird (Ausgangssprache ist naheliegend), und im Code begründet.
- [ ] Der Test ist umgedreht: Der Datensatz ohne Übersetzung wird in der Ausgangssprache gelistet, der übersetzte nicht.
- [ ] Ein Test belegt, dass `where` und `lastModified` bei `untranslatedLang` wegfallen — oder dass sie jetzt greifen, falls das mitentschieden wird.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.
