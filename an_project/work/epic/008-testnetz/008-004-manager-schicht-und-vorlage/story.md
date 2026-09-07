---
id: 008-004-0000
title: Manager-Schicht, Systemendpunkte und die Vorlage
status: review
depends_on: []
---

# Manager-Schicht, Systemendpunkte und die Vorlage

## Goal
Die Manager-Schicht ist die Naht zwischen Framework und Projekt: Über sie registriert ein Projekt
Routen, Typen und Commands. Epic `009` baut den Kernel darunter aus — was die Manager **nach
außen** zusagen, muss danach identisch gelten. Diese Story macht diese Zusage prüfbar und deckt
zugleich die letzten ungetesteten Endpunkte sowie die Vorlage `custom/` ab.

## Umfang

### A — Die Manager als Vertrag
Nach Epic `012` sind alle fünf schlank; genau deshalb lässt sich ihr Verhalten knapp festhalten:

| Manager | Was zugesichert ist |
|---|---|
| `RouteManager` | `mount()` sammelt Provider ein, `bindRoutes()` bindet sie — **nach** `custom/app.php`. Ein Projekt-Mount landet unter seinem Pfad und antwortet. |
| `TypeManager` | `registerType()` nimmt einen `Type`, weist einen `PluginType` mit Ausnahme ab, registriert dessen Annotationsdatei und legt ihn unter seinem Alias ab. `getType($alias)` liefert `null` statt zu werfen. |
| `ConsoleManager` | `addCommand()` hängt einen `CustomCommand` in den Dispatcher; er taucht unter `custom:<name>` auf. |
| `LoginManager` | `createManagedUser()` legt einen Benutzer an oder aktualisiert ihn; der Alias wird mit `md5(get_class($this))` präfixiert. |
| `PluginManager` | `register()` lädt ein Plugin, `getEntities()` sammelt dessen Entities ein. Der Nachweis aus `012-006-0002` (Wegwerf-Plugin mit Entity und Command) wird hier zu einem dauerhaften Test. |

> **Nebenbefund aus `012-006-0002`, hier zu berücksichtigen:** `PluginManager::getPlugin()`
> referenziert im Fehlerfall eine nicht existierende Variable `$key` statt `$pluginName`. Ein
> Test darauf würde den Fehler auslösen. Er ist zu **charakterisieren oder auszusparen**, aber
> nicht stillschweigend zu reparieren — die Reparatur ist ein eigener Task.

### B — Die verbliebenen Endpunkte
- **`/api/config`** — die einzige Route ohne `$checkAuth`. Was sie ohne Token preisgibt, gehört
  festgehalten.
- **`/api/mail`** — versendet Mails über PHPMailer. Zu testen ohne echten Versand; welcher Weg
  (Transport-Attrappe, `MAILER_*` auf einen Auffang-Host), ist beim Umsetzen zu entscheiden.
- **`SystemController`** — die verbliebenen Systemaktionen.

### C — Die Vorlage `custom/` läuft mit
Das Epic verlangt, dass „ein Projekt auf Basis dieses Frameworks funktioniert" ebenfalls
abgesichert ist. Konkret:
- Der Beispiel-Endpunkt `POST api/v1/example/bootstrap` aus `custom/app.php` antwortet mit dem
  Standard-Envelope — er ist zugleich der Beleg, dass `RouteManager` und die ungesicherte Route
  zusammenspielen.
- `custom/Entity/Core/Example.php` erscheint im Schema und ist über die generischen
  `/api`-Endpunkte les- und schreibbar.
- Die `before`/`after`-Hooks aus `custom/app.php` greifen — der `Referrer-Policy`-Header ist
  daran am einfachsten nachweisbar.

## Fertig, wenn
- Für jeden der fünf Manager ist die nach außen zugesagte Wirkung durch mindestens einen Test
  festgehalten.
- Das Plugin-Szenario aus `012-006-0002` existiert als dauerhafter Test, nicht mehr als
  Wegwerf-Aufbau.
- `/api/config`, `/api/mail` und der `SystemController` sind abgedeckt; beim Mailversand fließt
  keine echte Mail.
- Die drei Punkte zur Vorlage `custom/` sind abgedeckt.
- Der `getPlugin()`-Befund ist entweder charakterisiert oder mit Begründung ausgespart — und in
  beiden Fällen als eigener Task notiert.
- Die Suite läuft gegen den heutigen Silex-Stand grün.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 008-004-0001 — Manager-Schicht als Vertrag festhalten
- [ ] 008-004-0002 — Plugin-Infrastruktur dauerhaft absichern
- [ ] 008-004-0003 — SystemController und die Token-Verwaltung
- [ ] 008-004-0004 — /api/mail festhalten
- [ ] 008-004-0005 — Die Vorlage custom/ läuft mit
