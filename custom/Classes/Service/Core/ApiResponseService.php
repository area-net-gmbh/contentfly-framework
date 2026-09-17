<?php
namespace Custom\Classes\Service\Core;

use Areanet\PIM\Classes\Envelope;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The template's responses — in the framework's envelope (`011-003-0001`).
 *
 * ## What changed, and why it had to
 *
 * Until Epic `011` this class answered with `success`, `status`, `i18n`, `data`, `errors`, `meta`
 * and `timestamp`. It was written before the framework had a shape of its own, and
 * `an_project/docs/api-envelope.md` used it as the model — **as a model yes, literally no.** Two of
 * its fields were deliberately not adopted:
 *
 * - **`success` and `status`** repeat the HTTP status code in the body. Two sources for one
 *   statement, and one of them eventually disagrees. The status code is in the status code.
 * - **`i18n`** is a requirement of the project this service came from, not of the framework.
 *
 * Since `011-001` every endpoint of the framework answers with `data` / `errors` / `meta`. A
 * project that copied this template built an API that contradicted the framework it runs on —
 * which is precisely what Epic `011` removed. So the template now shows the one shape, and shows
 * it by using the framework's own class rather than by imitating it.
 *
 * ## Where the i18n key went
 *
 * It did not disappear, it moved into the place the envelope has for it: `errors[].context`. That
 * is more useful than a parallel format, because it survives a client that only knows the
 * envelope — such a client still finds the error, and one that knows the key finds the key.
 *
 * On the SUCCESS side the message is gone entirely, for the same reason `011-001-0004` removed
 * "Login successful" and "File uploaded" from the framework: a `200` already says it, and it says
 * it in a way that survives a translation.
 *
 * ## When a project needs this class at all
 *
 * A controller that extends `Areanet\PIM\Classes\Controller\BaseController` can call
 * `$this->renderResponse($data, $status, $meta)` directly — that is the shortest way and the one
 * the framework's own controllers take. This class earns its place where the answer is built
 * somewhere that is NOT a controller, and for `fault()`, which carries the project's translation
 * key into the entry.
 */
class ApiResponseService
{
    /**
     * A success response in the envelope.
     *
     * @param mixed $data the payload — what the endpoint has to say
     * @param array<string,mixed> $meta what this endpoint adds to the standard meta,
     *                                  e.g. `totalItems` or `itemsPerPage`
     */
    public static function success($data = null, array $meta = array(), int $status = 200): JsonResponse
    {
        // The schema hash is null here: it describes the framework's entity schema, and a
        // project's own endpoint has nothing to say about it. `BaseController::renderResponse()`
        // fills it in where it is meaningful.
        return new JsonResponse(Envelope::success($data, null, $meta), $status);
    }

    /**
     * An error response — the same hull, `data` null and `errors` filled.
     *
     * @param list<array<string,mixed>> $errors one entry per fault, built with fault()
     * @param array<string,mixed> $meta
     */
    public static function error(array $errors, int $status = 400, array $meta = array()): JsonResponse
    {
        return new JsonResponse(Envelope::failure($errors, null, $meta), $status);
    }

    /**
     * One entry for `errors` — with the project's translation key in `context`.
     *
     * THIS IS THE PART WORTH COPYING. The envelope's four keys are fixed, and `context` is where
     * anything a project knows beyond them belongs. A translation key is exactly that: the
     * framework has no opinion about it, and a client that does not translate still reads `code`
     * and `detail`.
     *
     * @param string $code what a client branches on — a stable identifier, not a sentence
     * @param string $detail for a human; the fallback when nothing is translated
     * @param array<string,mixed> $parameters placeholder values for the key
     * @return array<string,mixed>
     */
    public static function fault(string $code, string $detail, ?string $i18nKey = null, array $parameters = array()): array
    {
        return Envelope::fault(
            $code,
            $detail,
            self::class,
            $i18nKey === null ? null : array('i18nKey' => $i18nKey, 'i18nParameters' => $parameters ?: null)
        );
    }
}
