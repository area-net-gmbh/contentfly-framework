---
id: 000-000-0062
title: /api/tree2 baut den Parameter lang ungeprüft in SQL ein
status: review
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
- [x] `lang` geht als gebundener Parameter in die Abfrage, nicht als String.
- [x] Keine weitere Stelle in `lib/` baut Request-Werte per String-Verkettung in SQL ein — geprüft und im Ergebnis festgehalten.
- [x] Ein Integrationstest mit einer i18n-Tree-Test-Entity belegt, dass ein `lang` mit Anführungszeichen keine Wirkung auf die Abfrage hat.

## Verification
Test mit `lang = "de' OR '1'='1"`: vor der Änderung liefert er Knoten aller Sprachen bzw. einen
SQL-Fehler, danach keinen Knoten und keinen Fehler.

## Ergebnis
**`lang` ist gebunden.** `getTree2()` schreibt `AND t.lang = ?` und reicht den Wert als Parameter
an `fetchAllAssociative()`; für einen Baum ohne i18n geht kein Parameter mit.

**Belegt mit einem Unit-Test statt eines Integrationstests — eine Abweichung vom dritten
Kriterium.** Der Pfad existiert nur für eine Entity auf Basis von `BaseI18nTree`; Framework und
Vorlage haben keine, und die Integration-Suite erreicht ihn nicht ohne eigene Test-Entity samt
Schema. Das wäre ein Umbau der Test-Infrastruktur, grösser als der Fix. Zu beweisen ist etwas
Enges: welche Anweisung und welche Parameter bei der Verbindung ankommen. Das zeichnet ein
Double der DBAL-`Connection` auf — die tatsächlich gesendete Anweisung, keine Vermutung darüber.
`tests/Unit/Security/Tree2LangBindingTest.php`: mit `lang = "de' OR '1'='1"` **vor** dem Fix rot
(der Wert stand im SQL), danach grün.

**Suche nach weiteren Stellen (Kriterium 2):** Die **Werte** sind überall sonst gebunden;
Tabellen- und Entity-Namen, die in Abfragen stehen, kommen aus dem Schema, nicht aus dem Request.
Offen sind aber **Feldnamen** — `where`-Schlüssel in `getSingle()`, `order` und `groupBy` in
`getList()`, `properties` in `getTree()` gehen ungeprüft in DQL. Das ist DQL-Injection →
`000-000-0063`.
