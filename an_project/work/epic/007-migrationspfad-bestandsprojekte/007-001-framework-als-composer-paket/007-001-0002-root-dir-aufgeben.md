---
id: 007-001-0002
title: ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten
status: todo
depends_on: [007-001-0001]
---

# ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten

## Context
**Das ist die eigentliche Sperre, nicht das Manifest.** In `lib/contentfly/bootstrap.php`, Zeile
2, steht:

```php
const ROOT_DIR = __DIR__ . '/../..';
```

Das Framework rechnet sich das Projektverzeichnis aus **seiner eigenen Lage** aus. Das stimmt
genau so lange, wie es unter `lib/contentfly/` im Projekt liegt. Sobald es unter
`vendor/areanet/…/lib/contentfly/` liegt, zeigt `../..` nach `vendor/areanet/` — und jeder Pfad
darauf ins Leere.

**Der Umfang, nachgezählt am 2026-09-11:** 80 Vorkommen in 18 Dateien.

| Datei | Vorkommen |
|---|---|
| `lib/contentfly/bootstrap.php` | 15 |
| `lib/contentfly/Command/InstallCommand.php` | 8 |
| `lib/contentfly/Classes/Plugin.php` | 6 |
| `lib/contentfly/Classes/File/Backend/FileSystem.php` | 5 |
| übrige in `lib/` | 4 |
| `custom/config.php` | 2 |
| `tests/` | 40 |

Die 40 Vorkommen in `tests/` sind kein Nebenschauplatz: Sie zeigen, dass auch die Suite heute
annimmt, im selben Baum zu liegen.

**Die Konstante ist zugleich der Grund, warum der Fehler leise wäre.** `__DIR__ . '/../..'`
liefert immer einen Pfad — er existiert nur nicht. Ein `file_exists()` darauf gibt `false`
zurück, und die Meldung, die daraus entsteht, handelt von einer fehlenden Datei und nicht von
einer falschen Wurzel. Wer das Paket zum ersten Mal einbindet, sucht an der falschen Stelle.
**Es ist dasselbe Muster wie in `000-000-0029`:** Ein Fehler, der sich als etwas anderes ausgibt.

## Acceptance criteria
- [ ] Das Projektverzeichnis kommt von aussen — vom Einstiegspunkt — und wird nirgends mehr aus der Lage einer Frameworkdatei abgeleitet.
- [ ] Kein `__DIR__`-relativer Sprung aus `lib/contentfly/` heraus bleibt übrig; ein Test hält das fest, damit der nächste nicht wieder einen einbaut.
- [ ] Fehlt die Angabe, bricht der Start mit einer Meldung ab, die **das** benennt — nicht eine Folgedatei, die nicht gefunden wurde.
- [ ] Die Suite kommt ohne die Annahme aus, im selben Baum zu liegen.
- [ ] Die volle Suite bleibt grün, und `appcms:install` läuft durch.

## Verification
Das Framework in ein Verzeichnis verschieben, das **nicht** zwei Ebenen unter der Projektwurzel
liegt, und von dort starten — vorher und nachher. Vorher: eine Meldung über eine fehlende Datei.
Nachher: entweder es läuft, oder die Meldung nennt die fehlende Angabe. Volle Suite, PHPStan.
