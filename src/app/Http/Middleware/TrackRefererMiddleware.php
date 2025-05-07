<?php

namespace App\Http\Middleware;

use App\Models\UserReferer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackRefererMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем, нужно ли сохранять данные (например, только для GET-запросов)
        if ($request->isMethod('get')) {
            $referer = $request->headers->get('referer');

            // Если реферер есть и это не наш же сайт
            if ($referer && !str_contains($referer, $request->getHost())) {
                UserReferer::create([
                    'user_id' => Auth::check() ? Auth::id() : null,
                    'ip_address' => $request->ip(),
                    'referer_url' => $referer,
                    'landing_page' => $request->fullUrl(),
                    'utm_source' => $request->input('utm_source'),
                    'utm_medium' => $request->input('utm_medium'),
                    'utm_campaign' => $request->input('utm_campaign'),
                    'utm_term' => $request->input('utm_term'),
                    'utm_content' => $request->input('utm_content'),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $next($request);
    }
}
