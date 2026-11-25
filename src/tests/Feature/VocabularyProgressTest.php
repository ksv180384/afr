<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PersonalVocabularySource;
use App\Models\Learning\VocabularyEventEffect;
use App\Models\User\User;
use App\Services\Learning\RunIntegrations;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->actingAs($this->learner);
    $this->payload = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $session = LearningSession::factory()->create(['unit_id' => $unit->id, 'external_id' => $this->payload['id']]);
    $this->version = lessonWithContent(['session_id' => $session->id, 'payload' => $this->payload, 'checksum' => CanonicalJson::hash($this->payload), 'status' => 'published']);
});

function vocabularyInput(array $extra = [], int $revision = 1): array
{
    return ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $revision, ...$extra];
}
function vocabularyStart($test, array $extra = []): array
{
    return $test->postJson('/daily/api/runs', vocabularyInput(['kind' => 'extra', 'session_id' => $test->payload['id'], ...$extra]))->assertOk()->json('state');
}
function vocabularyCommand($test, array $state, string $action, array $extra = []): array
{
    return $test->postJson('/daily/api/runs/'.$state['run_id'].'/'.$action, vocabularyInput($extra, $state['revision']))->assertOk()->json('state');
}
function vocabularyComplete($test, array $state): array
{
    while (true) {
        if (! $state['presentation']['first_result']) {
            $state = vocabularyCommand($test, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
        }
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = vocabularyCommand($test, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
    }

    return vocabularyCommand($test, $state, 'finish');
}
function vocabularyWord($test, int $index = 0): PersonalVocabulary
{
    $snapshot = $test->payload['vocabulary'][$index];
    return PersonalVocabulary::factory()->studied($snapshot, $test->version)->create(['user_id' => $test->learner->id, 'needs_review' => true]);
}

it('keeps personal difficult marks through mastery and trains only the marked selection with alternating questions', function () {
    $context = $this->payload['vocabulary'][0]['review_items'][0];
    $context['id'] = 'context-question';
    $context['prompt'] = ['text' => 'Bonjour, Marie !', 'text_lang' => 'fr'];
    $this->payload['vocabulary'][0]['review_items'][] = $context;
    $this->version->update(['payload' => $this->payload, 'checksum' => CanonicalJson::hash($this->payload)]);
    $word = vocabularyWord($this);
    vocabularyWord($this, 1);
    $this->patchJson('/daily/api/vocabulary/'.$word->id, ['is_difficult' => true])
        ->assertOk()->assertJsonPath('word.is_difficult', true);
    $plan = $this->getJson('/daily/api/vocabulary/test-plan?filter=difficult')->assertOk()->json();
    expect($plan['word_ids'])->toBe([$word->id]);
    $previous = null;
    foreach (range(1, 3) as $step) {
        $this->travel(1)->days();
        $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => $plan['word_ids']]);
        expect($state['task']['id'])->not->toBe($previous);
        $previous = $state['task']['id'];
        $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'],
            'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
        vocabularyCommand($this, $state, 'finish');
    }
    expect($word->fresh()->is_difficult)->toBeTrue();
    expect($word->fresh()->needs_review)->toBeFalse();
    $this->getJson('/daily/api/vocabulary?filter=difficult')->assertOk()->assertJsonCount(1, 'words');
    $this->patchJson('/daily/api/vocabulary/'.$word->id, ['is_difficult' => false])->assertOk();
    $this->getJson('/daily/api/vocabulary?filter=difficult')->assertOk()->assertJsonCount(0, 'words');
});

it('preserves vocabulary and review schedules when raising and lowering the profile level', function (bool $hasWords) {
    if ($hasWords) {
        vocabularyWord($this)->update([
            'errors' => 4, 'streak' => 2, 'review_step' => 3,
            'last_reviewed_at' => now('UTC')->subDay(), 'next_review_at' => now('UTC')->addDays(7),
        ]);
    }
    $words = fn () => PersonalVocabulary::orderBy('id')->get()->map->getRawOriginal()->all();
    $sources = fn () => PersonalVocabularySource::orderBy('id')->get()->map->getRawOriginal()->all();
    $beforeWords = $words();
    $beforeSources = $sources();
    $revision = 1;

    foreach (['B1', 'PRE_A1'] as $level) {
        $this->patchJson('/daily/api/profile', vocabularyInput(['current_level' => $level, 'timezone' => 'UTC'], $revision++))
            ->assertOk()->assertJsonPath('profile.current_level', $level);
        $this->getJson('/daily/api/vocabulary')->assertOk()->assertJsonPath('pagination.total', $hasWords ? 1 : 0);
        expect($words())->toBe($beforeWords);
        expect($sources())->toBe($beforeSources);

        if ($hasWords && $level === 'B1') {
            $this->postJson('/daily/api/runs', vocabularyInput(['kind' => 'vocabulary_test', 'word_ids' => [PersonalVocabulary::sole()->id]], $revision))
                ->assertOk()->assertJsonPath('state.kind', 'vocabulary_test');
        }
    }
})->with(['empty dictionary' => false, 'studied words' => true]);

