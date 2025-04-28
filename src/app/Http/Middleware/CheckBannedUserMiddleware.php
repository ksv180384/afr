<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->is_ban) {
            if ($request->inertia()) {
                if ($request->inertia()) {
                    // Inertia автоматически вернётся на предыдущую страницу
                    throw new HttpResponseException(
                        back()->withErrors(['ban' => 'Ваш аккаунт заблокирован.'])
                    );
                }

                return response()->json(['error' => 'Ваш аккаунт заблокирован.'], 403);
            }
        }

        return $next($request);
    }
}
