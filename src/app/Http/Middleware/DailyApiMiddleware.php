<?php

namespace App\Http\Middleware;

use App\Services\Learning\PracticeTelemetry;
use App\Services\Learning\SessionContentCache;
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
        try {
            app(SessionContentCache::class)->pruneExpired();
        } catch (\Throwable $error) {
            app(PracticeTelemetry::class)->record('cache_prune_failure', ['error_class' => $error::class]);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
