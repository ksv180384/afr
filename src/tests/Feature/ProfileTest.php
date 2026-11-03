<?php

use App\Models\Learning\LearningProfile;
use App\Models\Proverb;
use App\Models\User\User;
use Database\Seeders\Learning\LearningLevelSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    Proverb::query()->create([
        'text' => 'Qui vivra verra.',
        'translation' => 'Поживём — увидим.',
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'day_birthday' => false,
            'yar_birthday' => false,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'day_birthday' => false,
            'yar_birthday' => false,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('profile offers a timezone list without creating learning data on a read', function () {
    Proverb::query()->create(['text' => 'Bonjour', 'translation' => 'Привет']);
    $this->actingAs(User::factory()->create())->get('/profile')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('practiceTimezone', null)
            ->where('timezones', fn ($zones) => in_array('Europe/Moscow', $zones->all(), true) && in_array('Europe/Paris', $zones->all(), true)));
    expect(LearningProfile::count())->toBe(0);
});

test('profile timezone is validated and saved without changing the selected learning level', function () {
    $this->seed(LearningLevelSeeder::class);
    $user = User::factory()->create();
    $profile = LearningProfile::factory()->create(['user_id' => $user->id, 'current_level' => 'A1', 'timezone' => 'Europe/Moscow', 'revision' => 3]);
    $input = ['name' => $user->name, 'day_birthday' => false, 'yar_birthday' => false];
    $this->actingAs($user)->patch('/profile', [...$input, 'timezone' => 'Europe/Paris'])
        ->assertSessionHasNoErrors()->assertRedirect('/profile');
    expect($profile->fresh()->timezone)->toBe('Europe/Paris')
        ->and($profile->fresh()->getRawOriginal('current_level'))->toBe('A1')
        ->and($profile->fresh()->revision)->toBe(4);
    $this->patch('/profile', [...$input, 'timezone' => 'Europe/Paris'])->assertSessionHasNoErrors();
    expect($profile->fresh()->revision)->toBe(4);
    $this->patch('/profile', [...$input, 'timezone' => 'Not/A_Timezone'])->assertSessionHasErrors('timezone');
    expect($profile->fresh()->timezone)->toBe('Europe/Paris');
});

test('saving the detected timezone initializes it for new practice sessions', function () {
    $this->seed(LearningLevelSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'day_birthday' => false, 'yar_birthday' => false, 'timezone' => 'Asia/Vladivostok'])
        ->assertSessionHasNoErrors();
    expect(app(\App\Services\Learning\AccountRunService::class)->profile($user))
        ->toMatchArray(['timezone' => 'Asia/Vladivostok', 'persisted' => true, 'current_level' => 'PRE_A1']);
});
