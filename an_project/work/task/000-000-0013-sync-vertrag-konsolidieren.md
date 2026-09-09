---
id: 000-000-0013
title: Den Sync-Vertrag konsolidieren
status: review
depends_on: []
---

# Den Sync-Vertrag konsolidieren

## Context
Drei Befunde aus Epic `008`, die alle denselben Bereich betreffen: Was ein Sync-Client aus der
API ableiten kann — und was nicht.

## Umfang

### A — `getDeleted()` führt eine zweite, unsichtbare Ausschlussliste
Neben dem annotationsgesteuerten `excludeFromSync` trägt `Api::getDeleted()` eine fest
verdrahtete Liste im Code:

```php
$entitiesToExclude = array(
    'PIM\Folder', 'PIM\Token', 'PIM\Group', 'PIM\ThumbnailSetting',
    'PIM\Permission', 'PIM\Nav', 'PIM\NavItem', 'PIM\Log', '_hash'
);
```

Mit `000-000-0007` nutzt `getAll()` jetzt dieselbe Liste — die beiden Sync-Hälften sind also
konsistent. Aber: Die Liste steht **in keiner Annotation und in keiner Konfiguration**. Ein
Projekt kann nicht erkennen, warum eine Entity nie synchronisiert, und kann es nicht ändern.

Zu klären: Wird die Liste zu Annotationen (`excludeFromSync` auf den betroffenen Entities), zu
Konfiguration, oder bleibt sie im Code — dann aber dokumentiert?

### B — `sortBy` und `sortOrder` haben keinen Leser
`008-001-0002` hat gemessen: `Api::getList()` wertet die beiden **nicht** aus. Sortiert wird
allein nach dem `order`-Parameter des Requests; fehlt er, bleibt es bei `ORDER BY id DESC`.

Story `012-005-0002` hat beide mit der Begründung „Sortierung der API-Antworten" behalten. Das
trifft so nicht zu. Nur `sortRestrictTo` hat einen echten Leser (`JoinBidirectionalType`).

Der Unterschied zum gestrichenen `readonly`: Ein Client **kann** die Werte aus dem Schema lesen
und selbst anwenden. Zu klären ist, ob das die Absicht ist — dann gehört es dokumentiert — oder
ob `getList()` sie anwenden sollte, wenn kein `order` mitkommt.

### C — `pim_log.created` hat Sekundenauflösung
`008-002-0004` hat festgestellt: Ein Lebenszyklus, der in derselben Sekunde abläuft — bei je
einem API-Aufruf der Normalfall — hinterlässt Log-Zeilen mit **identischem Zeitstempel**. Aus
dem Protokoll allein lässt sich die Reihenfolge dann nicht rekonstruieren.

Das betrifft auch `/api/deleted`, das über `created > ?` filtert: Löschungen innerhalb der
Grenzsekunde sind nicht sauber abgrenzbar. Ein Sync-Client kann Einträge doppelt bekommen oder
verpassen.

Mögliche Richtungen: eine Spalte mit höherer Auflösung, eine monoton steigende Sequenz, oder
die Grenze bewusst inklusiv behandeln und dokumentieren.

## Acceptance criteria
- [x] Für die Ausschlussliste ist entschieden und begründet festgehalten, wo sie künftig steht.
- [x] Für `sortBy`/`sortOrder` ist entschieden, ob sie angewandt oder als Client-Metadaten
      dokumentiert werden; die Begründung in `012-005-0002` ist entsprechend korrigiert.
- [x] Die Zeitstempel-Auflösung ist bewertet und die Folge für `/api/deleted` benannt.
- [x] Betroffene Charakterisierungstests sind bewusst gedreht, nicht gelöscht.
- [x] Was den Vertrag mit Sync-Clients ändert, ist als Breaking Change für Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün. Für C zusätzlich ein Ablauf, der zwei Löschungen in derselben
Sekunde erzeugt und zeigt, dass `/api/deleted` sie beide meldet.

## Ergebnis

### A — Die Liste geht in Annotationen

**Entschieden: `@PIM\Config(excludeFromSync=true)` an den Entities.** Von den drei Möglichkeiten
— Annotationen, Konfiguration, im Code bleiben mit Dokumentation — ist die dritte die
schwächste: Ein Kommentar erklärt einem Projekt, warum eine Entity nie synchronisiert wird,
aber es sieht ihn nicht im Schema, und ändern kann es nichts. Konfiguration wäre ein dritter
Ort für eine Frage, für die es schon zwei gibt.

Es gibt jetzt **einen** Mechanismus statt zwei, und er steht im Schema, das jeder Client lesen
kann. Sieben Entities tragen das Flag: `PIM\Folder`, `PIM\Group`, `PIM\Log`, `PIM\Nav`,
`PIM\NavItem`, `PIM\Permission`, `PIM\ThumbnailSetting` — jede mit ihrer Begründung an der
Klasse.

**Zwei Einträge der alten Liste waren tot**, und das ist erst beim Umziehen aufgefallen:
`PIM\Token` steht gar nicht im Schema — die Entity leitet sich nicht von `Base` ab —, und
`PIM\PushToken` gibt es im Baum nicht.

