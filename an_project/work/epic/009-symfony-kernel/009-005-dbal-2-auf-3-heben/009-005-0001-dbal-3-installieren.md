---
id: 009-005-0001
title: DBAL 3.10 installieren und den Bruch sichtbar machen
status: review
depends_on: []
---

# DBAL 3.10 installieren und den Bruch sichtbar machen

## Context
Der erste Schritt, und er soll ausdrücklich **nicht** grün enden: `doctrine/dbal` auf `^3.10`
heben und danach messen, was bricht. Die Liste der entfallenen Methoden ist bekannt, aber eine
Liste aus der Dokumentation ist keine Messung — es geht darum, jede Stelle zu **sehen**, bevor
sie angefasst wird.

`doctrine/orm` bleibt auf 2.20: Es erlaubt `doctrine/dbal ^2.13.1 || ^3.2` bereits, und alles,
was darüber hinausgeht, gehört zu Epic `010`. Silex bleibt ebenfalls stehen — es nagelt
`symfony/*` fest, nicht Doctrine.

## Acceptance criteria
- [x] `composer.json` fordert `doctrine/dbal ^3.10`; `composer update` löst auf, ohne dass
      `doctrine/orm`, `silex/silex` oder eine `symfony/*`-Komponente die Hauptversion wechselt.
- [x] Die Liste der Bruchstellen ist **gemessen**, nicht abgeschrieben: volle Suite laufen
      lassen und jeden Fehlschlag mit Datei, Zeile und Ursache festhalten.
- [x] `composer audit --locked` ist ausgewertet; ändert sich etwas an der Ausnahmeliste aus
      `006-005-0001`, steht der Grund dabei.
- [x] Das Ergebnis nennt auch, was **nicht** gebrochen ist — eine erwartete Bruchstelle, die
      ausbleibt, ist genauso ein Befund.

## Verification
`composer update`, dann die volle Suite gegen eine Wegwerf-Datenbank. Erwartet wird **rot**; das
Ergebnis dieses Tasks ist die Liste, nicht die grüne Suite.

## Ergebnis

`doctrine/dbal` steht auf **3.10.6**. Die Auflösung ist so eng ausgefallen, wie sie sollte:
Zwei Pakete haben sich bewegt, die Gesamtzahl ist unverändert.

| Paket | vorher | nachher |
|---|---|---|
| `doctrine/dbal` | 2.13.9 | 3.10.6 |
| `doctrine/event-manager` | 1.2.0 | 2.1.1 |
| Pakete gesamt | 78 | 78 |

`doctrine/orm` bleibt auf 2.20.13, `silex/silex` auf 2.3.0, keine `symfony/*`-Komponente hat sich
bewegt. Genau das war die Voraussetzung dafür, dass diese Story eigenständig prüfbar ist.

### Der Befund, mit dem der Task nicht gerechnet hat

Die Suite meldet **173 Fehlschläge von 249 Tests** — nicht neun Aufrufstellen, sondern fast
alles. Der Grund ist einer, und er steht in der Antwort auf `GET /api/config`:

```
Context: Using the database to generate a UUID through Doctrine\ORM\Id\UuidGenerator
Problem: Feature was deprecated in doctrine/dbal 2.x and is not supported by
         installed doctrine/dbal:3.x
```

**Die Id-Erzeugung selbst bricht.** `Entity\Base` trägt
`@ORM\GeneratedValue(strategy=APPCMS_ID_STRATEGY)`, und bei GUID-Strategie ist das `UUID` —
also Doctrines `UuidGenerator`. Der fragt die Datenbank über
`AbstractPlatform::getGuidExpression()`, und die Methode gibt es in DBAL 3 nicht mehr. Doctrines
eigener Quelltext sagt, was an ihre Stelle gehört:

> `@deprecated use an application-side generator instead`

