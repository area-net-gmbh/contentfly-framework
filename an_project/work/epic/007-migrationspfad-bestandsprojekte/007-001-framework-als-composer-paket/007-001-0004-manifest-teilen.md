---
id: 007-001-0004
title: Das Manifest teilen — Bibliothekspaket und Projekt getrennt
status: todo
depends_on: [007-001-0002, 007-001-0003]
---

# Das Manifest teilen — Bibliothekspaket und Projekt getrennt

## Context
**Ein Manifest trägt heute drei Namensräume.** Aus `composer.json`, Stand 2026-09-11:

```json
"name": "areanet/contentfly-framework",
"type": "project",
"autoload": { "psr-4": {
    "Areanet\\PIM\\": "lib/contentfly/",
    "Custom\\":       "custom/",
    "Plugins\\":      "plugins/"
}}
```

`type: project` beschreibt eine Anwendung, die man klont — kein Paket, das man einbindet. Und
Framework, Projektcode und Plugins hängen an **einer** Datei. Das ist genau die Vermischung, die
das Update unmöglich macht: Wer eine neue Frameworkversion will, bekommt sie nur, indem er den
Baum überschreibt, in dem auch sein eigener Code liegt.

**Was mit umzieht, ist mehr als die drei Zeilen.** Am Manifest hängen der `extra.hinweis`-Block
mit der Begründung jedes Constraints, `config.platform.php`, `config.audit.ignore`, der
`suggest`-Eintrag für `symfony/ldap`, `require-dev` samt Werkzeugen, und `autoload-dev` für
`Tests\`. Jeder Posten gehört zugeordnet: Wer eine Bibliothek einbindet, erbt ihre
`require`-Angaben, aber nicht ihre Werkzeuge.

**`plugins/` steht im Autoload und ist leer** — versioniert liegt dort keine Datei. Ein
PSR-4-Präfix auf ein leeres Verzeichnis ist kein Fehler, aber auch keine Entscheidung; hier wird
es eine.

**Die zweite Hälfte, `custom/composer.json`,** hat heute ein leeres `require` und einen eigenen
`vendor/`-Baum. Was daraus wird, hat `007-001-0001` entschieden; hier wird es umgesetzt.

## Acceptance criteria
- [ ] Das Bibliothekspaket trägt `type: library`, einen eigenen Namen und nur den Namensraum des Frameworks.
- [ ] Projektcode, Plugins und Tests hängen nicht mehr am Manifest des Frameworks.
- [ ] Jeder Posten des alten Manifests ist zugeordnet — `extra.hinweis`, `platform`, `audit.ignore`, `suggest`, `require-dev`, `autoload-dev`; keiner geht verloren, keiner liegt doppelt.
- [ ] Die Entscheidung zu `plugins/` ist umgesetzt und begründet.
- [ ] Die beiden toten Importe auf Projektklassen sind weg (`OnejoinType` → `Custom\Entity\TestMeta`, `SystemController` → `Custom\Entity\Ansprechpartner`; beide Klassen existieren nicht). Nachgetragen mit `007-001-0001`, weil erst die Trennlinie sie zu einem Befund macht.
- [ ] Die Gates laufen weiter: `composer audit --locked` ohne Advisories, PHPStan `[OK] No errors`, 0 Deprecations.
- [ ] Die volle Suite bleibt grün.

## Verification
`composer validate` auf jedem entstandenen Manifest. `composer install` von Null gegen den neuen
Zuschnitt, danach die volle Suite und die drei Gates. Der Beweis, dass das Paket wirklich
beziehbar ist, folgt in `007-001-0005`.
