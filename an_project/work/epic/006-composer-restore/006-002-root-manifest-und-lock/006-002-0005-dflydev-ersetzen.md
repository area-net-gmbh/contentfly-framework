---
id: 006-002-0005
title: dflydev-Service-Provider durch eigenen Aufbau ersetzen
status: todo
depends_on: [006-002-0002]
---

# dflydev-Service-Provider durch eigenen Aufbau ersetzen

## Context
Dieser Task entstand **während** `006-002-0003`, als der erste `composer update` gegen das
neue Manifest lief. Die Anwendung bootete nicht — und die Ursache ist nicht mit einem
Constraint zu beheben.

`dflydev/doctrine-orm-service-provider` **v2.0.1 ist die letzte Version** (2018; es gibt kein
v3) und benutzt:

```php
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
```

`doctrine/persistence` 2.0 hat diesen Namensraum nach `Doctrine\Persistence\` verschoben.
Nachgemessen im aufgelösten Baum:

```
Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain   FEHLT
Doctrine\Persistence\Mapping\Driver\MappingDriverChain          da
```

`doctrine/orm` 2.20 verlangt `doctrine/persistence ^2.4 || ^3`. **Der Provider und ein
PHP-8-taugliches ORM schliessen sich aus.**

> **Warum Composer das trotzdem auflöst:** `dflydev` deklariert keine Constraints auf
> `doctrine/persistence`. Der Bruch ist deshalb im Lock nicht sichtbar — er zeigt sich erst
> zur Laufzeit, beim ersten Zugriff auf `$app['orm.em']`. Genau die Sorte Fehler, die ein
> `--dry-run` nicht findet und ein Testlauf schon.

## Umfang

### Was ersetzt wird
Der Provider hat **466 Zeilen** und deckt Mehrfach-Verbindungen, sechs Cache-Treiber und fünf
Mapping-Formate ab. Das Framework benutzt davon einen schmalen Ausschnitt
(`lib/contentfly/bootstrap.php:120-143`):

| | |
|---|---|
| Verbindungen | **eine**, unter dem Namen `pim` |
| Mappings | zwei, beide `annotation`: `Areanet\PIM\Entity` → `lib/contentfly/Entity`, `Custom\Entity` → `custom/Entity` |
| Proxies | Verzeichnis `data/cache/doctrine`, `auto_generate` aus der Konfiguration |
| DQL | eine numerische Funktion: `Find_In_Set` |

Dazu setzt `bootstrap.php` unmittelbar danach selbst die `QuoteStrategy` und die Cache-Treiber
über `$app['orm.em']->getConfiguration()`.

Ein Ersatz muss also **nicht** den Provider nachbauen, sondern nur diesen Ausschnitt: grob
60–80 Zeilen, die einen `EntityManager` erzeugen und unter `$app['orm.em']` ablegen.

### Wo er hingehört
`lib/contentfly/Classes/` — der Ort, an dem das Framework seine eigenen Bausteine hält. Der
Name sollte nicht `…ServiceProvider` heissen, wenn es keiner ist; die Klasse baut einen
EntityManager, mehr nicht.

### Was unverändert bleiben muss
Der **Container-Schlüssel `$app['orm.em']`**. 26 Dateien greifen darauf zu, und die Suite
prüft das Verhalten dahinter. Ändert sich der Schlüssel, ändert sich alles.

Ebenso die Konfigurationsfelder, die heute in den Provider fliessen — `APP_AUTOGENERATE_PROXIES`,
`APP_CACHE_DRIVER` und die DB-Zugangsdaten. Ein Projekt, das sie gesetzt hat, muss sie
weiterhin setzen können.

> `$app['orm.ems']` und `$app['orm.em.config']` gehören ebenfalls zur heutigen Oberfläche des
> Providers. Beim Umsetzen ist zu prüfen, ob sie irgendwo benutzt werden — ein `grep` über
> `lib/`, `custom/` und `tests/` beantwortet das. Was niemand benutzt, wird nicht nachgebaut.

### Was **nicht** dazugehört
- Kein Umbau von Silex oder Pimple. Das ist Epic `009`.
- Keine Mehrfach-Verbindungen, keine Nicht-Annotation-Mappings, keine Cache-Treiber-Auswahl im
  Ersatz — was das Framework nicht benutzt, wird nicht nachgebaut. Wer es später braucht, baut
  es dann.
- `knplabs/console-service-provider` bleibt: Es fasst Doctrine nicht an und ist von dem
  Problem nicht betroffen (geprüft).

## Das gehört ehrlich benannt
Dies ist **vorgezogene Arbeit aus Epic `009`**. Der Provider fällt dort ohnehin, zusammen mit
Silex und Pimple. Er wird hier ersetzt, weil er sonst den Doctrine-Wechsel blockiert — nicht,
weil Epic `006` den Kernel umbauen wollte.

Der Ersatz darf deshalb **schlank und provisorisch** sein. Er muss nur so lange tragen, bis
`009` ihn wegräumt. Was er nicht sein darf: eine neue Abstraktion, die `009` dann erst
verstehen und abtragen muss.

## Acceptance criteria
- [ ] `dflydev/doctrine-orm-service-provider` steht in keinem `require` mehr und ist aus
      `bootstrap.php` verschwunden.
- [ ] `$app['orm.em']` liefert weiterhin einen funktionsfähigen `EntityManager`; der
      Container-Schlüssel ist unverändert.
- [ ] Die beiden Annotation-Mappings, das Proxy-Verzeichnis, `auto_generate` und die
      DQL-Funktion `Find_In_Set` wirken wie zuvor.
- [ ] Geprüft und entschieden, ob `$app['orm.ems']` und `$app['orm.em.config']` nachgebaut
      werden müssen — mit dem `grep`, der die Frage beantwortet hat.
- [ ] Der Ersatz ist als Übergangslösung gekennzeichnet, mit Verweis auf Epic `009`.
- [ ] Die Suite bleibt grün — gegen den **alten** `vendor/`-Baum, denn der Lock kommt erst
      mit `006-002-0003`.

## Verification
Zwei Läufe, und beide zählen:

1. **Gegen den heutigen `vendor/`-Baum** (Doctrine-Fork, Symfony 3.4): Die Suite muss grün
   bleiben. Der Ersatz darf am Ist-Zustand nichts ändern — sonst hat er eine Nebenwirkung,
   die niemand wollte.
2. **Gegen den neu aufgelösten Baum** im Klon (Doctrine 2.20, Symfony 4.4): Hier zeigt sich,
   ob der Ersatz seinen Zweck erfüllt. Bootet die Anwendung, ist die Blockade weg.

Der zweite Lauf ist zugleich die Vorschau auf `006-002-0003`: Was dort noch rot ist, sind die
verbleibenden vier Major-Sprünge — nicht mehr `dflydev`.

`php bin/console.php list` und ein HTTP-Aufruf gehören in beide Läufe: Ein EntityManager, der
sich im Container erzeugen lässt, ist noch keiner, der eine Abfrage beantwortet.
