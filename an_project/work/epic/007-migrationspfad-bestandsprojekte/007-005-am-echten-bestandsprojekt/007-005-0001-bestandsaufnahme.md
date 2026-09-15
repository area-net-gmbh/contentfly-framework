---
id: 007-005-0001
title: Kopie bereitstellen und Bestandsaufnahme
status: done
depends_on: []
---

# Kopie bereitstellen und Bestandsaufnahme

## Context
Bevor irgendetwas umgestellt wird, muss feststehen, **was** dieses Projekt vom Framework benutzt.
Bei einem Projekt dieser Grösse entscheidet die Bestandsaufnahme, wie gross der Rest der Story
wird — und ob Task `0003`/`0004` das ganze Projekt oder einen repräsentativen Ausschnitt nehmen.

Phase 1 des Leitfadens (`an_project/docs/migration.md`) verlangt eine Bestandsaufnahme — aber
nur als Frageliste. Hier wird sie **erhoben**, und das Werkzeug dafür ist selbst ein Ergebnis:
Das nächste Bestandsprojekt braucht dieselbe Liste.

**Am Rand bereits gesehen:** `backend/lib/contentfly/` weicht in 76 Dateien vom Import-Stand
dieses Repos (`b9284090`) ab. Die Frage ist, welche davon **Projekt-Patches am Framework** sind:
Die gehen beim Wechsel auf das Paket verloren, wenn niemand sie findet.

## Acceptance criteria
- [x] **Eine lauffähige Kopie der Datenbank** liegt in einer eigenen Instanz, getrennt vom Bind-Mount `docker/mysql8/` des Projekts; der Bind-Mount ist nachweislich unberührt.
- [x] **Das alte Projekt läuft** in seinem bestehenden Container und antwortet auf `/api/config` — Voraussetzung für Task `0002`.
- [x] **Ein Inventar-Werkzeug** unter `tools/migration/` erhebt für ein beliebiges Projektverzeichnis: Entities, `@ORM`- und `@PIM`-Annotationen (gegen `pim-annotationen-migration.md`), eigene Types, Plugins, LoginManager, genutzte `$app[...]`-Schlüssel (gegen die feste Liste aus `architecture.md`), eigene Commands und Controller-Provider, Nutzung der **sieben Codepfade ohne Auslöser** aus dem Epic.
- [x] **Die Framework-Kopie des Projekts ist eingeordnet:** Jede der abweichenden Dateien ist als Projekt-Patch, als Versionsunterschied oder als unklar markiert; jeder Projekt-Patch mit einem Satz, was er tut und ob das neue Framework das schon abdeckt.
- [x] **Der Bericht** steht im Ergebnis dieses Tasks: Zahlen, die Einordnung der Patches, und eine Empfehlung, ob Task `0003`/`0004` das ganze Projekt oder einen Ausschnitt nehmen — mit Begründung.
- [x] Im Projekt-Repo ist **nichts** geändert.

## Verification
`git -C <projekt> status` vor und nach dem Task leer. Das Inventar-Werkzeug einmal gegen die
Vorlage `custom/` dieses Repos laufen lassen (bekannte Zahlen) und einmal gegen das Projekt.
`/api/config` des alten Projekts antwortet.

## Ergebnis

### Die Umgebung

- **Datenbank:** `docker/mysql8/` in den Scratchpad kopiert, eigener Container `ufp-probe-db`
  (MySQL 8.2, wie im Projekt), Port 55056, Netzwerk-Alias `ufp_db_v2`. 82 Tabellen. Das Original
  ist per Fingerabdruck (Grösse und Änderungszeit jeder Datei) vor und nach dem Task identisch.
