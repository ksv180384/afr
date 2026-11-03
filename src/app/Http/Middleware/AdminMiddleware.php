<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/daily-practice', 'admin/daily-practice/*')) {
            app(\App\Services\Learning\ContentAccess::class)->check($request->user());
            $response = $next($request);
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        // Проверяем, авторизован ли пользователь и является ли он администратором
        if (Auth::check() && Auth::user()->is_admin) {
            return $next($request); // Если да, продолжаем выполнение запроса
        }

        // Если нет, перенаправляем на страницу с ошибкой или другую страницу
        return redirect()->route('index')->with('error', 'У вас нет доступа.');
    }
}
