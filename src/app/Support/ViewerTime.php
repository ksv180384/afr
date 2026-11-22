<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;

final class ViewerTime
{
    public static function format(DateTimeInterface $date, Request $request, string $format = 'd.m.Y H:i'): string
    {
        $timezone = $request->user()?->timezone;

        return $timezone === null
            ? $date->format($format)
            : CarbonImmutable::instance($date)->setTimezone($timezone)->format($format);
    }
}
