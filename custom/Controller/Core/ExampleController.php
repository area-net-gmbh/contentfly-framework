<?php
namespace Custom\Controller\Core;

use Areanet\PIM\Classes\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Custom\Classes\Service\Core\ApiResponseService;

/**
 *  Example Controller for
 * 
 * @package Custom\Controller\Core
 */
class ExampleController extends BaseController
{

    /**
     * Retrieves bootstrap configuration for application initialization.
     * 
     * Resolves configuration based on:
     * - X-Origin-Host header
     * - originHost query parameter
     * - Request host
     * - Optional tenant slug override
     * 
     * @param Request $request The HTTP request
     * @return JsonResponse Configuration with proper tenant context
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