it('adds studied words only at finish and replays accumulated mistakes once without distractors', function () {
    $state = vocabularyStart($this);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $finished = vocabularyComplete($this, $state);
    expect($finished['summary']['words_added'])->toBe(6);
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->assertDatabaseCount('studied_vocabulary_sources', 6);
    $word = PersonalVocabulary::where('lexeme_key', $this->payload['vocabulary'][0]['lexeme_key'])->sole();
    expect($word->needs_review)->toBeTrue();
    expect($word->errors)->toBeGreaterThan(0);
    $errors = $word->errors;
    vocabularyCommand($this, $finished, 'finish');
    expect($word->fresh()->errors)->toBe($errors);
    $this->assertDatabaseCount('studied_vocabulary_sources', 6);
    expect(PersonalVocabulary::where('lexeme_key', 'fr:merci:thanks')->exists())->toBeFalse();
});

it('shares lesson sets and grants newly edited sources only after the learner finishes again', function () {
    $first = vocabularyComplete($this, vocabularyStart($this));
    $other = User::factory()->create();
    $this->actingAs($other);
    $second = vocabularyComplete($this, vocabularyStart($this));
    $set = LearningRun::findOrFail($first['run_id'])->vocabulary_set_id;
    expect($set)->toBeInt()->and(LearningRun::findOrFail($second['run_id'])->vocabulary_set_id)->toBe($set);
    $this->assertDatabaseCount('learning_vocabulary_sets', 1);
    $this->assertDatabaseCount('learning_vocabulary_set_entries', 6);
    $this->assertDatabaseCount('learning_vocabulary_legacy_studies', 0);
    $this->assertDatabaseCount('studied_vocabulary_sources', 12);

    $this->actingAs($this->learner);
    $repeatLesson = fn () => vocabularyComplete($this, vocabularyStart($this, [
        'expected_revision' => \App\Models\Learning\LearningProfile::where('user_id', $this->learner->id)->value('revision'),
    ]));
    $repeat = $repeatLesson();
    expect(LearningRun::findOrFail($repeat['run_id'])->vocabulary_set_id)->toBeNull();
    $original = $this->version->fresh()->payload;
    $extra = [...$original['vocabulary'][0], 'id' => 'salut', 'lexeme_key' => 'fr:salut:edited'];
    $word = PersonalVocabulary::factory()->studied($extra)->create(['user_id' => $this->learner->id, 'is_difficult' => true]);
    $edited = $original;
    $edited['vocabulary'][] = $extra;
    $update = fn (array $payload) => $this->version->update(['payload' => $payload, 'checksum' => CanonicalJson::hash($payload)]);
    $update($edited);
    expect($word->sources()->count())->toBe(1);
    $this->travel(1)->days();
    $third = $repeatLesson();
    expect($word->sources()->count())->toBe(2)
        ->and($third['summary']['words_added'])->toBe(0)
        ->and(PersonalVocabulary::where('user_id', $other->id)->where('lexeme_key', $extra['lexeme_key'])->exists())->toBeFalse()
        ->and(PersonalVocabularySource::where('user_id', $this->learner->id)->where('session_id', $this->version->id)
            ->where('first_added_at', LearningRun::findOrFail($first['run_id'])->finished_at)->count())->toBe(6);

    $update($original);
    $update($edited);
    expect($word->fresh()->is_difficult)->toBeTrue()->and($word->sources()->count())->toBe(1);
    $repeatLesson();
    expect($word->sources()->count())->toBe(2);
});

