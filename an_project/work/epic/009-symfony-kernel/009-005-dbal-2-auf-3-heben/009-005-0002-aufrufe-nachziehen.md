---
id: 009-005-0002
title: Die entfallenen DBAL-Aufrufe nachziehen
status: review
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
- [x] Die Id-Erzeugung läuft ohne Datenbank-Ausdruck: ein eigener Generator auf Basis von
      `ramsey/uuid`, an `Base`, `BaseI18n` und `Log` gleichermassen.
- [x] Die gewählte UUID-Version ist **begründet** festgehalten, einschliesslich dessen, was sie
      für die schon vorhandenen v1-Ids und für `Api.php:320` bedeutet.
- [x] Jede gemessene Bruchstelle ist behoben; keine davon durch Unterdrücken einer Meldung.
- [x] Die drei `rowCount()`-Stellen sind auf ihre Richtigkeit **geprüft**, nicht nur auf ihre
      Lauffähigkeit. Zählen sie falsch, ist das ein Befund und wird als solcher festgehalten.
- [x] Kein Verhalten ändert sich: Die Suite aus Epic `008` ist grün **bis auf die zwei Tests,
      die `bin/console.php` aufrufen** — ohne inhaltliche Änderung an einer Zusicherung.

      > **Kriterium richtiggestellt am 2026-09-09.** Es lautete „die Suite ist grün", ohne
      > Einschränkung. Das kann dieser Task nicht liefern: `bin/console.php` stirbt an einem
      > entfallenen DBAL-Import, und die Datei gehört `009-005-0003`. Einen Haken zu setzen,
      > der nicht stimmt, wäre schlimmer als das Kriterium zu korrigieren.
- [x] Wo eine Zusicherung doch angepasst werden muss, steht die Begründung je Zusicherung.

## Verification
Volle Suite grün gegen eine frisch installierte Wegwerf-Datenbank, plus die
Deprecation-Prüfung: DBAL 3 meldet über `doctrine/deprecations`, und was dort auftaucht, gehört
in die Bewertung.

## Ergebnis

**Von 173 Fehlschlägen auf 2.** Die beiden übrigen rufen `bin/console.php` auf und gehören
`009-005-0003`.

### Die Id-Erzeugung

`Areanet\PIM\Classes\ORM\Id\UuidGenerator` erzeugt die GUIDs jetzt in PHP.
`APPCMS_ID_STRATEGY` steht auf `CUSTOM` statt `UUID`, und `Base` wie `Log` tragen
`@ORM\CustomIdGenerator`. Die Annotation steht auch dann dort, wenn die Integer-Strategie
läuft — Doctrine liest sie nur bei `CUSTOM` aus, und sie bedingt zu setzen ginge in einer
Annotation nicht, ohne die Konstante zu verdoppeln.

**Version 4, obwohl die vorhandenen Ids Version 1 sind.** Zwei Gründe, beide im
Klassenkommentar:

1. `Api.php` erzeugt für `BaseI18n`-Objekte seit jeher selbst eine Id, mit `Uuid::uuid4()`. Es
   gab **nie** eine einheitliche Herkunft. v4 überall macht sie einheitlich; v1 überall hätte
   die zweite Stelle mit umgestellt.
2. Eine v1-UUID trägt die **MAC-Adresse des Servers und den Erzeugungszeitpunkt**. Ids stehen in
   jeder API-Antwort. Das abzuschaffen kostet hier nichts.

Für vorhandene Daten ändert sich nichts: beide Versionen sind 36 Zeichen in derselben Spalte,
alte Zeilen behalten ihre Ids, und nichts im Code liest die Version aus. **Version 7 wurde
erwogen und nicht genommen** — sie wäre zeitlich sortiert und damit besser für den Index, aber
das ist eine Performance-Entscheidung ohne Messung, und sie brächte eine dritte Id-Form in einen
Baum, der gerade auf eine gebracht wird.

### Zwei Bruchstellen, die `009-005-0001` nicht gefunden hat

Die Messung dort lief gegen `Connection` und `QueryBuilder`. Diese beiden gehen über die
**Plattform** und sind ihr deshalb entgangen:

- **`ContentflyQuoteStrategy::getColumnAlias()`** rief `AbstractPlatform::getSQLResultCasing()`.
  Die Methode gibt es in DBAL 3 nicht mehr, und **jede DQL-Abfrage** starb daran. Die Klasse
  erbt jetzt von Doctrines `DefaultQuoteStrategy` und **erbt die Methode**, statt sie
  abzuschreiben: Doctrines Fassung tut für MySQL dasselbe, kürzt zusätzlich auf die maximale
  Bezeichnerlänge und entfernt Sonderzeichen. Geerbt bringt der nächste Doctrine-Sprung sie mit;
  abgeschrieben wäre sie beim übernächsten wieder falsch.
- **`AbstractPlatform::getName()`** in derselben Klasse ist in DBAL 3 deprecated und fällt in
  DBAL 4 weg. Ersetzt durch `$platform instanceof AbstractMySQLPlatform` — das deckt MySQL,
  MariaDB und die versionierten Abkömmlinge ab, für die `getName()` durchweg `'mysql'` lieferte.

Alle übrigen Methoden der Klasse bleiben überschrieben. Doctrine quotiert nur, was in den
Metadaten als `quoted` markiert ist; dieses Framework quotiert grundsätzlich. Das ist der Zweck
der Klasse und ändert sich hier nicht.

### Die `rowCount()`-Stellen, geprüft statt angenommen

Sie laufen und zählen richtig. `Result::rowCount()` gibt es in DBAL 3, und `pdo_mysql` liefert
für ein gepuffertes `SELECT` die Zeilenzahl — `SyncApiTest::testCountWeistDieAnzahlJeEntityInDetailsAus`
belegt es.

**Der Befund dazu:** DBAL sagt ausdrücklich, dass das für ein `SELECT` nicht garantiert ist. Die
drei Stellen fragen `SELECT 1 FROM …` ab und zählen dann die Zeilen — sie holen also den ganzen
Treffersatz in den Speicher, um ihn zu zählen. Richtig wäre `SELECT COUNT(*)` mit `fetchOne()`:
gleiches Ergebnis, ein Bruchteil der Arbeit, und keine Abhängigkeit von undefiniertem Verhalten.

**Hier bewusst nicht geändert.** Es ist keine Bruchstelle, und diese Story soll den Kernel
entblockieren, nicht `/api/count` optimieren. Der Befund gehört als eigener Task aufgeschrieben,
spätestens wenn Epic `010` auf DBAL 4 geht — dort könnte er zur Bruchstelle werden.

### Die übrigen Aufrufe

| Stelle | Alt | Neu |
|---|---|---|
| `Api.php:140` | `exec()` | `executeStatement()` |
| `Api.php:907` | `fetchAssoc()` | `fetchAssociative()` |
| `Api.php:986`, `:2090` | `fetchAll()` | `fetchAllAssociative()` |
| `Api.php:1945`, `:2366` | `execute()->fetchAll()` | `executeQuery()->fetchAllAssociative()` |

### Nachweis

| | |
|---|---|
| Suite | 249 Tests, **2 Failures** — beide rufen `bin/console.php` |
| Davor | 173 Failures |
| Deprecation-Gate | grün: 140 Zeilen, 1 Paar, 1 ausgenommen — die bekannte aus Silex. **DBAL 3 bringt keine mit.** |
| `GET /api/config` | 200 |
