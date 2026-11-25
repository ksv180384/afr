<?php

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\PracticeTelemetry;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true, 'daily_practice.operations.telemetry' => false]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $this->unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $this->payload = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    $this->session = LearningSession::factory()->create(['unit_id' => $this->unit->id, 'external_id' => $this->payload['id']]);
    $this->version = lessonWithContent(['session_id' => $this->session->id, 'payload' => $this->payload,
        'checksum' => CanonicalJson::hash($this->payload), 'status' => 'published', 'published_at' => now('UTC')]);
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
});

function operationsStart($test, ?User $owner = null): array
{
    return app(AccountRunService::class)->start($owner ?? $test->owner, ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])['state'];
}

it('sweeps only expired practice cache keys in bounded batches during ordinary API requests', function () {
    config(['cache.default' => 'database', 'cache.stores.database.prefix' => 'tenant_%:']);
    $prefix = Cache::store()->getStore()->getPrefix();
    $legacy = $prefix.'daily-practice:content:';
    $current = $prefix.'daily-practice:lesson:';
    $now = now()->timestamp;
    $expired = array_map(fn ($i) => $legacy.$i, range(1, 100));
    $expired[] = $current.'old';
    $protected = [$current.'live', $prefix.'unrelated', 'tenant-abc:daily-practice:lesson:foreign'];
    foreach ([...$expired, ...$protected] as $key) {
        DB::table('cache')->insert(['key' => $key, 'value' => serialize('test'), 'expiration' => $key === $current.'live' ? $now + 600 : $now - 1]);
    }
    $this->actingAs($this->owner);
    $this->getJson('/daily/api/profile')->assertOk();
    expect(DB::table('cache')->whereIn('key', $expired)->count())->toBe(1);
    $this->getJson('/daily/api/profile')->assertOk();
    expect(DB::table('cache')->whereIn('key', $expired)->count())->toBe(1);
    $this->travel(61)->seconds();
    $this->getJson('/daily/api/profile')->assertOk();
    expect(DB::table('cache')->whereIn('key', $expired)->count())->toBe(0)
        ->and(DB::table('cache')->whereIn('key', $protected)->count())->toBe(3);
});

it('hides foreign run reads commands media and presentations without creating attempts', function () {
    $run = operationsStart($this);
    $this->actingAs($this->stranger);
    $this->getJson('/daily/api/runs/'.$run['run_id'])->assertNotFound();
    $this->get('/daily/api/runs/'.$run['run_id'].'/media/'.Str::uuid())->assertNotFound();
    foreach (['answers', 'help', 'pause', 'resume', 'finish', 'advance', 'introduction', 'review-mistakes'] as $path) {
        $this->postJson('/daily/api/runs/'.$run['run_id'].'/'.$path, ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1,
            ...in_array($path, ['answers', 'help', 'advance', 'introduction']) ? ['presentation_id' => $run['presentation']['presentation_id']] : [],
            ...$path === 'answers' ? ['event_type' => 'answer', 'answer' => ['choice_id' => 'a']] : [],
            ...$path === 'help' ? ['event_type' => 'unknown'] : []])->assertNotFound();
    }
    $this->get('/admin/daily-practice/sessions/'.$this->version->id.'/export')->assertForbidden();
    expect(LearningAttempt::count())->toBe(0)->and(LearningRun::count())->toBe(1);
});

