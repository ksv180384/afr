<?php

use App\Models\GameSession;
use App\Services\App\FallingTranslationsService;

function fallingTranslationsPoints(
    int $completed,
    int $streak,
    int $responseMs,
    int $wrongAttempts = 0,
): array {
    $session = new GameSession([
        'correct_count' => $completed,
        'wrong_count' => 0,
        'missed_count' => 0,
        'streak' => $streak,
    ]);

    $method = new ReflectionMethod(FallingTranslationsService::class, 'pointBreakdown');
    $method->setAccessible(true);

    return $method->invoke(new FallingTranslationsService(), $session, $responseMs, $wrongAttempts);
}

it('calculates the level one speed bonus independently from base points', function () {
    expect(fallingTranslationsPoints(0, 0, 0))->toBe([
        'base_points' => 100,
        'speed_bonus' => 150,
        'combo_bonus' => 0,
        'error_penalty' => 0,
        'total' => 250,
    ]);
});

it('breaks combo and subtracts penalties after a wrong attempt', function () {
    expect(fallingTranslationsPoints(0, 4, 16000, 2))->toBe([
        'base_points' => 100,
        'speed_bonus' => 75,
        'combo_bonus' => 0,
        'error_penalty' => 70,
        'total' => 105,
    ]);
});

it('caps the combo bonus at one hundred points', function () {
    expect(fallingTranslationsPoints(0, 15, 32000))->toBe([
        'base_points' => 100,
        'speed_bonus' => 0,
        'combo_bonus' => 100,
        'error_penalty' => 0,
        'total' => 200,
    ]);
});

it('applies the level multiplier to base points and speed bonus', function () {
    expect(fallingTranslationsPoints(36, 10, 0))->toBe([
        'base_points' => 208,
        'speed_bonus' => 312,
        'combo_bonus' => 100,
        'error_penalty' => 0,
        'total' => 620,
    ]);
});
