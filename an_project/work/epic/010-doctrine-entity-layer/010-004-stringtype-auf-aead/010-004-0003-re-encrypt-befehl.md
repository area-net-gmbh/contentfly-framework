---
id: 010-004-0003
title: Der Re-Encrypt-Befehl mit Trockenlauf und Rückweg
status: done
depends_on: [010-004-0002]
---

# Der Re-Encrypt-Befehl mit Trockenlauf und Rückweg

## Context
Ein Befehl, der vorhandene Werte vom alten auf das neue Format bringt. Er läuft **in dieser
Story auf keinen echten Daten** — im Baum gibt es keine Entity mit `encoded=true`. Belegt wird
er gegen eine eigene Testentity.

**Trockenlauf und Rückweg gehören zur Abnahme, nicht zur Kür.** Der Befehl läuft später in
fremden Bestandsprojekten auf deren Daten. Eine Migration, die man nicht vorher ansehen und
nicht zurücknehmen kann, wird zu Recht nicht ausgeführt.

**Was er finden muss:** jede Eigenschaft mit `encoded=true` in jeder Entity, auch in denen eines
Projekts — er darf nicht auf die Framework-Entities eingeschränkt sein.

## Acceptance criteria
- [x] Der Befehl findet die betroffenen Eigenschaften über das Schema, nicht über eine feste Liste.
- [x] `--dry-run` zeigt, was er täte, und ändert nichts — nachgewiesen an unveränderten Datenbankzeilen.
- [x] Werte, die bereits im neuen Format liegen, werden übersprungen; ein zweiter Lauf ändert nichts.
- [x] Der Rückweg ist beschrieben und ausführbar: was zu sichern ist, bevor er läuft, und wie man zurückkommt.
- [x] Er arbeitet in Stapeln und hält den Speicher konstant — eine Tabelle mit vielen Zeilen darf ihn nicht umbringen.
- [x] Ein Fehler mitten im Lauf lässt keinen halb umgeschlüsselten Datensatz zurück.

## Verification
Eine Testentity mit `encoded=true`, gefüllt mit Werten im alten Format. Dann: Trockenlauf
(nichts ändert sich), echter Lauf (alle Werte neu), zweiter Lauf (nichts mehr zu tun), und ein
Lesevorgang über die API, der den Klartext unverändert zurückgibt.

## Ergebnis

**`appcms:security:reencrypt` steht, mit `--dry-run` und `--batch`.** Die Suite wächst von 277
auf **282**; die fünf neuen Tests bringen 39 Assertions.

Auf dem echten Baum meldet der Befehl, was er soll:

```
Kein Feld mit encoded: true — es gibt nichts umzuschluesseln.
```

### Warum der Test seine eigene Tabelle anlegt

Es gibt im Framework kein Feld mit `encoded: true`. Ein Test, der den Befehl von aussen aufriefe,
könnte nur bestätigen, dass er nichts tut — und das ist keine Zusicherung über die
Umschlüsselung.

`ReencryptCommandTest` legt deshalb `probe_reencrypt` an, füllt sie mit Werten im **alten**
Format und lässt den echten Codepfad darüberlaufen. Gemessen wird an echten Zeilen, nicht an
einer Attrappe. Die Tabelle wird am Ende entfernt und berührt kein Schema des Frameworks.

Zwei Methoden des Befehls sind dafür öffentlich — `betroffeneFelder()` und
`spalteUmschluesseln()`. Das ist der Preis der Prüfbarkeit und an beiden Stellen begründet.

### Was die Tests zusichern

| Test | Zusicherung |
|---|---|
| `testDerTrockenlaufAendertNichts` | Er zählt drei, und die Zeilen sind Byte für Byte dieselben |
| `testDerEchteLaufSchluesseltUmUndDerZweiteFindetNichtsMehr` | Klartext unverändert, Format neu, zweiter Lauf 0 umgeschlüsselt |
| `testErArbeitetInStapelnUndErwischtAlleZeilen` | 25 Zeilen bei Stapelgrösse 4 — keine bleibt liegen, kein Kreisel |
| `testEinUnlesbarerWertBrichtDenStapelAbUndLaesstIhnUnveraendert` | Transaktion greift, kein halber Stand |
| `testHeuteGibtEsKeinFeldMitEncoded` | Die leere Ausgabe ist gewollt, nicht defekt |

**Der dritte ist der, der einen echten Fehler gefunden hätte:** Die Stapelabfrage arbeitet mit
Keyset-Paginierung über den Primärschlüssel. Wäre sie über `OFFSET` gelaufen, hätte der Lauf
Zeilen übersprungen oder sich im Kreis gedreht, sobald der erste Stapel geschrieben ist.

### DBAL statt EntityManager, und warum das zählt

Über den EntityManager müsste der Befehl jede Entity laden. Das hätte drei Folgen: den ganzen
Bestand im Speicher, die Lifecycle-Callbacks am Hals — `Base::updateModifiedDatetime()` würde
`modified` fortschreiben, obwohl sich fachlich nichts ändert — und keinen Weg, in Stapeln zu
arbeiten. Über DBAL sind es Stapel fester Grösse und ein `UPDATE` je Zeile.

### Der Rückweg, und was er wirklich abdeckt

In `deployment.md` beschrieben: Sicherung, Trockenlauf, Lauf, zweiter Trockenlauf als Prüfung.

**Ein abgebrochener Lauf ist kein Schaden** — jeder Stapel ist eine Transaktion, beide Formate
bleiben lesbar, und ein erneuter Start macht dort weiter, wo er aufgehört hat. Die Sicherung
deckt einen anderen Fall ab: dass jemand mit dem **falschen** `SECURITY_CIPHER_KEY` gelaufen
ist. Dann sind die Werte nicht kaputt, aber mit einem Schlüssel verschlüsselt, den niemand
wollte. Der Befehl hält an, sobald sich ein Wert nicht entschlüsseln lässt, und nennt genau
diesen Verdacht.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `ReencryptCommandTest` | `OK (5 tests, 39 assertions)` |
| Volle Suite | `OK (282 tests, 692 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `appcms:security:reencrypt --dry-run` auf dem echten Baum | meldet, dass es nichts zu tun gibt |
| `php bin/console.php list` | der Befehl steht unter `appcms:` |
