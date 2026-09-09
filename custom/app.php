<?php
/**
 * Projekt-Bootstrap — die eine Datei, in der ein Projekt sein eigenes Verhalten registriert.
 *
 * Wird von lib/contentfly/bootstrap.php geladen, nachdem das Framework steht und bevor
 * $app['routeManager']->bindRoutes() die Routen bindet. Alles, was hier registriert wird,
 * gehört dem Projekt — das Framework selbst bleibt unangetastet.
 *
 * Diese Datei ist eine **Vorlage**: Sie zeigt die vier Muster, die ein Projekt braucht, an
 * lauffähigen Beispielen. Wie ein echtes Projekt das ausbaut — Middleware-Reihenfolge,
 * Trusted Proxies — steht in an_project/docs/technical.md.
 *
 * ── Nach dem Kernel-Wechsel (Epic 009) ──────────────────────────────────────────────────
 *
 * Unter dieser Datei liegt seit Epic 009 ein Symfony-7.4-Kernel statt Silex 2. **An allen vier
 * Mustern hier ändert das nichts** — genau dafür wurde in 009-001 eine eigene Schnittstelle
 * zwischen Framework und Kernel gelegt, bevor der Kernel getauscht wurde.
 *
 * Was gleich bleibt: `$app['schlüssel']` als Container, die faule Factory mit `$app` als
 * Argument, `$app->before()` und `->after()` samt Prioritätsargument, der `routeManager` mit
 * `mount()` und `isSecure`, der `consoleManager` mit `CustomCommand`.
 *
 * Was sich für ein Projekt ändert, steht in an_project/docs/breaking-changes.md. Kurz: Der
 * Container ist nicht mehr Pimple (`protect()`, `share()`, `raw()` gibt es nicht),
 * `$app['request']` und `$app['controllers_factory']` sind entfallen, und ein eigener
 * Controller-Provider liefert jetzt eine RouteCollection.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/* -----------------------------------------------------------------------------------------
 * 1. Services — als Factory im Container, abrufbar über $app['key'] bzw. $this->app['key']
 *    im Controller. Die Factory läuft erst beim ersten Zugriff, nicht beim Bootstrap;
 *    $app steht ihr als Argument zur Verfügung, um Abhängigkeiten aufzulösen.
 *
 *    Das ist Pimples Vertrag, und er gilt weiter: Der Container des Frameworks
 *    (Areanet\PIM\Classes\Kernel\Container) bildet ihn seit 009-002-0002 selbst nach, weil
 *    Symfonys DI-Container zur Laufzeit nur fertige Objekte annimmt und diese Datei hier
 *    Factories registriert.
 *
 *    Nicht alles gehört in den Container: ApiResponseService und ApiDateTimeFormatter sind
 *    zustandslose statische Helfer und werden direkt aufgerufen. In den Container gehört,
 *    was Abhängigkeiten hat oder Zustand hält.
 * --------------------------------------------------------------------------------------- */
//   $app['meine.service'] = function ($app) {
//       return new \Custom\Classes\Service\Core\MeinService($app['orm.em']);
//   };

/* -----------------------------------------------------------------------------------------
 * 2. Routen — über den RouteManager, nicht über $app->get()/post() direkt. Der Manager
 *    sammelt die Mounts ein; bindRoutes() bindet sie unmittelbar nach dieser Datei.
 *
 *    mount(<Pfad>, <Controller-Klasse>) und darauf ->get()/->post()/->match() mit
 *    (<Route>, <isSecure>, <Action>). isSecure=true verlangt einen gültigen Token.
 *
 *    isSecure ist die Authentifizierungsentscheidung pro Route. Unter Silex hing sie an einem
 *    before()-Filter am Controller, jetzt an einem Listener auf kernel.controller
 *    (Kernel\Routing\AbsicherungListener). Am Aufruf hier ändert das nichts.
 * --------------------------------------------------------------------------------------- */
$controllerProvider = $app['routeManager'];

$controllerProvider->mount('api/v1/example/', '\Custom\Controller\Core\ExampleController')
    ->post('/bootstrap', false, 'bootstrapAction');

/* -----------------------------------------------------------------------------------------
 * 3. Middleware — before-Hooks laufen vor der Action, after-Hooks nach ihr.
 *
 *    Bei gleicher Priorität ist die Reihenfolge der Registrierung die Ausführungsreihenfolge;
 *    ein zweites Argument hebt oder senkt sie ($app->before($fn, 128)). Das ist kein Detail:
 *    Sicherheits-Hooks, die aufeinander aufbauen, müssen in der gedachten Reihenfolge laufen.
 *    Nachgewiesen wird das von tests/Unit/Kernel/HookReihenfolgeTest.php — dort steht, was
 *    tatsächlich passiert, nicht was man annimmt.
 *
 *    Ein before-Hook, der eine Response zurückgibt, bricht die Verarbeitung ab — genau so
 *    blockiert man einen Request.
 * --------------------------------------------------------------------------------------- */
$app->before(function (Request $request) use ($app) {
    // Beispiel: Ein Kennzeichen für alle folgenden Hooks und Actions bereitstellen.
    // Ein `return new JsonResponse(...)` an dieser Stelle würde den Request abbrechen.
    $app['request.startedAt'] = microtime(true);
});

$app->after(function (Request $request, Response $response) {
    // Beispiel: Response-Header, die für jede Antwort gelten sollen. CORS und die
    // Basis-Header setzt bereits lib/contentfly/bootstrap-web.php — hier kommt dazu,
    // was das Projekt zusätzlich braucht.
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
});

/* -----------------------------------------------------------------------------------------
 * 4. Console-Commands — über den ConsoleManager, danach über bin/console.php aufrufbar:
 *
 *        $app['consoleManager']->addCommand(new \Custom\Command\MeinCommand());
 *
 *    Der Manager nimmt ausschließlich Commands, die von
 *    Areanet\PIM\Classes\Command\CustomCommand erben — diese Basisklasse stellt den
 *    Namen automatisch auf `custom:<name>`, damit Projekt-Commands nie mit denen des
 *    Frameworks kollidieren.
 *
 *    custom/Command/ExampleCommand.php zeigt es. Es erbt seit 009-004-0001 von CustomCommand
 *    und ist unten registriert; bis dahin erbte es von Symfony\…\Command, passte damit nicht
 *    auf diesen Weg und lag unbenutzt herum.
 *
 *    Der Command heisst dadurch `custom:example:command:run` — den Präfix stellt CustomCommand
 *    voran, und das ist die Zusicherung dahinter: Ein Projekt-Command kann nie einen des
 *    Frameworks überschreiben.
 * --------------------------------------------------------------------------------------- */

$app['consoleManager']->addCommand(new \Custom\Command\ExampleCommand());
