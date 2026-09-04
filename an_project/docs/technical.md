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
