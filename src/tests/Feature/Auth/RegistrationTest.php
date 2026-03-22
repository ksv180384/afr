<?php

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Event::fake([Registered::class]);

    $response = $this->post('/register', [
        'name' => 'Test User2',
        'email' => 'test2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'confirmation_rules' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('index', absolute: false));
});
