<?php

namespace App\Support\Learning;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PracticeAuthReturn
{
    private const SESSION_KEY = 'daily_practice_return';

    public static function remember(Request $request): array
    {
        if (! config('daily_practice.enabled')) {
            return [];
        }

        if ($request->boolean('practice')) {
            $guestRun = $request->query('guest_run');
            $request->session()->put(self::SESSION_KEY, [
                'guest_run' => is_string($guestRun) && Str::isUuid($guestRun) ? $guestRun : null,
            ]);
        }

        $destination = $request->session()->get(self::SESSION_KEY);

        return $destination ? array_filter(['practice' => 1, 'guest_run' => $destination['guest_run'] ?? null]) : [];
    }

    public static function pull(Request $request): ?array
    {
        $destination = $request->session()->pull(self::SESSION_KEY);
        if (! $destination) {
            return null;
        }

        return array_filter(['guest_run' => $destination['guest_run'] ?? null]);
    }
}
