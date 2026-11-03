<?php

namespace App\Support\Learning;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class PracticeTime
{
    public const SERVER_TIMEZONE = 'UTC';

    public const DEFAULT_TIMEZONE = 'Europe/Moscow';

    public const FALLBACK_MESSAGE = 'Не удалось определить часовой пояс браузера. Используется Europe/Moscow.';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::SERVER_TIMEZONE);
    }

    public static function timezone(string $timezone): DateTimeZone
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException('Expected an IANA timezone: '.$timezone);
        }

        return new DateTimeZone($timezone);
    }

    /**
     * Use the timezone saved on the run, not a later profile preference.
     */
    public static function localDate(DateTimeInterface $instant, string $timezone): string
    {
        return CarbonImmutable::instance($instant)
            ->setTimezone(self::timezone($timezone))
            ->format('Y-m-d');
    }
}
