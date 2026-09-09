---
id: 009-005-0003
title: Die Doctrine-Console-Commands und der Nachweis
status: review
depends_on: [009-005-0002]
---

# Die Doctrine-Console-Commands und der Nachweis

## Context
`bin/console.php` registriert **achtzehn** Doctrine-Commands und baut dafür ein `HelperSet` mit
`ConnectionHelper` und `EntityManagerHelper`. In DBAL 3 gibt es `ImportCommand` nicht mehr, und
die Helper-Konstruktion hat sich geändert — `SystemController` importiert den `ConnectionHelper`
ebenfalls.

**Was nicht mehr läuft, wird gestrichen, nicht repariert.** Diese Commands sind Werkzeug von
Doctrine, nicht Funktion von Contentfly; Epic `010` hebt Doctrine ohnehin weiter. Ein
auskommentierter Command ist schlechter als ein entfernter: Er sieht aus wie etwas, das
zurückkommt.

Dazu der Abschluss der Story: der Nachweis, dass der Stand tatsächlich trägt — und dass
`009-002` jetzt auflösbar ist, was der eigentliche Zweck der ganzen Story war.

## Acceptance criteria
- [x] Für jeden der achtzehn Commands ist gemessen, ob er unter DBAL 3 startet; was nicht
      läuft, ist entfernt, und die Liste des Entfernten steht mit Begründung im Ergebnis.
- [x] Das `HelperSet` steht auf dem Weg, den DBAL 3 und ORM 2.20 vorsehen.
- [x] `SystemController` benutzt keinen entfallenen Import mehr.
- [x] `php bin/console.php list` läuft, `appcms:install` legt ein Schema auf einer leeren
      Datenbank an, `appcms:token:cleanup --dry-run` läuft.
- [x] **Die Vorbedingung ist erfüllt:** `composer update` mit
      `symfony/http-foundation ^7.4` löst probeweise auf — der Konflikt, der `009-002`
      blockiert hat, besteht nicht mehr. Die Probe wird zurückgenommen, nicht committet.
- [x] Volle Suite grün, Deprecation-Gate grün, Postausgang 0 Byte.

## Verification
`php bin/console.php list`, `appcms:install` gegen eine frische Wegwerf-Datenbank,
`appcms:token:cleanup --dry-run`, volle Suite, `composer audit --locked` und ein
`composer update --dry-run` mit probeweise auf `^7.4` gehobenem `symfony/http-foundation`.

## Ergebnis

**Die Suite ist grün: `OK (249 tests, 616 assertions)`, 0 übersprungen — ohne eine einzige
geänderte Zusicherung.** Das ist der Nachweis, um den es dieser Story ging.

### Die achtzehn Commands, einzeln gemessen

| Ergebnis | Anzahl | |
|---|---|---|
| Läuft unverändert | 15 | die ORM-Commands |
| Braucht jetzt einen Provider | 2 | `dbal:reserved-words`, `dbal:run-sql` |
| Entfallen | 1 | `ImportCommand` |

**Provider statt `HelperSet`.** Bis DBAL 2 bekamen die Commands ihre Verbindung über ein
`HelperSet` mit `ConnectionHelper`. Die Klasse gibt es in DBAL 3 nicht mehr — `bin/console.php`
starb daran in Zeile 14, und damit war die **ganze Konsole** unbenutzbar, `appcms:install`
eingeschlossen. An ihre Stelle tritt der `ConnectionProvider`, den DBAL 3 den Commands in den
Konstruktor gibt.

Für die ORM-Commands gilt dasselbe mit dem `EntityManagerProvider`. Das dortige `HelperSet`
gibt es zwar noch, ist aber deprecated — zwei Wege nebeneinander wären einer zu viel, also
laufen beide Seiten über Provider.

**`ImportCommand` ist entfernt, nicht auskommentiert.** Es las eine SQL-Datei ein; wer das
braucht, nimmt den `mysql`-Client. Ein auskommentierter Command sieht aus wie etwas, das
zurückkommt.

`SystemController` hatte den `ConnectionHelper` nur importiert und nie benutzt — der Import ist
weg.

### Der eigentliche Nachweis: `009-002` ist entblockiert

Probeweise `symfony/http-foundation ^7.4` samt der übrigen Komponenten ins Manifest gesetzt und
`composer update --dry-run` laufen lassen. Ergebnis:

```
- Removing pimple/pimple (v3.6.2)
- Removing silex/silex (v2.3.0)
- Upgrading symfony/http-kernel (v4.4.51 => v7.4.18)
```

**Es löst auf.** Der Konflikt `doctrine/dbal <3.6`, an dem `009-002-0001` gescheitert ist,
besteht nicht mehr. Die Probe wurde zurückgenommen und ist nicht committet — das Manifest auf
diesem Branch trägt weiterhin Silex.

### Zwei Befunde am Rande, beide älter als diese Story

**`orm:validate-schema` meldet zwei Abweichungen, und keine davon kommt von DBAL 3.**

1. Der Index `modified_index` fehlt in der Datenbank, obwohl `LoadMetadata` ihn ins Mapping
   schreibt. Der Grund steht in `bootstrap.php`: Der Listener wird nur registriert, **wenn
   `is_installed` wahr ist** — während `appcms:install` läuft, ist er das nicht. Der Installer
   legt das Schema also ohne diesen Index an, und danach behauptet das Mapping ihn. Gegengeprüft:
   Die Datenbank aus einem DBAL-**2**-Install hatte ihn ebenso wenig.
2. `BaseI18nTree` meldet eine ungültige Zuordnung: Die Join-Spalten von `treeParent` decken nicht
   alle Identifier-Spalten (`id, lang`) ab.

Beides ist **nicht** angefasst worden. Diese Story soll den Kernel entblockieren, und keiner der
beiden Punkte hindert daran; sie gehören als eigene Tasks aufgeschrieben.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (249 tests, 616 assertions)`, 0 übersprungen |
| `appcms:install` | auf einer **frisch angelegten** Datenbank durchgelaufen |
| Erzeugte Id | `4b09ed3e-8403-4ca0-…` — Version 4, wie in `009-005-0002` entschieden |
| `php bin/console.php list` | 20 Commands |
| `appcms:token:cleanup --dry-run` | läuft |
| `dbal:run-sql` und `orm:validate-schema` | laufen |
| Deprecation-Gate | grün: 1 Paar, 1 ausgenommen — die bekannte aus Silex |
| Postausgang | 0 Byte |
| `composer audit --locked` | unverändert 5 ignorierte Meldungen, alle zu Symfony 4 |
