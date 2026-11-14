<?php

namespace App\Http\Controllers\App\Info;

use App\Http\Controllers\Controller;
use App\Support\CookiePreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class CookiePreferencesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['analytics' => ['required', 'boolean']]);
        $analytics = $request->boolean('analytics');

        $response = response()->json(['analytics' => $analytics])
            ->header('Cache-Control', 'private, no-store')
            ->withCookie(Cookie::make(
                CookiePreferences::NAME, CookiePreferences::value($analytics),
                CookiePreferences::DURATION_MINUTES, '/',
                secure: $request->isSecure() || app()->isProduction(), sameSite: 'lax',
            ));

        if (! $analytics) {
            $response->withCookie(Cookie::forget('user_key', '/'));
        }

        return $response;
    }
}
