---
id: 013-001-0005
title: Die funktionalen Defekte und die Bruchstellen
status: done
depends_on: [013-001-0001, 013-001-0002, 013-001-0003, 013-001-0004]
---

# Die funktionalen Defekte und die Bruchstellen

## Context
Der Rest der Story, und die Buchführung.

**Tote Routen.** `POST /api/login` und `POST /api/logout` zeigen auf
`api.controller:loginAction` beziehungsweise `logoutAction` — beide Methoden gibt es im
`ApiController` **nicht**. Ein Aufruf endet nicht mit 404, sondern mit einem Fehler aus dem
Controller-Resolver. Zu entscheiden: entfernen oder auf `auth.controller` zeigen lassen. Die
funktionierenden Routen sind `/auth/login` und `/auth/logout`.

**Verdrehter Plugin-Zweig.** `substr($loginProviderClassName, 7) == 'Plugins'`
(`AuthController.php:158`) schneidet **ab** Position 7, statt die ersten sieben Zeichen zu
prüfen. Für `Plugins\Auth\Ldap` ergibt das `\Auth\Ldap`; die Bedingung greift nie, und der
Name wird fälschlich zu `Custom\Classes\Plugins\Auth\Ldap`. **Login-Manager aus Plugins
funktionieren dadurch nicht** — und das fällt niemandem auf, weil `plugins/` leer ist.

**Die Bruchstellen** aus dieser Story gehören gesammelt nach `breaking-changes.md`: das
Master-Passwort, die Token-Spalte, die Passwort-Spalte, und dass alle Sitzungen enden.

## Acceptance criteria
- [x] Über die toten Routen ist entschieden und begründet; ein Aufruf liefert danach eine verständliche Antwort statt eines Resolver-Fehlers.
- [x] Der Plugin-Zweig prüft die ersten sieben Zeichen; ein Test belegt die Auflösung für einen Namen mit und einen ohne `Plugins`-Präfix.
- [x] Dass der Pfad mangels Plugin nicht end-to-end prüfbar ist, steht als Einschränkung im Ergebnis — nicht als stillschweigende Lücke.
- [x] Alle Bruchstellen der Story stehen in `breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [x] Die Gates bleiben grün, und der Lauf auf PHP 8.4 ebenfalls.

## Verification
Volle Suite auf beiden PHP-Versionen, `composer audit --locked`, PHPStan. Für die Routen ein
Aufruf über HTTP: Was antwortet `/api/login` vorher, was nachher? Für den Plugin-Zweig ein
Unit-Test auf die Namensauflösung.

## Ergebnis

### Die toten Routen waren schlimmer und harmloser zugleich

Der Task nahm an, `POST /api/login` und `/api/logout` endeten „mit einem Fehler aus dem
Controller-Resolver". **Gemessen antworteten sie mit `405`** — wortgleich mit einem Pfad, den es
gar nicht gibt:

```
POST /api/login       405  No route found for "POST …/api/login": Method Not Allowed (Allow: OPTIONS)
POST /api/gibtsnicht  405  No route found for "POST …/api/gibtsnicht": Method Not Allowed (Allow: OPTIONS)
```

Der Grund ist ein eigener Defekt: **Sie erreichten den Router nie.** `Routensammlung` zählt ihre
Routen **je Provider** durch — die erste Route heisst `login_0`, egal aus welchem Provider sie
kommt. `RouteCollection::addCollection()` überschreibt beim Namen, und `/auth` wird nach `/api`
gemountet. Nachgezählt an der laufenden Anwendung:

| | |
|---|---|
| registriert | 30 |
| in der Sammlung | 29 |
| verschwunden | `POST /api/login`, `POST /api/logout` |

**Das ist keine Eigenheit dieser beiden Routen.** Jedes Projekt, das über `custom/app.php`
mountet, verlor stillschweigend jede Route, deren Name mit einer schon gemounteten kollidierte.
`Application::mount()` stellt den Namen jetzt den normalisierten Mountpunkt voran. Nach der
Korrektur: 28 registriert, 28 angekommen, plus die Projektroute aus der Vorlage.

**Die beiden Routen sind entfernt, nicht umgebogen.** Sie auf `auth.controller` zeigen zu lassen
wäre ein zweiter Name für dieselbe Sache und eine zweite Oberfläche, die man absichern muss. Für
einen Aufrufer ändert sich dadurch nichts — `405`, wie vorher, jetzt aus dem richtigen Grund.

### Der Plugin-Zweig

`substr($name, 7) == 'Plugins'` schneidet **ab** Position 7. Für `Plugins\Auth\Ldap` ergibt das
`\Auth\Ldap`; die Bedingung griff nie, und der Name wurde zu `Custom\Classes\Plugins\Auth\Ldap`.
Jetzt `str_starts_with()`.

Die Auflösung steht als eigene Methode `AuthController::providerKlasse()` — damit sie ohne
laufende Anwendung prüfbar ist. Ein Test hält beide Fälle fest und ein dritter rechnet die alte
Formel nach, damit nicht nur das Ergebnis stimmt, sondern belegt ist, dass der Fehler weg ist.

**Einschränkung, ausdrücklich und nicht stillschweigend:** Dieser Pfad ist **nicht**
end-to-end prüfbar. `plugins/` ist leer, und ein Plugin nur für einen Test anzulegen hiesse, die
Lücke mit Testcode zu füllen, statt sie zu benennen. Geprüft ist die Stelle, die falsch war —
dass ein aufgelöster Name danach durch `class_exists()` und die `LoginManager`-Prüfung läuft,
deckt `LoginManagerApiTest` für den `Custom\Classes`-Weg ab.

### Der dritte Defekt war schon erledigt

Die doppelte Provider-Auflösung (`Auth.php` und `AuthController.php`) gibt es nicht mehr —
festgestellt beim Refinement am 2026-09-10, gefallen irgendwo in Epic `009` oder `012`.
`technical.md` ist entsprechend nachgezogen.

### Die Buchführung

Zehn Bruchstellen dieser Story stehen in `breaking-changes.md`, jede mit dem, was ein Projekt zu
tun hat:

| Aus | Bruchstelle |
|---|---|
| `0001` | Argon2id; `pim_user.pass` fasst 255 statt 100 Zeichen |
| `0002` | `APP_MASTER_PASSWORD` gibt es nicht mehr |
| `0003` | Der Login antwortet mit `429`; `APP_TRUSTED_PROXIES` hinter einem Proxy |
| `0004` | Alle Sitzungen enden; `listTokens` liefert den Hash; `pim_log.model_label` trägt den Hash |
| `0005` | `/api/login` und `/api/logout` entfallen; Routennamen tragen den Mountpunkt; Plugin-LoginManager funktionieren |

In `technical.md` sind A-1 bis A-4 durchgestrichen und die funktionalen Defekte aufgelöst.

### Nachweis

| Probe | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Volle Suite | `OK (321 tests, 856 assertions)` | `OK (321 tests, 856 assertions)` |
| Deprecations | 0 | 0 |
| Postausgang | 0 Byte | 0 Byte |

Dazu PHPStan `[OK] No errors` und `composer audit --locked` ohne Advisories.