it('filters the dictionary by the new words from an owned completion including older results', function () {
    $known = vocabularyWord($this);
    $this->travel(2)->seconds();
    $finished = vocabularyComplete($this, vocabularyStart($this));
    $runId = $finished['run_id'];
    $ids = $finished['summary']['added_word_ids'];
    expect($ids)->toHaveCount(5)->not->toContain($known->id);
    $assertSelection = function () use ($runId, $ids) {
        $words = $this->getJson('/daily/api/vocabulary?added_in_run='.$runId)->assertOk()->json('words');
        expect(array_column($words, 'id'))->toEqualCanonicalizing($ids);
    };
    $assertSelection();

    $legacy = LearningRun::findOrFail($runId)->result;
    unset($legacy['added_word_ids']);
    LearningRun::whereKey($runId)->toBase()->update(['result' => json_encode($legacy, JSON_THROW_ON_ERROR)]);
    $assertSelection();

    $anotherLearner = User::factory()->create();
    PersonalVocabulary::factory()->create(['user_id' => $anotherLearner->id]);
    $this->actingAs($anotherLearner)->getJson('/daily/api/vocabulary?added_in_run='.$runId)
        ->assertOk()->assertJsonCount(0, 'words');
});

it('updates existing words immediately, ignores help for streak, and accepts three independent runs', function () {
    $word = vocabularyWord($this);
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
    expect($word->fresh()->errors)->toBe(1);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'reference']);
    $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->streak)->toBe(0);
    expect(\App\Services\Learning\VocabularySuccessWindow::latest($word->fresh()))->toBeNull();
    vocabularyCommand($this, $state, 'finish');
    for ($i = 1; $i <= 3; $i++) {
        $this->travel(1)->days();
        $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
        $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
        expect($word->fresh()->streak)->toBe($i);
        expect($word->fresh()->needs_review)->toBe($i < 3);
        expect($word->fresh()->review_step)->toBe($i);
        vocabularyCommand($this, $state, 'finish');
    }
    expect($word->fresh()->errors)->toBe(1);
    expect($word->fresh()->next_review_at->diffInDays($word->fresh()->last_reviewed_at, true))->toEqual(7);
    $credit = \App\Models\Learning\VocabularyRunEffect::where('user_id', $word->user_id)->where('lexeme_key', $word->lexeme_key)->sole();
    expect($credit->run_id)->toBe($state['run_id']);
});

it('keeps the run success marker through a later error and cannot increment again in that run', function () {
    $word = vocabularyWord($this);
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->streak)->toBe(1);
    $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'b']]);
    expect($word->fresh()->streak)->toBe(0);
    expect($word->fresh()->review_step)->toBe(0);
    $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->streak)->toBe(0);
    expect($state['presentation']['first_result']['score'])->toBe(1);
});

it('marks only wrong assessed pairs and deduplicates an accepted event before effects', function () {
    foreach (range(0, 3) as $index) {
        vocabularyWord($this, $index)->update(['needs_review' => false]);
    }
    $state = vocabularyStart($this);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'technical_skip', 'reason' => 'audio_unavailable']);
    $state = vocabularyCommand($this, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
    $pairs = $this->payload['tasks'][1]['correct_pairs'];
    [$pairs[0]['right_id'],$pairs[1]['right_id']] = [$pairs[1]['right_id'], $pairs[0]['right_id']];
    $pairs = array_map(fn ($pair) => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $pairs);
    $input = vocabularyInput(['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['pairs' => $pairs]], $state['revision']);
    $receipt = $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input)->assertOk()->json();
    $before = VocabularyEventEffect::count();
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/answers', $input)->assertExactJson($receipt);
    expect(VocabularyEventEffect::count())->toBe($before);
    expect(PersonalVocabulary::where('needs_review', true)->count())->toBe(0);
    $state = $receipt['state'];
    while ($state['position'] < $state['total']) {
        $state = vocabularyCommand($this, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
        $task = $this->payload['tasks'][$state['position'] - 1];
        $answer = match ($task['type']) {
            'single_choice' => ['choice_id' => $task['correct_choice_id']],
            'word_order' => ['token_ids' => $task['accepted_orders'][0]],
            'fill_blank' => $task['input_mode'] === 'choice' ? ['choice_id' => $task['correct_choice_id']] : ['value' => $task['accepted_answers'][0]],
            'short_answer' => ['value' => $task['accepted_answers'][0]],
        };
        $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'],
            'event_type' => 'answer', 'answer' => $answer]);
    }
    vocabularyCommand($this, $state, 'finish');
    expect(PersonalVocabulary::where('errors', 1)->count())->toBe(2);
});

