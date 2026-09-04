<!-- PURPOSE: RUNBOOK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/runbook.md). /new-project füllt {{BACKEND}} und {{FRONTEND}} aus den
     Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. Erklärt einem Developer,
     wie er das Projekt nach dem Checkout lokal zum Laufen bringt. -->

# Runbook — lokales Setup (Individual)

Schritt für Schritt vom frischen Checkout zur laufenden lokalen Umgebung. Das Team ergänzt
die konkreten Befehle je Stack und ersetzt die Platzhalter unten.

## Voraussetzungen
- **Docker & Docker Compose** — für die Datenbank
- **PHP** lokal (aktuell 8.3, Zielplattform 8.5). Bewusst nicht im Container: Ein
  PHP-Container würde die Version festschreiben, die im Zuge der Migration gerade
  geändert wird.
- Composer — sobald Epic `006` das Manifest wiederhergestellt hat; heute liegt der
  `vendor/`-Baum noch eingefroren im Repo.

## 1. Datenbank hochfahren

```sh
docker compose up -d
docker compose ps            # wartet, bis der Dienst "healthy" meldet
```

Startet einen MySQL 8.0 unter dem Namen `contentfly-db`:

| | Standard | überschreibbar mit |
|---|---|---|
| Port | **3307** | `CONTENTFLY_DB_PORT` |
| Datenbank | `contentfly` | `CONTENTFLY_DB_NAME` |
| Benutzer / Passwort | `contentfly` / `contentfly` | `CONTENTFLY_DB_USER` · `CONTENTFLY_DB_PASSWORD` |
| root-Passwort | `root` | `CONTENTFLY_DB_ROOT_PASSWORD` |

Der Port steht bewusst **nicht** auf 3306 — dort läuft auf Entwicklungsmaschinen meist
schon eine andere Datenbank. Zeichensatz und Kollation sind auf `utf8mb3` /
`utf8mb3_unicode_ci` gesetzt, passend zu `Config::DB_CHARSET` und `DB_COLLATE`.

Verbindung prüfen:

```sh
php -r '$p = new PDO("mysql:host=127.0.0.1;port=3307;dbname=contentfly", "contentfly", "contentfly");
        echo $p->query("SELECT VERSION()")->fetchColumn(), "\n";'
```

**Daten wegwerfen und neu anfangen** — das benannte Volume überlebt `down`, deshalb braucht
es `-v`:

```sh
docker compose down -v       # Container UND Datenvolume entfernen
docker compose up -d         # frische, leere Datenbank
```

**Herunterfahren ohne Datenverlust:** `docker compose down`

## 2. Backend installieren

```sh
php bin/console.php appcms:install \
    --db-host=127.0.0.1 --db-name=contentfly \
    --db-user=contentfly --db-pass=contentfly \
    --dry-run                                    # erst prüfen, schreibt nichts

php bin/console.php appcms:install \
    --db-host=127.0.0.1 --db-name=contentfly \
    --db-user=contentfly --db-pass=contentfly    # dann wirklich installieren
```

Der Command schreibt `custom/config.php`, legt das Schema an und erzeugt die Basisdaten
(Benutzer `admin`). Alle Optionen gibt es auch als Umgebungsvariable (`APPCMS_DB_HOST` …),
und `--admin-password` setzt ein echtes Passwort statt des Standards.

> **Heute noch nicht durchführbar**, aus zwei Gründen: `appcms:install` kommt erst mit
> Story `012-002` in den Hauptzweig, und `custom/config.php` ist die Konfigurationsdatei
> eines Kundenprojekts ohne `$SET_*`-Platzhalter — der Installer hält das System deshalb
> für bereits installiert und bricht ab (Task `000-000-0002`). Bis dahin ist die Datenbank
> aus Schritt 1 nur für eigene Verbindungen nutzbar.

Die Verzeichnisse unter `data/` (`files`, `cache`, `temp`, `import`) liegen im Repo, weil der
Installer in sie schreibt; ihr Inhalt ist ignoriert.

## 3. Frontend installieren
Entfällt — dieses Projekt hat kein Frontend (reines Backend/API).

## 3a. Smoke-Test ohne Datenbank

Der aktuelle Stand bootet ohne laufende Datenbank, solange die aufgerufene Route keine
Daten anfasst. Damit lässt sich nach jedem Eingriff in 30 Sekunden prüfen, ob Framework
und Vorlage überhaupt noch hochkommen:

```sh
# 1. Console — bootet den Kernel und listet die Commands
php bin/console.php list

# 2. HTTP — eingebauter PHP-Server, index.php als Router
php -S 127.0.0.1:8123 index.php &
curl -s -X POST http://127.0.0.1:8123/api/v1/example/bootstrap
kill %1
```

Erwartet wird der Standard-Envelope mit `"success":true` und einem Zeitstempel im
API-Format. Kommt stattdessen eine HTML-Fehlerseite, steht die Ursache in deren
`exception-message` — meist eine Klasse, die die Vorlage referenziert, aber nicht mitbringt.

Ein `HTTP 405` auf `/` oder einem unbekannten Pfad ist **kein** Fehler: Der
OPTIONS-Catch-All (`{anything}`) in `lib/contentfly/bootstrap-web.php` fängt jeden Pfad ab,
sodass GET dort mit „Method Not Allowed" statt mit 404 beantwortet wird.

## 3b. Tests ausführen

```sh
./custom/vendor/bin/phpunit                    # beide Suiten
./custom/vendor/bin/phpunit --testsuite unit   # ohne Datenbank, muss immer grün sein
```

`tests/Unit` läuft ohne Container, `tests/Integration` braucht die Datenbank aus Schritt 1 und
eine durchgeführte Installation. Details und der Grund für die Trennung: `tests/README.md`.

Für Abdeckung wird ein Treiber gebraucht (Xdebug oder PCOV) und der Schalter `--coverage-text`.
Die Konfiguration fordert bewusst keinen Bericht bei jedem Lauf an — sonst endet die Suite ohne
installierten Treiber mit Exit-Code 1, obwohl jeder Test grün ist.

## 4. Zugriff
<!-- URLs/Ports: Backend-API, DB, Mailhog … -->
- Backend-API: http://localhost:8000

## 5. API-Doku neu generieren
<!-- Befehl, der die OpenAPI/Swagger-Doku aktualisiert — Details in an_project/docs/dev-guide.md. -->

## Troubleshooting
<!-- Häufige Stolpersteine: Ports belegt, DB-Verbindung, Cache leeren (bin/console cache:clear). -->
