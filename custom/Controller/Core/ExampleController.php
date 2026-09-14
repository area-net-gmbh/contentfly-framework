<?php
namespace Custom\Controller\Core;

use Areanet\PIM\Classes\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Custom\Classes\Service\Core\ApiResponseService;

/**
 * The template's example controller.
 *
 * It shows how a project builds its own routes: an action that accepts a `Request`
 * and returns a `JsonResponse`. It is registered in `custom/app.php`.
 *
 * @package Custom\Controller\Core
 */
class ExampleController extends BaseController
{

    /**
     * Returns an empty example response in the standard envelope.
     *
     * **Until `000-000-0017` this comment described something else** — a resolution via
     * the `X-Origin-Host` header, an `originHost` parameter and a tenant slug.
     * None of that is in the body, and none of it ever existed in this tree: they were
     * leftovers from the customer project the template was cut out of.
     *
     * What the method really shows is the path from route to response — that is all it is
     * meant to do. Anyone who needs a resolution here builds it themselves.
     *
     * @param Request $request The HTTP request
     * @return JsonResponse Standard envelope with empty `data`
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