- **Altes Backend:** eigener Container `ufp-probe-old` aus dem vorhandenen Projekt-Image
  (PHP 7.4.33), Code **nur lesend** eingebunden, `data/` als Kopie, Port 55112. `/api/config`
  antwortet `200` mit `"version":"1.6.0"`. Anfragen gehen nur an `localhost` — die Konfiguration
  des Projekts wählt nach Hostname, und nur so greift die lokale statt der Live-Konfiguration.
  Einen Mail-Server gibt es in der Probe nicht; Versand geht ins Leere.
- **Projekt-Repo:** `git status` vor und nach dem Task leer.

### Das Werkzeug

`tools/migration/inventory.php <backend-dir> [--json]` — liest nur, führt keinen Projektcode aus.
Kommentare zählen nicht (die Vorlage erwähnt `$app['request']` und `encoded` nur in Kommentaren).
`InventoryToolTest` prüft es an einem kleinen Projekt mit je einem Fall **und** dass seine
Listen nicht von ihren Quellen abweichen: entfallene `@PIM`-Annotationen und -Felder gegen
`rector.php`, Container-Schlüssel gegen `ContainerKeysTest`.

Gegen die Vorlage `custom/`: 1 Entity, 1 Command, 1 Controller, 3 zugesicherte Schlüssel, keiner
der sieben Pfade. **Nebenbefund:** `plugins/` enthält acht `Probe*`-Ordner — Reste aus Testläufen,
von `.gitignore` verdeckt. Das Werkzeug zählt sie, weil sie auf der Platte liegen.

### Das Projekt in Zahlen

| | |
|---|---|
| Entities | **65** (49 `Base`, 16 `BaseSortable`), 635 `@ORM`-Annotationen, 0 Attribute |
| `@PIM` | 478 Annotationen; **46 entfallene Annotationen** und **532 entfallene Felder** — Arbeit für die Rector-Regel |
| LoginManager | **6**: Standard, OAuth2, Insight, UX, PublicUser, PublicUserFeatureRequest |
| Controller | 40 mit 216 Actions; **keine** nimmt `$app` als Argument |
| Commands, Types, Plugins, Controller-Provider | 0 · 0 · 0 · 0 (ein eigener Provider erbt nicht von `BaseControllerProvider`, siehe unten) |
| `$app[...]` zugesichert | `orm.em` 596 · `database` 176 · `auth.user` 99 · `mailer` 6 · `routeManager` 2 · `auth.token` 2 |
| `$app[...]` unbekannt | **`twig` 5** (Mail-Templates) · **`config` 2** · **`controllers_factory` 1** |
| Silex im Projektcode | 2 Dateien: `custom/app.php` (Typangabe am `mailer`), `custom/Classes/Core/SecureControllerProvider.php` |
| Die sieben Codepfade ohne Auslöser | **keiner benutzt** |
| Eigene Abhängigkeiten | `custom/composer.json`: phpmailer, ramsey/uuid, fzaninotto/faker (abandoned) |

### Die Framework-Kopie

Version **1.6.0**, importiert in `7700c49b` (2021). Die 76 Abweichungen gegenüber dem Import-Stand
dieses Repos sind überwiegend der Versionsunterschied 1.6.0 → 2.0.0. **Was das Projekt selbst
geändert hat, sagt seine eigene Historie:** 12 Commits, **14 Dateien**, +605/−39.

