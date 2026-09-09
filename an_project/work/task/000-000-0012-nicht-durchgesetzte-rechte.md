---
id: 000-000-0012
title: Die nicht durchgesetzten Berechtigungsfelder entscheiden
status: review
depends_on: []
---

# Die nicht durchgesetzten Berechtigungsfelder entscheiden

## Context
`008-003-0005` hat gemessen: `canExport` und `getExtended` stehen im `permissions`-Block des
Schemas und werden **an keiner Stelle geprüft**. Ein Benutzer mit `export = 0` liest, schreibt
und löscht trotzdem; ein `extended`-Eintrag schränkt die Antwort nicht ein.

`canExport`s Konsument war der `ExportController` — gelöscht in `012-001-0003`. `getExtended`
hatte nie einen Durchsetzungspunkt im Framework; die JSON-Struktur war für die Maske gedacht.

## Der Unterschied zum gestrichenen `readonly`
`012-005-0002` hat `readonly` ersatzlos gestrichen, weil es nur ins Schema geschrieben und nie
gelesen wurde. Hier liegt es anders:

- Beide haben eine **Datenbankspalte** (`pim_permission.export`, `pim_permission.extended`).
  Ein Bestandsprojekt hat dort möglicherweise Werte stehen.
- Ein **Client** kann sie aus dem Schema lesen und selbst anwenden — anders als `readonly`,
  das nur eine Maske bedient hätte.

Sie sind also nicht tot, sondern wirkungslos. Die Entscheidung ist deshalb eine echte.

## Ein zweiter Befund: `canExport` folgt eigenen Regeln
`readable`, `writable` und `deletable` laufen über `Permission::is()` und melden ihren
Stufenwert als Integer. `canExport` hat eine **eigene** Implementierung:

```php
if($user->getIsAdmin()) return true;
if($user->getGroup() === null) return false;
foreach(…){ if(…) return ($permission->getExport() == 2); }
return false;
```

