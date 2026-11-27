---
id: 016-002-0000
title: Was ein Projekt entscheiden muss, schreibt das Framework nicht mehr fest — Konto, Verbindung, Origin
status: todo
depends_on: []
---

# Was ein Projekt entscheiden muss, schreibt das Framework nicht mehr fest — Konto, Verbindung, Origin

## Goal

Drei Vorgaben, die das Framework heute unverhandelbar setzt, kann ein Projekt setzen, ohne
Frameworkcode zu patchen. Erledigt sind damit `#103`, `#104` und `#107`.

**Dreimal dasselbe Muster**, und darum eine Story: eine Annahme des Frameworks ist für ein
mandantenfähiges Projekt falsch, die Annahme steckt in Code statt in Konfiguration, und das
Projekt lebt heute mit einem Patch im Framework. Genau diese Patches sind die Abnahme — jedes der
drei Issues nennt den seinen unter *„Womit wir heute leben"*.

Gegengeprüft am 27.11.2026 auf `master`:

| Issue | Vorgabe heute | Warum sie nicht trägt |
|---|---|---|
| `#103` | `Entity/User`: `alias` ist `unique: true`, `pass` hat kein `nullable` | In einer mandantenfähigen Anwendung gehört die Eindeutigkeit auf `(tenant_id, email)`, nicht auf `alias` allein. Bei SSO oder einer offenen Einladung existiert das Konto, bevor es ein Passwort gibt. |
| `#104` | `ConnectionDefaults::driverOptions()` gibt genau eine Option zurück, `PDO::ATTR_STRINGIFY_FETCHES => true` | Ein Projekt kann keinen `MYSQL_ATTR_INIT_COMMAND` ergänzen. `driverOptions` werden bei der Konstruktion gelesen — ein späteres Setzen wirkt nicht mehr. Dem Projekt bleibt nur, Verbindung und EntityManager nach dem Bootstrap neu zu bauen. |
| `#107` | `CorsPolicy::allowedOrigin()` vergleicht mit `strcasecmp()` exakt gegen die Einträge aus `APP_ALLOW_ORIGIN` | Eine Anwendung mit einer Subdomain je Mandant kann diese Liste nicht pflegen — sie wächst mit jedem Mandanten, und beim Anlegen müsste die Konfiguration nachgezogen werden. |

## Die Entscheidung, die dreimal gilt

`#103` schlägt zwei Wege vor und lässt die Wahl offen: **Config-Schalter** oder **Mapping per
`loadClassMetadata`-Event überschreibbar**. Diese Frage ist einmal zu beantworten und gilt für alle
drei Vorgaben, sonst bekommt das Framework drei verschiedene Erweiterungsmechanismen für dasselbe
Bedürfnis. Die Antwort gehört als ADR festgehalten.

Zwei Punkte sind dabei nicht Geschmackssache:

- **Der Opt-out für `ATTR_STRINGIFY_FETCHES` dreht eine Vorgabe, die `000-000-0044` bewusst
  gesetzt hat.** Die Begründung steht ausführlich im Docblock von `ConnectionDefaults`: seit
  PHP 8.1 liefert `pdo_mysql` native Typen, und im Bestandsprojekt UFP vergleicht das Frontend an
  rund fünfzig Stellen strikt mit `'1'`/`'0'`. Keiner dieser Vergleiche wirft, sie werden alle nur
  still falsch. Ein Opt-out ist vertretbar, das Umdrehen der **Vorgabe** nicht — und der Docblock
  sagt bereits, warum. Diese Begründung ist beim Umbau mitzunehmen, nicht zu überschreiben.
- **Zusätzliche `driverOptions` werden mit den Standardwerten zusammengeführt, nicht ersetzt**, und
  zwar je Verbindung. Ein Projekt, das einen INIT-Command setzt, darf dabei nicht versehentlich den
  `STRINGIFY`-Standard verlieren.

## Was bei `#107` schiefgehen kann

Hier ist der Vorschlag sicherheitsrelevant, und das Issue benennt die Falle selbst. Beim Umsetzen
gilt:

- `*` ersetzt **genau ein Host-Label** (`*.example.com`), und abgeglichen wird gegen den **Host**
  der Origin — nicht als freies Muster über den ganzen String. Sonst trifft
  `evil.com/x.example.com`.
- Credentials nur bei einem Treffer.
- Die Rückfall-Regel „beliebige Origin spiegeln plus Credentials" darf **nicht** greifen, wenn kein
  Eintrag passt.
- Der heutige Sonderfall `*` als einzelner Eintrag (gibt `'*'` zurück) bleibt davon unberührt und
  muss nach dem Umbau dasselbe tun.

Das Projekt löst das heute mit einer eigenen Middleware, die die Antwort der Lib zurücknimmt. Das
Issue fragt ausdrücklich, ob sich das mit v2 als **Hook** sauberer lösen lässt — diese Frage ist
mit zu beantworten, nicht nur das Muster zu bauen.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] noch keine — über `/new-task 016-002` anlegen
