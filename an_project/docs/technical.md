<!-- PURPOSE: Architektur, Tech-Stack und technische Konventionen DIESES Projekts. -->

# Technische Konventionen und Ist-Zustand

Der Ziel-Stack steht in `an_project/docs/tech-stack.md`, die Kernel-Entscheidung in
`an_project/docs/architecture.md`. Hier steht, wie der heutige Stand zustande kam — Wissen, das
weder aus dem Code noch aus der Git-Historie hervorgeht.

## `custom/` ist eine Vorlage, kein halbes Projekt

`custom/` enthält absichtlich nur je ein `Example`-Artefakt (`Controller/Core/ExampleController.php`,
`Entity/Core/Example.php`, `Classes/Service/Core/ApiResponseService.php`, `Command/ExampleCommand.php`).
Der Ordner wurde aus einem größeren Kundenprojekt kopiert, ausgedünnt und auf `Example` umgestellt.
Er dient als **Hilfe und Referenz**, was aktuell wie eingesetzt wird.

Konsequenz für alle, die den Baum lesen: `custom/app.php` (1358 Zeilen, 82 `mount()`-Aufrufe)
referenziert Klassen, die hier bewusst nicht liegen. Das ist kein Defekt und kein
unvollständiger Import. Dasselbe gilt für `custom/vendor` — der Inhalt stammt aus dem
Kundenprojekt und ist nicht der Soll-Zustand der Vorlage.

## Muster aus einem echten Projekt-Bootstrap

`custom/app.php` war bis zum 2026-09-04 die vollständige `app.php` eines Kundenprojekts —
1358 Zeilen, 82 Mounts, gegen Klassen, die es hier nie gab. Sie wurde durch eine schlanke,
bootfähige Vorlage ersetzt (Task `000-000-0001`). Was an ihr dokumentarisch wertvoll war,
steht hier: Es beschreibt, wie ein gewachsenes Projekt auf diesem Framework aussieht, und
ist die Vorlage dafür, **was Epic 009 beim Wechsel auf Symfony portieren muss**. Der
Originalstand ist über die Git-Historie erreichbar (`git show <commit vor dem Ersatz>:custom/app.php`).

### Reihenfolge der Middleware ist Sicherheitslogik, kein Detail

Vier eigene Middleware, deren Reihenfolge jeweils begründet war:

1. **StateGate** (`before`) — blockt zustandsändernde Requests (POST/PUT/PATCH/DELETE), wenn
   der Tenant trial-abgelaufen ist oder offene Rechtsdokumente hat. Lesende Zugriffe passieren
   immer; eine enge Allowlist hält Renewal-, Billing- und Logout-Endpunkte erreichbar.
2. **CrossTenantBackstop** (`before`) — Auffangnetz für „die Action hat ihre
   Tenant-Mitgliedschaftsprüfung vergessen". Fail-open: blockt nur ein signaturgeprüftes,
   tenant-gebundenes JWT, dessen Inhaber im `tenantId` des Bodys **keine** Rolle hat.
   Bewusst **nach** StateGate, damit Trial-/Legal-423 Vorrang behalten.
3. **JwtDenylist** (`before`) — weist 401 zurück, wenn die `jti` des Tokens widerrufen wurde
   (Logout, Deaktivierung, Leak). Fail-open: Nur ein bestätigter Treffer blockt.
4. **ActivityTracker** (`after`) — schreibt `lastActivityAt` fort, gedrosselt auf ≤ 1×/h.
   Bewusst als After-Hook, damit es den Request unter keinen Umständen stören kann.

Silex nimmt bei `before()`/`after()` ein **Prioritätsargument** (`$app->before(fn, 128)`,
`$app->after(fn, -100)`). Wo es gesetzt war, war es Absicht — beim Portieren auf Symfony-Listener
muss die effektive Reihenfolge nachgewiesen werden, nicht die Registrierungsreihenfolge geraten.

### Weitere Muster, die beim Portieren nicht verloren gehen dürfen

