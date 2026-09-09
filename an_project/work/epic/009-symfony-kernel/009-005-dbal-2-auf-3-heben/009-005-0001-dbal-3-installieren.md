---
id: 009-005-0001
title: DBAL 3.10 installieren und den Bruch sichtbar machen
status: todo
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
- [ ] `composer.json` fordert `doctrine/dbal ^3.10`; `composer update` löst auf, ohne dass
      `doctrine/orm`, `silex/silex` oder eine `symfony/*`-Komponente die Hauptversion wechselt.
- [ ] Die Liste der Bruchstellen ist **gemessen**, nicht abgeschrieben: volle Suite laufen
      lassen und jeden Fehlschlag mit Datei, Zeile und Ursache festhalten.
- [ ] `composer audit --locked` ist ausgewertet; ändert sich etwas an der Ausnahmeliste aus
      `006-005-0001`, steht der Grund dabei.
- [ ] Das Ergebnis nennt auch, was **nicht** gebrochen ist — eine erwartete Bruchstelle, die
      ausbleibt, ist genauso ein Befund.

## Verification
`composer update`, dann die volle Suite gegen eine Wegwerf-Datenbank. Erwartet wird **rot**; das
Ergebnis dieses Tasks ist die Liste, nicht die grüne Suite.
