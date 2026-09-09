<?php
namespace Custom\Classes\Service\Core;

use Symfony\Component\HttpFoundation\JsonResponse;

use \DateTimeImmutable;

class ApiResponseService
{
    /**
     * Die Erfolgsantwort der Vorlage.
     *
     * Die Form ist bewusst konsistent: Ein Client wertet jede Antwort gleich aus. Sie ist
     * das Vorbild fuer den kuenftigen Envelope des Frameworks — nicht woertlich, siehe
     * an_project/docs/api-envelope.md.
     *
     * @param mixed $data The main data object (e.g., Entity, Array, Collection, etc.)
     * @param string $message Fallback message for systems that do not use i18n
     * @param string $messageKey Schluessel fuer die Uebersetzung im Client (optional)
     * @param array $messageParameters Platzhalter-Werte zu diesem Schluessel (optional)
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
            // Ein Formatierer fuer jeden Zeitstempel, den die API ausgibt, Envelope eingeschlossen.
            'timestamp' => ApiDateTimeFormatter::format(new DateTimeImmutable())
        ], $status);
    }

    /**
     * Die Fehlerantwort der Vorlage, in derselben Form wie der Erfolgsfall.
     *
     * Ein Client muss nicht zwei Formate koennen, um zu erfahren, dass etwas schiefging.
     *
     * @param array $errors List of errors, e.g., [
     *     ['code' => 'invalid_input', 'field' => 'email', 'detail' => 'Invalid email address']
     * ]
     * @param string $message Fallback message for systems that do not use i18n
     * @param string $messageKey Schluessel fuer die Uebersetzung im Client (optional)
     * @param array $messageParameters Platzhalter-Werte zu diesem Schluessel (optional)
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
            // Ein Formatierer fuer jeden Zeitstempel, den die API ausgibt, Envelope eingeschlossen.
            'timestamp' => ApiDateTimeFormatter::format(new DateTimeImmutable())
        ], $status);
    }
}
