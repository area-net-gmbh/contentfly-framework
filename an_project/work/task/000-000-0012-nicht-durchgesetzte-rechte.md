---
id: 000-000-0012
title: Die nicht durchgesetzten Berechtigungsfelder entscheiden
status: todo
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
- [ ] Es ist entschieden und begründet festgehalten, welche Richtung gilt.
- [ ] Der Umgang mit `canExport`s Stufen-Kollaps ist mitentschieden.
- [ ] Die Tests aus `008-003-0005` sind auf das neue Verhalten gedreht — bewusst, nicht durch
      Löschen.
- [ ] Ist die Wahl eine Verhaltens- oder Schemaänderung, ist sie als Breaking Change für
      Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün mit den angepassten Zusicherungen; bei Variante 1 zusätzlich
ein Schema-Vergleich, der belegt, dass nur diese beiden Schlüssel verschwinden.
