<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PracticeRobots
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->routeIs('info.privacy-policy', 'info.personal-data-consent', 'info.terms-user')) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        } elseif ($request->is('daily', 'daily/*', 'profile', 'profile/*', 'admin', 'admin/*', 'search', 'song/search*', 'word/search')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
