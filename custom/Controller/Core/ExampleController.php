<?php
namespace Custom\Controller\Core;

use Areanet\PIM\Classes\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Custom\Classes\Service\Core\ApiResponseService;

/**
 * Der Beispiel-Controller der Vorlage.
 *
 * Er zeigt, wie ein Projekt eigene Routen baut: eine Action, die einen `Request` entgegen-
 * nimmt und eine `JsonResponse` liefert. Registriert wird sie in `custom/app.php`.
 *
 * @package Custom\Controller\Core
 */
class ExampleController extends BaseController
{

    /**
     * Liefert eine leere Beispielantwort im Standard-Envelope.
     *
     * **Der Kommentar beschrieb bis `000-000-0017` etwas anderes** — eine Aufloesung ueber
     * den `X-Origin-Host`-Header, einen `originHost`-Parameter und einen Mandanten-Slug.
     * Nichts davon steht im Rumpf, und nichts davon gab es je in diesem Baum: Es waren
     * Reste aus dem Kundenprojekt, aus dem die Vorlage herausgeschnitten wurde.
     *
     * Was die Methode wirklich zeigt, ist der Weg von der Route zur Antwort — mehr soll sie
     * nicht. Wer hier eine Aufloesung braucht, baut sie selbst.
     *
     * @param Request $request Die HTTP-Anfrage
     * @return JsonResponse Standard-Envelope mit leerem `data`
     */
    public function bootstrapAction(Request $request): JsonResponse
    {
       
        // Do something ...

        // return Example JSON response 
        return ApiResponseService::success(
            [],
            'Configuration loaded successfully',
            'core.config.loaded',
            [],
            [],
            null,
            200
        );

    }
}
