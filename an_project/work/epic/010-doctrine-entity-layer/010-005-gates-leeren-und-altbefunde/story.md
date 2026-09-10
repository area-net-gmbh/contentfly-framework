---
id: 010-005-0000
title: Die Gates leeren und die Altbefunde aus Epic 009 abräumen
status: todo
depends_on: [010-003-0000]
---

# Die Gates leeren und die Altbefunde aus Epic 009 abräumen

## Goal
Die Ausnahmelisten aller drei Gates sind leer, und die vier Altbefunde aus Epic `009` sind
abgeräumt oder mit Begründung abgelehnt.

Das ist die Schluss-Story des Epics, aufgebaut wie `009-003`: Sie schafft keinen neuen Stand,
sondern weist nach, dass der erreichte trägt.

- **PHPStan:** acht benannte Muster über 31 Doctrine-Befunde — alle sollten mit `010-001` bis
  `010-003` gegenstandslos geworden sein. **Regel 3 gilt:** Ein Muster, das nichts mehr trifft,
  macht den Lauf rot; die Liste räumt sich also nicht von allein, sie muss geräumt werden.
- **`composer audit`:** `tools/ci/audit.sh` steht auf `--abandoned=ignore` mit dem Vermerk „auf
  `fail` umstellen, sobald Epic 010 durch ist". Beide abandoned Pakete
  (`doctrine/annotations`, `doctrine/cache`) fallen in diesem Epic.
- **Die vier Altbefunde:** die `rowCount()`-Stellen, die zum Zählen den ganzen Treffersatz
  holen; der fehlende `modified_index`; die ungültige Zuordnung in `BaseI18nTree`; und die
  Entscheidung über den PHP-8.4-Job, für den seit `009-004-0002` ein grüner Lauf vorliegt.

Was davon nicht behoben wird, verlässt das Epic **mit benanntem Auflöser** — nicht
stillschweigend.

## Gemessen am 2026-09-10 — die Hälfte ist schon eingelöst

| Punkt | Stand |
|---|---|
| PHPStan-Ausnahmen | **erledigt mit `010-003-0003`** — von acht ist eine übrig, und die kommt aus DBAL, nicht aus dem ORM |
| `--abandoned=fail` | **erledigt mit `010-003-0003`**; `composer audit` meldet 0 abandoned |
| PHP-8.4-Job blockierend | **erledigt mit `010-003-0003`**; die Pipeline hat kein `allow_failure` mehr |
| `rowCount()`-Stellen | offen, 3 in `Classes/Api.php` |
| `modified_index` | offen |
| `BaseI18nTree` | offen, **zwei** Fehler |

Die Gates haben sich also nicht am Schluss räumen lassen, sondern unterwegs — Regel 3 hat jedes
Muster in dem Task eingefordert, in dem es gegenstandslos wurde. Was hier bleibt, sind die drei
Altbefunde und die Abnahme des Epics.

### Was `orm:validate-schema` genau sagt

```
The association BaseI18nTree#treeParent refers to the inverse side
BaseI18nTree#treeChilds which targets a different entity (BaseTree).

The join columns of the association 'treeParent' have to match to ALL identifier
columns of the target entity 'BaseI18nTree', however 'id, lang' are missing.
```

**Der erste ist ein Kopierfehler** — `treeChilds` zeigt auf `BaseTree` statt auf die eigene
Klasse. Eindeutig falsch, eindeutig zu beheben.

**Der zweite ist es nicht.** `BaseI18nTree` hat einen zusammengesetzten Schlüssel (`id`, `lang`),
und ob ein Kindknoten auf einen Elternknoten **derselben Sprache** zeigen soll, steht nirgends.
**Niemand erbt von der Klasse** — die Absicht ist an nichts zu prüfen.

**Entschieden am 2026-09-10, mit dem Auftraggeber:** Der Kopierfehler wird behoben, die zweite
Join-Spalte nicht. Sie zu erfinden hiesse, Semantik für Code festzulegen, den niemand benutzt,
und dabei das Schema einer Tabelle zu ändern. Sie verlässt das Epic mit benanntem Auflöser.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 010-005-0001 — Die rowCount-Stellen auf COUNT(*) bringen
- [ ] 010-005-0002 — Den modified_index-Listener in die Factory ziehen
- [ ] 010-005-0003 — Die Abnahme des Epics
