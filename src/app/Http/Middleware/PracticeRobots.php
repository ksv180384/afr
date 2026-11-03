<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PracticeRobots
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('daily', 'daily/*', 'profile', 'profile/*', 'admin', 'admin/*', 'search', 'song/search*', 'word/search')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
