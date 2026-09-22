---
id: 000-000-0081
title: loadJoinedLang findet über den zweispaltigen Schlüssel keinen Datensatz
status: todo
depends_on: []
---

# loadJoinedLang findet über den zweispaltigen Schlüssel keinen Datensatz

## Context
**Gefunden in `000-000-0075` (2026-09-22).** `/api/single` mit `loadJoinedLang` soll einen Join auf
eine übersetzbare Entity in einer anderen Sprache lesen. Der Verweis auf eine übersetzbare Entity
hat aber zwei Spalten, `<feld>_id` und `<feld>_lang`, und die zweite legt die Sprache schon fest.
Die zusätzliche Bedingung `lang = :loadJoinedLang` kann dann nie zugleich gelten — der Join kommt
als `null`, obwohl das Ziel in dieser Sprache existiert. Der Zweig „neu übersetzen" von
`compareToLang` meldet deshalb immer fehlende Übersetzungen.

Festgehalten in `ReadPathApiTest::testLoadJoinedLangFindsNoJoinedRecordInAnotherLanguage()`.

**Erst klären, was gewollt ist:** ob `loadJoinedLang` über `id` allein joinen soll, oder ob der
Parameter seit dem zweispaltigen Schlüssel keinen Zweck mehr hat. Wer ihn nutzt, war die gelöschte
PIM-Oberfläche.

## Acceptance criteria
- [ ] Entscheidung festgehalten: reparieren oder entfernen (mit Registereintrag in `breaking-changes.md`).
- [ ] Bei Reparatur: Der Test ist umgedreht und liest das Ziel in `loadJoinedLang`; `compareToLang` mit `loadJoinedLang` meldet nur noch echte Lücken.
- [ ] Bei Entfernung: Der Parameter wird abgelehnt oder ignoriert, und der Test hält das fest.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.