**Die drei Listen waren nicht identisch.** Der Task sagt, `000-000-0007` habe `getAll()` auf
dieselbe Liste gebracht; das stimmt für `getAll()` und `getDeleted()`, nicht für `getCount()`:
Dort standen zusätzlich `PIM\File` und das nicht existierende `PIM\PushToken`. `PIM\File`
bleibt dort stehen, aber als das, was es ist — **keine Sync-Entscheidung**, sondern die
Vermeidung einer Doppelzählung, weil Dateien in derselben Statistik schon unter `filesCount`
und `filesSize` erscheinen.

**`getDeleted()` hat `excludeFromSync` nie geprüft** und tut es jetzt. Eine Entity, die aus dem
Bestand ausgeschlossen ist, aber ihre Löschungen meldet, ergibt keinen Sinn — ein Sync-Client
bekäme Löschmeldungen zu Objekten, die er nie erhalten hat.

`_hash` bleibt als Code-Guard: Das ist kein Entity-Name, sondern der Schema-Hash, und es gibt
keine Klasse, an die man eine Annotation schreiben könnte.

### B — `sortBy` und `sortOrder` werden nicht angewandt

**Entschieden: Angaben für den Client, und die Begründung aus `012-005-0002` ist korrigiert.**

Das Anwenden lag nahe und ist am eigenen Detail gescheitert: Die Vorgabe für jede Entity ohne
eigene Angabe ist `sortBy = 'created'`, `sortOrder = 'DESC'`. Heute sortiert `getList()` ohne
`order` nach `id DESC` — und `id` ist eindeutig, `created` nicht. Die Sortierung anzuwenden
hätte eine stabile Blätterreihenfolge gegen eine unstabile getauscht, still, für jeden Client,
der kein `order` schickt. Dafür hätte man einen Tiebreaker erfinden müssen, und damit wäre aus
dem Aufräumen ein Entwurf geworden.

**Der Widerspruch zu `000-000-0012` ist mir bewusst** — dort habe ich argumentiert, dass etwas
zu veröffentlichen, das nichts garantiert, schlechter ist als es wegzulassen. Der Unterschied
ist die Art der Aussage: `export` war ein **Recht**, und ein unerzwungenes Recht sieht wie eine
Zusicherung aus, hinter der nichts steht. `sortBy` ist eine **Angabe des Projekts über seine
eigenen Daten**; ein Client kann daraus sein `order` bauen, und wenn er es nicht tut, bekommt er
eine andere Reihenfolge, keine Sicherheitslücke.

Korrigiert ist die Zeile in `an_project/docs/pim-annotationen-migration.md`, die beide unter
„Sortierung der API-Antworten" führte. `sortRestrictTo` steht jetzt getrennt — es ist das
einzige der drei mit einem echten Leser.

### C — Die Grenzsekunde gehört dazu

**Entschieden: `created >= ?` statt `created > ?`, und die höhere Auflösung ist benannt, aber
nicht gebaut.**

Die Folge des alten Filters ist schärfer, als der Task sie beschreibt: Nicht „doppelt bekommen
oder verpassen", sondern **immer verpassen**. Ein Client merkt sich den Zeitstempel der zuletzt
gemeldeten Zeile; jede andere Löschung aus derselben Sekunde ist nicht *grösser* und kommt
deshalb nie — und nichts weist je darauf hin. Das Objekt bleibt beim Client für immer stehen.

`>=` liefert die Grenzsekunde erneut. Doppelt melden ist folgenlos, der Client löscht etwas, das
schon weg ist. **`getAll()` filtert seit jeher mit `modified >= :lastModified`** — die beiden
Hälften derselben Synchronisation lagen auf verschiedenen Seiten der Grenze.

Eine Spalte mit höherer Auflösung oder eine monoton steigende Sequenz wäre die eigentliche
Lösung. Beides braucht eine Migration für jedes Bestandsprojekt und gehört zum Kernel-Wechsel;
in `breaking-changes.md` steht es als das, was noch aussteht.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (246 tests, 611 assertions)`, 0 übersprungen |
| Vorher | 244 Tests |
| Gegenprobe zu C | Mit `created > ?` schlägt `testZweiLoeschungenInDerselbenSekundeKommenBeide()` fehl, und zwar schon an der Grenzzeile selbst |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |
| Postausgang | 0 Byte |

Zwei Charakterisierungstests umgedreht statt gelöscht:
`testDeletedSchliesstEineFesteListeVonEntitiesAus()` heisst jetzt
`testDeletedSchliesstAusWasExcludeFromSyncSetzt()` — gleiche Wirkung, andere Ursache —, und
`testKeineEntitySetztExcludeFromSync()` ist zu `testSiebenEntitiesSetzenExcludeFromSync()`
geworden. **Der alte Test hat genau das eingefordert:** „Setzt jemand das Flag, schlägt dieser
Test an und fordert den regulären Nachweis ein." Er hat angeschlagen. Zwei Tests sind neu: der
Grenzsekunden-Ablauf aus der Verification und einer, der belegt, dass `$entitiesToExclude` im
Code nicht zurückkehrt.

Vier Einträge in `an_project/docs/breaking-changes.md`.
