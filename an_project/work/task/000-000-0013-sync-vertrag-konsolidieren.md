---
id: 000-000-0013
title: Den Sync-Vertrag konsolidieren
status: todo
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
- [ ] Für die Ausschlussliste ist entschieden und begründet festgehalten, wo sie künftig steht.
- [ ] Für `sortBy`/`sortOrder` ist entschieden, ob sie angewandt oder als Client-Metadaten
      dokumentiert werden; die Begründung in `012-005-0002` ist entsprechend korrigiert.
- [ ] Die Zeitstempel-Auflösung ist bewertet und die Folge für `/api/deleted` benannt.
- [ ] Betroffene Charakterisierungstests sind bewusst gedreht, nicht gelöscht.
- [ ] Was den Vertrag mit Sync-Clients ändert, ist als Breaking Change für Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün. Für C zusätzlich ein Ablauf, der zwei Löschungen in derselben
Sekunde erzeugt und zeigt, dass `/api/deleted` sie beide meldet.
