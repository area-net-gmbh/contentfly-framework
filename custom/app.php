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
 * Trusted Proxies, Session-Verhalten — steht in an_project/docs/technical.md.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/* -----------------------------------------------------------------------------------------
 * 1. Services — als Factory im Container, abrufbar über $app['key'] bzw. $this->app['key']
 *    im Controller. Die Factory läuft erst beim ersten Zugriff, nicht beim Bootstrap;
 *    $app steht ihr als Argument zur Verfügung, um Abhängigkeiten aufzulösen.
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
 * --------------------------------------------------------------------------------------- */
$controllerProvider = $app['routeManager'];

$controllerProvider->mount('api/v1/example/', '\Custom\Controller\Core\ExampleController')
    ->post('/bootstrap', false, 'bootstrapAction');

/* -----------------------------------------------------------------------------------------
 * 3. Middleware — before-Hooks laufen vor der Action, after-Hooks nach ihr.
 *
 *    Die Reihenfolge der Registrierung ist die Ausführungsreihenfolge. Das ist kein Detail:
 *    Sicherheits-Hooks, die aufeinander aufbauen, müssen in der gedachten Reihenfolge
 *    registriert werden. Ein before-Hook, der eine Response zurückgibt, bricht die
 *    Verarbeitung ab — genau so blockiert man einen Request.
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
 *    Hinweis: custom/Command/ExampleCommand.php erbt heute von Symfony\…\Command und
 *    erwartet $app im Konstruktor — es passt damit nicht auf diesen Weg und ist deshalb
 *    hier bewusst nicht registriert. Siehe an_project/docs/technical.md.
 * --------------------------------------------------------------------------------------- */
