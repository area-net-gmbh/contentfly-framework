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
unvollständiger Import. Dasselbe galt für `custom/vendor` — der Inhalt stammte aus dem
Kundenprojekt und war nicht der Soll-Zustand der Vorlage. Seit `006-003` liegt der Baum nicht
mehr im Repo und wird auch nicht mehr gebaut.

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

`before()` und `after()` nehmen weiterhin ein **Prioritätsargument** (`$app->before(fn, 128)`,
`$app->after(fn, -100)`) — die Schnittstelle ist die von Silex, der Unterbau seit `009-002` ein
Symfony-`EventDispatcher`. Wo eine Priorität gesetzt war, war es Absicht, und sie gilt
unverändert: Höhere Priorität läuft zuerst, bei gleicher Priorität die frühere Registrierung.
Die Vorgabe ist −8 wie in Silex, damit ein Projekt-Hook Vorrang vor dem Router hat.

**Nachgewiesen, nicht behauptet:** `tests/Unit/Kernel/HookOrderTest.php` hält die
effektive Reihenfolge fest, für `before` wie für `after`, und dazu die Regel, dass ein Hook
**keine** spätere Command-Registrierung verhindern darf (`009-004-0004`). Genau daran ist der
Nachbau zuerst gescheitert.

### Weitere Muster, die beim Portieren nicht verloren gehen dürfen

| Muster | Kern |
|---|---|
| **Session-Write-Close** | Das Framework startet bei **jedem** Request eine PHP-Session (`Auth::init()`), und PHP hält darauf einen exklusiven Lock bis Skriptende. Da alle Browser-Tabs eine PHPSESSID teilen, serialisieren gleichzeitige API-Aufrufe dahinter. Das Projekt schloss die Session für `/api/v1/*` **sofort beim Require**, nicht in einer Middleware — eine ganze Bootstrap-Phase früher. Gemessen: Nebenläufigkeit von ~2,8× auf Richtung ~3,7×. Mit Story `012-004` entfällt die Session ganz, damit auch dieser Workaround. |
| **Trusted Proxies** | `Request::setTrustedProxies(<Proxy-Range>, HEADER_X_FORWARDED_FOR)` — bewusst **nur** X-Forwarded-For, nicht Host/Proto, damit CORS und Tenant-Subdomain-Auflösung unverändert bleiben. Ohne das liefert `getClientIp()` die Proxy-IP, und Login-Ratelimit wie Audit-Log werden wertlos. |
| ~~**Master-Password neutralisiert**~~ | **Erledigt mit `013-001-0002`, und zwar an der Wurzel.** Das Kundenprojekt setzte `APP_MASTER_PASSWORD` beim Bootstrap auf `null`, um die Framework-Hintertür inert zu machen — es kannte das Problem und schützte sich davor. Die Konstante ist jetzt **ersatzlos entfallen**; es gibt nichts mehr zu neutralisieren. Wer dieses Muster portiert, kann die Zeile streichen. |
| **Security-Header** | HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy als After-Hook auf jeder Antwort; CSP zunächst im Report-Only-Modus und erst nach sauberen Reports scharf geschaltet. |
| **CORS-Allowlist** | In Produktion werden `Access-Control-Allow-Origin`/`-Credentials` entfernt, wenn keine Allowlist gesetzt ist — Fail-closed. In Entwicklung bleibt der Reflect-Origin-Fallback des Frameworks, damit lokales Arbeiten nicht bricht. |
| **Refresh-Token als HttpOnly-Cookie** | Der Refresh-Token wurde zusätzlich als HttpOnly-Cookie ausgeliefert (host-only, SameSite=Lax, Secure nur über HTTPS), damit die SPA ihn nicht im localStorage halten muss — wo ein XSS ihn zu einer dauerhaften Kontoübernahme machen kann. |
| **Pro-Tenant-JWT-Secret** | `$app['tenantSecretResolver']` als `$app->protect()`-Closure: schlägt das JWT-Secret pro Tenant in der Datenbank nach. `protect()` war nötig, weil Pimple eine Closure sonst als Factory versteht und aufruft. **Der neue Container kennt `protect()` nicht** — er hat dieselbe Closure-Regel, aber niemand im Baum legt eine Closure als Wert ab. Wer dieses Muster portiert, braucht es zuerst; der Fehler wäre laut, nicht still (`Kernel\Container::offsetSet()`). |

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

