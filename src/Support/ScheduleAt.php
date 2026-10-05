<?php

namespace Calisero\LaravelSms\Support;

/**
 * @internal
 */
final class ScheduleAt
{
    /**
     * The API reads schedule_at as Romania time.
     */
    public const TIMEZONE = 'Europe/Bucharest';

    /**
     * The format the API accepts for schedule_at; it refuses ISO 8601 with a 422.
     */
    public const FORMAT = 'Y-m-d H:i:s';

    /**
     * A date-time as the API wants it, in Romania time; a string is passed as is.
     */
    public static function format(\DateTimeInterface|string $scheduleAt): string
    {
        if (is_string($scheduleAt)) {
            return $scheduleAt;
        }

        return \DateTimeImmutable::createFromInterface($scheduleAt)
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->format(self::FORMAT);
    }
}
