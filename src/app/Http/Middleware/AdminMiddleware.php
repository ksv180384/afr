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
        // Проверяем, авторизован ли пользователь и является ли он администратором
        if (Auth::check() && Auth::user()->is_admin) {
            return $next($request); // Если да, продолжаем выполнение запроса
        }

        // Если нет, перенаправляем на страницу с ошибкой или другую страницу
        return redirect()->route('index')->with('error', 'У вас нет доступа.');
    }
}
