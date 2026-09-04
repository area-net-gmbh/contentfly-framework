---
id: 012-002-0001
title: Installationsschritte des InstallControllers erfassen
status: review
depends_on: []
---

# Installationsschritte des InstallControllers erfassen

## Context
Bevor der `InstallController` (203 Zeilen) verschwindet, muss belegt sein, was er tut — sonst fehlt dem Console-Command hinterher ein Schritt, den niemand vermisst, bis eine Neuinstallation scheitert.

## Acceptance criteria
- [x] Jeder Schritt ist benannt: Schema/Tabellen, erster Benutzer, Konfigurationsdateien, Verzeichnisse und Rechte, Plugin-Registrierung.
- [x] Für jeden Schritt ist festgehalten, welche Eingaben er braucht und was er voraussetzt.
- [x] Die Aufstellung liegt als Grundlage im Task-/Story-Kontext vor, nicht nur im Kopf.

## Ergebnis — was der InstallController tatsächlich tut

Gelesen aus `lib/contentfly/Controller/InstallController.php` (203 Zeilen), Stand vor dem Ersatz.

| # | Schritt | Braucht | Voraussetzung |
|---|---|---|---|
| 0 | **Guard** — läuft nur, solange `DB_HOST == '$SET_DB_HOST'`; sonst Redirect auf die Oberfläche | — | Ein installiertes System darf nicht erneut installiert werden |
| 1 | **Eingaben prüfen** — `db_host`, `db_name`, `db_user`, `db_pass`, `db_strategy` müssen gesetzt sein | die fünf Werte | — |
| 2 | **`chmod()` verfügbar?** — sonst Abbruch mit Klartext | — | PHP-Funktion nicht per `disable_functions` gesperrt |
| 3 | **Rechte setzen** — `chmod 0775` auf `custom/config.php`, `data/files`, `data/cache`; danach prüfen, ob `custom/config.php` schreibbar ist | — | Verzeichnisse existieren |
| 4 | **DB-Verbindung testen** — `new \PDO('mysql:host=…;dbname=…')` mit den eingegebenen Daten | die fünf Werte | Datenbank existiert bereits — sie wird **nicht** angelegt |
| 5 | **`custom/config.php` schreiben** — Platzhalter ersetzen: `$SET_DB_HOST`, `$SET_DB_NAME`, `$SET_DB_USER`, `$SET_DB_PASS` und `'$SET_DB_GUID_STRATEGY'` → `true`/`false` | Schritt 1–4 | Datei schreibbar (Schritt 3) |
| 6 | **ID-Strategie festlegen** — `guid` → `APPCMS_ID_TYPE='string'`, `APPCMS_ID_STRATEGY='UUID'`; sonst `'integer'`/`'AUTO'`. Als Konstanten, **bevor** die Entities geladen werden | `db_strategy` | Nicht nachträglich änderbar: die Konstanten stecken in den Entity-Annotationen |
| 7 | **Doctrine registrieren** — DBAL mit den frisch eingegebenen Zugangsdaten, dazu ORM mit den beiden Annotation-Mappings `Areanet\PIM\Entity` und `Custom\Entity` und der Custom-Function `Find_In_Set` | Schritte 5–6 | Nötig, weil `bootstrap.php` Doctrine bei `is_installed == false` gar nicht erst registriert |
| 8 | **TypeManager aufbauen** — `$app['typeManager']` setzen und alle `APP_SYSTEM_TYPES` registrieren | Schritt 7 | Ohne die Typen fehlt dem Schema die Feldabbildung |
| 9 | **Schema anlegen** — `SchemaTool::updateSchema()` über alle Entity-Metadaten | Schritte 7–8 | — |
| 10 | **Basisdaten** — `$app['helper']->install($em)`: Admin-Benutzer (`admin`/`admin`) und die internen Thumbnail-Größen | Schritt 9 | — |

## Erkenntnisse für die Umsetzung

- **Schritt 10 gibt es bereits als Command**: `appcms:setup` (`lib/contentfly/Command/SetupCommand.php`)
  ruft genau `$app['helper']->install($em)` auf. Der neue Command muss ihn nicht ersetzen, sondern
  dieselbe Stelle aufrufen — doppelte Wahrheit wäre schlimmer als ein Aufruf mehr.
- **Neu am Console-Weg sind die Schritte 0–9**, insbesondere das Schreiben der `config.php`.
- **Schritt 7 ist der Knackpunkt**: Ohne Konfiguration registriert `bootstrap.php` weder DBAL
  noch ORM (`if($app['is_installed'])`). Der Command muss beides zur Laufzeit selbst
  registrieren — genau wie der Controller es tat.
- **Der Admin wird mit `admin`/`admin` angelegt.** Über eine Weboberfläche mit anschließendem
  Login-Zwang war das vertretbar; ein Command, der skriptbar ist, sollte ein Passwort setzen
  können. Als Option aufnehmen, Standard beibehalten.

## Verification
Die Aufstellung wird gegen den Code gegengelesen: Jede Methode des `InstallController` ist einem Schritt zugeordnet oder als überflüssig markiert.
