---
id: 000-000-0082
title: untranslatedLang liefert leer, sobald die Entity auf eine übersetzbare joint
status: done
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
- [x] Der Join benutzt einen eigenen Parameter; `:lang` der Liste bleibt unangetastet.
- [x] Entschieden, in welcher Sprache der Join bei `untranslatedLang` gelesen wird (Ausgangssprache ist naheliegend), und im Code begründet.
- [x] Der Test ist umgedreht: Der Datensatz ohne Übersetzung wird in der Ausgangssprache gelistet, der übersetzte nicht.
- [x] Ein Test belegt, dass `where` und `lastModified` bei `untranslatedLang` wegfallen — oder dass sie jetzt greifen, falls das mitentschieden wird.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.

## Ergebnis (2026-09-22)
**`untranslatedLang` listet wieder die Datensätze ohne Übersetzung.**

- Der i18n-Join in `getList()` bindet einen eigenen Parameter `:joinLang`; `:lang` der Liste bleibt
  unangetastet.
- **Sprache des Joins:** die der gelisteten Zeilen — bei `untranslatedLang` die Ausgangssprache,
  sonst `lang`. Begründet im Code: Der Verweis auf eine übersetzbare Entity trägt die Sprache ohnehin
  in seinem Schlüssel, jede andere Sprache fände nichts (vgl. `0081`).
- `where` und `lastModified` fallen bei `untranslatedLang` weiterhin weg; das ist nicht
  mitentschieden worden und als Ist-Zustand im Test festgehalten.

### Belegt
`ReadPathApiTest::testUntranslatedLangListsTheRecordsWithoutThatTranslation` (umgedreht: der
deutsche Datensatz ohne englische Fassung kommt in `de`, samt Join auf das deutsche Ziel; der
übersetzte nicht) und `…testUntranslatedLangIgnoresWhereAndLastModified`. **Gegenprobe:** ohne die
Änderung beide rot. Suite 784 Tests grün, PHPStan ohne Fehler.

