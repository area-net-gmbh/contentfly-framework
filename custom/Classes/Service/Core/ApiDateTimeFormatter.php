<?php
namespace Custom\Classes\Service\Core;

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One format for every timestamp the API delivers.
 *
 * The point of this class is that there is exactly **one** place where the API's time format
 * is defined. As soon as two controllers format their timestamps themselves, they drift
 * apart — and clients that have to parse them bear the consequences.
 *
 * Defined as: ISO 8601 in UTC with milliseconds and an explicit offset
 * (`2026-09-04T11:42:07.123+00:00`). UTC, because a timestamp without a zone cannot be
 * compared; milliseconds, because sorting by seconds becomes ambiguous for events that
 * follow each other quickly.
 *
 * This class is part of the `custom/` template — a project may change the format, but only
 * here and for all timestamps together.
 */
class ApiDateTimeFormatter
{
    /** Time format of all API timestamps: ISO 8601, UTC, with milliseconds. */
    public const FORMAT = 'Y-m-d\TH:i:s.vP';

    /**
     * Formats a point in time for API output.
     *
     * @param DateTimeInterface|null $dateTime Null becomes null — a missing point in time
     *                                         stays missing and does not turn into "now".
     */
    public static function format(?DateTimeInterface $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(self::FORMAT);
    }

    /** The current point in time in API format. */
    public static function now(): string
    {
        return self::format(new DateTimeImmutable());
    }
}
