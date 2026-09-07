---
id: 000-000-0009
title: /api/multiupdate bricht mitten im Stapel ab und meldet nicht, wie weit es kam
status: todo
depends_on: []
---

# /api/multiupdate bricht mitten im Stapel ab und meldet nicht, wie weit es kam

## Context
Beim Charakterisieren der Schreibseite (`008-002-0003`) aufgefallen. `/api/multiupdate` nimmt
eine Liste von Objekten entgegen und aktualisiert sie nacheinander. Scheitert eines davon,
bricht die Schleife ab — **die vorher verarbeiteten Objekte bleiben geändert**, die danach
werden nie angefasst, und die Antwort ist ein nackter HTTP 500.

Der Client kann daraus nicht ableiten, in welchem Zustand seine Daten sind.

## Der Befund, gemessen

Drei Objekte, das mittlere mit unbekannter Id:

```
POST /api/multiupdate
{"objects":[
  {"entity":"PIM\\Tag","id":"m1",          "data":{"title":"A-vor-Fehler"}},
  {"entity":"PIM\\Tag","id":"gibtsnicht",  "data":{"title":"X"}},
  {"entity":"PIM\\Tag","id":"m3",          "data":{"title":"C-nach-Fehler"}}
]}
→ HTTP 500
```

Danach in der Datenbank:

| Id | Titel | |
|---|---|---|
| `m1` | `A-vor-Fehler` | **geändert** — vor dem Fehler verarbeitet |
| `m3` | `C-neu` | unverändert — nie erreicht |

`ApiController::multiupdateAction()` läuft in einer schlichten `foreach`-Schleife über
`$objects` und ruft je `Api::doUpdate()`. Es gibt **keine Transaktion**, **keine
Fehlersammlung** und **keinen Rollback**.

## Ein zweiter Punkt: die Antwort sagt nichts
Der Erfolgsfall liefert `renderResponse(array())` — der Rumpf besteht aus `version` und `hash`,
sonst nichts. Kein `ts`, kein `data`, keine Liste der aktualisierten Ids. Damit ist der
Endpunkt auch im Erfolgsfall nicht auswertbar, und der Vergleich „was habe ich geschickt, was
ist angekommen" fällt aus.

## Mögliche Richtungen — nicht vorentschieden
1. **Transaktion.** Der ganze Stapel gelingt oder keiner. Sauber, aber eine
   Verhaltensänderung, auf die sich Bestandsprojekte womöglich nicht eingestellt haben.
2. **Fehlersammlung.** Alle Objekte werden versucht, die Antwort listet Erfolge und
   Fehlschläge je Id. Näher am heutigen Verhalten, macht es aber auswertbar.
3. **Nur die Antwort verbessern.** Reihenfolge und Abbruch bleiben, die Antwort nennt aber,
   wie weit gekommen wurde. Kleinster Eingriff.

Die Entscheidung gehört zu Epic `007` (Migrationsleitfaden) beziehungsweise `009`, weil sie den
Vertrag mit Bestandsprojekten berührt.

## Acceptance criteria
- [ ] Es ist entschieden und begründet festgehalten, welche der drei Richtungen gilt.
- [ ] Der Client kann nach einem Teilfehler feststellen, welche Objekte geändert wurden.
- [ ] Der Erfolgsfall liefert eine auswertbare Antwort.
- [ ] Der Charakterisierungstest aus `008-002-0003`, der den heutigen Abbruch festhält, ist
      auf das neue Verhalten gedreht — bewusst, nicht durch Löschen.
- [ ] Ist die Wahl eine Verhaltensänderung, ist sie als Breaking Change in
      `an_project/docs/pim-annotationen-migration.md` oder dem Migrationsleitfaden vermerkt.

## Verification
Der Ablauf aus dem Befund oben, mit dem jeweils gewählten Zielverhalten geprüft — plus die
Suite aus Epic `008` grün.
