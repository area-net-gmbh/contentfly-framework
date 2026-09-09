---
id: 009-000-0000
title: Silex durch Symfony 7.4 ersetzen
status: in-progress
depends_on: [006-000-0000, 008-000-0000, 012-000-0000]
---

# Silex durch Symfony 7.4 ersetzen

## Goal
Das Framework läuft auf einem **Symfony-7.4-LTS-Kernel**; Silex und Pimple sind aus dem Baum
verschwunden. Silex ist seit 2018 EOL und zieht Symfony-2/3-Komponenten herein, die nie für
PHP 8 freigegeben wurden — das ist der Kern des Updates. Kernel-Entscheidung und Begründung:
`an_project/docs/architecture.md`, *Key decisions*, 2026-09-04.

Der Prüfstein ist die Suite aus Epic `008`. `an_project/docs/technical.md` formuliert es als
Regel:

> **Der Kernel-Tausch gilt als gelungen, wenn diese Suite ohne inhaltliche Änderung grün
> bleibt.**

Damit ist auch gesagt, was dieses Epic *nicht* tut: Es verbessert nichts. Jede Zusicherung, die
heute grün ist, ist danach grün, und zwar aus demselben Grund. Wo eine Erwartung angepasst
werden muss, ist das ein Befund und braucht eine Begründung — keine Anpassung.

## Es gibt keinen Übergangszustand
`silex/silex` 2.3.0 fordert `symfony/http-kernel`, `symfony/http-foundation`, `symfony/routing`
und `symfony/event-dispatcher` jeweils auf `^4.0`. Alter und neuer Kernel können also **nicht
nebeneinander** in einem `vendor/` liegen; es gibt keine Route, keinen Endpunkt und keinen
Testlauf, der schon auf Symfony 7.4 läuft, während der Rest noch auf Silex steht. Der Tausch ist
ein Schnitt.

