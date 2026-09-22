---
id: 000-000-0081
title: loadJoinedLang findet über den zweispaltigen Schlüssel keinen Datensatz
status: done
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
- [x] Entscheidung festgehalten: reparieren oder entfernen (mit Registereintrag in `breaking-changes.md`).
- [x] ~~Bei Reparatur:~~ entfällt durch die Entscheidung „entfernen“ — Der Test ist umgedreht und liest das Ziel in `loadJoinedLang`; `compareToLang` mit `loadJoinedLang` meldet nur noch echte Lücken.
- [x] Bei Entfernung: Der Parameter wird abgelehnt oder ignoriert, und der Test hält das fest.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.

## Ergebnis (2026-09-22)
**Entscheidung (2026-09-22, im Gespräch): entfernen.** Eine Reparatur hätte das Ziel nach dem Laden
gesondert in der anderen Sprache nachladen müssen — für `join`, `multijoin` und `compareToLang` —,
für einen Parameter, dessen einziger bekannter Nutzer die gelöschte PIM-Oberfläche war.

- `getSingle()` lehnt einen gesetzten `loadJoinedLang` mit **400**
  `contentfly_general_invalid_params` ab (`context.value` = `loadJoinedLang`); ein leerer Wert gilt
  als nicht gesendet. Der Parameter bleibt in der Signatur, damit Aufrufer mit Positionsargumenten
  (`…, null, $clearEM`) weiterlaufen.
- Die Joins auf übersetzbare Entities lesen in `lang`; der Parameter `:loadJoinedLang` ist weg.
- Der Zweig „neu übersetzen" von `compareToLang` ist entfernt; `compareToLang` allein unverändert.
- Registereintrag in `breaking-changes.md`.

### Belegt
`ReadPathApiTest::testLoadJoinedLangIsRejected`, `…testCompareToLangWithLoadJoinedLangIsRejectedToo`,
`…testAnEmptyLoadJoinedLangIsNone`; die bestehenden `compareToLang`-Tests bleiben grün.
**Gegenprobe:** ohne die Änderung 2 von 3 rot. Suite 784 Tests grün, PHPStan ohne Fehler.

