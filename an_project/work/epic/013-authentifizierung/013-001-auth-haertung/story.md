---
id: 013-001-0000
title: Auth-Härtung — Passwörter, Master-Passwort, Rate-Limiting, Token-Speicherung
status: todo
depends_on: []
---

# Auth-Härtung — Passwörter, Master-Passwort, Rate-Limiting, Token-Speicherung

## Goal
Die Befunde aus dem Review vom 2026-09-04 beheben. **Diese Story hängt an nichts** — weder am
Kernel-Wechsel (Epic `009`) noch am restlichen Epic. Es sind Defekte im heutigen Stand, und jeder
Tag, den sie stehen bleiben, ist ein Tag mit knackbaren Passwörtern und einer Konfigurationszeile,
die Vollzugriff gewährt.

Deshalb **vorgezogen**: Sie wird auf dem Silex-Stand umgesetzt, nicht nach dem Umbau. Der spätere
Wechsel auf Symfony macht die Arbeit nicht überflüssig, er vereinfacht sie nur (siehe unten).

## Umfang

### A-1 — Passwort-Hashing
`User::setPass()` und `isPass()` rechnen `hash("sha256", $pass.$salt)`
([User.php:122](../../../../lib/contentfly/Entity/User.php)). Der Salt ist in Ordnung (64 Hex
pro Benutzer, im Konstruktor erzeugt), aber SHA-256 hat **keinen Arbeitsfaktor** — eine GPU prüft
Milliarden Kandidaten pro Sekunde. Umstellen auf `password_hash()` mit Argon2id (Fallback bcrypt).

**Bestandsdaten wandern beim Login mit:** Passt der alte SHA-256-Hash, wird das Passwort sofort neu
gehasht und gespeichert. Kein Zwangs-Reset, keine Migration im Voraus. Auf Symfony wird daraus
später `migrate_from: [legacy]` plus `PasswordUpgraderInterface` — dieselbe Logik, dann vom
Framework erledigt.

Die Spalte `pass` muss die längeren Hashes aufnehmen (mindestens `varchar(255)`).

### A-2 — Master-Passwort entfernen
`APP_MASTER_PASSWORD` akzeptiert den Login **für jeden Benutzer**
([AuthController.php:82-88](../../../../lib/contentfly/Controller/AuthController.php)). Das
Kundenprojekt setzte es beim Bootstrap ausdrücklich auf `null` — das Problem war bekannt.
**Ersatzlos entfernen**, nicht abschaltbar machen: Ein Schalter, der Vollzugriff gewährt, ist auch
ausgeschaltet eine Hintertür.

Für Bestandsprojekte ein Breaking Change — gehört in den Migrationsleitfaden (Epic `007`).

### A-3 — Rate-Limiting am Login
`CHECK_LOGIN_INTERVAL` ist eine `false`-Konstante, also fest aus; selbst eingeschaltet wäre es ein
60-Sekunden-Intervall **pro Benutzer** und keine Bremse gegen Raten über viele Konten. Es braucht
eine Begrenzung pro Kennung **und** pro IP, mit ansteigender Verzögerung. Die IP kommt hinter einem
Proxy nur mit korrekt gesetzten Trusted Proxies richtig an — sonst begrenzt man den Proxy.

### A-4 — Tokens gehasht speichern
`pim_token.token` steht im Klartext. Ein Lesezugriff auf die Datenbank — Backup, SQL-Injection —
übergibt sämtliche laufenden Sitzungen. Künftig nur noch einen Hash speichern und beim Prüfen
hashen statt vergleichen. Der Token selbst wird dem Client genau einmal ausgeliefert.

### Funktionale Defekte
- **Tote Routen:** `POST /api/login` und `/api/logout` zeigen auf `api.controller:loginAction`
  bzw. `logoutAction` — beide Methoden existieren im `ApiController` nicht. Entfernen oder
  implementieren, aber nicht so stehen lassen.
- **Verdrehter Plugin-Zweig:** `substr($loginProviderClassName, 7) == 'Plugins'`
  ([AuthController.php:159](../../../../lib/contentfly/Controller/AuthController.php)) schneidet
  **ab** Position 7 statt die ersten sieben Zeichen zu prüfen. Für `Plugins\Auth\Ldap` ergibt das
  `\Auth\Ldap`; die Bedingung greift nie und der Name wird fälschlich zu
  `Custom\Classes\Plugins\Auth\Ldap`. Login-Manager aus Plugins funktionieren dadurch nicht.
- **Doppelte, auseinandergelaufene Auflösung** des Login-Providers in `Auth::getLoginProvider()`
  und `AuthController::getLoginProvider()` — die eine kennt den Plugin-Zweig, die andere nicht.
  Auf eine Stelle zusammenführen.

### A-6 — bewusst nicht hier
`LoginManager::createManagedUser()` setzt das Passwort auf den Benutzernamen. Der Riegel „nur über
LoginManager authorisierbar" verhindert heute die Ausnutzung, aber die Kombination ist eine Mine.
Behoben wird sie in `013-004`, wo der LoginManager ohnehin umgebaut wird — hier würde sie eine
Änderung an einer Klasse erzwingen, die dort neu geschnitten wird.

## Fertig, wenn
- Kein Passwort mehr mit SHA-256 geprüft wird, außer beim einmaligen Umschlüsseln im Login.
- `APP_MASTER_PASSWORD` existiert nirgends mehr — weder in Code noch in Konfiguration.
- Wiederholte Fehlversuche werden nachweislich gebremst, pro Kennung und pro IP.
- `pim_token` enthält keine verwendbaren Tokens mehr, und die Authentifizierung funktioniert
  unverändert.
- Die drei funktionalen Defekte sind behoben.
- Die Breaking Changes (Master-Passwort, Token-Spalte, Passwort-Spalte) sind für Epic `007`
  notiert.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
