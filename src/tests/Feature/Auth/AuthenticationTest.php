<?php

use App\Models\User\User;
use Illuminate\Support\Str;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('unverified users are redirected to email verification notice', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('login from a guest lesson retains the lesson through the authentication redirect', function (bool $verified) {
    $this->withoutVite();
    config(['daily_practice.enabled' => true]);
    $user = ($verified ? User::factory() : User::factory()->unverified())->create();
    $run = (string) Str::uuid();

    $this->get('/login?practice=1&guest_run='.$run)->assertOk();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route($verified ? 'daily' : 'verification.notice', ['guest_run' => $run], absolute: false));
})->with([true, false]);

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('guests cannot submit forms that persist data on the server', function () {
    foreach (['/post-comment/store', '/profile/avatar-upload', '/post/store'] as $path) {
        $this->postJson($path, ['text' => 'bonjour'])->assertUnauthorized();
    }
});

test('guests can use the text search without signing in', function () {
    $this->postJson('/search', ['text' => 'bonjour', 'type' => 'word', 'lang' => 'Fr'])
        ->assertOk()->assertJsonStructure(['search']);
    $this->assertGuest();
});
test('authenticated users can use the text search', function () {
    $this->actingAs(User::factory()->create())->postJson('/search', ['text' => 'bonjour', 'type' => 'word', 'lang' => 'Fr'])
        ->assertOk()->assertJsonStructure(['search']);
});
