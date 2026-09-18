---
id: 000-000-0062
title: /api/tree2 baut den Parameter lang ungeprüft in SQL ein
status: todo
depends_on: []
---

# /api/tree2 baut den Parameter lang ungeprüft in SQL ein

## Context
**Gefunden bei `000-000-0057`, beim Lesen von `Api::getTree2()`.** Für eine i18n-Tree-Entity
entsteht die Join-Bedingung so:

```php
$joinI18NCond = "AND t.lang = e.lang AND t.lang = '$lang'";
```

`$lang` kommt unverändert aus dem Request-Body von `/api/tree2`. Das ist eine **SQL-Injection**
für jeden angemeldeten Benutzer — und zusammen mit `000-000-0061` fehlt davor nicht einmal eine
Rechteprüfung.

**Reichweite:** Nur erreichbar, wenn ein Projekt eine Entity auf Basis von `BaseI18nTree` hat.
Framework und Vorlage haben keine; hier ist der Pfad deshalb nicht auslösbar und wurde **nicht**
aktiv ausgenutzt, nur gelesen. Für ein Projekt mit i18n-Baum ist er kritisch.

## Acceptance criteria
- [ ] `lang` geht als gebundener Parameter in die Abfrage, nicht als String.
- [ ] Keine weitere Stelle in `lib/` baut Request-Werte per String-Verkettung in SQL ein — geprüft und im Ergebnis festgehalten.
- [ ] Ein Integrationstest mit einer i18n-Tree-Test-Entity belegt, dass ein `lang` mit Anführungszeichen keine Wirkung auf die Abfrage hat.

## Verification
Test mit `lang = "de' OR '1'='1"`: vor der Änderung liefert er Knoten aller Sprachen bzw. einen
SQL-Fehler, danach keinen Knoten und keinen Fehler.