### Aufgelöst: die Inkonsistenz in der Vorlage

`custom/Command/ExampleCommand.php` erbte von `Symfony\…\Console\Command` und erwartete
`$app` im Konstruktor. Der `ConsoleManager` nimmt aber ausschließlich
`Areanet\PIM\Classes\Command\CustomCommand`, und das Beispiel war deshalb in `custom/app.php`
nicht registriert — es zeigte einen Weg, den das Framework nicht anbietet.

**Entschieden mit `009-004-0001`: Das Beispiel erbt jetzt von `CustomCommand` und ist
registriert.** Nicht der andere Weg — den `ConsoleManager` für jedes Symfony-Command zu öffnen
—, weil der `custom:`-Präfix die Zusicherung trägt, dass ein Projekt-Command nie einen des
Frameworks überschreibt. Wäre der Manager offen, wäre der Präfix nur noch ein Angebot, und
`appcms:install` liesse sich überschreiben. Der Command heisst dadurch
`custom:example:command:run`; am Namen sieht man, wem er gehört.

**Dabei ist ein Defekt aufgefallen** (`009-004-0004`): Ein `before()`-Hook in `custom/app.php`
las den Dispatcher aus und fror ihn ein, sodass jede danach registrierte Console-Anmeldung mit
`RuntimeException` scheiterte. Silex hatte die Registrierung vor dem Boot verschoben; beim
Nachbau ging das verloren, weil kein Test die Abfolge „erst ein Hook, dann ein Command"
abdeckte — die Vorlage ging diesen Weg ja nie.


## Authentifizierung heute (Review 2026-09-04)

Es gibt **zwei** Auth-Wege, und beide sind stateful:

| Weg | Träger | Wo |
|---|---|---|
| ~~Session~~ | ~~`$_SESSION['auth.userid']`~~ | mit Story `012-004` entfallen |
| ~~DB-Token über `checkToken()`~~ | Tabelle `pim_token` | mit `013-002-0004` abgelöst |

Der Token-Weg: `POST /auth/login` prüft die Anmeldedaten, legt eine `Token`-Zeile an (128 Hex aus
64 Zufallsbytes) und gibt sie zurück. Folgerequests schicken sie als `appcms-token`,
`X-XSRF-TOKEN` oder `_token`. Nachgeschlagen wird der Hash der Zeile (`013-001-0004`), geprüft
werden Benutzer und Timeout (`APP_TOKEN_TIMEOUT`, per Gruppe überschreibbar), und `modified` wird
**bei jedem Request zurückgeschrieben** — Sliding Expiration als DB-Write pro Aufruf. Das ist der
eigentliche Preis des Modells und das Argument für einen stateless-Pfad.

### Seit `013-002`: ein Mechanismus, zwei Zweige

`checkToken()` gibt es nicht mehr. An seiner Stelle steht Symfonys `access_token`-Authenticator,
gefahren von einem eigenen Treiber — **ohne** Firewall, denn der Baum hat weder `config/` noch
SecurityBundle:

| Klasse | tut |
|---|---|
| `Classes/Security/Tokenquellen` | woher ein Token kommen darf: `Authorization: Bearer` plus die vier Altquellen, in der Reihenfolge von früher |
| `Classes/Security/Tokenhandler` | verzweigt nach der Form: JWT oder `pim_token` |
| `Classes/Security/Anmeldetreiber` | fährt den Authenticator und fängt jeden Fehlschlag gleich ab |
| `Classes/Security/Benutzerlader` | macht aus einer Kennung einen Benutzer |

