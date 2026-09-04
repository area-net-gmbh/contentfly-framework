<?php
namespace Custom\Classes\Service\Core;

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Ein Format für jeden Zeitstempel, den die API ausliefert.
 *
 * Der Sinn dieser Klasse ist, dass es genau **eine** Stelle gibt, an der das Zeitformat der
 * API festgelegt ist. Sobald zwei Controller ihre Zeitstempel selbst formatieren, driften
 * sie auseinander — und Clients, die parsen müssen, tragen die Folgen.
 *
 * Festgelegt: ISO 8601 in UTC mit Millisekunden und explizitem Offset
 * (`2026-09-04T11:42:07.123+00:00`). UTC, weil ein Zeitstempel ohne Zone nicht vergleichbar
 * ist; Millisekunden, weil Sortierung nach Sekunden bei schnell aufeinander folgenden
 * Ereignissen mehrdeutig wird.
 *
 * Diese Klasse gehört zur Vorlage `custom/` — ein Projekt darf das Format ändern, aber nur
 * hier und für alle Zeitstempel gemeinsam.
 */
class ApiDateTimeFormatter
{
    /** Zeitformat aller API-Zeitstempel: ISO 8601, UTC, mit Millisekunden. */
    public const FORMAT = 'Y-m-d\TH:i:s.vP';

    /**
     * Formatiert einen Zeitpunkt für die API-Ausgabe.
     *
     * @param DateTimeInterface|null $dateTime Null wird zu null — ein fehlender Zeitpunkt
     *                                         bleibt fehlend und wird nicht zu "jetzt".
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

    /** Der aktuelle Zeitpunkt im API-Format. */
    public static function now(): string
    {
        return self::format(new DateTimeImmutable());
    }
}
