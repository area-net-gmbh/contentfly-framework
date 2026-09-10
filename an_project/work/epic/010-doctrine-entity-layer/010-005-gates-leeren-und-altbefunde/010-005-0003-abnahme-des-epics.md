---
id: 010-005-0003
title: Die Abnahme des Epics
status: todo
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
- [ ] `BaseI18nTree::$treeChilds` zeigt auf `BaseI18nTree`; `orm:validate-schema` meldet einen Mapping-Fehler weniger.
- [ ] Der verbliebene Fehler steht als eigener Task im Backlog, mit dem, was zu entscheiden ist.
- [ ] Alle drei Gates sind mit gemessenen Zahlen belegt: PHPStan, Deprecations, `composer audit`.
- [ ] `tech-stack.md` und `deployment.md` beschreiben den erreichten Stand.
- [ ] Das Epic-Ergebnis steht in `epic.md`: was erreicht wurde, was gemessen ist, was weitergeht.
- [ ] Die Suite ist grün, auf PHP 8.3 **und** 8.4.

## Verification
Volle Suite auf beiden PHP-Versionen, `composer audit --locked`, PHPStan,
`orm:validate-schema`, ein vollständiger Durchlauf mit frischer Datenbank. Die Bilanz im
Epic-Ergebnis nennt Zahlen, keine Eindrücke.
