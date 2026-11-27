<?php

use Illuminate\Auth\Events\Registered;
use App\Support\LegalDocuments;
use Illuminate\Support\Facades\Event;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Event::fake([Registered::class]);

    $response = $this->post('/register', [
        'name' => uniqid('user'),
        'email' => uniqid('test'). '@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'confirmation_rules' => true,
        'personal_data_consent' => true,
        'legal_version' => LegalDocuments::VERSION,
    ]);

    $this->assertAuthenticated();
    expect(LegalDocuments::acceptedBy(auth()->user()))->toBeTrue();
    $response->assertRedirect(route('verification.notice', absolute: false));
});
