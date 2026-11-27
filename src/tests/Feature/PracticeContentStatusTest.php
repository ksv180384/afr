<?php

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('reports an expanded authoring target without counting planned lessons as prepared', function () {
    Storage::fake('learning_plan_test');
    $disk = Storage::disk('learning_plan_test');
    $levels = [];
    foreach (LearningLevel::cases() as $level) {
        $count = $level->value === 'PRE_A1' ? 105 : 100;
        $levels[] = [
            'code' => $level->value, 'target_sessions' => $count,
            'required_objective_ids' => [], 'checkpoint_session_ids' => [],
            'units' => [[
                'id' => $level->value.'-U01', 'package_path' => 'missing.json',
                'sessions' => array_map(fn ($i) => ['id' => $level->value.'-L'.$i], range(1, $count)),
            ]],
        ];
    }
    $disk->put('programme/map.v1.json', json_encode(['levels' => $levels], JSON_THROW_ON_ERROR));
    config(['daily_practice.content_path' => $disk->path('')]);
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('daily-practice:content-status', ['--json' => true]))->toBe(1);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($report['target_sessions'])->toBe(705)
        ->and($report['authoring_remaining'])->toBe(705)
        ->and($report['file_sessions'])->toBe(0)
        ->and($report['machine_valid_sessions'])->toBe(0)
        ->and($report['db_published_sessions'])->toBe(0)
        ->and($report['stage_17_complete'])->toBeFalse();
    expect(LearningSession::count())->toBe(0)->and(LearningRun::count())->toBe(0);
});
