<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Learning\PracticeAuthReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $practice = PracticeAuthReturn::remember($request);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'registrationUrl' => route('register', $practice, absolute: false),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $practiceQuery = PracticeAuthReturn::pull($request);

        //        return redirect()->intended(route('index', absolute: false));
        if ($request->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $request->user()->hasVerifiedEmail()) {
            return redirect(route('verification.notice', $practiceQuery ?? [], absolute: false));
        }

        if ($practiceQuery !== null) {
            return redirect(route('daily', $practiceQuery, absolute: false));
        }

        return back();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
