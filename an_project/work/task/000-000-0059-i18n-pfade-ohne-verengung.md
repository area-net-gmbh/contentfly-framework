---
id: 000-000-0059
title: Zwei i18n-Pfade prüfen die Stufe, verengen aber nicht auf Eigentümerschaft
status: todo
depends_on: []
---

# Zwei i18n-Pfade prüfen die Stufe, verengen aber nicht auf Eigentümerschaft

## Context
**Gefunden bei `000-000-0057`.** Zwei der sechs Stellen, die die Stufe nur gegen `0` prüfen,
betreffen ausschliesslich i18n-Entities. Im Framework und in der Vorlage ist **keine** Entity
i18n — die Pfade sind hier nicht erreichbar und deshalb nicht in der Rechtematrix getestet. In
einem Projekt mit i18n-Entities sind sie es.

1. **`Api::doInsert()` mit übergebener `id`.** Ein Insert, der die `id` eines bestehenden
   Datensatzes mitbringt, legt eine **Sprachvariante genau dieses Datensatzes** an. Geprüft wird
   nur `writable != 0` auf der Entity — ein Benutzer mit `writable = OWN` legt damit Übersetzungen
   **fremder** Datensätze an.
2. **`Api::getTranslations()`** zählt unübersetzte Datensätze **über alle Eigentümer**.
   `getCount()` verengt dieselbe Art Zahl auf `OWN`/`GROUP`. Es fliessen nur Anzahlen, keine
   Inhalte — aber ein `OWN`-Benutzer erfährt, wie viele fremde Datensätze es gibt.

## Acceptance criteria
- [ ] Eine i18n-Test-Entity existiert in der Test-Umgebung, sodass beide Pfade über HTTP erreichbar sind.
- [ ] `doInsert` mit der `id` eines fremden Datensatzes wird bei `writable = OWN` bzw. `GROUP` abgelehnt — geprüft wird die Eigentümerschaft des **bestehenden** Datensatzes.
- [ ] `getTranslations` verengt wie `getCount()` auf `OWN`/`GROUP`.
- [ ] Die Matrix-Tabelle im Kopf von `tests/Integration/Api/PermissionMatrixApiTest.php` ist nachgezogen.

## Verification
Integrationstest je Pfad mit einem eigenen und einem fremden Datensatz: vor der Änderung rot,
danach grün.
