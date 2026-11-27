<?php

namespace App\Support;

use App\Support\Learning\PracticeTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;

final class ViewerTime
{
    public static function format(?DateTimeInterface $date, Request $request, string $format = 'd.m.Y H:i'): ?string
    {
        if ($date === null) {
            return null;
        }

        $timezone = $request->user()?->timezone ?? PracticeTime::DEFAULT_TIMEZONE;

        return CarbonImmutable::instance($date)->setTimezone($timezone)->format($format);
    }
}