Damit ist jede Entity betroffen, die von `Base` erbt — nicht ein Codepfad, sondern der
Normalfall. Das ist der eigentliche Umfang dieser Story, und er stand in keiner der Tabellen,
mit denen sie geschnitten wurde.

**Ein Anhaltspunkt liegt schon im Baum:** `Api.php:320` erzeugt für `BaseI18n`-Objekte bereits
selbst eine Id, mit `Ramsey\Uuid\Uuid::uuid4()`. Das Paket ist also vorhanden und im Einsatz,
und der Baum vermischt heute schon zwei Id-Herkünfte — MySQLs `UUID()` für alles aus `Base`,
ramseys v4 für i18n-Objekte. Vorhandene Ids in der Datenbank sind **Version 1**
(`7697af14-ac72-11f1-…`), was MySQLs `UUID()` entspricht.

### Die harten Bruchstellen, gemessen

| Stelle | Was fehlt |
|---|---|
| `Entity\Base` und alles, was davon erbt | `ORM\Id\UuidGenerator` wirft im Konstruktor |
| `Classes/Api.php:905` | `Connection::fetchAssoc()` |
| `Classes/Api.php:983`, `:2084` | `Connection::fetchAll()` |
| `Classes/Api.php:1940`, `:2358` | `QueryBuilder::execute()->fetchAll()` — `fetchAll()` am Result |
| `bin/console.php:14`, `Controller/SystemController.php:12` | `DBAL\Tools\Console\Helper\ConnectionHelper` |
| `bin/console.php:34` | `DBAL\Tools\Console\Command\ImportCommand` |

`php bin/console.php list` stirbt an der ersten dieser beiden Console-Stellen — die Konsole ist
komplett unbenutzbar, `appcms:install` eingeschlossen.

### Was **nicht** gebrochen ist — auch das ist ein Befund

Der Task ging von einer Tabelle aus, die drei Dinge zu hart eingeschätzt hat:

| Erwartet entfallen | Tatsächlich |
|---|---|
| `Connection::exec()` | **vorhanden**, mit `@deprecated` |
| `QueryBuilder::execute()` | **vorhanden**, mit `@deprecated` |
| `executeQuery()->rowCount()` | **vorhanden** — `executeQuery()` liefert jetzt ein `Result`, und das hat `rowCount()` |

Die drei sind also keine Bruchstellen, sondern Deprecations. Sie gehören trotzdem nachgezogen:
Das Gate aus `006-005` liest `doctrine/deprecations` mit, und Epic `009` will deprecation-frei
bauen. Aber sie halten nichts auf, und das ändert ihre Dringlichkeit.

Ebenfalls unverändert nutzbar: `ReservedWordsCommand`, `RunSqlCommand`, `EntityManagerHelper`
und die ORM-Commands aus `bin/console.php`. Von achtzehn registrierten Commands fällt genau
**einer** weg.

### `composer audit`

Unverändert fünf ignorierte Meldungen, alle zu Symfony-4-Paketen — die Liste aus `006-005-0001`
ist von dieser Story nicht berührt. Sie fällt mit `009-002`. Neu ist nichts dazugekommen:
DBAL 3.10.6 bringt keine Meldung mit.

### Folge für den Schnitt der Story

`009-005-0002` muss um die Id-Erzeugung erweitert werden — das ist keine Erweiterung des
Umfangs, sondern die Korrektur einer falschen Schätzung. Die Entscheidung, **welche
UUID-Version** ein eigener Generator erzeugt, gehört dorthin und wird nicht hier vorweggenommen.

## Verification — durchgeführt

`composer update` (2 Pakete bewegt), volle Suite gegen die Wegwerf-Datenbank: **173 Failures von
249 Tests**, wie erwartet rot. `composer audit --locked` ausgewertet. Statische Prüfung jeder
gerufenen DBAL-Methode gegen `Doctrine\DBAL\Connection` und `…\Query\QueryBuilder` in 3.10.6.
