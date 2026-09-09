---
id: 000-000-0009
title: /api/multiupdate bricht mitten im Stapel ab und meldet nicht, wie weit es kam
status: done
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
- [x] Es ist entschieden und begründet festgehalten, welche der drei Richtungen gilt.
- [x] Der Client kann nach einem Teilfehler feststellen, welche Objekte geändert wurden.
- [x] Der Erfolgsfall liefert eine auswertbare Antwort.
- [x] Der Charakterisierungstest aus `008-002-0003`, der den heutigen Abbruch festhält, ist
      auf das neue Verhalten gedreht — bewusst, nicht durch Löschen.
- [x] Ist die Wahl eine Verhaltensänderung, ist sie als Breaking Change in
      `an_project/docs/pim-annotationen-migration.md` oder dem Migrationsleitfaden vermerkt.

## Verification
Der Ablauf aus dem Befund oben, mit dem jeweils gewählten Zielverhalten geprüft — plus die
Suite aus Epic `008` grün.

## Ergebnis

**Gewählt ist Richtung 1, die Transaktion.** Der ganze Stapel gelingt oder keiner.

Der Grund ist nicht Sauberkeit, sondern was der Aufrufer hinterher weiß. Die Fehlersammlung
(Richtung 2) hätte den Teilfehler gemeldet, aber den Mischzustand behalten — und sie hängt
daran, dass die Antwort ankommt. Geht sie unterwegs verloren, steht der Aufrufer wieder da, wo
er vorher stand. Die Transaktion hält auch dann: Kommt keine Antwort, wurde entweder alles
geschrieben oder nichts, und ein Wiederholen des ganzen Stapels ist gefahrlos. Richtung 3 hätte
den Mangel beschrieben statt ihn zu beheben.

Damit beantwortet sich das zweite Kriterium von selbst: Nach einem Fehler wurde **nichts**
geändert, und das gilt unabhängig davon, ob die Antwort ankommt oder gelesen wird.

### Was gebaut wurde

`multiupdateAction()` öffnet eine Transaktion auf der Doctrine-Verbindung, arbeitet den Stapel
wie bisher ab und committet am Ende. Gefangen wird `\Throwable`, nicht `\Exception`: `doUpdate()`
nimmt `entity`, `id` und `data` typisiert entgegen, ein Eintrag ohne diese Schlüssel löst einen
`TypeError` aus — und der ist kein `Exception`. Fiele er hier durch, bliebe die Transaktion
offen; die Verbindung räumte sie am Requestende zwar ohne Commit ab, aber aus Versehen.

Der Erfolgsfall antwortet jetzt mit `ts` und `data`. `data` listet je Objekt aus dem Request
`entity` und `id`, in dessen Reihenfolge. Die Mitschriften in die übrigen Sprachen einer
I18N-Entität sind Folge desselben Eintrags und keine eigenen Listenpunkte.

**Ein fehlendes `objects` ist jetzt ein Fehler.** Vorher lief `foreach` über `null` durch und der
Aufruf endete mit `200`. Solange die Antwort leer war, fiel das nicht auf; seit sie aufzählt,
was geschrieben wurde, wäre eine leere Liste auf einen kaputten Request hin eine falsche
Auskunft. Der leere Stapel `[]` bleibt ausdrücklich erlaubt und meldet eine leere Liste.

### Tests

Zwei Charakterisierungstests sind umgedreht statt gelöscht, mit der alten Zusicherung im
Verlauf: `testTeilfehlerLaesstDasVorherigeGeschrieben()` heißt jetzt
`testTeilfehlerRolltDenGanzenStapelZurueck()`, `testDieAntwortNenntWederZeitstempelNochErgebnis()`
ist `testDieAntwortNenntZeitstempelUndDieGeaendertenObjekte()`. Zwei sind dazugekommen:
`testEinFehlerHinterlaesstAuchKeineProtokollzeile()` — die Rückabwicklung muss auch mitnehmen,
was `doUpdate()` nebenbei nach `pim_log` schreibt, sonst behauptete das Protokoll eine Änderung,
die es nicht mehr gibt — und `testEinFehlendesObjectsIstEinFehlerUndKeinLeerlauf()`.

### Zwei Messfehler auf dem Weg

**Ich habe 404 erwartet und 500 gemessen.** `doUpdate()` wirft bei unbekannter Id eine
`ContentflyException` mit dem Code 404, und ich habe daraus geschlossen, dass der Client 404
sieht. Er sieht 500 — und zwar aus einem Grund, der genau `000-000-0006` ist: Die Ausnahme
erreicht den Fehlerhandler der Anwendung nicht. Im Serverlog steht, was stattdessen passiert:

```
PHP Fatal error: Uncaught ... GetResponseForExceptionEvent::__construct():
Argument #2 ($request) must be of type ...Request, null given,
called in lib/contentfly/bootstrap-web.php on line 55
```

Der Notfall-Handler aus `bootstrap-web.php` baut das Ereignis mit `$app['request']`, und das ist
zu diesem Zeitpunkt `null`. Ergebnis ist Symfonys „Whoops"-Seite mit 500. Die Erwartungen in den
Tests stehen deshalb auf 500, mit Verweis auf `000-000-0006`; dort werden sie nachgezogen.

**Ein alter Testserver auf Port 8170 hat die erste Messung verfälscht** — genauer: er hätte es
fast. Mein Aufräumbefehl in den Hilfsskripten lautete `pkill -f "php -S 127.0.0.1:8170"`, und
diese Zeichenkette kommt in der Kommandozeile nie vor, weil die `-d`-Flags zwischen `php` und
`-S` stehen. Der Server lief seit über einer Stunde weiter, der neue konnte den Port nicht
belegen und schrieb sein Log ins Leere. Dass die Messung trotzdem stimmte, liegt allein daran,
dass der eingebaute Server die PHP-Dateien je Request neu liest. Das Muster ist korrigiert.

### Nachweis

- `MultiupdateApiTest` — `OK (8 tests, 18 assertions)`
- Volle Suite — `OK (241 tests, 599 assertions)`, 0 übersprungen
- Deprecation-Gate grün, 1 Paar, 1 ausgenommen; Postausgang 0 Byte
- Drei Einträge in `an_project/docs/breaking-changes.md`