it('carries only remaining mistakes into the next correction round and preserves the first result', function () {
    $this->actingAs($this->owner);
    $state = operationsStart($this);
    $path = '/daily/api/runs/'.$state['run_id'];
    $send = function (string $action, array $extra = []) use (&$state, $path) {
        $state = $this->postJson($path.'/'.$action, [
            'client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], ...$extra,
        ])->assertOk()->json('state');
    };
    $presentation = fn () => $this->getJson($path)->assertOk()->json('presentation.presentation_id');
    $answerFor = fn (array $task) => match (true) {
        isset($task['correct_choice_id']) => ['choice_id' => $task['correct_choice_id']],
        $task['type'] === 'matching_pairs' => ['pairs' => array_map(fn ($pair) => array_intersect_key($pair, array_flip(['left_id', 'right_id'])), $task['correct_pairs'])],
        $task['type'] === 'word_order' => ['token_ids' => $task['accepted_orders'][0]],
        default => ['value' => $task['accepted_answers'][0]],
    };
    $first = $state['presentation']['presentation_id'];
    $last = null;
    foreach ($this->payload['tasks'] as $index => $task) {
        $current = $state['presentation']['presentation_id'];
        $last = $current;
        if ($index === 0 || $index === count($this->payload['tasks']) - 1) {
            $send('help', ['presentation_id' => $current, 'event_type' => 'unknown']);
        } else {
            $send('answers', ['presentation_id' => $current, 'event_type' => 'answer', 'answer' => $answerFor($task)]);
        }
        if ($index < count($this->payload['tasks']) - 1) {
            $send('advance', ['presentation_id' => $current]);
        }
    }
    $summary = $state['summary'];
    $send('review-mistakes');
    expect($state['corrections'])->toMatchArray(['started' => false, 'position' => 1, 'total' => 2, 'round' => 1]);
    expect($presentation())->toBe($first);
    $send('advance', ['presentation_id' => $first]);
    expect($state['presentation']['first_result'])->toBeNull();
    $this->postJson($path.'/advance', ['client_event_id' => (string) Str::uuid(), 'presentation_id' => $first,
        'expected_revision' => $state['revision']])->assertUnprocessable();
    $send('help', ['presentation_id' => $first, 'event_type' => 'unknown']);
    $send('advance', ['presentation_id' => $first]);
    expect($presentation())->toBe($last);
    $send('answers', ['presentation_id' => $last, 'event_type' => 'answer', 'answer' => $answerFor(end($this->payload['tasks']))]);
    expect($state['corrections']['can_finish'])->toBeFalse();
    $this->postJson($path.'/finish', ['client_event_id' => (string) Str::uuid(),
        'expected_revision' => $state['revision']])->assertUnprocessable();
    $send('advance', ['presentation_id' => $last]);
    $this->getJson($path)->assertOk()->assertJsonPath('corrections.started', false)->assertJsonPath('corrections.round', 2)
        ->assertJsonPath('corrections.total', 1)->assertJsonPath('corrections.fixed_last_round', 1)
        ->assertJsonPath('presentation.presentation_id', $first)->assertJsonPath('presentation.last_result', null);
    $send('advance', ['presentation_id' => $first]);
    $send('answers', ['presentation_id' => $first, 'event_type' => 'answer', 'answer' => $answerFor($this->payload['tasks'][0])]);
    expect($state['corrections']['can_finish'])->toBeTrue();
    $send('finish');
    expect($state['finished'])->toBeTrue()
        ->and($state['mistakes_count'])->toBe(2)
        ->and($state['summary']['score_numerator'])->toBe($summary['score_numerator']);
});