Das bestimmt den Zuschnitt dieses Epics. Vorbereiten lässt sich genau eines: die Ablösung des
eigenen Codes von den **Silex-Typen**. Heute nennen 28 Dateien `Silex\` oder `Pimple\` —
Controller mit `Silex\Application` im Konstruktor, Provider mit
`Silex\Api\ControllerProviderInterface`, Manager mit `$this->app->extend('dispatcher', …)`. Diese
Bindungen lassen sich hinter eigene Schnittstellen legen, **während Silex noch läuft** und die
Suite grün bleibt. Was danach übrig bleibt, ist der Bootstrap — und der wird in einem Zug
getauscht.

Praktisch heißt das: Der Schnitt selbst ist **eine** Story mit mehreren Tasks. Die Tasks sind je
ein Commit auf dem Story-Branch, und grün ist der Branch als Ganzes, nicht jeder Commit einzeln.
Ein feinerer Schnitt in eigenständige Stories wäre eine Fiktion — jede von ihnen würde per
`/done` einen roten Stand nach `master` mergen.

## Erfolgskriterien
- **Kernel und Container:** Ein Symfony-7.4-Kernel mit DI-Container ersetzt `Silex\Application`
  und Pimple. `knplabs/console-service-provider` entfällt ersatzlos — es deckelt
  `symfony/console` auf `^4` und ist damit ohnehin nicht mitzunehmen. Die Silex-eigenen Provider
  (`ServiceControllerServiceProvider`, `DoctrineServiceProvider`, `ValidatorServiceProvider`)
  fallen mit Silex weg und werden durch Container-Definitionen ersetzt.

  > **Korrektur, 2026-09-09.** Dieses Kriterium nannte bisher auch
  > `dflydev/doctrine-orm-service-provider`. Das Paket steht weder in `composer.json` noch im
  > Lock und liegt nicht im Baum. Die DBAL-Verbindung kommt aus Silex' eigenem
  > `DoctrineServiceProvider`, den Entity-Manager baut `Areanet\PIM\Classes\ORM\EntityManagerFactory`
  > von Hand.

- **`$app['…']` bleibt nutzbar:** Ein `ArrayAccess`-Bridge auf den Symfony-Container hält die
  bestehenden String-Keys am Leben — `orm.em`, `schema`, `database`, `auth.user`, `typeManager`,
  `pluginManager`, `routeManager`, `consoleManager`, `helper`, `auth`, `mailer`, `debug`,
  `is_installed`, `thumbnailSettings` —, damit Bestandsprojekte ihre Controller nicht anfassen
  müssen. Auch die Projektseite hängt daran: `custom/app.php` registriert eigene Services als
  `$app['meine.service'] = function ($app) { … }`, also als **Factory-Closure**. Ob der Bridge
  dauerhaftes API oder befristete Migrationshilfe ist, entscheidet `007`; hier wird sie gebaut.

- **Routing:** `RouteManager` und die `mount()`-Struktur laufen über Symfony Routing. Die
  Absicherung heißt im Code **`Route::$isSecure`** (`Classes/Controller/Provider/Base/CustomControllerProvider.php`),
  nicht `_secured` — die Bezeichnung kommt im Baum nicht vor, festgestellt in `012-006-0003`.
  Sie ist die Authentifizierungsentscheidung pro Route und das, was beim Umschreiben von Routen
  als Erstes verloren geht. Die Routen der Vorlage `custom/app.php` werden **als Daten
  eingelesen**, nicht einzeln übersetzt: `RouteManager::mount()` sammelt Provider ein,
  `bindRoutes()` bindet sie; beide Schritte bleiben, nur ihr Unterbau wechselt.

- **Middleware und Fehler:** before/after-Hooks werden zu `kernel.request`/`kernel.response`-Listenern
  **in unveränderter Reihenfolge**. Silex nimmt bei `before()`/`after()` ein Prioritätsargument;
  wo es gesetzt ist, war es Absicht. Die effektive Reihenfolge ist **nachzuweisen, nicht aus der
  Registrierungsreihenfolge zu raten**. `$app->error()` wird zu einem Exception-Listener, der die
  Fehlerantwort unverändert ausgibt — einschließlich dessen, was `000-000-0006` dort in Ordnung
  gebracht hat: der Statuscode aus `getCode()` mit `getStatusCode()` als Rückfall, der Rumpf mit
  `message`/`type`/`status`, kein Redirect auf `/`, und `FileNotFoundException` als 404. CORS und
  die Security-Header werden mitportiert; der Session-Bootstrap entfällt (`012`).

- **Console:** Die Commands laufen unter dem Symfony-Kernel, `ConsoleManager` bleibt funktional.
  `doctrine/orm` liegt auf 2.20.13 und erlaubt `symfony/console ^7.0` — die Console blockiert den
  Schnitt also nicht und zwingt Epic `010` nicht vor. **Ungeprüft** ist, ob die
  DBAL-2-Console-Commands, die `bin/console.php` registriert (`ImportCommand`,
  `ReservedWordsCommand`, `RunSqlCommand`), unter Symfony Console 7 noch laufen; das ist im
  Schnitt zu messen und, falls nicht, mit Begründung zu streichen statt zu reparieren.

- **Silex und Pimple sind physisch weg:** kein `silex/silex`, kein `pimple/pimple`, keine
  Symfony-4-Komponente mehr in `composer.lock`. Prüfbar, nicht behauptet — und automatisiert, wie
  die Autoloader-Zusicherung aus `006-004-0003`.

- **CI-Gate „0 Deprecations" ist unter Symfony 7.4 scharf.** Das Gate steht seit `006-005`
  (Laufzeit-Log plus PHPStan mit `phpstan-deprecation-rules`) und trägt heute **eine** Ausnahme:
  die Deprecation aus Silex. Mit Silex fällt der Grund für die Ausnahme weg, und die
  Ausnahmeliste muss leer sein — sonst hat der Schnitt neue Schulden aufgenommen, statt die alten
  zu tilgen. Das ist die Voraussetzung dafür, dass der spätere Sprung auf Symfony 8.4 LTS ein
  Constraint-Bump bleibt.

- **Das Testnetz aus `008` ist grün, ohne inhaltliche Änderung.** Stand vor dem Schnitt:
  247 Tests, 614 Assertions, 0 übersprungen. Jede Abweichung ist zu begründen; die Zahl allein
  genügt nicht, weil ein übersprungener Test wie ein bestandener aussieht.

## Abgrenzung
Die PIM-CMS-Oberfläche wird **nicht** portiert — sie ist mit `012` ersatzlos gestrichen. Was hier
auf Symfony landet, ist ausschließlich die API-Seite: Routing, Middleware, Fehlerbehandlung, Auth
und Console. Kein Twig, keine Session-Auth.

Ebenfalls nicht in diesem Epic:

- **Keine Bundle-Struktur.** `an_project/docs/tech-stack.md` verlangt für den Zielzustand
  „Backend (Symfony) — IMMER in Bundles". `lib/contentfly/` bleibt hier trotzdem, wie es ist.
  Grund: Die Charakterisierungstests sollen genau eine Variable messen. Werden Kernel und
  Struktur gleichzeitig getauscht, ist jede Abweichung mehrdeutig — Umbau oder Umzug? Die
  Aufteilung in Bundles kommt als eigenes Vorhaben nach dem Schnitt. Entschieden am 2026-09-09.
- **Kein Doctrine-Umbau — mit einer erzwungenen Ausnahme.** ORM 2 → 3, der Wegfall der
  Annotationen und der Ersatz des abandoned `doctrine/annotations` sind Epic `010`.

  > **Richtiggestellt am 2026-09-09.** Hier stand „`009` lässt Doctrine, wie es ist". Das ist
  > nicht möglich: `symfony/http-foundation` erklärt seit v7.1.7 einen **harten Konflikt** mit
  > `doctrine/dbal <3.6`, aufgefallen beim ersten Auflösungsversuch in `009-002-0001`. Der
  > Kernel-Wechsel erzwingt damit DBAL 2.13 → 3.10. **`doctrine/orm` bleibt auf 2.20** — es
  > erlaubt `doctrine/dbal ^3.2` bereits —, die Annotationen und der Entity-Layer bleiben
  > unberührt, und Epic `010` behält seinen Umfang bis auf den DBAL-Teil. Der Schritt liegt in
  > Story `009-005`, die **vor** dem Schnitt läuft und eigenständig gemergt wird: Silex nagelt
  > nur `symfony/*` fest, also läuft die volle Suite dabei — als einziger Teil des Umbaus.
- **Keine Änderung am Antwort-Envelope.** Die Vereinheitlichung auf `data`/`errors`/`meta` ist
  mit `000-000-0014` entschieden und liegt bewusst in Epic `011` — genau deshalb, weil die
  Abnahmegrundlage dieses Epics sonst gleichzeitig mit dem Kernel wandern würde. Siehe
  `an_project/docs/api-envelope.md`.
- **Keine Änderung an der Authentifizierung.** `013` behandelt sie.

  > **Zur Reihenfolge.** `an_project/docs/technical.md` schreibt, die Härtung `013-001` werde
  > „vor dem Kernel-Wechsel umgesetzt". Beschlossen ist das nicht, und `009` hängt nicht daran:
  > `depends_on` nennt `013` nicht. Die Härtung kann jederzeit parallel laufen; sie berührt
  > Passwort-Hashing, Master-Passwort, Rate-Limiting und den Token-Klartext, also Code, den der
  > Kernel-Tausch nicht anfasst. Festgehalten am 2026-09-09, damit die Aussage in `technical.md`
  > nicht unwidersprochen stehen bleibt.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 009-001-0000 — Den eigenen Code von den Silex-Typen lösen
- [x] 009-005-0000 — DBAL 2 auf 3 heben — die Vorbedingung des Kernel-Schnitts
- [x] 009-002-0000 — Der Kernel-Schnitt — Symfony 7.4 statt Silex
- [x] 009-003-0000 — Das Deprecation-Gate unter Symfony 7.4 scharfstellen
- [x] 009-004-0000 — Vorlage und Dokumentation auf den neuen Kernel nachziehen
