<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DailyApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        if (! config('daily_practice.enabled')) {
            throw new \App\Services\Learning\AnswerCheckingException(\App\Enums\Learning\PracticeErrorCode::Disabled, 'Практика пока недоступна.');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
