---
id: 009-005-0000
title: DBAL 2 auf 3 heben — die Vorbedingung des Kernel-Schnitts
status: done
depends_on: []
---

# DBAL 2 auf 3 heben — die Vorbedingung des Kernel-Schnitts

## Goal
`doctrine/dbal` steht auf 3.10, die Suite aus Epic `008` ist grün, und **Silex läuft dabei
weiter**. Nach dieser Story ist der Kernel-Schnitt (`009-002`) nicht mehr durch Doctrine
blockiert.

## Warum es diese Story gibt
Aufgefallen beim ersten Versuch von `009-002-0001`, und zwar sofort:

```
symfony/http-foundation[v7.4.0, ..., v7.4.18] conflict with doctrine/dbal <3.6.
```

Das ist ein **harter Konflikt**, kein Constraint, den man umgehen kann. Er ist mit
`symfony/http-foundation` **v7.1.7** dazugekommen — in v7.0.0 gibt es ihn noch nicht. Auf einer
alten Patch-Version stehenzubleiben hiesse, auf Sicherheitsfixes zu verzichten, also genau auf
das, wogegen Epic `009` antritt.

**Damit ist die Abgrenzung des Epics widerlegt.** Sie sagte „Kein Doctrine-Umbau — `009` lässt
Doctrine, wie es ist"; das ist nicht möglich. Der Satz ist im Epic entsprechend richtiggestellt,
nicht stillschweigend übergangen.

## Warum eine eigene Story und nicht ein Task in `009-002`
**Weil sie sich prüfen lässt.** Silex nagelt `symfony/*` auf `^4.0` fest — Doctrine nicht. DBAL 3
lässt sich also installieren, während der alte Kernel weiterläuft, und die **volle Suite läuft
dabei**. Das ist der einzige Teil des Umbaus, für den das gilt.

Eigenständig gemergt heisst: Der riskante Schnitt startet von einem nachweislich guten Stand,
und wenn später etwas klemmt, ist die Doctrine-Änderung einzeln zurücknehmbar. Läge sie im
Schnitt-Merge, wäre sie es nicht.

## Umfang, gemessen
`doctrine/orm` bleibt auf **2.20** — es erlaubt `doctrine/dbal ^2.13.1 || ^3.2` bereits. Die
Annotationen und der Entity-Layer bleiben unberührt; Epic `010` behält seinen Umfang bis auf den
DBAL-Teil.

Die eigene DBAL-Oberfläche ist klein:

| Aufruf | Fundstellen | In DBAL 3 |
|---|---|---|
| `Connection::fetchAll()` | 2 | entfallen → `fetchAllAssociative()` |
| `Connection::fetchAssoc()` | 1 | entfallen → `fetchAssociative()` |
| `Connection::exec()` | 1 | entfallen → `executeStatement()` |
| `QueryBuilder::execute()->fetchAll()` | 2 | `executeQuery()->fetchAllAssociative()` |
| `Connection::executeQuery()->rowCount()` | 3 | bleibt, Rückgabetyp geändert |

Dazu die achtzehn Doctrine-Console-Commands in `bin/console.php`: `ImportCommand` gibt es in
DBAL 3 nicht mehr, und die Helper-Konstruktion hat sich geändert.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 009-005-0001 — DBAL 3.10 installieren und den Bruch sichtbar machen
- [x] 009-005-0002 — Die entfallenen DBAL-Aufrufe nachziehen
- [x] 009-005-0003 — Die Doctrine-Console-Commands und der Nachweis

## Ergebnis

**`doctrine/dbal` steht auf 3.10.6, Silex läuft weiter, und die Suite ist grün:
`OK (249 tests, 616 assertions)`, 0 übersprungen — ohne eine einzige geänderte Zusicherung.**
`009-002` ist damit entblockiert; die Gegenprobe steht in `009-005-0003`.

Die Auflösung ist so eng geblieben, wie sie sollte: **zwei** Pakete bewegt (`doctrine/dbal`,
`doctrine/event-manager`), Gesamtzahl unverändert 78, `doctrine/orm` auf 2.20.13,
keine `symfony/*`-Komponente gerührt.

### Der Umfang war anders als geschätzt — in beide Richtungen

Die Story wurde mit einer Tabelle von neun Aufrufstellen geschnitten. Die Messung in
`009-005-0001` hat sie in beiden Richtungen widerlegt, und das war der Zweck des Tasks.

