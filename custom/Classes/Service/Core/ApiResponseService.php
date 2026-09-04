<?php
namespace Custom\Classes\Service\Core;

use Symfony\Component\HttpFoundation\JsonResponse;

use \DateTimeImmutable;

class ApiResponseService
{
    /**
     * Generates a standardized successful API response for Usabiq.
     *
     * This method should be used for all successful API responses to ensure
     * a consistent response structure across the platform.
     *
     * @param mixed $data The main data object (e.g., Entity, Array, Collection, etc.)
     * @param string $message Fallback message for systems that do not use i18n
     * @param string $messageKey ngx-translate key for frontend localization (optional)
     * @param array $messageParameters Placeholder parameters for ngx-translate (optional)
     * @param array $translations Alternative translations for external systems or logs (optional)
     * @param mixed $meta Additional metadata (optional, e.g., pagination, filters)
     * @param int $status HTTP status code (default: 200 OK)
     *
     * @return JsonResponse The standardized successful JSON response
    */
    public static function success(
        $data = null,
        string $message = 'OK',
        string $messageKey = '',
        array $messageParameters = [],
        array $translations = [],
        $meta = null,
        int $status = 200
    ): JsonResponse {
        return new JsonResponse([
            'success' => true,
            'status' => $status,
            'i18n' => [
                'message' => $message,
                'key' => $messageKey ?: null,
                'parameters' => $messageParameters ?: null,
                'translations' => $translations ?: null,
            ],
            'data' => $data,
            'errors' => null,
            'meta' => $meta,
            // Task 119: one formatter for every timestamp the API emits, envelope included.
            'timestamp' => ApiDateTimeFormatter::format(new DateTimeImmutable())
        ], $status);
    }

    /**
     * Generates a standardized error API response for Usabiq.
     *
     * This method should be used for all API errors to provide a unified response structure,
     * making it easier for frontend clients to handle error cases and display localized messages.
     *
     * @param array $errors List of errors, e.g., [
     *     ['code' => 'invalid_input', 'field' => 'email', 'detail' => 'Invalid email address']
     * ]
     * @param string $message Fallback message for systems that do not use i18n
     * @param string $messageKey ngx-translate key for frontend localization (optional)
     * @param array $messageParameters Placeholder parameters for ngx-translate (optional)
     * @param array $translations Alternative translations for external systems or logs (optional)
     * @param int $status HTTP status code (default: 400 Bad Request)
     * @param mixed $data Optional additional data object (e.g., for returning context information)
     * @param mixed $meta Additional metadata (optional, e.g., pagination, filters)
     *
     * @return JsonResponse The standardized error JSON response
    */
    public static function error(
        array $errors,
        string $message = 'Error',
        string $messageKey = '',
        array $messageParameters = [],
        array $translations = [],
        int $status = 400,
        $data = null,
        $meta = null
    ): JsonResponse {
        return new JsonResponse([
            'success' => false,
            'status' => $status,
            'i18n' => [
                'message' => $message,
                'key' => $messageKey ?: null,
                'parameters' => $messageParameters ?: null,
                'translations' => $translations ?: null,
            ],
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
            // Task 119: one formatter for every timestamp the API emits, envelope included.
            'timestamp' => ApiDateTimeFormatter::format(new DateTimeImmutable())
        ], $status);
    }
}