Damit ist „stateful oder stateless" keine Endpunkt-Entscheidung mehr, sondern eine Eigenschaft
des ausgestellten Tokens. Der Sliding-Expiration-Write passiert nur noch im opaquen Zweig; der
JWT-Zweig fasst `pim_token` nicht an.

### Seit `013-003`: JWT werden ausgestellt, erneuert und widerrufen

Der Login gibt auf `tokenType: "jwt"` ein kurzlebiges Access-JWT plus ein Refresh-Token aus.
**Ohne diese Angabe bleibt es beim opaquen Token** — ein Bestandsclient merkt nichts.

| Stück | wo |
|---|---|
| Claims und Ausstellung | `Classes/Security/Zugangstoken` — `sub`, `iss`, `iat`, `exp`, `jti`, mehr nicht |
| Erneuerung | `POST /auth/refresh`, mit Rotation des Refresh-Tokens |
| Widerruf | `GET /auth/logout` + Sperrliste `pim_revoked_token` für das Restfenster |
| Schlüsselwechsel | `kid` im Header, zwei Schlüssel während einer Übergangszeit |

**Rollen, Gruppen und Berechtigungen stehen nicht im Token.** Sie können sich ändern, während es
gilt; stünden sie darin, wirkte eine Rechteänderung erst nach dessen Ablauf. Der Benutzer wird
deshalb bei jedem Request aus `pim_user` geladen — womit eine Sperrung sofort wirkt, ohne
Sperrliste.

Das Refresh-Token ist eine `pim_token`-Zeile mit `purpose = refresh`. Es öffnet die API
**nicht**: Der opaque Zweig weist es ab, sonst wäre es ein langlebiger Generalschlüssel.

Betrieb, Schlüsselwechsel und die nötigen Umgebungsvariablen stehen in
`an_project/docs/deployment.md`.

`firebase/php-jwt` steht auf `^7.0` — die 6er-Reihe trägt CVE-2025-45769.
Seit `006-003` wird dieser Baum nicht mehr gebaut — das Paket ist also auch physisch weg.
`006-001-0004` hat es zum Streichen vorgesehen; `013-003` nimmt JWT bewusst neu auf. Was oben unter *Pro-Tenant-JWT-Secret* steht, beschreibt
fremden Code, keine vorhandene Funktion.

### Befunde

| # | Befund | Wirkung |
|---|---|---|
| ~~A-1~~ | ~~Passwörter sind `hash("sha256", $pass.$salt)`. SHA-256 hat keinen Arbeitsfaktor.~~ **Behoben mit `013-001-0001`:** Argon2id über `password_hash()`; Bestandshashes werden beim ersten Login des jeweiligen Benutzers ersetzt, der alte Weg wird nur noch gelesen. |
| ~~A-2~~ | ~~`APP_MASTER_PASSWORD` akzeptiert den Login für **jeden** Benutzer.~~ **Behoben mit `013-001-0002`:** ersatzlos entfernt, nicht abschaltbar gemacht. |
| ~~A-3~~ | ~~Kein Rate-Limiting — `CHECK_LOGIN_INTERVAL` ist eine `false`-Konstante.~~ **Behoben mit `013-001-0003`:** Anmeldebremse pro Kennung **und** pro IP, mit ansteigender Verzögerung (60 s → 900 s → 3600 s). Beide Konstanten und der tote Zweig sind entfallen; `setTrustedProxies()` ist konfigurierbar, damit die Achse IP hinter einem Proxy den Richtigen trifft. |
| ~~A-4~~ | ~~`pim_token.token` steht im Klartext.~~ **Behoben mit `013-001-0004`:** gespeichert wird ein SHA-256, nachgeschlagen wird der Hash. Der Token verlässt das System genau einmal, bei der Anmeldung. Auch `pim_log.model_label` trug ihn im Klartext — dort steht jetzt ebenfalls der Hash. |
| A-5 | `referrer`-Tokens laufen nie ab, und der Token-String kommt beim Anlegen vom Client (`Controller/SystemController.php:159`) | Ratbare Dauerschlüssel möglich |
| ~~A-6~~ | ~~`LoginManager::createManagedUser()` setzt `setPass($alias)` — das Passwort ist der Benutzername.~~ **Behoben mit `013-004-0002`:** Ein über ein Fremdsystem angelegter Benutzer hat ein **gesperrtes** Passwort (`*`), gegen das keine Eingabe passt. Die Provisionierung liegt im Framework statt im Projekt; `Classes\Manager\LoginManager` ist entfallen. |