**Grösser, an einer Stelle, die alles überwog.** `Entity\Base` trägt
`@ORM\GeneratedValue(strategy="UUID")`, und Doctrines `UuidGenerator` fragt die Datenbank über
`AbstractPlatform::getGuidExpression()` — in DBAL 3 entfallen. Betroffen ist damit jede Entity,
die von `Base` erbt: **173 von 249 Tests**, nicht neun Aufrufstellen. Dazu zwei weitere
Bruchstellen, die die Messung selbst nicht gefunden hat, weil sie über die **Plattform** gehen
statt über die Connection: `ContentflyQuoteStrategy::getColumnAlias()` rief
`getSQLResultCasing()`, woran jede DQL-Abfrage starb, und `AbstractPlatform::getName()` ist
deprecated.

**Kleiner, an drei Stellen.** `Connection::exec()`, `QueryBuilder::execute()` und
`executeQuery()->rowCount()` gibt es in DBAL 3 weiterhin — nur deprecated. Sie sind trotzdem
nachgezogen, aber sie hielten nichts auf. Und von achtzehn Console-Commands fällt genau **einer**
weg.

### Die Entscheidungen

**UUID Version 4, obwohl die vorhandenen Ids v1 sind.** Weil `Api.php` für `BaseI18n`-Objekte
seit jeher selbst `uuid4()` erzeugt — es gab nie eine einheitliche Herkunft —, und weil eine
v1-UUID die MAC-Adresse des Servers und den Erzeugungszeitpunkt trägt, die in jeder API-Antwort
stehen. Für vorhandene Daten ändert sich nichts. **v7 erwogen und verworfen:** besser für den
Index, aber eine Performance-Entscheidung ohne Messung, und eine dritte Id-Form in einem Baum,
der gerade auf eine gebracht wird.

**`ContentflyQuoteStrategy` erbt jetzt von Doctrines `DefaultQuoteStrategy`** und **erbt**
`getColumnAlias()`, statt sie abzuschreiben. Geerbt bringt der nächste Doctrine-Sprung sie mit;
abgeschrieben wäre sie beim übernächsten wieder falsch. Alle übrigen Methoden bleiben
überschrieben — Doctrine quotiert nur, was als `quoted` markiert ist, dieses Framework
quotiert grundsätzlich.

**Provider statt `HelperSet`** in der Konsole, auf beiden Seiten. Das ORM-`HelperSet` gäbe es
noch, ist aber deprecated; zwei Wege nebeneinander wären einer zu viel.

### Vier Befunde, die stehen bleiben

Alle vier sind **älter als diese Story** und keiner hindert den Kernel-Wechsel. Sie gehören als
eigene Tasks aufgeschrieben.

1. **Die drei `rowCount()`-Stellen** fragen `SELECT 1 FROM …` ab und zählen dann die Zeilen —
   sie holen den ganzen Treffersatz in den Speicher, um ihn zu zählen. Richtig wäre
   `SELECT COUNT(*)` mit `fetchOne()`. DBAL sagt ausdrücklich, dass `rowCount()` für ein
   `SELECT` nicht garantiert ist; mit DBAL 4 könnte daraus eine Bruchstelle werden.
2. **Der Index `modified_index` fehlt in jeder installierten Datenbank.** `LoadMetadata`
   schreibt ihn ins Mapping, aber `bootstrap.php` registriert den Listener nur, wenn
   `is_installed` wahr ist — während `appcms:install` läuft, ist er das nicht. Gegengeprüft an
   einer DBAL-2-Datenbank: dort fehlt er ebenso.
3. **`BaseI18nTree` hat eine ungültige Zuordnung** — die Join-Spalten von `treeParent` decken
   nicht alle Identifier-Spalten (`id, lang`) ab.
4. **Ein Akzeptanzkriterium war falsch geschnitten.** `009-005-0002` verlangte eine grüne Suite,
   die es nicht liefern konnte, weil `bin/console.php` zu `009-005-0003` gehört. Richtiggestellt
   statt falsch abgehakt.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (249 tests, 616 assertions)`, 0 übersprungen |
| Zwischenstand nach dem Sprung | 173 Failures |
| `appcms:install` | auf einer frisch angelegten Datenbank durchgelaufen |
| Erzeugte Id | `4b09ed3e-8403-4ca0-…` — Version 4 |
| Konsole | 20 Commands, `dbal:run-sql` und `orm:validate-schema` laufen |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen — DBAL 3 bringt keine mit |
| Postausgang | 0 Byte |
| Entblockierung | `composer update --dry-run` mit Symfony 7.4 löst auf und entfernt silex und pimple |