it('uses current lesson material with a stable studied vocabulary source', function () {
    $word = vocabularyWord($this);
    $lesson = vocabularyStart($this);
    $content = $this->payload;
    $content['title'] = 'Обновлённое занятие';
    $content['vocabulary'][0]['fr'] = 'bonjour nouvelle version';
    $content['vocabulary'][0]['example'] = ['fr' => 'Bonjour Léa !', 'ru' => 'Здравствуйте, Леа!'];
    $this->version->update(['payload' => $content, 'checksum' => CanonicalJson::hash($content)]);

    $this->getJson('/daily/api/vocabulary')->assertOk()
        ->assertJsonPath('words.0.snapshot.fr', 'bonjour nouvelle version')
        ->assertJsonPath('words.0.snapshot.example.fr', 'Bonjour Léa !')
        ->assertJsonPath('words.0.sources.0.session_version_id', $this->version->id);
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    expect($state['vocabulary'][0]['fr'])->toBe('bonjour nouvelle version');
    $this->getJson('/daily/api/runs/'.$lesson['run_id'])->assertConflict();
});

it('filters owners words offers self check and handles zero one two and multiple blocks without changing pinned selection', function () {
    $this->getJson('/daily/api/vocabulary/test-plan')->assertOk()->assertJsonPath('total', 0);
    $ids = [];
    for ($i = 0; $i < 12; $i++) {
        $snapshot = $this->payload['vocabulary'][0];
        $snapshot['lexeme_key'] = 'fr:test:'.$i;
        $ids[] = PersonalVocabulary::factory()->studied($snapshot)->create(['user_id' => $this->learner->id, 'needs_review' => $i < 8])->id;
    }
    $self = PersonalVocabulary::factory()->studied(['fr' => 'sans modèle', 'ru' => ['без шаблона'], 'transcription_ru' => 'са(н) модэль'])->create(['user_id' => $this->learner->id, 'needs_review' => true]);
    $this->postJson('/daily/api/runs', vocabularyInput(['kind' => 'vocabulary_test', 'word_ids' => [$self->id]]))->assertStatus(503);
    $plan = $this->getJson('/daily/api/vocabulary/test-plan')->assertOk()->assertJsonPath('total', 12)->assertJsonCount(1, 'self_check_words')->json();
    $first = array_slice($plan['word_ids'], 0, 10);
    expect(PersonalVocabulary::whereIn('id', $first)->where('needs_review', true)->count())->toBe(6); // Only four healthy words: the sufficient-choice cap cannot fill five.
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => $plan['word_ids']]);
    expect($state['total'])->toBe(10);
    expect($state['has_next_test_block'])->toBeTrue();
    $this->patchJson('/daily/api/vocabulary/'.$first[0], ['needs_review' => false])->assertOk();
    expect($this->getJson('/daily/api/runs/'.$state['run_id'])->json('total'))->toBe(10);
    $state = vocabularyComplete($this, $state);
    $next = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'continue_run_id' => $state['run_id']]);
    expect($next['total'])->toBe(2);
    expect($next['has_next_test_block'])->toBeFalse();
    vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$ids[0]]]);
    vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$ids[0], $ids[1]]]);
    $foreign = PersonalVocabulary::factory()->create();
    $this->patchJson('/daily/api/vocabulary/'.$foreign->id, ['needs_review' => true])->assertNotFound();
    $this->postJson('/daily/api/runs', vocabularyInput(['kind' => 'vocabulary_test', 'word_ids' => [$foreign->id]]))->assertNotFound();
    $this->getJson('/daily/api/vocabulary?filter=errors')->assertOk();
    $this->get('/daily/vocabulary')->assertOk();
});

it('rolls back vocabulary effects with the accepting transaction when another integration fails', function () {
    $word = vocabularyWord($this);
    $this->app->bind(RunIntegrations::class, fn () => new class extends RunIntegrations
    {
        public function accepted(LearningRun $run, \App\Models\Learning\LearningAttempt $attempt): void
        {
            parent::accepted($run, $attempt);
            throw new \App\Services\Learning\AnswerCheckingException(\App\Enums\Learning\PracticeErrorCode::Unavailable, 'integration failed');
        }
    });
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/help', vocabularyInput(['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown'], $state['revision']))->assertStatus(503);
    expect($word->fresh()->errors)->toBe(0);
    $this->assertDatabaseCount('vocabulary_event_effects', 0);
    $this->assertDatabaseCount('learning_attempts', 0);
});

