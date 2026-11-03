<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
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
    $this->payload = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true)['sessions'][0];
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC')]);
    $unit = LearningUnit::factory()->create(['published_at' => now('UTC')]);
    $session = LearningSession::factory()->create(['unit_id' => $unit->id, 'external_id' => $this->payload['id']]);
    $this->version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $this->payload, 'checksum' => CanonicalJson::hash($this->payload), 'status' => 'published']);
    $session->update(['active_version_id' => $this->version->id]);
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
    $word = PersonalVocabulary::factory()->create(['user_id' => $test->learner->id, 'lexeme_key' => $snapshot['lexeme_key'], 'snapshot' => $snapshot, 'needs_review' => true]);
    PersonalVocabularySource::factory()->create(['user_id' => $test->learner->id, 'personal_word_id' => $word->id, 'session_version_id' => $test->version->id, 'vocabulary_id' => $snapshot['id'], 'snapshot' => $snapshot]);

    return $word;
}

it('adds studied words only at finish and replays accumulated mistakes once without distractors', function () {
    $state = vocabularyStart($this);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
    $this->assertDatabaseCount('personal_vocabulary', 0);
    $finished = vocabularyComplete($this, $state);
    expect($finished['summary']['words_added'])->toBe(6);
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->assertDatabaseCount('personal_vocabulary_sources', 6);
    $word = PersonalVocabulary::where('lexeme_key', $this->payload['vocabulary'][0]['lexeme_key'])->sole();
    expect($word->needs_review)->toBeTrue();
    expect($word->errors)->toBeGreaterThan(0);
    $errors = $word->errors;
    vocabularyCommand($this, $finished, 'finish');
    expect($word->fresh()->errors)->toBe($errors);
    $this->assertDatabaseCount('personal_vocabulary_sources', 6);
    expect(PersonalVocabulary::where('lexeme_key', 'fr:merci:thanks')->exists())->toBeFalse();
});

it('updates existing words immediately, ignores help for streak, and accepts three independent runs', function () {
    $word = vocabularyWord($this);
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown']);
    expect($word->fresh()->errors)->toBe(1);
    $state = vocabularyCommand($this, $state, 'help', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'help', 'help_kind' => 'reference']);
    $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
    expect($word->fresh()->streak)->toBe(0);
    vocabularyCommand($this, $state, 'finish');
    for ($i = 1; $i <= 3; $i++) {
        $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
        $state = vocabularyCommand($this, $state, 'answers', ['presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => 'a']]);
        expect($word->fresh()->streak)->toBe($i);
        expect($word->fresh()->needs_review)->toBe($i < 3);
        expect($word->fresh()->review_step)->toBe($i);
        vocabularyCommand($this, $state, 'finish');
    }
    expect($word->fresh()->errors)->toBe(1);
    expect($word->fresh()->next_review_at->diffInDays($word->fresh()->last_reviewed_at, true))->toEqual(7);
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
    expect(PersonalVocabulary::where('needs_review', true)->count())->toBe(2);
    expect(PersonalVocabulary::where('errors', 1)->count())->toBe(2);
});

it('preserves studied source examples after archive and chooses the latest studied source rather than a new publication', function () {
    $word = vocabularyWord($this);
    $later = $this->payload;
    $later['version'] = 2;
    $later['vocabulary'][0]['fr'] = 'bonjour nouvelle version';
    $new = LearningSessionVersion::factory()->create(['session_id' => $this->version->session_id, 'version' => 2, 'payload' => $later, 'checksum' => CanonicalJson::hash($later), 'status' => 'published']);
    $this->version->session->update(['active_version_id' => $new->id, 'archived_at' => now()]);
    $this->getJson('/daily/api/vocabulary')->assertOk()->assertJsonPath('words.0.snapshot.fr', 'bonjour');
    $state = vocabularyStart($this, ['kind' => 'vocabulary_test', 'session_id' => null, 'word_ids' => [$word->id]]);
    expect($state['vocabulary'][0]['fr'])->toBe('bonjour');
    $source = $later['vocabulary'][0];
    $source['example'] = ['fr' => 'Bonjour Léa !', 'ru' => 'Здравствуйте, Леа!', 'transcription_ru' => 'бо(н)жур леа́'];
    PersonalVocabularySource::factory()->create(['user_id' => $this->learner->id, 'personal_word_id' => $word->id, 'session_version_id' => $new->id, 'snapshot' => $source, 'first_added_at' => now()->addMinute()]);
    $this->getJson('/daily/api/vocabulary')->assertJsonPath('words.0.snapshot.fr', 'bonjour nouvelle version')->assertJsonPath('words.0.snapshot.example.fr', 'Bonjour Léa !');
    expect($this->getJson('/daily/api/runs/'.$state['run_id'])->json('vocabulary.0.fr'))->toBe('bonjour');
});

it('filters owners words offers self check and handles zero one two and multiple blocks without changing pinned selection', function () {
    $this->getJson('/daily/api/vocabulary/test-plan')->assertOk()->assertJsonPath('total', 0);
    $ids = [];
    for ($i = 0; $i < 12; $i++) {
        $snapshot = $this->payload['vocabulary'][0];
        $snapshot['lexeme_key'] = 'fr:test:'.$i;
        $ids[] = PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'lexeme_key' => $snapshot['lexeme_key'], 'snapshot' => $snapshot, 'needs_review' => $i < 8])->id;
    }
    $self = PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'snapshot' => ['fr' => 'sans modèle', 'ru' => ['без шаблона'], 'transcription_ru' => 'са(н) модэль'], 'needs_review' => true]);
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
            $runs[$entry['run']] = LearningRun::factory()->create(['user_id' => $this->learner->id, 'session_version_id' => $this->version->id]);
            $presentations[$entry['run']] = \App\Models\Learning\LearningPresentation::factory()->create(['run_id' => $runs[$entry['run']]->id, 'user_id' => $this->learner->id]);
        }
        $run = $runs[$entry['run']];
        $event = \App\Models\Learning\LearningAttempt::factory()->create(['run_id' => $run->id, 'user_id' => $this->learner->id, 'presentation_id' => $presentations[$entry['run']]->id,
            'is_first_answer' => null, 'sequence' => $index + 1, 'received_at' => \Carbon\CarbonImmutable::parse('2026-10-25T12:00:00Z'),
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
    expect($word->fresh()->next_review_at->toIso8601String())->toBe('2026-11-24T12:00:00+00:00');
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
    $this->assertDatabaseCount('personal_vocabulary_sources', 0);
    $this->assertDatabaseCount('vocabulary_event_effects', 0);
    $hook->fail = false;
    $receipt = $this->postJson($url, $input)->assertOk()->json();
    $this->postJson($url, $input)->assertExactJson($receipt);
    $this->assertDatabaseCount('personal_vocabulary', 6);
    $this->assertDatabaseCount('personal_vocabulary_sources', 6);
});