Die Spalte trägt dieselbe Vierstufen-Semantik, aber nur `ALL` (2) gilt als erlaubt. Und weil
die Konstanten **nicht aufsteigend geordnet** sind (`NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3),
ergibt ausgerechnet `GROUP` ein `false`. Wer „mehr als ALL" meint, sperrt sich aus.

## Mögliche Richtungen — nicht vorentschieden
1. **Entfernen**, wie `readonly`. Sauber, aber es verwirft Daten, die in Bestandsprojekten
   stehen könnten, und nimmt Clients eine Information.
2. **Durchsetzen.** `canExport` bräuchte einen Endpunkt, den es nicht mehr gibt — es sei denn,
   ein Export kehrt zurück. `getExtended` müsste die Feldauswahl der API einschränken; das
   wäre neue Funktionalität, kein Aufräumen.
3. **Als Client-Metadaten behalten und dokumentieren.** Der ehrlichste kleine Schritt: Im
   Schema bleiben sie, aber es steht ausdrücklich dabei, dass die API sie nicht durchsetzt.

Unabhängig davon ist die Frage zu klären, ob `canExport` seine vier Stufen weiterhin auf ein
Boolean kollabieren soll.

## Acceptance criteria
- [x] Es ist entschieden und begründet festgehalten, welche Richtung gilt.
- [x] Der Umgang mit `canExport`s Stufen-Kollaps ist mitentschieden.
- [x] Die Tests aus `008-003-0005` sind auf das neue Verhalten gedreht — bewusst, nicht durch
      Löschen.
- [x] Ist die Wahl eine Verhaltens- oder Schemaänderung, ist sie als Breaking Change für
      Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün mit den angepassten Zusicherungen; bei Variante 1 zusätzlich
ein Schema-Vergleich, der belegt, dass nur diese beiden Schlüssel verschwinden.

## Ergebnis

**Gewählt ist Richtung 1: entfernen — aber nur aus dem Schema, nicht aus der Datenbank.**

Der Ausschlag gab nicht die Sauberkeit, sondern Richtung 3. „Als Client-Metadaten behalten und
dokumentieren" klingt nach dem ehrlichen kleinen Schritt und ist die gefährlichste der drei:
Ein Recht, das der Server veröffentlicht und nicht durchsetzt, sieht wie eine Zusicherung aus.
Ein Client, der `export: false` liest und den Knopf ausblendet, hält sich für abgesichert — wer
die API direkt ruft, ist davon unberührt. Ein Kommentar im Schema ändert daran nichts, weil
Clients Schlüssel lesen und keine Kommentare. Etwas zu veröffentlichen, das nichts garantiert,
ist schlechter als es wegzulassen.

Richtung 2 schied für beide Felder aus, aber aus verschiedenen Gründen: `canExport` bräuchte
einen Endpunkt, den es seit `012-001-0003` nicht mehr gibt; `getExtended` müsste die Feldauswahl
der API einschränken, und das wäre neue Funktionalität in einem Aufräum-Task.

### Was bleibt und warum

**Die Spalten `pim_permission.export` und `pim_permission.extended` sind unangetastet.** In
einem Bestandsprojekt stehen dort möglicherweise Werte; Daten wegzuwerfen ist die nicht
umkehrbare Richtung, und der Zweck des Entfernens war die falsche Behauptung, nicht die Zahl in
der Spalte. Lesbar bleiben sie über `Areanet\PIM\Entity\Permission`. Ein eigener Test hält das
fest, damit es nicht bei der Absicht bleibt.

Entfallen sind `Areanet\PIM\Classes\Permission::canExport()` und `::getExtended()` — ohne die
Schema-Schlüssel ruft sie niemand mehr. Ein Projekt, das sie aufruft, bekommt einen Fehler.
Das ist beabsichtigt: laut ist besser als still, und still war der ganze Befund.

### Der Stufen-Kollaps

**Nicht repariert, sondern mit dem Feld entfernt.** `canExport` lautete
`return ($permission->getExport() == 2)`; weil die Konstanten nicht aufsteigend geordnet sind
(`NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3), ergab ausgerechnet `GROUP` ein `false`. Jede Reparatur
hätte entschieden, welche Benutzer künftig dürfen — für ein Recht, das niemand prüft. Die
Entscheidung gehört an den Endpunkt, den es dann gibt; die Vorgeschichte steht in
`breaking-changes.md`, damit sie nicht verloren geht.

### Der Schema-Vergleich, den die Verification verlangt

Volles `/api/schema` vor und nach der Änderung, sortiert verglichen:

| | |
|---|---|
| Unterschiedliche Zeilen | 32 |
| davon `"export": true` | 14 — je Entity eine |
| davon `"extended": null` | 14 — je Entity eine |
| davon Hash | 2 (`hash` und `_hash`, dieselbe Zahl) |

Vierzehn Entities, zwei Schlüssel, plus der Hash, der sich ändern **muss** — genau dafür gibt es
ihn. Sonst verschwindet nichts.

### Tests

Drei Zusicherungen umgedreht statt gelöscht: Der `permissions`-Block führt jetzt drei Schlüssel
statt fünf, die beiden Felder kommen auch für einen Nicht-Admin nicht mehr an, und neu
hinzugekommen ist der Nachweis, dass die Spalten mit ihren Werten stehen bleiben. Die beiden
Tests zur Wirkungslosigkeit bleiben **unverändert** stehen — sie hielten vorher einen
Widerspruch fest und halten jetzt eine Aussage fest.

**Beim Testaufbau danebengegriffen:** Ich habe die Spalte `group` abgefragt; sie heisst
`group_id`. Doctrine haengt bei einer Beziehung `_id` an, und ich hatte vom Feldnamen der Entity
auf den Spaltennamen geschlossen.

### Nachweis

- Volle Suite `OK (244 tests, 608 assertions)`, 0 übersprungen
- Schema-Vergleich wie oben
- Zwei Einträge in `an_project/docs/breaking-changes.md`; die Lückenliste in `technical.md`
  verliert ihren zweiten Eintrag
