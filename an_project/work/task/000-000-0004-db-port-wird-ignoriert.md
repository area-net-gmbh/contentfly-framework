---
id: 000-000-0004
title: DB_PORT wird von der ORM-Verbindung ignoriert
status: review
depends_on: []
---

# DB_PORT wird von der ORM-Verbindung ignoriert

## Context
Das Framework kennt `Config::DB_PORT` (Standard 3306), verwendet ihn aber nur an einer von zwei
Stellen:

| Verbindung | Port |
|---|---|
| `$app['database']` — `lib/contentfly/bootstrap.php:265` | nutzt `DB_PORT` |
| ORM-Verbindung `dbs.options['pim']` — `lib/contentfly/bootstrap.php:87-90` | **fehlt** — immer 3306 |

Eine Datenbank auf einem abweichenden Port ist für Doctrine damit nicht erreichbar. Schlimmer als
ein Fehler ist das stille Verhalten: Läuft auf 3306 *irgendeine* MySQL, verbindet sich das
Framework dorthin — nicht mit einem Fehler, sondern mit falschen Daten oder einem
„Access denied" für einen Benutzer, den man nie gemeint hat.

Dieselbe Lücke hat der `InstallCommand` geerbt: Er reicht keinen Port durch, weder in den
PDO-Verbindungstest noch in die Doctrine-Registrierung — die Vorlage stammt aus dem
`InstallController`, dem er ebenfalls fehlte.

Aufgefallen bei der Verifikation von `000-000-0002`: Die Entwicklungsumgebung aus
`000-000-0003` läuft bewusst auf **3307**, weil 3306 auf Entwicklungsmaschinen belegt ist.
Der Installer versuchte, sich gegen den fremden Container auf 3306 anzumelden.

## Acceptance criteria
- [x] Die ORM-Verbindung in `bootstrap.php` reicht `DB_PORT` durch.
- [x] Der `InstallCommand` nimmt `--db-port` (Umgebungsvariable `APPCMS_DB_PORT`, Standard 3306)
      und verwendet ihn im Verbindungstest **und** in der Doctrine-Registrierung.
- [x] Der Port landet in `custom/config.php`, sonst funktioniert nach der Installation nichts:
      Die Vorlage braucht einen Platzhalter, den die Installation ersetzt.
- [x] Eine Installation gegen die Entwicklungsumgebung auf 3307 läuft durch — nachweislich gegen
      *diese* Datenbank und nicht gegen 3306.

## Verification
`docker compose up -d`, dann `appcms:install` mit `--db-port=3307` ausführen. Danach in der
Datenbank auf 3307 nachsehen, dass die Tabellen dort angelegt wurden — und im fremden Container
auf 3306 nachsehen, dass sich dort nichts geändert hat.
