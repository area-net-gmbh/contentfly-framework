---
id: 010-005-0003
title: Die Abnahme des Epics
status: review
depends_on: [010-005-0001, 010-005-0002]
---

# Die Abnahme des Epics

## Context
Die Schluss-Story schafft keinen neuen Stand, sondern weist nach, dass der erreichte trägt.

**Der Kopierfehler in `BaseI18nTree`** wird hier behoben: `treeChilds` zeigt auf `BaseTree`
statt auf die eigene Klasse. Die fehlende zweite Join-Spalte für den zusammengesetzten
Schlüssel wird **nicht** ergänzt — sie verlässt das Epic mit benanntem Auflöser, weil niemand
von der Klasse erbt und die Absicht damit an nichts zu prüfen ist.

Dazu die Bilanz: Was hat Epic `010` verändert, was ist gemessen, und was geht mit welchem
Auflöser weiter.

## Acceptance criteria
- [x] `BaseI18nTree::$treeChilds` zeigt auf `BaseI18nTree`; `orm:validate-schema` meldet einen Mapping-Fehler weniger.
- [x] Der verbliebene Fehler steht als eigener Task im Backlog, mit dem, was zu entscheiden ist.
- [x] Alle drei Gates sind mit gemessenen Zahlen belegt: PHPStan, Deprecations, `composer audit`.
- [x] `tech-stack.md` und `deployment.md` beschreiben den erreichten Stand.
- [x] Das Epic-Ergebnis steht in `epic.md`: was erreicht wurde, was gemessen ist, was weitergeht.
- [x] Die Suite ist grün, auf PHP 8.3 **und** 8.4.

## Verification
Volle Suite auf beiden PHP-Versionen, `composer audit --locked`, PHPStan,
`orm:validate-schema`, ein vollständiger Durchlauf mit frischer Datenbank. Die Bilanz im
Epic-Ergebnis nennt Zahlen, keine Eindrücke.

## Ergebnis

**Von zwei Mapping-Fehlern in `BaseI18nTree` ist einer weg, die Datenbank ist in sync, und die
Bilanz des Epics steht in `epic.md`.**

### Der Kopierfehler

`treeChilds` zeigte auf `BaseTree` — die Klasse nebenan, aus der diese hier kopiert wurde.
`orm:validate-schema` nannte es beim Namen, und die Korrektur ist eindeutig. Danach:

```
Mapping
 [FAIL] … 'treeParent' … 'id, lang' are missing.

Database
 [OK] The database schema is in sync with the mapping files.
```

Eine Zeile weniger, und die verbliebene ist die dokumentierte Entscheidung.

### Was nicht behoben wurde, und warum

`treeParent` verweist mit **einer** Join-Spalte auf eine Entity mit **zusammengesetztem**
Schlüssel. Drei Gründe, das hier nicht zu entscheiden:

- **Niemand erbt von `BaseI18nTree`** — gemessen über `lib/` und `custom/`. Die Absicht lässt
  sich an keinem Nutzer prüfen.
- **Die Frage ist fachlich:** Soll ein Kindknoten auf einen Elternknoten derselben Sprache
  zeigen? Das ist die naheliegende Lesart, aber sie steht nirgends.
- **Die Antwort ändert das Schema** — eine zweite Join-Spalte bedeutet eine neue Spalte in
  `pim_i18n_tree`.

Aufgeschrieben als **`000-000-0025`**, mit beiden Wegen: die Beziehung reparieren, oder
`BaseI18nTree` und `BaseI18nSortable` ersatzlos streichen. Die Entscheidung gehört in
`architecture.md` unter *Key decisions*, weil sie das Datenmodell betrifft.

### Die Gates, mit Zahlen

| Gate | Stand |
|---|---|
| `composer audit --locked` | 0 Advisories, **0 abandoned**, Schalter auf `fail` |
| Deprecation-Log | 0 Zeilen bei 0 Ausnahmen, PHP 8.3 **und** 8.4 |
| PHPStan | `[OK] No errors`; **eine** Ausnahme, und die kommt aus DBAL |
| `orm:validate-schema` | Datenbank in sync, ein Mapping-Fehler mit benanntem Auflöser |

Die Gate-Hälfte dieser Story war schon mit `010-003-0003` eingelöst — Regel 3 hat jedes Muster
in dem Task eingefordert, in dem es gegenstandslos wurde, statt es bis zum Schluss aufzusparen.
**Das ist das eigentliche Ergebnis dieser Story:** Sie musste die Gates nicht räumen, weil sie
sich unterwegs geräumt haben.

### Der Lauf auf PHP 8.4

Auf dem Endstand des Epics nachgefahren, im Pipeline-Image gegen einen `mysql:8.0`-Service:
`OK (282 tests, 692 assertions)`, 0 Deprecations, Postausgang 0 Byte. Damit liegt der grüne
8.4-Lauf zum dritten Mal vor — mit ORM 2.20, mit ORM 3.7 und jetzt.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite auf PHP 8.3 | `OK (282 tests, 692 assertions)`, 0 übersprungen |
| Volle Suite auf PHP 8.4 | dieselbe Zahl, 0 Deprecations |
| PHPStan | `[OK] No errors` |
| `composer audit --locked --abandoned=fail` | Exit 0 |
| `orm:validate-schema` | Datenbank in sync; ein Mapping-Fehler |
| `appcms:install` auf frischer Datenbank | läuft durch |