| Muster | Kern |
|---|---|
| **Session-Write-Close** | Das Framework startet bei **jedem** Request eine PHP-Session (`Auth::init()`), und PHP hält darauf einen exklusiven Lock bis Skriptende. Da alle Browser-Tabs eine PHPSESSID teilen, serialisieren gleichzeitige API-Aufrufe dahinter. Das Projekt schloss die Session für `/api/v1/*` **sofort beim Require**, nicht in einer Middleware — eine ganze Bootstrap-Phase früher. Gemessen: Nebenläufigkeit von ~2,8× auf Richtung ~3,7×. Mit Story `012-004` entfällt die Session ganz, damit auch dieser Workaround. |
| **Trusted Proxies** | `Request::setTrustedProxies(<Proxy-Range>, HEADER_X_FORWARDED_FOR)` — bewusst **nur** X-Forwarded-For, nicht Host/Proto, damit CORS und Tenant-Subdomain-Auflösung unverändert bleiben. Ohne das liefert `getClientIp()` die Proxy-IP, und Login-Ratelimit wie Audit-Log werden wertlos. |
| **Master-Password neutralisiert** | `Adapter::getConfig()->APP_MASTER_PASSWORD = null` direkt nach dem Bootstrap — die Framework-Hintertür wird unabhängig von Konfiguration und Umgebung inert gesetzt. |
| **Security-Header** | HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy als After-Hook auf jeder Antwort; CSP zunächst im Report-Only-Modus und erst nach sauberen Reports scharf geschaltet. |
| **CORS-Allowlist** | In Produktion werden `Access-Control-Allow-Origin`/`-Credentials` entfernt, wenn keine Allowlist gesetzt ist — Fail-closed. In Entwicklung bleibt der Reflect-Origin-Fallback des Frameworks, damit lokales Arbeiten nicht bricht. |
| **Refresh-Token als HttpOnly-Cookie** | Der Refresh-Token wurde zusätzlich als HttpOnly-Cookie ausgeliefert (host-only, SameSite=Lax, Secure nur über HTTPS), damit die SPA ihn nicht im localStorage halten muss — wo ein XSS ihn zu einer dauerhaften Kontoübernahme machen kann. |
| **Pro-Tenant-JWT-Secret** | `$app['tenantSecretResolver']` als `$app->protect()`-Closure: schlägt das JWT-Secret pro Tenant in der Datenbank nach. `protect()` ist nötig, weil Pimple eine Closure sonst als Factory versteht und aufruft. |

### Routen: der RouteManager, nicht `$app->get()`

Ein Projekt registriert Routen über `$app['routeManager']`, nicht direkt am `$app`:

```php
$controllerProvider = $app['routeManager'];
$controllerProvider->mount('api/v1/bereich/', '\Custom\Controller\...Controller')
    ->post('/aktion', true, 'aktionAction')   // true = Token erforderlich
    ->get('/liste',   false, 'listeAction');  // false = öffentlich
```

Der Manager sammelt die Mounts nur ein; gebunden werden sie in `bootstrap.php` unmittelbar
nach `custom/app.php` durch `bindRoutes()`. Das `isSecure`-Flag ist die
Authentifizierungsentscheidung pro Route — beim Portieren ist es die Information, die als
Erstes verloren geht, wenn Routen „nur umgeschrieben" werden.

### Offene Inkonsistenz in der Vorlage

`custom/Command/ExampleCommand.php` erbt von `Symfony\…\Console\Command` und erwartet
`$app` im Konstruktor. Der `ConsoleManager` des Frameworks nimmt aber ausschließlich
`Areanet\PIM\Classes\Command\CustomCommand`. Das Beispiel ist deshalb in `custom/app.php`
bewusst **nicht** registriert — es zeigt einen Weg, den das Framework so nicht anbietet.
Beim Aufräumen der Vorlage zu entscheiden: Beispiel auf `CustomCommand` umstellen, oder den
`ConsoleManager` für gewöhnliche Symfony-Commands öffnen.


