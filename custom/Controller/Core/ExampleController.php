<?php
namespace Custom\Controller\Core;

use Areanet\PIM\Classes\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Custom\Classes\Service\Core\ApiDateTimeFormatter;
use Custom\Classes\Service\Core\ApiResponseService;
use DateTimeImmutable;

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
     * An example response in the envelope every endpoint of this API uses.
     *
     * **Until `000-000-0017` this comment described something else** — a resolution via
     * the `X-Origin-Host` header, an `originHost` parameter and a tenant slug.
     * None of that is in the body, and none of it ever existed in this tree: they were
     * leftovers from the customer project the template was cut out of.
     *
     * What the method really shows is the path from route to response — that is all it is
     * meant to do. Anyone who needs a resolution here builds it themselves.
     *
     * **Since `011-003-0001` it shows one thing more, and it is the point of the example:** the
     * answer has the same shape as `/api/config` and every other endpoint — `data`, `errors`,
     * `meta`. Before, this template answered with `success`, `status` and `i18n`, so a project
     * that copied it built an API that contradicted the framework it runs on.
     *
     * `startedAt` is in the payload on purpose: it shows what `ApiDateTimeFormatter` is for —
     * formatting DATA. The envelope's own timestamp is `meta.ts` and needs nobody's help.
     *
     * @param Request $request The HTTP request
     * @return JsonResponse `data` / `errors` / `meta`
     */
    public function bootstrapAction(Request $request): JsonResponse
    {
        // Do something ...

        return ApiResponseService::success(array(
            'name'      => 'contentfly',
            'startedAt' => ApiDateTimeFormatter::format(new DateTimeImmutable()),
        ));
    }

    /**
     * The error case — same hull, and the project's translation key inside the entry.
     *
     * Not a route; it stands here because the interesting half of the envelope is the one a
     * template usually leaves out. Whoever builds an endpoint that can fail copies this shape:
     * `code` to branch on, `detail` for a human, and whatever the project knows beyond that in
     * `context`.
     *
     * @return JsonResponse `data` null, `errors` with one entry
     */
    public function exampleError(): JsonResponse
    {
        return ApiResponseService::error(
            array(ApiResponseService::fault(
                'example_not_configured',
                'The example endpoint has not been configured.',
                'core.example.not_configured',
                array('endpoint' => 'bootstrap')
            )),
            400
        );
    }
}