### Seit `013-004`: der Nachfolger des LoginManagers

Die Grundidee bleibt — das Framework stellt Vertrag und Provisionierung, das Projekt
programmiert die Prüfung. Was fällt, sind die Konstruktionsfehler:

| Stück | wo |
|---|---|
| Vertrag | `Classes/Security/Anmeldeprovider` — **eine** Pflicht: `pruefen(Request): ?Fremdkennung` |
| Auswahl | `Classes/Security/Anbieterverzeichnis`, gefüllt aus `custom/app.php`; ein Name, kein Klassenname |
| Provisionierung | `Classes/Security/Benutzerbereitstellung` — gesperrtes Passwort, Kennung in `pim_user.externalId` |
| Rollenabbildung | `Classes/Security/Gruppenabbildung`, konfiguriert über `SECURITY_PROVIDER_GRUPPEN` |
| Vorlage | `custom/Classes/Anmeldung/BeispielProvider` — läuft, lässt aber ohne Konfiguration niemanden herein |

**Ein Provider fasst die Datenbank nicht an.** Das ist der Unterschied zum alten `LoginManager`,
der eine fertige `User`-Entity liefern musste und damit die Provisionierung ins Projekt schob —
`setPass($alias)` ist das prominenteste Ergebnis dieser Aufteilung.

**Der MD5-Präfix im Alias ist weg.** Die Eindeutigkeit kommt jetzt aus einer Bedingung über
`loginManager` und `externalId`; der Alias liest sich als `<provider>:<kennung>`.

Was ein Bestandsprojekt zu tun hat, steht in `an_project/docs/breaking-changes.md`.

### Seit `013-005`: LDAP und OIDC hängen am selben Vertrag

Zwei mitgelieferte Provider, beide **nicht** vorregistriert:

| Provider | prüft | Einschränkung im Test |
|---|---|---|
| `Classes/Security/LdapProvider` | Bind gegen LDAP/AD — suchen, dann binden | gegen `LdapInterface`-Doppelgänger, nicht gegen ein Verzeichnis |
| `Classes/Security/OidcProvider` | Userinfo-Endpunkt des Identity-Providers | gegen `MockHttpClient`, nicht gegen einen echten Provider |

Beide münden in dieselbe Token-Ausstellung wie der lokale Login — ein Client merkt nicht, woher
der Benutzer kam. Das ist der Weg hinter `Anmeldeprovider`, und den misst
`AnmeldeproviderApiTest` end-to-end.

**Wer aus dem Fremdsystem verschwindet, wird gesperrt, nicht gelöscht.**
`appcms:provider:abgleich` hält den Bestand dagegen; `Bestandspruefung::kenntKennung()` gibt
`?bool` zurück, und `null` heisst „kann es gerade nicht sagen" — **ein Ausfall darf nicht wie ein
gelöschter Benutzer aussehen**, sonst sperrt ein Netzwerkfehler die ganze Belegschaft aus.

Was ein Projekt konfigurieren muss, steht in `an_project/docs/dev-guide.md`.

**Funktionale Defekte — behoben mit `013-001-0005`:**

- ~~`POST /api/login` und `/api/logout` zeigen auf Methoden, die im `ApiController` nicht
  existieren.~~ Beim Nachmessen war es schlimmer und harmloser zugleich: Sie erreichten den
  Router **nie**. `Routensammlung` zählt ihre Routen je Provider durch, `/api/login` hiess
  `login_0` und wurde beim Mounten von `/auth/login` gleichen Namens verdrängt — 30 registrierte
  Routen, 29 in der Sammlung. Die Namen tragen jetzt den Mountpunkt, die beiden toten Routen
  sind entfernt statt umgebogen.
