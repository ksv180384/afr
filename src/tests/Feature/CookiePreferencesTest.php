<?php

use App\Helpers\Helper;
use App\Models\UserReferer;
use App\Support\CookiePreferences;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/cookie-test-page', fn () => response()->json([
        'tracked' => Helper::getUserKey() !== null,
    ]));
});

it('skips optional tracking until the visitor opts in', function () {
    $this->withHeader('Referer', 'https://example.org/search');
    $this->get('/cookie-test-page')
        ->assertOk()->assertJsonPath('tracked', false)->assertCookieMissing('user_key');
    $this->assertDatabaseCount('user_referers', 0);

    $this->postJson('/cookie-preferences', ['analytics' => true])
        ->assertOk()->assertJsonPath('analytics', true)
        ->assertCookie(CookiePreferences::NAME, CookiePreferences::value(true));

    $this->withCookie(CookiePreferences::NAME, CookiePreferences::value(true))
        ->get('/cookie-test-page')
        ->assertOk()->assertJsonPath('tracked', true)->assertCookie('user_key');
    expect(UserReferer::firstOrFail()->referer_url)->toBe('https://example.org/search');
});

it('remembers a refusal and expires the optional visitor cookie', function () {
    $this->withCookie('user_key', 'previous-visitor');
    $this->postJson('/cookie-preferences', ['analytics' => false])
        ->assertOk()->assertJsonPath('analytics', false)
        ->assertCookie(CookiePreferences::NAME, CookiePreferences::value(false))
        ->assertCookieExpired('user_key');

    $this->withCookie(CookiePreferences::NAME, CookiePreferences::value(false))
        ->withHeader('Referer', 'https://example.org/search')
        ->get('/cookie-test-page')
        ->assertOk()->assertJsonPath('tracked', false)->assertCookieMissing('user_key');
    $this->assertDatabaseCount('user_referers', 0);
});

it('requires an explicit boolean choice', function () {
    $this->postJson('/cookie-preferences', [])
        ->assertUnprocessable()->assertCookieMissing(CookiePreferences::NAME);
});
