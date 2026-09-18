---
id: 000-000-0059
title: Zwei i18n-Pfade prüfen die Stufe, verengen aber nicht auf Eigentümerschaft
status: done
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
- [x] Eine i18n-Test-Entity existiert in der Test-Umgebung, sodass beide Pfade über HTTP erreichbar sind.
- [x] `doInsert` mit der `id` eines fremden Datensatzes wird bei `writable = OWN` bzw. `GROUP` abgelehnt — geprüft wird die Eigentümerschaft des **bestehenden** Datensatzes.
- [x] `getTranslations` verengt wie `getCount()` auf `OWN`/`GROUP`.
- [x] Die Matrix-Tabelle im Kopf von `tests/Integration/Api/PermissionMatrixApiTest.php` ist nachgezogen.

## Verification
Integrationstest je Pfad mit einem eigenen und einem fremden Datensatz: vor der Änderung rot,
danach grün.

## Ergebnis
**Beide Pfade verengen jetzt auf Eigentümerschaft.**

- **`doInsert()` mit übergebener `id`** prüft die Eigentümerschaft des **bestehenden**
  Datensatzes nach der Regel von `doUpdate()`: `OWN` erreicht eigene und in `users` freigegebene
  Zeilen, `GROUP` zusätzlich die der eigenen Gruppe. Die Prüfung liest die Rohzeile
  (`Api::reachesRow()`), weil der Datensatz in der Zielsprache ja noch nicht existiert.
- **`getTranslations()`** verengt wie `getCount()`.

**Die i18n-Test-Entity steht in der Vorlage:** `custom/Entity/Core/ExampleI18n` — die erste
übersetzbare Entity in diesem Baum. Bewusst dort und nicht in einem Test-Verzeichnis: Die
Mappings kennen nur Framework und Vorlage, und eine Funktion, die die Vorlage nicht zeigt, testet
auch niemand. Ein Projekt ohne Übersetzungen löscht die Datei. Die CI legt die Tabelle bei der
Installation an; lokal einmal `orm:schema-tool:update --force`.

**Tests in `PermissionMatrixApiTest`**, vor dem Fix rot: die abgelehnten Übersetzungs-Inserts
(`OWN`/`GROUP` auf nicht erreichbare Datensätze) bekamen 500 statt 403; die Zählung lieferte
bei `OWN` **3 statt 1**. Danach grün; Suite 686 Tests, PHPStan ohne Fehler.

**Ein Befund, der grösser ist als dieser Task:** Eine **erlaubte** Übersetzung lässt sich über
`/api/insert` gar nicht anlegen — `id` fehlt im Schema jeder `BaseI18n`-Entity, vermutlich seit
`010-003-0002`. Die Lücke, der dieser Task nachging, war deshalb **nicht ausnutzbar, sondern der
ganze Pfad kaputt**. Die Eigentümerprüfung läuft vor der Feldprüfung und ist damit schon jetzt
belegt; die erlaubten Fälle kommen mit `000-000-0064` in den Test.