- ~~Der Plugin-Zweig der LoginManager-Auflösung prüft `substr($name, 7) == 'Plugins'` statt der
  ersten sieben Zeichen und greift deshalb nie.~~ Jetzt `str_starts_with()`. **Einschränkung:**
  Der Pfad ist mangels Plugin nicht end-to-end prüfbar; geprüft wird die Auflösung selbst.
- ~~Die Auflösung existiert doppelt (`Auth.php` und `AuthController.php`).~~ Bereits beim
  Refinement am 2026-09-10 erledigt vorgefunden: `Auth::getLoginProvider()` gibt es nicht mehr,
  gefallen irgendwo in Epic `009` oder `012`.

Behandelt wurde das in Epic `013`, Story `013-001` — bewusst an nichts hängend und vor dem
Kernel-Wechsel umgesetzt.

## Warum `vendor/` in Git *lag*

**Abgelöst am 2026-09-09 mit Story `006-003`.** Der Baum entsteht seit dem beim Build; im Repo
liegen `composer.json` und `composer.lock`. Warum er überhaupt dort lag, bleibt hier stehen —
es erklärt Dinge, die noch eine Weile nachwirken.

Der Root-`vendor/`-Baum war committet und hatte **kein `composer.json`**. Das war kein
Versehen, sondern eine bewusste Notlösung: Um Contentfly ohne größeres Update und ohne
Ausfallzeiten produktiv auf PHP 8.x weiterbetreiben zu können, wurde ein schnelles
„Fake"-Update gefahren — Composer entfernt, der funktionierende Vendor-Stand eingefroren und
versioniert.

Der Preis dafür war die Lage, aus der Epic `006` herausgeführt hat: kein Upgrade-Pfad, kein
`composer audit`, keine ausdrückbaren Version-Constraints. Und der Baum war **kein gültiger
Composer-Zustand**, sondern nur ein Verzeichnis, das zufällig funktionierte — `006-001-0003`
hat es nachgemessen: 27 von 78 Paketen lagen als `source` ohne `.git`, ein `doctrine/orm` als
Fork von 2018, ohne Release dahinter. Wer heute auf ein Paket stösst, das sich seltsam
verhält oder eine unerwartete Version trägt, findet hier die Erklärung.

### Was an seine Stelle getreten ist

| | vorher | jetzt |
|---|---|---|
| Quelle der Wahrheit | der committete Baum | `composer.json` + `composer.lock` (`006-002`) |
| Dateien im Git-Index | 11 208 (`vendor/` 5 239 · `custom/vendor/` 5 969) | 0 — beide Bäume ignoriert (`006-003-0001`) |
| Entstehung | Checkout | `composer install`, 77 Pakete / 7 275 Dateien (`006-003-0002`) |
| Deployment-Artefakt | derselbe Baum | `composer install --no-dev --optimize-autoloader`, 49 Pakete |

**Ein frischer Checkout ist damit ohne `composer install` nicht lauffähig.** Das ist gewollt
und der Punkt des Umbaus, aber es ist die erste Änderung seit Jahren, die einen Checkout
unbrauchbar macht, bis ein Befehl lief — und sie meldet sich mit fehlenden Klassen, nicht mit
ihrem Grund. Der Ablauf steht in `an_project/docs/runbook.md`.

`custom/vendor/` wird dabei **nicht** wieder aufgebaut; es gibt keinen Schritt, der es täte.
Beide Bootstraps laden es über ein `file_exists`-Tor, das schlicht nicht mehr greift. Damit ist
das Ziel „`custom/vendor` ist initial leer" aus Epic `006` nicht nur entschieden, sondern
hergestellt.

## Zwei Autoloader in einem Prozess

