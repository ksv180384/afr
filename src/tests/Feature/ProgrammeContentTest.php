<?php

use App\Services\Learning\SessionPackageValidator;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
});

function programmeFirstPackage(): array
{
    return json_decode(file_get_contents(resource_path('learning/programme/packages/PRE_A1/PRE_A1-U01.v1.json')), true, 512, JSON_THROW_ON_ERROR);
}

it('keeps the authored programme map separate from 700 ready sessions', function () {
    $map = json_decode(file_get_contents(resource_path('learning/programme/map.v1.json')), true);
    expect($map['status'])->toBe('authoring_plan')->and($map['levels'])->toHaveCount(7);
    foreach ($map['levels'] as $level) {
        expect($level['units'])->toHaveCount(20)->and($level['checkpoint_session_ids'])->toHaveCount(4);
        $ids = [];
        foreach ($level['units'] as $unit) {
            expect($unit['sessions'])->toHaveCount(5)->and($unit['vocabulary_plan'])->not->toBeEmpty();
            foreach ($unit['sessions'] as $session) {
                expect($session['status'])->toBe('planned');
                foreach ($session['prerequisite_ids'] as $id) {
                    expect($ids)->toContain($id);
                }
                $ids[] = $session['id'];
            }
        }
        expect(array_unique($ids))->toHaveCount(100);
    }
});

it('validates the first five-session authored package and preserves the original two snapshots', function () {
    $package = programmeFirstPackage();
    $report = app(SessionPackageValidator::class)->validate(CanonicalJson::encode($package));
    expect($report->toArray()['errors'])->toBe([]);
    expect($package['sessions'])->toHaveCount(5);
    $samples = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    expect(array_slice($package['sessions'], 0, 2))->toBe($samples['sessions']);
    foreach ($package['sessions'] as $session) {
        expect(count($session['tasks']))->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(8);
        foreach ($session['vocabulary'] as $word) {
            expect($word['review_items'])->not->toBeEmpty();
        }
    }
});

it('offers every first-unit goal on two distinct sessions with vocabulary and another skill', function () {
    $coverage = [];
    foreach (programmeFirstPackage()['sessions'] as $session) {
        foreach ($session['tasks'] as $task) {
            foreach ($task['objective_ids'] as $id) {
                $coverage[$id]['tasks'][$session['id'].':'.$task['id']] = true;
                $coverage[$id]['sessions'][$session['id']] = true;
                $coverage[$id]['skills'][$task['skill']] = true;
            }
        }
    }
    foreach ($coverage as $id => $goal) {
        expect(count($goal['tasks']), $id)->toBeGreaterThanOrEqual(2);
        expect(count($goal['sessions']), $id)->toBeGreaterThanOrEqual(2);
        expect(array_keys($goal['skills']), $id)->toContain('vocabulary');
        expect(count($goal['skills']), $id)->toBeGreaterThanOrEqual(2);
    }
});
it('reports planned and prepared content separately without publishing or creating learner state', function () {
    $this->withoutMockingConsoleOutput();
    expect(\Illuminate\Support\Facades\Artisan::call('daily-practice:content-status', ['--json' => true]))->toBe(1);
    $report = json_decode(\Illuminate\Support\Facades\Artisan::output(), true);
    $map = json_decode(file_get_contents(resource_path('learning/programme/map.v1.json')), true);
    $prepared = [];
    foreach ($map['levels'] as $level) {
        foreach ($level['units'] as $unit) {
            $path = resource_path('learning/programme/'.$unit['package_path']);
            if (is_file($path)) {
                foreach (json_decode(file_get_contents($path), true)['sessions'] as $session) {
                    if ($session['level'] === $level['code'] && $session['unit_id'] === $unit['id']) {
                        $prepared[$session['id']] = true;
                    }
                }
            }
        }
    }
    expect($report['stage_17_complete'])->toBeFalse()->and($report['target_sessions'])->toBe(700)->and($report['file_sessions'])->toBe(count($prepared))
        ->and($report['machine_valid_sessions'])->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(count($prepared))
        ->and($report['authoring_remaining'])->toBe(700 - $report['machine_valid_sessions'])->and($report['db_published_sessions'])->toBe(0);
    expect(\App\Models\Learning\LearningSession::count())->toBe(0)->and(\App\Models\Learning\LearningRun::count())->toBe(0);
});