## Authentifizierung heute (Review 2026-09-04)

Es gibt **zwei** Auth-Wege, und beide sind stateful:

| Weg | Träger | Wo |
|---|---|---|
| Session | `$_SESSION['auth.userid']` | `Classes/Auth.php:32` — nur für die Admin-UI, fällt mit Story `012-004` |
| DB-Token | Tabelle `pim_token` | `Classes/Controller/Provider/BaseControllerProvider.php:92` (`checkToken`) |

Der Token-Weg: `POST /auth/login` prüft die Anmeldedaten, legt eine `Token`-Zeile an (128 Hex aus
`openssl_random_pseudo_bytes(64)` — Entropie ist in Ordnung) und gibt sie zurück. Folgerequests
schicken sie als `appcms-token`, `X-XSRF-TOKEN` oder `_token`. `checkToken()` schlägt nach, prüft
Benutzer und Timeout (`APP_TOKEN_TIMEOUT`, per Gruppe überschreibbar) und **schreibt `modified`
bei jedem Request zurück** — Sliding Expiration als DB-Write pro Aufruf. Das ist der eigentliche
Preis des Modells und das Argument für einen stateless-Pfad.

**JWT gibt es im Framework nicht.** `firebase/php-jwt` liegt ausschließlich in `custom/vendor`;
die JWT-Logik lag im Kundenprojekt. Was oben unter *Pro-Tenant-JWT-Secret* steht, beschreibt
fremden Code, keine vorhandene Funktion.

### Befunde

| # | Befund | Wirkung |
|---|---|---|
| A-1 | Passwörter sind `hash("sha256", $pass.$salt)` (`Entity/User.php:122`). Salt pro Benutzer ist da, aber SHA-256 hat keinen Arbeitsfaktor | Geleakte Benutzertabelle ist in Stunden geknackt |
| A-2 | `APP_MASTER_PASSWORD` akzeptiert den Login für **jeden** Benutzer (`Controller/AuthController.php:82-88`) | Vollzugriff über eine Konfigurationszeile |
| A-3 | Kein Rate-Limiting — `CHECK_LOGIN_INTERVAL` ist eine `false`-Konstante | Brute Force gegen A-1 ungebremst |
| A-4 | `pim_token.token` steht im Klartext | Ein Lesezugriff auf die DB übergibt alle laufenden Sitzungen |
| A-5 | `referrer`-Tokens laufen nie ab, und der Token-String kommt beim Anlegen vom Client (`Controller/SystemController.php:159`) | Ratbare Dauerschlüssel möglich |
| A-6 | `LoginManager::createManagedUser()` setzt `setPass($alias)` — das Passwort ist der Benutzername | Latente Übernahme aller SSO-Konten |

**Funktionale Defekte:** `POST /api/login` und `/api/logout` zeigen auf Methoden, die im
`ApiController` nicht existieren · der Plugin-Zweig der LoginManager-Auflösung prüft
`substr($name, 7) == 'Plugins'` statt der ersten sieben Zeichen und greift deshalb nie ·
die Auflösung existiert doppelt (`Auth.php` und `AuthController.php`) und ist auseinandergelaufen.

Behandelt wird das in Epic `013`; die Härtung (A-1 bis A-4 und die Defekte) hängt in Story
`013-001` bewusst an nichts und wird vor dem Kernel-Wechsel umgesetzt.

## Warum `vendor/` in Git liegt

Der Root-`vendor/`-Baum ist committet und hat **kein `composer.json`**. Das war kein Versehen,
sondern eine bewusste Notlösung: Um Contentfly ohne größeres Update und ohne Ausfallzeiten
produktiv auf PHP 8.x weiterbetreiben zu können, wurde ein schnelles „Fake"-Update gefahren —
Composer entfernt, der funktionierende Vendor-Stand eingefroren und versioniert.