it('matches the guest parity fixture for every interval and a success followed by an error in the same run', function () {
    $word = vocabularyWord($this);
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/vocabulary-parity.json')), true);
    $runs = [];
    $presentations = [];
    foreach ($fixture['events'] as $index => $entry) {
        if (! isset($runs[$entry['run']])) {
            $runs[$entry['run']] = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_id' => $this->version->id]);
            $presentations[$entry['run']] = \App\Models\Learning\LearningPresentation::factory()->create(['run_id' => $runs[$entry['run']]->id, 'user_id' => $this->learner->id]);
        }
        $run = $runs[$entry['run']];
        $event = \App\Models\Learning\LearningAttempt::factory()->create(['run_id' => $run->id, 'user_id' => $this->learner->id, 'presentation_id' => $presentations[$entry['run']]->id,
            'is_first_answer' => null, 'sequence' => $index + 1, 'received_at' => \Carbon\CarbonImmutable::parse($entry['at']),
            'result' => ['assessed_lexemes' => [['lexeme_key' => $word->lexeme_key, 'effect' => $entry['kind']]]]]);
        $service = app(\App\Services\Learning\VocabularyProgressService::class);
        \Illuminate\Support\Facades\DB::transaction(function () use ($service, $run, $event) {
            $service->accepted($run, $event);
            $service->accepted($run, $event);
        });
        foreach (['streak', 'errors', 'review_step', 'needs_review'] as $key) {
            expect($word->fresh()->$key)->toBe($entry[$key]);
        }
    }
    expect($word->fresh()->next_review_at->toIso8601String())->toBe('2026-11-30T12:00:00+00:00');
    expect(VocabularyEventEffect::count())->toBe(count($fixture['events']));
});

it('rolls back newly added words sources and effects when finish fails and retries the same ID once', function () {
    $hook = new class extends RunIntegrations
    {
        public bool $fail = true;

        public function finishing(LearningRun $run, array $summary, \Carbon\CarbonImmutable $at): array
        {
            $result = parent::finishing($run, $summary, $at);
            if ($this->fail) {
                throw new \App\Services\Learning\AnswerCheckingException(\App\Enums\Learning\PracticeErrorCode::Unavailable, 'finish failed');
            }

            return $result;
        }
    };
    $this->app->instance(RunIntegrations::class, $hook);
    $state = vocabularyStart($this);
    while (true) {
        $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
        if ($state['position'] === $state['total']) {
            break;
        }
        $state = vocabularyCommand($this, $state, 'advance', ['presentation_id' => $state['presentation']['presentation_id']]);
    }
    $input = vocabularyInput([], $state['revision']);
    $url = '/daily/api/runs/'.$state['run_id'].'/finish';
    $this->postJson($url, $input)->assertStatus(503);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $this->assertDatabaseCount('studied_vocabulary_sources', 0);
    $this->assertDatabaseCount('vocabulary_event_effects', 0);
    $hook->fail = false;
    $receipt = $this->postJson($url, $input)->assertOk()->json();
    $this->postJson($url, $input)->assertExactJson($receipt);
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->assertDatabaseCount('studied_vocabulary_sources', 6);
});


it('keeps two tab runs correct but credits one elapsed-day success across review types', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-03-28T23:00:00Z'));
    $word = vocabularyWord($this);
    $first = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $second = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $first = vocabularyCommand($this, $first, 'answers', ['presentation_id' => $first['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    $this->travel(10)->minutes();
    $request = vocabularyInput(['presentation_id' => $second['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']], $second['revision']);
    $receipt = $this->postJson('/daily/api/runs/'.$second['run_id'].'/answers', $request)->assertOk()->json();
    expect($receipt['state']['presentation']['first_result']['score'])->toBe(1);
    expect($word->fresh()->review_step)->toBe(1);expect($word->fresh()->streak)->toBe(1);
    $this->travelTo(\Carbon\Carbon::parse('2026-03-29T23:00:00Z'));
    $this->postJson('/daily/api/runs/'.$second['run_id'].'/answers', $request)->assertExactJson($receipt);
    $next = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    vocabularyCommand($this, $next, 'answers', ['presentation_id' => $next['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->review_step)->toBe(2);expect($word->fresh()->streak)->toBe(2);
});