| Patch | Einordnung |
|---|---|
| `Classes/Types/JsonType.php` (neu) | **abgedeckt** — das Framework hat `JsonType` |
| `bootstrap.php`: `session.cookie_secure` | **überholt** — Sessions gibt es seit `012-004` nicht mehr |
| `bootstrap-web.php`: HSTS-Header | **abgedeckt** über `APP_FORCE_SSL` |
| `Config.php`: `APP_ALLOW_HEADERS_SDK` erweitert | **Konfiguration** — setzt das Projekt in `custom/config.php` |
| `Api.php`, `Manager/LoginManager.php` | nur Leerzeichen |
| `Entity/NavItem.php`: `pim_navItem` → `pim_nav_item` | **projektspezifisch** — Tabellenname für die Datenmigration (Phase 9) |
| Rollenprüfung auf `/api`, `/file`, `/export` (`BaseControllerProvider::checkRole`, drei Provider, `APP_ROLES_*`) | **offen, Framework-Frage.** Mehrere LoginManager vergeben Tokens ohne Zugangsdaten (anonyme Teilnehmer, Share-Links); das Projekt schützt die generischen Routen deshalb über die Gruppenrolle. Das neue Framework kennt keine Rolle. Zu entscheiden in `0004`, wenn die LoginManager umziehen. |
| Upload-Whitelist (`File/Validator.php`, `FileController`, `FILE_ALLOWED_TYPES`, Messages) | **Framework-Befund, Sicherheit** — siehe unten |
| `bootstrap-web.php`: `Access-Control-Allow-Origin` aus der Konfiguration | **Framework-Befund, Sicherheit** — siehe unten |

### Zwei Sicherheitslücken im neuen Framework — gemessen

Das Projekt hat beide in seiner Kopie geschlossen; im neuen Framework sind sie offen. Gemessen an
einer frischen Installation dieses Repos:

1. **Upload → Codeausführung.** `POST /file/upload` mit einer Datei `probe-upload.php` wird
   gespeichert (`data/files/<id>/probe-upload.php`), und ihr Abruf antwortet **`EXECUTED-42`** —
   der Code lief. Es genügt ein gültiges Token; in einem Projekt mit anonymen Logins ist das
   ohne Zugangsdaten. Die Probedateien sind entfernt.
2. **CORS spiegelt jede Herkunft mit Credentials.** `Origin: https://evil.example` →
   `Access-Control-Allow-Origin: https://evil.example` und `Access-Control-Allow-Credentials: true`.
   `APP_ALLOW_ORIGIN` ist in `Config` deklariert und wird nirgends gelesen.

Beide liegen im Prüfstand `v2.0.0-pre-security-2026-09-14` und stehen nicht auf der Liste der
bekannten offenen Befunde. **Entschieden am 2026-09-15:** Sie werden als eigene Tasks vor `0002`
behoben; die IT-Security informiert der Auftraggeber selbst.

### Weitere Bruchstellen im Projektcode (für `0003`/`0004`)

- **`$app['twig']` für Mail-Templates** (5×). Twig ist mit Epic `012` aus dem Framework entfallen;
  das Projekt muss es selbst beziehen und registrieren oder die Templates umstellen.
- **`$app['config']`** (2×, `Traits/ShareAccessParsing.php`) — mit `isset` abgefragt; einen
  solchen Schlüssel gab es vermutlich auch im alten Stand nicht. Zu prüfen in `0002`, ob der
  Zweig je griff.
- **`SecureControllerProvider`** benutzt `controllers_factory` und die Silex-Typen — der Weg
  über die neue Provider-Schnittstelle steht in `breaking-changes.md`.
- **`fzaninotto/faker`** ist abandoned.
- **Am Rand, projektseitig:** `custom/config.php` enthält Zugangsdaten für die Dev- und die
  Live-Datenbank im Klartext und ist versioniert. Kein Migrationsthema, aber eines.

### Empfehlung für `0003`/`0004`: das ganze Projekt

**Die Zahlen sind gross, der Aufwand ist es nur an wenigen Stellen.** Die 1.113 Annotationen und
die 578 zu entfernenden `@PIM`-Angaben sind genau das, wofür die Rector-Regel gebaut ist — sie
an 65 Entities zu fahren ist ihr eigentlicher Test, und ein Ausschnitt würde ihn verschenken. Die
40 Controller hängen an zugesicherten Schlüsseln und nehmen `$app` nicht als Argument. Der
Handarbeitsanteil sitzt an vier Stellen: die sechs LoginManager, die Rollenprüfung, die
Mail-Templates und der eigene Provider. Ein Ausschnitt würde genau die Stellen schneiden, an denen
die Migration etwas lernt.
