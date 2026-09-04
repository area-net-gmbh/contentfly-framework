---
id: 012-002-0002
title: Install-Command implementieren
status: done
depends_on: [012-002-0001]
---

# Install-Command implementieren

## Context
Die Installation wird ein Console-Command — nicht-interaktiv aufrufbar und damit skript- und CI-fähig, was die Twig-Maske nie konnte.

## Acceptance criteria
- [x] Ein Command führt die Installation vollständig durch.
- [x] Alle Eingaben sind über Optionen oder Umgebungsvariablen setzbar; kein Schritt erzwingt eine Eingabeaufforderung.
- [x] Ein zweiter Aufruf erkennt die bestehende Installation und bricht ab, statt Daten zu überschreiben.
- [x] Fehlerfälle (DB nicht erreichbar, Verzeichnis nicht schreibbar) melden verständlich, was fehlt.

## Stand am 2026-09-04 — offen: Happy Path

**Umgesetzt und verifiziert:**
- `lib/contentfly/Command/InstallCommand.php` — `appcms:install`, registriert in `bootstrap.php`
  neben `appcms:setup`. Optionen `--db-host/-name/-user/-pass/--db-strategy/--admin-password/--dry-run`,
  jeweils mit Umgebungsvariable als Rückfallebene (`APPCMS_DB_*`).
- Fehlende Angaben werden **alle auf einmal** gemeldet, nicht eine pro Lauf.
- Strategie-Validierung (`guid`/`auto`), DB-Verbindungsfehler im Klartext, Guard gegen erneute
  Installation, `--dry-run` schreibt nichts.
- **Nebenbefund und mitbehoben:** `bin/console.php` griff ungeschützt auf `$app['db']` und
  `$app['orm.em']` zu. Beide gibt es nur bei installiertem System — die Konsole war auf einem
  frischen Checkout gar nicht startbar, und `appcms:install` damit unerreichbar. Die
  Doctrine-Helper und -Commands hängen jetzt an `$app['is_installed']`.
- Der letzte Schritt (Admin, Thumbnail-Größen) ruft `$app['helper']->install()` auf, statt
  `appcms:setup` nachzubauen.

**Noch offen — bewusst, entschieden am 2026-09-04:** Der Happy Path (Konfiguration schreiben,
Schema anlegen, Basisdaten) ist nicht gegen eine echte Datenbank gelaufen. Im Repo steht keine
Contentfly-Datenbank bereit; die einzige erreichbare MySQL-Instanz gehört einem fremden Projekt.
Bis das nachgeholt ist, bleibt dieser Task `in-progress` — und **012-002-0003 darf nicht
starten**: Den Installer zu löschen, bevor sein Ersatz nachweislich funktioniert, würde das
Framework unaufsetzbar machen.

Zum Nachholen: eine leere MySQL-Datenbank anlegen und

```sh
php bin/console.php appcms:install --db-host=… --db-name=… --db-user=… --db-pass=… --dry-run
php bin/console.php appcms:install --db-host=… --db-name=… --db-user=… --db-pass=…
```

Erwartet: Tabellen sind angelegt, ein Benutzer `admin` existiert, ein zweiter Aufruf bricht ab.
Voraussetzung dafür ist eine `custom/config.php` **mit** den `$SET_DB_*`-Platzhaltern — die
heutige ist die Kundendatei ohne Platzhalter (siehe Task `000-000-0002`).

## Verification
Gegen eine leere Datenbank ausführen: Der Command läuft durch, die Anwendung ist danach benutzbar. Zweiter Aufruf bricht sauber ab.
