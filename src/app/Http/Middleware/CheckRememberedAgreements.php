<?php

namespace App\Http\Middleware;

use App\Support\LegalDocuments;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRememberedAgreements
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && Auth::viaRemember() && ! LegalDocuments::acceptedBy($user)) {
            Auth::logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->routeIs('info.privacy-policy', 'info.terms-user', 'info.personal-data-consent')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('daily/api/*', 'api/*')) {
                return response()->json(['code' => 'unauthenticated', 'message' => 'Войдите в аккаунт.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
