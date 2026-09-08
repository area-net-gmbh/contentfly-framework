---
id: 006-002-0005
title: dflydev-Service-Provider durch eigenen Aufbau ersetzen
status: review
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
- [x] `dflydev/doctrine-orm-service-provider` steht in keinem `require` mehr und ist aus
      `bootstrap.php` verschwunden.
- [x] `$app['orm.em']` liefert weiterhin einen funktionsfähigen `EntityManager`; der
      Container-Schlüssel ist unverändert.
- [x] Die beiden Annotation-Mappings, das Proxy-Verzeichnis, `auto_generate` und die
      DQL-Funktion `Find_In_Set` wirken wie zuvor.
- [x] Geprüft und entschieden, ob `$app['orm.ems']` und `$app['orm.em.config']` nachgebaut
      werden müssen — mit dem `grep`, der die Frage beantwortet hat.
- [x] Der Ersatz ist als Übergangslösung gekennzeichnet, mit Verweis auf Epic `009`.
- [x] Die Suite bleibt grün — gegen den **alten** `vendor/`-Baum, denn der Lock kommt erst
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

## Ergebnis
`lib/contentfly/Classes/ORM/EntityManagerFactory.php` — **106 Zeilen**, davon gut die Hälfte
Kommentar. Ersetzt 466 Zeilen `dflydev`.

`dflydev` ist aus `composer.json`, aus `bootstrap.php` und aus `Command/InstallCommand.php`
verschwunden. **Zwei Aufrufstellen**, nicht eine — der Installer registrierte den Provider ein
zweites Mal, mit derselben Konfiguration. Beide zeigen jetzt auf dieselbe Factory; wichen sie
voneinander ab, installierte der Installer gegen ein anderes Schema, als die Anwendung
benutzt.

### Was nachgebaut wurde — und was nicht
| | |
|---|---|
| eine Verbindung | ✓ |
| zwei Annotation-Mappings über eine `MappingDriverChain` | ✓ |
| Proxy-Verzeichnis, `auto_generate` | ✓ |
| die DQL-Funktion `Find_In_Set` | ✓ |
| `$app['orm.ems']`, `$app['orm.em.config']` | **nein** — der `grep` über `lib/`, `custom/` und `tests/` fand **0 Treffer** |
| Mehrfach-Verbindungen, sechs Cache-Treiber, fünf Mapping-Formate | **nein** — benutzt niemand |

### Der Fehler, den Lauf 1 gefunden hat
Die erste Fassung baute den Treiber selbst:

```php
new AnnotationDriver(new AnnotationReader(), array($mapping['path']))
```

Gegen den alten Baum ergab das **169 Fehler**:

```
[Semantical Error] The annotation "@Doctrine\ORM\Mapping\Entity" in class
Custom\Entity\Core\Example was never imported.
```

Ein frisch gebauter `AnnotationReader` registriert den Loader der `AnnotationRegistry` nicht,
ohne den die Doctrine-eigenen Annotationen nicht auflösbar sind. `dflydev` benutzte
`Configuration::newDefaultAnnotationDriver()`, die das intern erledigt — und die es in beiden
Doctrine-Ständen gibt. Genau darauf baut die Factory jetzt.

**Das ist der Grund, warum Lauf 1 im Task stand.** Ohne ihn wäre der Fehler erst gegen den
neuen Baum aufgefallen und dort mit vier Major-Sprüngen vermischt gewesen.

### Die Abnahme
| Prüfung | Ergebnis |
|---|---|
| Suite gegen den **alten** Baum | **247 Tests / 603 Assertions grün** |
| Console gegen den alten Baum | Exit 0 |
| Console gegen den **neuen** Baum (Doctrine 2.20, Symfony 4.4) | Exit 0 — ⚠️ siehe Korrektur |
| `MappingDriverChain`-Fehler im Lauf-2-Protokoll | **0 Treffer** |

> **Korrektur, nachgetragen mit `006-002-0003`:** Die Zeile „Console gegen den neuen Baum:
> Exit 0" taugt **nicht** als Beleg dafür, dass der EntityManager funktioniert.
> `php bin/console.php list` erzeugt ihn zwar, lädt aber keine Metadaten — der Fehler kommt
> erst beim ersten `getClassMetadata()`. Der Task-Text dieser Datei warnte ausdrücklich davor
> („Ein EntityManager, der sich im Container erzeugen lässt, ist noch keiner, der eine Abfrage
> beantwortet"), und ich habe die eigene Warnung beim Auswerten nicht befolgt.
>
> **Was trotzdem gilt:** Die dflydev-Blockade ist weg — belegt durch die 0 Treffer auf
> `MappingDriverChain` und dadurch, dass `006-002-0003` danach *andere* Fehler fand und
> schrittweise auflösen konnte. Der eigentliche Beleg ist der grüne Lauf gegen den alten Baum
> plus die inzwischen funktionierende Anmeldung gegen den neuen.

**Die Blockade ist weg.** Gegen den alten Baum ändert sich nichts, gegen den neuen bootet die
Anwendung.

### Was für `006-002-0003` offen bleibt
Der Lauf gegen den neuen Baum ist **nicht** grün — 188 Fehler, 7 Errors. Das ist erwartet: Er
war im Task als *Vorschau* definiert, nicht als Abnahmekriterium. Die Fehler verteilen sich auf
die vier verbleibenden Sprünge, und ein Punkt ist ausdrücklich **ungeklärt**:

> Im HTTP-Pfad — und nur dort — meldet Doctrine
> `The annotation "@Doctrine\ORM\Mapping\MappedSuperclass" ... was never imported`.
> Die Console ist grün, und sie unterscheidet sich vom HTTP-Pfad genau darin, dass sie
> `APPCMS_CONSOLE` setzt und damit den Cache-Block in `bootstrap.php` überspringt.
>
> Zwei Thesen sind bereits **widerlegt**: der geerbte Metadaten-Cache (Leeren half nicht) und
> `AnnotationRegistry::registerFile()` als Auslöser (isoliert nachgestellt: bricht nichts).
> Ein frischer `AnnotationReader` löst die Annotation problemlos auf; der Unterschied liegt
> im `CachedReader`, den `newDefaultAnnotationDriver()` liefert.

Weiter zu graben gehört zu `006-002-0003` — das ist der Task, der den Doctrine-Wechsel abnimmt.
Hier wäre es Scope-Erweiterung, und der Befund ist sauberer aufgehoben, wo er hingehört.

Der Klon unter `scratchpad/lockprobe` mit dem aufgelösten Baum bleibt für `0003` stehen.
