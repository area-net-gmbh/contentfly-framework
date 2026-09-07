---
id: 008-002-0005
title: unique, Sortierung — und die beiden Lücken
status: todo
depends_on: [008-002-0001]
---

# unique, Sortierung — und die beiden Lücken

## Context
Die letzten beiden prüfbaren Nebenwirkungen der Schreibseite — und die ehrliche Buchführung
über zwei, die heute **keinen Prüfgegenstand haben**.

## Umfang

### A — `unique`-Verletzung
`PIM\Tag.title` trägt beides: `@ORM\Column(unique=true)` auf Datenbankebene und
`@PIM\Config(unique=true)` im Schema. `Api` prüft letzteres und wirft eine
`EntityDuplicateException`.

Festzuhalten: Was liefert die API beim Versuch, einen zweiten Tag mit gleichem Titel anzulegen —
welcher Statuscode, welche Nutzlast, und **entsteht dabei ein halb angelegtes Objekt?**

### B — Sortierung bei `BaseSortable`
`Option`, `NavItem` und `Nav` erben von `BaseSortable`; `Api::getSchema()` setzt für sie
`sortBy = 'sorting'`, `sortOrder = 'ASC'`, `isSortable = true`.

`PIM\Option` trägt zusätzlich `sortRestrictTo="group"` — die Sortierung läuft also **je
Optionsgruppe**, nicht global. Festzuhalten ist, welchen `sorting`-Wert ein neu angelegtes
Objekt bekommt und ob `sortRestrictTo` dabei greift.

`sortRestrictTo` ist eines der drei Sortier-Felder, die `012-005-0002` behalten hat — und laut
dem Befund aus `008-001-0002` das **einzige mit einem echten Leser** (`JoinBidirectionalType`).
Ein Test darauf sichert genau diese Unterscheidung.

### C — Die beiden Lücken, belegt statt behauptet

**`encoded`-Verschlüsselung.** Keine Entity im Framework und keine in der Vorlage setzt
`@PIM\Config(encoded=true)`, und `SECURITY_CIPHER_KEY` steht standardmäßig auf `null` —
`StringType` würde selbst dann werfen. Die Verschlüsselung ist also heute nicht auslösbar.

**OneJoin-Kaskade beim Löschen.** `Api::delete()` entfernt verjointe Objekte vom Typ `onejoin`
mit. Es gibt **keine einzige `@ORM\OneToOne`-Beziehung** im Framework oder der Vorlage — der
Code-Pfad hat keinen Auslöser.

Beide bekommen einen Test auf die **Vorbedingung**, nicht auf die Wirkung — dasselbe Muster wie
bei `excludeFromSync` und `i18n_universal` in `008-001`. Er schlägt an, sobald jemand eine
solche Entity anlegt, und fordert damit den fehlenden Nachweis ein, statt ihn stillschweigend
ausfallen zu lassen.

> Das ist der dritte Fall dieser Art in Epic `008`. Dass sich das häuft, ist selbst ein Befund:
> Das Framework trägt Funktionen, deren einzige Nutzer die gelöschte Oberfläche oder
> Kundenprojekte waren. Er gehört in die Zusammenfassung der Story, nicht in eine Reparatur.

## Acceptance criteria
- [ ] Die `unique`-Verletzung auf `PIM\Tag.title` ist festgehalten — Statuscode, Nutzlast, und
      ob ein halb angelegtes Objekt zurückbleibt.
- [ ] Der `sorting`-Wert eines neu angelegten `BaseSortable`-Objekts ist festgehalten.
- [ ] Die Wirkung von `sortRestrictTo` bei `PIM\Option` ist geprüft — mit Verweis auf den
      Befund aus `008-001-0002` im Kommentar.
- [ ] Ein Test belegt, dass **keine** Entity `encoded=true` setzt, und benennt im Kommentar,
      was zu ergänzen ist, sobald sich das ändert.
- [ ] Ein Test belegt, dass es **keine** `onejoin`-Eigenschaft im Schema gibt, mit demselben
      Hinweis.
- [ ] Die Häufung der Lücken ist in der Zusammenfassung der Story benannt.

## Verification
Mehrere vollständige Läufe grün. Für `unique` zusätzlich über `pdo()` prüfen, dass nach dem
fehlgeschlagenen Versuch **keine** zusätzliche Zeile in `pim_tag` steht — die API-Antwort allein
sagt darüber nichts.
