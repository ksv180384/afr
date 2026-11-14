<?php

namespace App\Support;

use Illuminate\Http\Request;

final class CookiePreferences
{
    public const NAME = 'afr_cookie_preferences';

    public const DURATION_MINUTES = 180 * 24 * 60;

    private const VERSION = 'v1';

    public static function choice(Request $request): ?bool
    {
        return match ($request->cookie(self::NAME)) {
            self::value(true) => true,
            self::value(false) => false,
            default => null,
        };
    }

    public static function allowsAnalytics(Request $request): bool
    {
        return self::choice($request) === true;
    }

    public static function value(bool $analytics): string
    {
        return self::VERSION.($analytics ? ':analytics' : ':essential');
    }
}
