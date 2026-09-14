---
id: 014-004-0000
title: Vorlage custom/ auf Englisch
status: done
depends_on: [014-003-0000]
---

# Vorlage custom/ auf Englisch

## Goal
`custom/` ist vollständig englisch. Die Kommentare sind es seit `000-000-0034`. Jetzt
kommen die Bezeichner dazu (`Custom\Classes\Authentication\ExampleProvider`, Providername
`example`, `CONTENTFLY_EXAMPLE_PROVIDER`), die beiden Strings in `ExampleCommand` und alle
Variablen- und Array-Schlüssel wie `kennung` oder `geheimnis`.

Das Root-`composer.json` (`description`, `extra.hinweis`) gehört ebenfalls dazu, weil es das
Manifest des Projekts ist.

## Tasks
- [x] 014-004-0001 — Beispiel-Provider der Vorlage auf Englisch
- [x] 014-004-0002 — ExampleCommand und Root-Manifest auf Englisch
- [x] 014-004-0003 — Sprachwächter um Vorlage und Root-Manifest erweitern