Der Preis dafür ist die heutige Lage: kein Upgrade-Pfad, kein `composer audit`, keine
ausdrückbaren Version-Constraints. Die Rückführung auf Composer ist Epic `006-000-0000`.

## Zwei Autoloader in einem Prozess

`lib/contentfly/bootstrap.php:8-10` lädt erst `vendor/autoload.php`, dann — falls vorhanden —
`custom/vendor/autoload.php`. Dadurch existieren Pakete doppelt, teils in inkompatiblen Majors:

| Paket | Root | `custom/` | Wirkung |
|---|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 | Der zuerst registrierte Loader gewinnt, also der alte. `sentry/sentry` 4.22 verlangt `^3` und läuft real gegen die 1.x-Interfaces — Signatur-Risiko. |
| `symfony/polyfill-ctype` | 1.14.0 | 1.37.0 | Durch `function_exists`-Guards unkritisch, aber Ballast. |
| `symfony/polyfill-mbstring` | 1.14.0 | 1.38.2 | dito |

Dazu treffen zwei Symfony-Generationen aufeinander: `symfony/http-foundation` 3.4 im Root gegen
`symfony/options-resolver` 7.4 in `custom/` (via Sentry).

## Was den Alt-Baum an PHP 8.5 hindert

| Paket | Constraint | Folge |
|---|---|---|
| `ellumilel/php-excel-writer` | `php: ^5.4\|^7.0` | Unter PHP 8.5 **nicht installierbar**. Genutzt in `lib/contentfly/Controller/ExportController.php:9`. |
| `silex/silex` 2.2.2 | `symfony/*: ~2.8\|^3.0` | Deckelt Symfony auf 3.4 (EOL Nov 2020, nie für PHP 8 freigegeben). Der `php`-Constraint ist nach oben offen — Composer meldet nichts. |
| `dflydev/doctrine-orm-service-provider` | `doctrine/orm: ~2.3` | Blockiert den Weg auf ORM 3. |
| `doctrine/orm` | `dev-bugfix-many2many` | Dev-Branch-Pin ohne Release — in keinem Upgrade-Pfad ausdrückbar. |

Dev-Werkzeuge liegen heute im ausgelieferten Baum: `phpstan/phpstan` 1.10.58 und `rector/rector`
1.0.1 im Root, `phpunit` 10.5 und `mockery` in `custom/vendor`.

## Die Testsuite ist die Abnahmegrundlage

**Festgelegt am 2026-09-08 mit Story `008-005`.** Epic `008` hat 238 Tests und 575 Assertions
hervorgebracht. Damit ist `tests/` mehr als ein Testordner: Es ist die **Beschreibung des
heutigen Verhaltens** — aufgenommen, bevor der Kernel getauscht wird, und genau zu diesem
Zweck.

> **Der Kernel-Tausch gilt als gelungen, wenn diese Suite ohne inhaltliche Änderung grün
> bleibt. Eine Testanpassung ist ein Verhaltenswechsel und braucht eine Begründung — sie ist
> kein Wartungsschritt.**

Ohne diese Festlegung ist beim Umbau nicht entschieden, was ein roter Test bedeutet: einen
Fehler im Umbau — oder einen Test, der „eben angepasst werden muss". Diese Unterscheidung ist
der einzige Grund, warum das Testnetz vor dem Umbau gebaut wurde.

### Was **keine** inhaltliche Änderung ist
Der Satz soll legitime Umbauten nicht blockieren. Erlaubt und nicht begründungspflichtig:

- ein geänderter Namensraum oder Klassenname,
- ein anderer Aufrufweg für **dieselbe** Zusicherung (etwa ein Symfony-Client statt cURL),
- ein umbenannter oder umgebauter Helfer in `IntegrationTestCase`,
- eine neue Assertion, die Bestehendes ergänzt, ohne eine bestehende abzuschwächen.