**Der zweite Autoloader ist seit `006-003` leer.** `lib/contentfly/bootstrap.php` lädt
`custom/vendor/autoload.php` hinter einem `file_exists`-Tor, und das greift nicht mehr — der
Baum wird nicht gebaut. Die Doppelungen unten sind damit heute **nicht** wirksam; sie stehen
hier, weil sie erklären, warum Pakete im Altbestand die Versionen tragen, die sie tragen. Ob
das zweite Manifest ganz verschwindet, entscheidet Story `006-004`.

Es galt: `lib/contentfly/bootstrap.php:8-10` lädt erst `vendor/autoload.php`, dann — falls
vorhanden — `custom/vendor/autoload.php`. Dadurch existierten Pakete doppelt, teils in
inkompatiblen Majors:

| Paket | Root | `custom/` | Wirkung |
|---|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 | Der zuerst registrierte Loader gewinnt, also der alte. `sentry/sentry` 4.22 verlangt `^3` und läuft real gegen die 1.x-Interfaces — Signatur-Risiko. |
| `symfony/polyfill-ctype` | 1.14.0 | 1.37.0 | Durch `function_exists`-Guards unkritisch, aber Ballast. |
| `symfony/polyfill-mbstring` | 1.14.0 | 1.38.2 | dito |

Dazu trafen zwei Symfony-Generationen aufeinander: `symfony/http-foundation` 3.4 im Root gegen
`symfony/options-resolver` 7.4 in `custom/` (via Sentry).

## Was den Alt-Baum an PHP 8.5 hindert

<!-- Durchgestrichen = erledigt. Stand 2026-09-11 (000-000-0027): alle vier Zeilen. -->

| Paket | Constraint | Folge |
|---|---|---|
| ~~`ellumilel/php-excel-writer`~~ | ~~`php: ^5.4\|^7.0`~~ | **Erledigt mit `012-001-0003`.** Unter PHP 8.5 nicht installierbar; sein einziger Konsument war der `ExportController`, und mit dem ist das Paket gefallen. Nachgemessen mit `000-000-0027`: weder in `vendor/` noch in einem Manifest. |
| ~~`silex/silex` 2.2.2~~ | ~~`symfony/*: ~2.8\|^3.0`~~ | **Erledigt mit `009-002`.** Deckelte Symfony auf 3.4 (EOL Nov 2020, nie für PHP 8 freigegeben), und der `php`-Constraint war nach oben offen, sodass Composer nichts meldete. Das Paket ist aus dem Baum; der Kernel steht auf Symfony 7.4. |
| ~~`dflydev/doctrine-orm-service-provider`~~ | ~~`doctrine/orm: ~2.3`~~ | **Erledigt mit `009-002`.** Blockierte den Weg auf ORM 3; entfallen mit dem Container. Der Weg auf ORM 3 ist damit frei, gegangen wird er in Epic `010`. |
| ~~`doctrine/orm`~~ | ~~`dev-bugfix-many2many`~~ | **Erledigt mit Epic `010`.** Der Dev-Branch-Pin ohne Release war in keinem Upgrade-Pfad ausdrückbar; das Manifest sagt jetzt `^3`, installiert ist 3.7.0. |

Dev-Werkzeuge lagen im ausgelieferten Baum: `phpstan/phpstan` 1.10.58 und `rector/rector`
1.0.1 im Root, `phpunit` 10.5 und `mockery` in `custom/vendor`. Das ist erledigt — sie stehen
seit `006-002` in `require-dev`, und `composer install --no-dev --optimize-autoloader` lässt sie
im Deployment-Artefakt weg (49 statt 77 Pakete, in `006-003-0002` nachgemessen).

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
| ~~`canExport`, `getExtended`~~ | **erledigt mit `000-000-0012`** — beide sind aus dem Schema entfernt, statt weiter etwas zu veröffentlichen, das die API nicht durchsetzt; die Datenbankspalten bleiben | `008-003-0005` |
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