it('ignores no owner supplied in request bodies and protects vocabulary by the authenticated owner', function () {
    $word = PersonalVocabulary::factory()->create(['user_id' => $this->owner->id]);
    $this->actingAs($this->stranger)->patchJson('/daily/api/vocabulary/'.$word->id, ['needs_review' => true])->assertNotFound();
    $this->patchJson('/daily/api/vocabulary/'.$word->id, ['needs_review' => true, 'user_id' => $this->owner->id])->assertUnprocessable();
    $this->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily', 'user_id' => $this->owner->id])->assertUnprocessable();
    $this->postJson('/daily/api/guest-sync', ['guest_id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'level_events' => [], 'runs' => [], 'marks' => [], 'user_id' => $this->owner->id])->assertUnprocessable();
    expect(LearningRun::count())->toBe(0);
});

it('rejects oversized bodies before decoding validation or writing progress', function () {
    config(['daily_practice.operations.request_bytes' => 256]);
    $this->actingAs($this->owner)->call('POST', '/daily/api/runs', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"text":"'.str_repeat('x', 300).'"}')
        ->assertStatus(413)->assertJsonPath('code', 'payload_too_large')->assertHeader('Cache-Control', 'no-store, private');
    $this->call('POST', '/admin/daily-practice/imports/preview', [], [], [], ['CONTENT_LENGTH' => 12000000])->assertStatus(413);
    expect(LearningRun::count())->toBe(0);
});

it('preserves retry headers on throttled sync requests', function () {
    $this->actingAs($this->owner);
    for ($n = 0; $n < 12; $n++) {
        $this->postJson('/daily/api/guest-sync', [])->assertUnprocessable();
    }
    $this->postJson('/daily/api/guest-sync', [])->assertTooManyRequests()->assertHeader('Retry-After')->assertJsonPath('code', 'rate_limited');
});

it('paginates only the owners vocabulary and never hydrates full session payloads for sources', function () {
    PersonalVocabulary::factory()->count(35)->create(['user_id' => $this->owner->id]);
    PersonalVocabulary::factory()->count(3)->create(['user_id' => $this->stranger->id]);
    $this->actingAs($this->owner)->getJson('/daily/api/vocabulary')->assertOk()->assertJsonCount(30, 'words')->assertJsonPath('pagination.total', 35)->assertJsonPath('pagination.last_page', 2);
    $this->getJson('/daily/api/vocabulary?page=2')->assertOk()->assertJsonCount(5, 'words');
    $this->getJson('/daily/api/vocabulary?page=0')->assertUnprocessable();
    $this->getJson('/daily/api/history')->assertOk()->assertJsonPath('total', 0);
});

it('serves 700 metadata entries without fetching all question snapshots', function () {
    for ($n = 2; $n <= 700; $n++) {
        $session = LearningSession::factory()->create(['unit_id' => $this->unit->id, 'external_id' => 'QA-SESSION-'.$n, 'sort_order' => $n]);
        $version = lessonWithContent(['session_id' => $session->id, 'payload' => $this->payload, 'status' => 'published']);
    }
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $response = $this->getJson('/daily/api/content')->assertOk()->assertJsonCount(700, 'sessions');
    expect(strlen($response->getContent()))->toBeLessThan(400000)->and($response->getContent())->not->toContain('correct_choice_id', 'review_items');
    $selects = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'content_title')));
    expect($selects)->toHaveCount(2);
    foreach ($selects as $select) {
        expect($select)->not->toContain('select *', 'payload', 'json_extract');
    }
});

it('caches immutable content but checks publication again on every request', function () {
    Cache::flush();
    $this->getJson('/daily/api/content/'.$this->session->external_id)->assertOk()->assertJsonPath('snapshot.id', $this->payload['id']);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $this->getJson('/daily/api/content/'.$this->session->external_id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(array_filter($queries, fn ($sql) => str_starts_with($sql, 'select "payload" from "learning_sessions"')))->toBe([]);
    $this->session->update(['archived_at' => now('UTC')]);
    $this->getJson('/daily/api/content/'.$this->session->external_id)->assertNotFound();
});

it('falls back to the immutable database payload if the cache is unavailable', function () {
    Cache::shouldReceive('remember')->once()->andThrow(new RuntimeException('cache unavailable'));
    $payload = app(\App\Services\Learning\SessionContentCache::class)->payload($this->version);
    expect(CanonicalJson::hash($payload))->toBe(CanonicalJson::hash($this->payload));
});

it('logs only allowlisted operational fields and still acknowledges a committed write when logging fails', function () {
    config(['daily_practice.operations.telemetry' => true]);
    $logger = Mockery::mock();
    Log::shouldReceive('channel')->with('daily_practice')->andReturn($logger);
    $logger->shouldReceive('log')->once()->with('warning', 'test', ['status' => 503]);
    app(PracticeTelemetry::class)->record('test', ['status' => 503, 'email' => 'private@example.test', 'answer' => 'private text', 'token' => 'secret']);
    $logger->shouldReceive('log')->andThrow(new RuntimeException('disk full'));
    $this->actingAs($this->owner)->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'daily'])->assertOk();
    expect(LearningRun::count())->toBe(1);
});

