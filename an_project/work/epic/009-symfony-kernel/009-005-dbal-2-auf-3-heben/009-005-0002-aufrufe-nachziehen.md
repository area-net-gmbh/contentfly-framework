---
id: 009-005-0002
title: Die entfallenen DBAL-Aufrufe nachziehen
status: todo
depends_on: [009-005-0001]
---

# Die entfallenen DBAL-Aufrufe nachziehen

## Context
Die gemessenen Bruchstellen aus `009-005-0001` beheben.

> **Erweitert am 2026-09-09, nach der Messung.** Dieser Task ging von neun Aufrufstellen aus.
> Die Messung hat einen zehnten Befund ergeben, der alle anderen überwiegt: **Die Id-Erzeugung
> bricht.** `Entity\Base` trägt `@ORM\GeneratedValue(strategy="UUID")`, und Doctrines
> `UuidGenerator` fragt die Datenbank über `AbstractPlatform::getGuidExpression()` — die Methode
> gibt es in DBAL 3 nicht mehr. Betroffen ist damit jede Entity, die von `Base` erbt; die Suite
> meldete 173 Fehlschläge von 249 Tests, und der Grund war dieser eine.
>
> Doctrines eigener Quelltext sagt, was an die Stelle gehört: „use an application-side generator
> instead". `ramsey/uuid` liegt bereits im Baum und wird in `Api.php:320` für
> `BaseI18n`-Objekte schon so benutzt.
>
> Das ist die Korrektur einer falschen Schätzung, keine Erweiterung des Umfangs: Ohne die
> Id-Erzeugung ist von dieser Story nichts prüfbar.
>
> **Zu entscheiden ist die UUID-Version.** Die vorhandenen Ids in der Datenbank sind **v1**
> (`7697af14-ac72-11f1-…`, aus MySQLs `UUID()`), `Api.php` erzeugt für i18n-Objekte **v4**. Der
> Baum vermischt heute also zwei Herkünfte. Beide Versionen belegen dieselbe Spalte und
> erzwingen keine Migration; alte Zeilen behalten ihre Ids in jedem Fall.

Die übrigen Stellen, alle in `Classes/Api.php`:

| Alt | Neu | Fundstellen | Art |
|---|---|---|---|
| `Connection::fetchAll()` | `fetchAllAssociative()` | 2 | **entfallen** |
| `Connection::fetchAssoc()` | `fetchAssociative()` | 1 | **entfallen** |
| `QueryBuilder::execute()->fetchAll()` | `executeQuery()->fetchAllAssociative()` | 2 | **entfallen** am Result |
| `Connection::exec()` | `executeStatement()` | 1 | vorhanden, deprecated |
| `QueryBuilder::execute()` | `executeQuery()` | 2 | vorhanden, deprecated |
| `Connection::executeQuery()->rowCount()` | unverändert | 3 | vorhanden — `executeQuery()` liefert ein `Result`, das `rowCount()` hat |

Die letzten drei Zeilen sind gegenüber dem ursprünglichen Text richtiggestellt: Sie sind keine
Bruchstellen, sondern Deprecations. Nachgezogen werden sie trotzdem — das Gate aus `006-005`
liest `doctrine/deprecations` mit —, aber sie halten nichts auf.

**Die `rowCount()`-Stellen sind die heiklen.** In DBAL 2 liefert `executeQuery()` ein
`Statement`, in DBAL 3 ein `Result`; `rowCount()` ist bei einem SELECT auf beiden nicht
zuverlässig definiert. Die drei Stellen zählen Datensätze für `/api/count` — ob sie das heute
richtig tun, ist beim Anfassen zu prüfen und nicht anzunehmen.

`exec('SET FOREIGN_KEY_CHECKS = 0;')` ist die einzige Stelle, an der das Framework
Fremdschlüssel abschaltet. Sie gehört gelesen, bevor sie umgeschrieben wird.

## Acceptance criteria
- [ ] Die Id-Erzeugung läuft ohne Datenbank-Ausdruck: ein eigener Generator auf Basis von
      `ramsey/uuid`, an `Base`, `BaseI18n` und `Log` gleichermassen.
- [ ] Die gewählte UUID-Version ist **begründet** festgehalten, einschliesslich dessen, was sie
      für die schon vorhandenen v1-Ids und für `Api.php:320` bedeutet.
- [ ] Jede gemessene Bruchstelle ist behoben; keine davon durch Unterdrücken einer Meldung.
- [ ] Die drei `rowCount()`-Stellen sind auf ihre Richtigkeit **geprüft**, nicht nur auf ihre
      Lauffähigkeit. Zählen sie falsch, ist das ein Befund und wird als solcher festgehalten.
- [ ] Kein Verhalten ändert sich: Die Suite aus Epic `008` ist grün, ohne inhaltliche Änderung
      an einer Zusicherung.
- [ ] Wo eine Zusicherung doch angepasst werden muss, steht die Begründung je Zusicherung.

## Verification
Volle Suite grün gegen eine frisch installierte Wegwerf-Datenbank, plus die
Deprecation-Prüfung: DBAL 3 meldet über `doctrine/deprecations`, und was dort auftaucht, gehört
in die Bewertung.
