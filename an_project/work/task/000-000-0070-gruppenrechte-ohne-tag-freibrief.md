---
id: 000-000-0070
title: Gruppenrechte schreiben, ohne PIM\Tag stillschweigend freizugeben
status: done
depends_on: []
---

# Gruppenrechte schreiben, ohne PIM\Tag stillschweigend freizugeben

## Context
**Gefunden in `000-000-0067` (2026-09-21).** `PermissionsType::toDatabase()` legt bei **jedem**
Schreiben der Rechte einer Gruppe zusätzlich eine Zeile für `PIM\Tag` an: lesen, schreiben und
löschen auf `ALL` — unabhängig davon, was der Request verlangt. Wer eine Gruppe über
`/api/insert` oder `/api/update` mit `permissions` anlegt, gibt ihr damit ungefragt vollen Zugriff
auf alle Tags.

Vermutlich ein Rest der gestrichenen PIM-Oberfläche (Epic `012`), die Tags an Dateien brauchte.
`FieldTypeApiTest::testWritingGroupPermissionsAlsoGrantsFullAccessToTags` hält das heutige
Verhalten fest, damit die Änderung sichtbar wird.

## Acceptance criteria
- [x] Entschieden: Die Zeile entfällt, oder sie bleibt als dokumentierte Voreinstellung — mit Begründung.
- [x] Entfällt sie: Eintrag im Register (`breaking-changes.md`), weil bestehende Gruppen die Rechte verlieren, sobald ihre Rechte neu geschrieben werden.
- [x] Der Charakterisierungstest ist entsprechend umgedreht oder begründet beibehalten.

## Verification
`FieldTypeApiTest` und die Rechtematrix (`PermissionMatrixApiTest`) grün.

## Ergebnis (2026-09-23)
**Die Zeile entfällt.** `PermissionsType::toDatabase()` schreibt nur noch, was der Request
verlangt.

### Warum entfallen und nicht als Voreinstellung dokumentiert
Der Task liess beides offen. Entschieden hat es ein Befund, der im Ticket noch nicht stand:

**Die Zeile verdeckte eine ausdrückliche Angabe.** Sie wurde **vor** den angeforderten Rechten
geschrieben, und `Classes\Permission::is()` gibt den **ersten** Treffer zum Entitätsnamen zurück.
`Entity\Group::$permissions` trägt kein `#[ORM\OrderBy]`, die Reihenfolge ist also die der
Einfügung. Ein mitgeschicktes `PIM\Tag` landete damit hinter der `ALL`-Zeile und wurde nie
wirksam — **ein Aufrufer konnte Tags auch dann nicht einschränken, wenn er es ausdrücklich
verlangte.**

Eine Voreinstellung, die sich nicht überschreiben lässt, ist keine Voreinstellung. Damit war die
zweite Möglichkeit erledigt, ohne dass es eine Abwägung gebraucht hätte.

Dass die Zeile weder `export` noch `extended` setzte — anders als die angeforderten — bestätigt
den Rest: ein Überbleibsel der mit Epic `012` gestrichenen Oberfläche, nie Teil des Vertrags.

### Belegt
Integration-Suite **lokal gefahren**, gegen eine eigens angelegte Datenbank `contentfly_0070`
(die Entwicklungs-Datenbank blieb unberührt, nachgezählt: unverändert 2/1/1 Zeilen in
`pim_permission`/`pim_group`/`pim_user`).

- `testWritingGroupPermissionsAlsoGrantsFullAccessToTags` umgedreht zu
  `…GrantsNothingThatWasNotAskedFor`: Eine leere Rechteliste schreibt **keine** Zeile.
- Neu `testAnExplicitTagPermissionIsTheOneThatCounts` für die Verdeckung: genau **eine**
  `PIM\Tag`-Zeile, und zwar die angeforderte.
- **Gegenprobe:** ohne den Fix sind beide rot — die erste findet `['PIM\Tag']` statt `[]`, die
  zweite zwei Zeilen statt einer. Damit ist die Verdeckung nicht behauptet, sondern gemessen.

`FieldTypeApiTest` und `PermissionMatrixApiTest` zusammen 84 Tests grün.

### Registereintrag
`breaking-changes.md` unter *API*, mit dem Hinweis auf Bestandsgruppen: Ihre vorhandene
`ALL`-Zeile bleibt stehen, bis ihre Rechte das nächste Mal geschrieben werden.

> **Für den Merge:** Die Registergrösse steht hier auf **135**, gezählt gegen `master`. `000-000-0087`
> (PR #50) fügt einen weiteren Eintrag hinzu. Wer als Zweiter mergt, zählt nach — die Zeile in
> `migration.md` ist genau die, die bei `0079`–`0081` schon einmal einem Konflikt zum Opfer fiel.
