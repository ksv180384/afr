<?php

use App\Models\User\User;
use App\Support\LegalDocuments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

it('requires two independent choices for registration', function (bool $rules, bool $consent, string $error) {
    $this->post('/register', [
        'name' => 'lecteur',
        'email' => 'lecteur@example.org',
        'password' => 'password',
        'password_confirmation' => 'password',
        'confirmation_rules' => $rules,
        'personal_data_consent' => $consent,
        'legal_version' => LegalDocuments::VERSION,
    ])->assertSessionHasErrors($error);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['name' => 'lecteur']);
    $this->assertDatabaseCount('user_legal_acceptances', 0);
})->with([
    [true, false, 'personal_data_consent'],
    [false, true, 'confirmation_rules'],
]);

it('checks the password before requesting or recording agreements and prompts only once', function () {
    $user = User::factory()->create();
    $login = ['email' => $user->email, 'password' => 'password', 'legal_version' => LegalDocuments::VERSION];
    $choices = ['confirmation_rules' => true, 'personal_data_consent' => true];

    $this->post('/login', ['password' => 'incorrect'] + $login + $choices)
        ->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors(array_keys($choices));
    $this->assertGuest();

    $this->post('/login', $login)->assertSessionHasErrors(array_keys($choices));
    $this->post('/login', $login + ['confirmation_rules' => true])->assertSessionHasErrors('personal_data_consent');
    $this->post('/login', ['legal_version' => '2025-01-01'] + $login + $choices)->assertSessionHasErrors('legal_version');
    $this->assertGuest();
    $this->assertDatabaseCount('user_legal_acceptances', 0);

    $this->post('/login', $login + $choices)->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    foreach (['terms', 'consent'] as $document) {
        $this->assertDatabaseHas('user_legal_acceptances', [
            'user_id' => $user->id, 'document' => $document, 'version' => LegalDocuments::VERSION,
        ]);
    }
    $original = DB::table('user_legal_acceptances')->pluck('accepted_at', 'document')->all();
    $this->post('/logout');
    $this->travel(1)->day();
    $this->post('/login', $login)->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    expect(DB::table('user_legal_acceptances')->pluck('accepted_at', 'document')->all())->toBe($original);
});

it('requires confirmation before restoring a legacy remembered login', function () {
    $user = User::factory()->create();
    $cookie = implode('|', [$user->id, $user->remember_token, $user->getAuthPassword()]);
    $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get('/profile')
        ->assertRedirect(route('login'));
    $this->assertGuest();
    $this->assertDatabaseCount('user_legal_acceptances', 0);
});

it('serves the full legal documents to guests without JavaScript', function (string $url, string $text) {
    $this->withoutVite()->get($url)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee($text)->assertSee('support@apprendrefr.ru')->assertSee('<noscript>', false)
        ->assertDontSee('Караоке')->assertDontSee('караоке');
})->with([
    ['/privacy-policy', 'Котелевский Сергей Васильевич'],
    ['/personal-data-consent', 'На что я даю согласие'],
    ['/info/terms-user', 'Публикации и общение'],
]);
