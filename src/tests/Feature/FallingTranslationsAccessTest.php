<?php

use App\Models\GameSession;
use App\Models\User\User;
use App\Models\Word\Word;

it('does not let guests create or change saved game sessions', function () {
    $session = GameSession::create([
        'user_id' => User::factory()->create()->id,
        'game_key' => 'falling-translations',
        'metadata' => ['played_word_ids' => []],
    ]);
    $before = $session->fresh()->getAttributes();
    $base = '/api/v1/games/falling-translations/sessions';

    $this->postJson($base)->assertUnauthorized();
    foreach (['next', 'answer', 'finish'] as $action) {
        $this->postJson("{$base}/{$session->id}/{$action}", ['response_ms' => 1000])->assertUnauthorized();
    }

    $this->assertDatabaseCount('game_sessions', 1);
    expect($session->fresh()->getAttributes())->toBe($before);
});

it('lets a signed in player start answer advance and finish their game', function () {
    $user = User::factory()->create();
    foreach (['bonjour' => 'привет', 'merci' => 'спасибо', 'salut' => 'здравствуй', 'oui' => 'да'] as $word => $translation) {
        Word::create(['word' => $word, 'translation' => $translation]);
    }
    $base = '/api/v1/games/falling-translations/sessions';
    $response = $this->actingAs($user)->postJson($base)->assertOk();
    $session = GameSession::findOrFail($response->json('session_id'));
    expect($session->user_id)->toBe($user->id);

    $this->postJson("{$base}/{$session->id}/answer", [
        'option_id' => data_get($session->metadata, 'question.correct_option_id'),
        'response_ms' => 1000,
    ])->assertOk()->assertJsonPath('correct', true);
    $this->postJson("{$base}/{$session->id}/next")->assertOk();
    $this->postJson("{$base}/{$session->id}/finish")->assertOk();
    expect($session->fresh()->status)->toBe('finished');
});

it('does not let a player change a game belonging to another account', function () {
    $session = GameSession::create([
        'user_id' => User::factory()->create()->id,
        'game_key' => 'falling-translations',
        'metadata' => ['played_word_ids' => []],
    ]);
    $before = $session->fresh()->getAttributes();
    $this->actingAs(User::factory()->create());

    foreach (['next', 'answer', 'finish'] as $action) {
        $this->postJson("/api/v1/games/falling-translations/sessions/{$session->id}/{$action}", ['response_ms' => 1000])->assertNotFound();
    }

    expect($session->fresh()->getAttributes())->toBe($before);
});