Begründungspflichtig ist alles, was **eine Zusicherung ändert oder streicht**: ein anderer
erwarteter Statuscode, eine gelockerte Assertion, ein entfernter oder als „skipped" markierter
Test.

### Für welche Epics das gilt
- **`009` (Kernel-Tausch):** Die Suite ist das Abnahmekriterium. Was heute grün ist, muss nach
  dem Tausch grün sein.
- **`011` (Release):** Dieselbe Suite als Release-Kriterium.
- **`007` (Migration der Bestandsprojekte):** Die Suite als Referenz, an der ein
  Bestandsprojekt prüfen kann, ob sein eigener Umstieg geglückt ist.

### Was die Suite **nicht** abdeckt
Eine Abnahmegrundlage, die ihre Grenzen verschweigt, wiegt in Sicherheit. Diese Liste ist
keine Schwäche des Testnetzes, sondern seine Bedienungsanleitung: **Wer beim Umbau eine dieser
Stellen anfasst, weiss, dass kein Test ihn auffängt.**

| Lücke | Warum | Festgestellt in |
|---|---|---|
| `excludeFromSync`, `i18n_universal`, `encoded`, OneJoin-Kaskade, **Schreibprüfung** des `MultijoinType` | Codepfade, die sich mit der Vorlage nicht auslösen lassen; geprüft ist nur die Vorbedingung — sie greifen, wenn jemand das Feature einschaltet | `008-001`, `008-002` |
| `canExport`, `getExtended` | veröffentlicht, aber nirgends durchgesetzt; ihr einziger Konsument war die gelöschte Oberfläche | `008-003-0005` |
| Das Notschloss im `before`-Hook des `SystemController` | nicht scharf geprüft — es verlangt ein absichtlich beschädigtes Schema der gemeinsamen Testdatenbank | `008-004-0003` |
| Der `before`-Hook der Vorlage | setzt einen Wert, den niemand liest; von aussen nicht nachweisbar | `008-004-0005` |
| Sprachen und i18n | die Vorlage konfiguriert keine Sprachen und bringt keine konkrete `BaseI18n`-Entity mit; abgedeckt ist nur die Logik im Unit-Test | `008-001-0005` |
| Der Erfolgsfall von `/api/mail` | existiert nicht — der Endpunkt scheitert an einer undefinierten Konstanten | `008-004-0004` |
| Das `json`-Feld der Beispiel-Entity | für die API nicht vorhanden, weil kein `json`-Typ registriert ist | `008-004-0005` |

### Was seit dieser Liste dazugekommen ist
- **Der ManyToMany-Pfad `PIM\File.tags`** ist seit `006-002-0001` abgedeckt
  (`ManyToManyApiTest`): anlegen, lesen, ändern, leeren, löschen und filtern, je mit Prüfung
  der Verknüpfungstabelle **per SQL**. Anlass war der Doctrine-Wechsel weg von einem eigenen
  Fork namens `bugfix-many2many`.

  **Nicht zu verwechseln** mit der Zeile oben: Die *Schreibprüfung* des `MultijoinType` ist ein
  Berechtigungspfad im `acceptFrom`-Zweig und bleibt nicht auslösbar. Das ORM-Verhalten ist
  jetzt geprüft, die Rechteprüfung darauf nicht.

Dazu kommt eine Grenze des Wächters selbst: Er prüft die **Vorbedingungen** eines
Integrationslaufs (Umgebungsvariablen, Testserver, Datenbank, Versandfalle), nicht jeden
denkbaren Übersprung. Ein `markTestSkipped()`, das jemand einem einzelnen Test hinzufügt,
fällt nicht auf.

### Wo die Suite läuft
`.gitlab-ci.yml` fährt sie bei jedem Push, gegen PHP 8.3 (pflicht) und 8.4 (`allow_failure`
als Frühwarnung für den Sprung auf 8.5). Die Schritte stehen in `tools/ci/`, damit sie sich
lokal nachspielen lassen. Details: `tests/README.md`, Einrichtung: `an_project/docs/runbook.md`.