it('reports aggregate starts finishes returning learners and steps without identifiers or answers', function () {
    $run = operationsStart($this);
    $input = ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'presentation_id' => $run['presentation']['presentation_id'], 'event_type' => 'unknown'];
    app(AccountRunService::class)->event($this->owner, $run['run_id'], 'help', $input);
    app(AccountRunService::class)->event($this->owner, $run['run_id'], 'help', $input);
    LearningRun::factory()->create(['user_id' => $this->owner->id, 'session_id' => $this->version->id, 'snapshot' => $this->payload,
        'status' => 'completed', 'started_at' => now('UTC')->subDays(2), 'local_date' => now('UTC')->subDays(2)->toDateString(), 'finished_at' => now('UTC')->subDay()]);
    $this->withoutMockingConsoleOutput();
    expect(\Illuminate\Support\Facades\Artisan::call('daily-practice:metrics', ['--days' => 7]))->toBe(0);
    $output = \Illuminate\Support\Facades\Artisan::output();
    $report = json_decode($output, true);
    expect($report['starts'])->toBe(2)->and($report['finishes'])->toBe(1)->and($report['returning_learners'])->toBe(1)
        ->and((int) $report['problem_steps'][0]['events'])->toBe(1)->and($output)->not->toContain('user_id', 'email', 'snapshot', 'choice_id');
    expect(\Illuminate\Support\Facades\Artisan::call('daily-practice:metrics', ['--days' => 0]))->toBe(1);
});

