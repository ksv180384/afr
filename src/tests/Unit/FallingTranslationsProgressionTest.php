<?php

use App\Models\GameSession;
use App\Services\App\FallingTranslationsService;

function fallingTranslationsProgression(int $completed): array
{
    $session = new GameSession([
        'correct_count' => $completed,
        'wrong_count' => 0,
        'missed_count' => 0,
        'streak' => 0,
    ]);
    $game = new FallingTranslationsService();
    $optionsMethod = new ReflectionMethod(FallingTranslationsService::class, 'optionsCount');
    $optionsMethod->setAccessible(true);

    $question = $game->question($session);

    return [
        'level' => $question['level'],
        'options' => $optionsMethod->invoke($game, $session),
        'duration_ms' => $question['duration_ms'],
    ];
}

it('uses the documented progression for all ten levels', function () {
    $expected = [
        0 => [1, 3, 32000],
        4 => [2, 3, 20000],
        8 => [3, 4, 20000],
        12 => [4, 4, 14545],
        16 => [5, 5, 14545],
        20 => [6, 5, 11429],
        24 => [7, 6, 11429],
        28 => [8, 6, 9412],
        32 => [9, 7, 9412],
        36 => [10, 7, 8000],
    ];

    foreach ($expected as $completed => [$level, $options, $durationMs]) {
        expect(fallingTranslationsProgression($completed))->toBe([
            'level' => $level,
            'options' => $options,
            'duration_ms' => $durationMs,
        ]);
    }
});
