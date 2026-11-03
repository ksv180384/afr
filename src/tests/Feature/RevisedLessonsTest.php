<?php

use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\SessionPackageValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function revisedLessonsPackage(int $version = 2): array
{
    return json_decode(file_get_contents(resource_path('learning/programme/packages/PRE_A1/PRE_A1-U01.v'.$version.'.json')), true, 512, JSON_THROW_ON_ERROR);
}

function revisedLessonsImport($test, int $version): array
{
    $preview = $test->post('/admin/daily-practice/imports/preview', [
        'file' => UploadedFile::fake()->createWithContent('lessons.json', json_encode(revisedLessonsPackage($version))),
    ], ['Accept' => 'application/json'])->assertCreated()->json();

    return $test->postJson('/admin/daily-practice/imports/'.$preview['batch_id'].'/commit', [
        'token' => $preview['token'], 'file_hash' => $preview['file_hash'],
    ])->assertOk()->json();
}

it('validates the revised lessons and keeps keyboard input out of lessons and vocabulary reviews', function () {
    $package = revisedLessonsPackage();
    $report = app(SessionPackageValidator::class)->validate(json_encode($package))->toArray();
    expect($report['errors'])->toBe([]);
    expect($package['sessions'])->toHaveCount(5);
    $coverage = [];
    foreach ($package['sessions'] as $session) {
        expect($session['version'])->toBe(2);
        expect(count($session['tasks']))->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(8);
        foreach ($session['introductions'] as $intro) {
            expect(count($intro['vocabulary_ids']))->toBeLessThanOrEqual(3);
        }
        foreach ($session['tasks'] as $task) {
            expect($task['type'])->toBeIn(['single_choice', 'matching_pairs', 'fill_blank', 'word_order']);
            if ($task['type'] === 'fill_blank') {
                expect($task['input_mode'])->toBe('choice');
            }
            foreach ($task['objective_ids'] as $id) {
                $coverage[$id]['sessions'][$session['id']] = true;
                $coverage[$id]['skills'][$task['skill']] = true;
            }
        }
        foreach ($session['vocabulary'] as $word) {
            expect($word['review_items'])->toHaveCount(2);
            expect(array_unique(array_column($word['review_items'], 'type')))->toBe(['single_choice']);
        }
    }
    expect($coverage)->toHaveCount(10);
    foreach ($coverage as $goal) {
        expect(count($goal['sessions']))->toBeGreaterThanOrEqual(2);
        expect(array_keys($goal['skills']))->toContain('vocabulary');
        expect(count($goal['skills']))->toBeGreaterThanOrEqual(2);
    }
});

it('downloads the prepared lessons only for a verified administrator', function () {
    $this->getJson('/admin/daily-practice/templates/pre-a1-u01-v2')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/admin/daily-practice/templates/pre-a1-u01-v2')->assertForbidden();
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $rank->id]);
    $this->actingAs($admin)->get('/admin/daily-practice/templates/pre-a1-u01-v2')->assertOk()->assertDownload('pre-a1-u01-v2.json');
});

it('upgrades all five lessons without rewriting old runs and completes every revised task through account grading', function () {
    $this->withoutVite();
    config(['daily_practice.enabled' => true]);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->actingAs(User::factory()->create(['rang_id' => $rank->id]));
    $old = revisedLessonsImport($this, 1);
    foreach ($old['catalog_revision_ids'] as $id) {
        $this->postJson('/admin/daily-practice/catalog/'.$id.'/publish-direct')->assertOk();
    }
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $old['version_ids'], 'status' => 'published'])->assertOk();
    $learner = User::factory()->create();
    $runs = app(AccountRunService::class);
    $input = fn (int $revision, array $fields = []) => ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $revision, ...$fields];
    $oldState = $runs->start($learner, $input(1, ['kind' => 'extra', 'session_id' => 'PRE_A1-U01-L01']))['state'];
    $oldRun = LearningRun::findOrFail($oldState['run_id']);
    $oldSnapshot = $oldRun->snapshot;
    $checksums = LearningSessionVersion::whereIn('id', $old['version_ids'])->pluck('checksum', 'id')->all();
    $slugs = LearningSession::pluck('slug', 'external_id')->all();

    $new = revisedLessonsImport($this, 2);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $new['version_ids'], 'status' => 'published'])->assertOk();
    expect(LearningSession::count())->toBe(5)->and(LearningSessionVersion::count())->toBe(10);
    expect(LearningSession::pluck('slug', 'external_id')->all())->toBe($slugs);
    expect($oldRun->fresh()->snapshot)->toBe($oldSnapshot);
    expect($runs->load($learner, $oldRun->id)['session_version_id'])->toBe($oldState['session_version_id']);
    expect(LearningSessionVersion::whereIn('id', $old['version_ids'])->pluck('checksum', 'id')->all())->toBe($checksums);
    $again = revisedLessonsImport($this, 2);
    expect($again['created_version_ids'])->toBe([])->and($again['skipped_version_ids'])->toHaveCount(5);

    foreach (revisedLessonsPackage()['sessions'] as $session) {
        $state = $runs->start($learner, $input($runs->profile($learner)['revision'], ['kind' => 'extra', 'session_id' => $session['id']]))['state'];
        expect(LearningSessionVersion::findOrFail($state['session_version_id'])->version)->toBe(2);
        foreach ($session['tasks'] as $task) {
            $pid = $state['presentation']['presentation_id'];
            if ($state['introductions']) {
                $state = $runs->command($learner, $state['run_id'], 'introduction', $input($state['revision'], ['presentation_id' => $pid]))['state'];
            }
            $answer = match ($task['type']) {
                'single_choice', 'fill_blank' => ['choice_id' => $task['correct_choice_id']],
                'word_order' => ['token_ids' => $task['accepted_orders'][0]],
                'matching_pairs' => ['pairs' => array_map(fn ($p) => ['left_id' => $p['left_id'], 'right_id' => $p['right_id']], $task['correct_pairs'])],
            };
            $state = $runs->event($learner, $state['run_id'], 'answer', $input($state['revision'], [
                'presentation_id' => $pid, 'event_type' => 'answer', 'answer' => $answer,
            ]))['state'];
            expect($state['presentation']['first_result']['correct'])->toBeTrue();
            if ($state['position'] < $state['total']) {
                $state = $runs->command($learner, $state['run_id'], 'advance', $input($state['revision'], ['presentation_id' => $pid]))['state'];
            }
        }
        $state = $runs->command($learner, $state['run_id'], 'finish', $input($state['revision']))['state'];
        expect($state['finished'])->toBeTrue()->and($state['summary']['independent_correct_steps'])->toBe(count($session['tasks']));
    }
});