it('closes new starts without removing runs snapshots or receipts when disabled', function () {
    $run = operationsStart($this);
    config(['daily_practice.enabled' => false]);
    $this->actingAs($this->owner)->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 2, 'kind' => 'daily'])->assertServiceUnavailable();
    expect(LearningRun::findOrFail($run['run_id'])->snapshot)->not->toHaveKey('tasks');
    $this->get('/daily')->assertOk();
});
it('isolates source filters and reads only version metadata for paginated word sources', function () {
    $word = PersonalVocabulary::factory()->studied($this->payload['vocabulary'][0], $this->version)->create(['user_id' => $this->owner->id]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $this->actingAs($this->owner)->getJson('/daily/api/vocabulary?source_session='.$this->session->external_id)->assertOk()->assertJsonCount(1, 'words')->assertJsonPath('words.0.sources.0.session_id', $this->session->external_id);
    expect(array_filter($queries, fn ($sql) => str_contains($sql, '"payload"') && str_contains($sql, 'from "learning_sessions"')))->toBeEmpty();
    $this->actingAs($this->stranger)->getJson('/daily/api/vocabulary?q=bonjour')->assertOk()->assertJsonCount(0, 'words')->assertJsonCount(0, 'source_sessions');
});

it('refreshes the catalog cache and refuses to grade a stale open lesson', function () {
    $run = operationsStart($this);
    $this->getJson('/daily/api/content/'.$this->session->external_id)->assertOk();
    $next = $this->payload;
    $next['title'] = 'Updated teaching material';
    $this->version->update(['payload' => $next, 'checksum' => CanonicalJson::hash($next)]);
    $this->getJson('/daily/api/content/'.$this->session->external_id)->assertOk()->assertJsonPath('snapshot.title', $next['title']);
    $this->actingAs($this->owner)->getJson('/daily/api/runs/'.$run['run_id'])->assertConflict();
});

it('does not mark a preview expired when file deletion fails', function () {
    $batch = \App\Models\Learning\SessionImportBatch::create(['file_hash' => str_repeat('a', 64), 'preview_token_hash' => str_repeat('b', 64), 'private_path' => 'previews/'.Str::uuid().'.json', 'original_filename' => 'preview.json', 'file_size' => 2, 'validation_report' => [], 'created_by' => $this->owner->id, 'status' => 'preview', 'expires_at' => now('UTC')->subMinute()]);
    $disk = Mockery::mock();
    \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('learning_private')->andReturn($disk);
    $disk->shouldReceive('exists')->with($batch->private_path)->andReturn(true);
    $disk->shouldReceive('delete')->with($batch->private_path)->once()->andReturn(false);
    expect(fn () => app(\App\Services\Learning\SessionImportService::class)->pruneExpired())->toThrow(RuntimeException::class);
    expect($batch->fresh()->status)->toBe('preview');
});

it('redacts unexpected practice failures from the default exception reporter', function () {
    config(['daily_practice.operations.telemetry' => true]);
    $logger = Mockery::mock();
    Log::shouldReceive('channel')->with('daily_practice')->andReturn($logger);
    Log::shouldReceive('error')->never();
    $logger->shouldReceive('log')->once()->withArgs(fn ($level, $event, $context) => $event === 'practice_request' && $context['status'] === 503 && ! str_contains(json_encode($context), 'private@example'));
    $logger->shouldReceive('log')->once()->withArgs(fn ($level, $event, $context) => $event === 'practice_failure' && $context['error_class'] === RuntimeException::class && ! str_contains(json_encode($context), 'private@example'));
    \Illuminate\Support\Facades\Route::post('/daily/api/ops-fixture', function () {
        throw new RuntimeException('private@example.test and private answer');
    });
    $this->postJson('/daily/api/ops-fixture')->assertServiceUnavailable()->assertJsonPath('code', 'unavailable');
});
it('bounds oversized read responses without reporting them as saved', function () {
    config(['daily_practice.operations.response_bytes' => 100]);
    $this->actingAs($this->owner)->getJson('/daily/api/content/'.$this->session->external_id)->assertServiceUnavailable()->assertJsonPath('code', 'response_too_large');
    expect(LearningRun::count())->toBe(0);
});

it('keeps a large vocabulary browsable and bounds a whole test plan with an explicit filter error', function () {
    $rows = PersonalVocabulary::factory()->count(5001)->raw(['user_id' => $this->owner->id, 'needs_review' => false]);
    foreach (array_chunk($rows, 250) as $chunk) {
        DB::table('learning_lexemes')->insert(array_map(fn ($row) => ['lexeme_key' => $row['lexeme_key'], 'content' => '{}'], $chunk));
        PersonalVocabulary::insert($chunk);
    }
    $this->actingAs($this->owner)->getJson('/daily/api/vocabulary?page=167')->assertOk()->assertJsonCount(21, 'words')->assertJsonPath('pagination.total', 5001);
    $this->getJson('/daily/api/vocabulary/test-plan')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    $this->getJson('/daily/api/vocabulary/test-plan?filter=errors')->assertOk()->assertJsonPath('total', 0);
});
it('preserves Unicode case folding in paginated French and Russian searches', function () {
    PersonalVocabulary::factory()->studied(['fr' => 'ÉcÔle', 'ru' => ['ШКОЛА'], 'transcription_ru' => 'эко́ль'])->create(['user_id' => $this->owner->id]);
    $this->actingAs($this->owner)->getJson('/daily/api/vocabulary?q='.urlencode('écôle'))->assertOk()->assertJsonCount(1, 'words');
    $this->getJson('/daily/api/vocabulary?q='.urlencode('школа'))->assertOk()->assertJsonCount(1, 'words');
});
it('distinguishes the disabled switch from an unexpected server failure in telemetry', function () {
    config(['daily_practice.enabled' => false, 'daily_practice.operations.telemetry' => true]);
    $logger = Mockery::mock();
    Log::shouldReceive('channel')->with('daily_practice')->andReturn($logger);
    $logger->shouldReceive('log')->once()->withArgs(fn ($level, $event, $context) => $event === 'practice_rejection' && $context['error_code'] === 'disabled' && $context['status'] === 503);
    $logger->shouldReceive('log')->once()->withArgs(fn ($level, $event, $context) => $event === 'practice_request' && $context['error_code'] === 'disabled');
    $this->actingAs($this->owner)->postJson('/daily/api/runs', [])->assertServiceUnavailable();
});

it('does not create successful import or media records when private storage returns a failed write', function () {
    $rank = \App\Models\User\Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $rank->id]);
    $disk = Mockery::mock();
    \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('learning_private')->andReturn($disk);
    $disk->shouldReceive('put')->twice()->andReturn(false);
    $data = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true);
    $package = ['schema_version' => 1, 'package_id' => 'storage-failure', 'language_pair' => 'fr-ru', 'levels' => $data['levels'], 'sessions' => []];
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('package.json', CanonicalJson::encode($package));
    expect(fn () => app(\App\Services\Learning\SessionImportService::class)->preview($admin, $file))->toThrow(\App\Services\Learning\ContentOperationException::class);
    $pcm = str_repeat(chr(0), 1600);
    $wave = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    expect(fn () => app(\App\Services\Learning\MediaLibraryService::class)->upload($admin, \Illuminate\Http\UploadedFile::fake()->createWithContent('sound.wav', $wave)))->toThrow(\App\Services\Learning\ContentOperationException::class);
    expect(\App\Models\Learning\SessionImportBatch::count())->toBe(0)->and(\App\Models\Learning\LearningMedia::count())->toBe(0);
});
