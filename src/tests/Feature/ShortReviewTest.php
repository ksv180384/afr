<?php

use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\ShortReviewSelection;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['daily_practice.enabled' => true]);
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();$this->actingAs($this->learner);
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T12:00:00Z'));
    $this->learner->update(['timezone' => 'Europe/Moscow']);
    LearningProfile::factory()->create(['user_id' => $this->learner->id, 'current_level' => 'B1']);
});
function changeShortWord(PersonalVocabulary $word, array $content): void
{
    $lesson = $word->sources()->firstOrFail()->session;
    $payload = $lesson->payload;
    $payload['vocabulary'] = [$content];
    $lesson->update(['payload' => $payload, 'checksum' => \App\Services\Learning\Validation\CanonicalJson::hash($payload)]);
    $word->refresh();
}

function shortWord($test, int $index = 0, array $attributes = []): PersonalVocabulary
{
    $snapshot = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true)['sessions'][0]['vocabulary'][0];
    $snapshot['lexeme_key'] = 'fr:short:'.$index;
    $context = $snapshot['review_items'][0];$context['id'] = 'misleading-meaning-'.$index;
    $context['prompt'] = ['text' => 'Bonjour, je suis Marie.', 'text_lang' => 'fr', 'transcription_ru' => 'бо(н)жур, жё сюи мари'];
    $snapshot['review_items'][] = $context;
    return PersonalVocabulary::factory()->studied($snapshot)->create(['user_id' => $test->learner->id,
        'needs_review' => false, 'next_review_at' => now('UTC')->subHour(), ...$attributes]);
}
function shortStart($test, string $mode = 'manual'): array
{
    return $test->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'review', 'review_mode' => $mode])->assertOk()->json('state');
}
function finishShort($test, array $state): array
{
    $snapshot = LearningRun::findOrFail($state['run_id'])->snapshot;
    foreach ($snapshot['items'] as $index => $item) {
        $state = $test->postJson('/daily/api/runs/'.$state['run_id'].'/answers', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'],
            'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer', 'answer' => ['choice_id' => $item['task']['correct_choice_id']]])->assertOk()->json('state');
        if ($index < count($snapshot['items']) - 1) {
            $state = $test->postJson('/daily/api/runs/'.$state['run_id'].'/advance', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'], 'presentation_id' => $state['presentation']['presentation_id']])->assertOk()->json('state');
        }
    }
    return $test->postJson('/daily/api/runs/'.$state['run_id'].'/finish', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision']])->assertOk()->json('state');
}

it('counts zero one two three and many owned gradable words without inferring knowledge from B1', function ($count) {
    foreach (range(1, $count ?: 1) as $i) { if ($count) { shortWord($this, $i); } }
    PersonalVocabulary::factory()->create();
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertOk()->assertJsonPath('total', min(3, $count));
    $today = $this->getJson('/daily/api/today')->assertOk();
    if (! $count) { $today->assertJsonPath('review_suggestion', null);$this->assertDatabaseCount('learning_runs', 0); }
    else { expect(shortStart($this, 'suggested')['total'])->toBe(min(3, $count)); }
})->with([0, 1, 2, 3, 9]);

it('matches guest priority diversity and rotation fixtures and classifies content instead of IDs', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/short-review-parity.json')), true);
    foreach ($fixture['cases'] as $case) { expect(array_column(ShortReviewSelection::select($case['rows'], $case['previous']), 'key'))->toBe($case['expected']); }
    $word = shortWord($this);
    expect(ShortReviewSelection::category($word->sources->first()->entry->content()['review_items'][0], $word->sources->first()->entry->content()))->toBe('meaning');
    expect(ShortReviewSelection::category($word->sources->first()->entry->content()['review_items'][1], $word->sources->first()->entry->content()))->toBe('context');
});

it('filters missing questions before counting and rechecks changes between card and start', function () {
    $word = shortWord($this);$good = shortWord($this, 1);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 2);
    $snapshot = $word->sources->first()->entry->content();$snapshot['review_items'] = [];changeShortWord($word, $snapshot);
    expect(shortStart($this, 'suggested')['total'])->toBe(1);
    $this->getJson('/daily/api/vocabulary')->assertOk()->assertJsonPath('pagination.total', 2);
});

it('offers no automatic grade for self-check-only material and permits manual practice before due time', function () {
    $word = shortWord($this, 0, ['next_review_at' => now('UTC')->addDays(10)]);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 0);
    $manual = $this->getJson('/daily/api/vocabulary/review-plan?mode=manual')->assertJsonPath('total', 1)->assertJsonPath('includes_not_due', true);
    $state = shortStart($this);expect($state['session']['description'])->toContain('ещё не наступил');
    finishShort($this, $state);
    $snapshot = $word->sources->first()->entry->content();$snapshot['review_items'] = [['id' => 'broken', 'type' => 'single_choice', 'prompt' => ['text' => 'bonjour'], 'choices' => []]];changeShortWord($word, $snapshot);
    $this->getJson('/daily/api/vocabulary/review-plan')->assertJsonPath('total', 0)->assertJsonPath('self_check_count', 1);
    $this->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'review'])->assertStatus(503);
});

it('suppresses successful blocks today but keeps manual next blocks distinct and snapshots pinned', function () {
    for ($i = 0; $i < 6; $i++) { shortWord($this, $i); }
    $first = shortStart($this, 'suggested');$run = LearningRun::find($first['run_id']);$keys = array_column($run->snapshot['vocabulary'], 'lexeme_key');
    $word = PersonalVocabulary::where('lexeme_key', $keys[0])->sole();
    $content = $word->sources->first()->entry->content();
    $content['example'] = ['fr' => 'Un autre exemple', 'ru' => 'Другой пример'];
    changeShortWord($word, $content);
    $this->getJson('/daily/api/runs/'.$run->id)->assertOk()->assertJsonPath('task.id', $first['task']['id']);
    finishShort($this, $first);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 0)->assertJsonPath('completed_today', true);
    $second = shortStart($this);$newKeys = array_column(LearningRun::find($second['run_id'])->snapshot['vocabulary'], 'lexeme_key');expect(array_intersect($keys, $newKeys))->toBe([]);
});

it('keeps active lessons unchanged and obeys disabled or snoozed suggestions', function () {
    shortWord($this);
    $profile = LearningProfile::where('user_id', $this->learner->id)->sole();$profile->update(['review_suggestions_enabled' => false]);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 0);
    $this->getJson('/daily/api/vocabulary/review-plan')->assertJsonPath('total', 1);
    $profile->update(['review_suggestions_enabled' => true, 'review_suggestions_snoozed_until' => now('UTC')->addHours(2)]);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 0);
    $active = LearningRun::factory()->create(['user_id' => $this->learner->id, 'kind' => 'extra', 'status' => 'in_progress']);$before = $active->fresh()->getAttributes();
    $this->getJson('/daily/api/vocabulary/review-plan')->assertJsonPath('total', 0)->assertJsonPath('active_run_id', $active->id);
    $this->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'review'])->assertUnprocessable();
    expect($active->fresh()->getAttributes())->toBe($before);$this->assertDatabaseCount('learning_runs', 1);
});

it('keeps both an unfinished vocabulary test and an unfinished review available to resume', function () {
    $word = shortWord($this);
    $test = $this->postJson('/daily/api/runs', ['client_event_id' => (string) Str::uuid(), 'expected_revision' => 1,
        'kind' => 'vocabulary_test', 'word_ids' => [$word->id]])->assertOk()->json('state');
    $this->postJson('/daily/api/runs/'.$test['run_id'].'/pause', ['client_event_id' => (string) Str::uuid(),
        'expected_revision' => $test['revision']])->assertOk();
    $review = shortStart($this, 'suggested');

    $this->getJson('/daily/api/today')->assertOk()
        ->assertJsonPath('active_run.id', $review['run_id'])
        ->assertJsonPath('vocabulary_runs.review', $review['run_id'])
        ->assertJsonPath('vocabulary_runs.vocabulary_test', $test['run_id'])
        ->assertJsonPath('review_suggestion', null);
    $this->assertDatabaseCount('learning_runs', 2);
});


it('closes a paused review when its remaining question disappears', function () {
    $word = shortWord($this);
    $state = shortStart($this);
    $this->postJson('/daily/api/runs/'.$state['run_id'].'/pause', ['client_event_id' => (string) Str::uuid(),
        'expected_revision' => $state['revision']])->assertOk();
    $content = $word->sources->first()->entry->content();
    $content['review_items'] = [];
    changeShortWord($word, $content);
    $this->getJson('/daily/api/runs/'.$state['run_id'])->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('task', null);
    $this->getJson('/daily/api/vocabulary/review-plan')->assertOk()->assertJsonPath('total', 0);
});

it('keeps accepted answers and vocabulary progress when a later review question disappears', function () {
    shortWord($this, 1);
    shortWord($this, 2);
    $state = shortStart($this);
    $run = LearningRun::findOrFail($state['run_id']);
    [$first, $second] = $run->snapshot['items'];
    $event = ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'],
        'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'answer',
        'answer' => ['choice_id' => $first['task']['correct_choice_id']]];
    $url = '/daily/api/runs/'.$run->id;
    $receipt = $this->postJson($url.'/answers', $event)->assertOk()->json('attempt');
    $answered = PersonalVocabulary::where('lexeme_key', $first['review_lexeme_key'])->sole();
    $progress = $answered->getAttributes();
    $remaining = PersonalVocabulary::where('lexeme_key', $second['review_lexeme_key'])->sole();
    $content = $remaining->sources->first()->entry->content();
    $content['review_items'] = [];
    changeShortWord($remaining, $content);
    $this->getJson($url)->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('session.title', $state['session']['title']);
    expect($run->refresh()->snapshot)->toBe(['title' => $state['session']['title']]);
    $operations = LearningRunOperation::where('run_id', $run->id)->get();
    expect($operations)->toHaveCount(2);
    foreach ($operations as $operation) {
        expect($operation->getRawOriginal('receipt'))->toBeNull()
            ->and($operation->receipt)->toBe(['run_id' => $run->id]);
    }
    $this->postJson($url.'/answers', $event)->assertOk()->assertJsonPath('state.status', 'cancelled')
        ->assertJsonPath('record_sequence', $operations->firstWhere('operation', 'answer')->id);
    $this->postJson($url.'/answers', [...$event, 'expected_revision' => $event['expected_revision'] + 1])->assertConflict();
    $attempt = $run->attempts()->sole();
    expect($attempt->payload)->toBe(['answer' => $event['answer']])
        ->and($attempt->result)->not->toHaveKeys(['feedback', 'solution', 'pairs', 'objective_ids'])
        ->and($attempt->result['correct'])->toBeTrue()
        ->and($attempt->result['score'])->toBe(1)
        ->and($attempt->result['assessed_lexemes'])->toBe($receipt['result']['assessed_lexemes'])
        ->and($attempt->vocabularyEffects()->sole()->effect['success_applied'])->toBeTrue()
        ->and($answered->refresh()->getAttributes())->toBe($progress);
});

it('uses the completion day rather than the start day across local midnight', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T20:59:00Z'));shortWord($this);
    $state = shortStart($this, 'suggested');
    $this->travelTo(Carbon\Carbon::parse('2026-11-06T21:01:00Z'));finishShort($this, $state);shortWord($this, 2);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('total', 0)->assertJsonPath('completed_today', true);
    $this->travelTo(Carbon\Carbon::parse('2026-11-07T21:01:00Z'));
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertJsonPath('completed_today', false);
    expect($this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->json('total'))->toBeGreaterThan(0);
});


it('alternates meaning and context for a single manual word using content rather than suffixes', function () {
    $word = shortWord($this);$first = shortStart($this);
    $item = LearningRun::find($first['run_id'])->snapshot['items'][0]['task'];expect(ShortReviewSelection::category($item, $word->sources->first()->entry->content()))->toBe('context');
    $finished = finishShort($this, $first);
    $run = LearningRun::findOrFail($first['run_id']);
    expect(json_decode($run->getRawOriginal('snapshot'), true)[0])->toBe('rs1')
        ->and(json_decode($run->getRawOriginal('result'), true)[0])->toBe('vr1');
    $this->getJson('/daily/api/history')->assertOk()->assertJsonPath('data.0.id', $run->id)
        ->assertJsonPath('data.0.result.independent_correct_steps', $finished['summary']['independent_correct_steps']);
    $second = shortStart($this);
    $next = LearningRun::find($second['run_id'])->snapshot['items'][0]['task'];expect(ShortReviewSelection::category($next, $word->sources->first()->entry->content()))->toBe('meaning');
    expect($next['id'])->not->toBe($item['id']);
});

it('budgets time for filtered audio and keeps the manual entry after a helped finish', function () {
    $word = shortWord($this, 0, ['needs_review' => true]);
    shortWord($this, 1, ['next_review_at' => now('UTC')->addDay()]);
    $snapshot = $word->sources->first()->entry->content();
    $snapshot['review_items'][1]['prompt']['audio'] = ['text' => str_repeat('Bonjour ', 90), 'lang' => 'fr-FR', 'asset_id' => null];
    changeShortWord($word, $snapshot);
    $plan = $this->getJson('/daily/api/vocabulary/review-plan?mode=manual&filter=errors')
        ->assertOk()->assertJsonPath('word_ids', [$word->id])->json();
    expect($plan['estimated_seconds'])->toBeGreaterThanOrEqual(150);
    $this->getJson('/daily/api/vocabulary/review-plan?filter=due')->assertOk()->assertJsonPath('word_ids', [$word->id]);
    $state = $this->postJson('/daily/api/runs', [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => 1, 'kind' => 'review', 'word_ids' => [$word->id],
    ])->assertOk()->assertJsonPath('state.session.duration_minutes', (int) ceil($plan['estimated_seconds'] / 60))->json('state');
    $run = '/daily/api/runs/'.$state['run_id'];
    $helped = $this->postJson($run.'/help', [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => $state['revision'],
        'presentation_id' => $state['presentation']['presentation_id'], 'event_type' => 'unknown',
    ])->assertOk()->json('state');
    $this->postJson($run.'/finish', [
        'client_event_id' => (string) Str::uuid(), 'expected_revision' => $helped['revision'],
    ])->assertOk();
    $this->getJson('/daily/api/vocabulary/review-plan?mode=suggested')->assertOk()
        ->assertJsonPath('finished_today', true)->assertJsonPath('completed_today', false)->assertJsonPath('total', 0);
    $this->getJson('/daily/api/today')->assertJsonPath('review_suggestion', null);
    $this->getJson('/daily/api/vocabulary/review-plan?mode=manual')->assertJsonPath('total', 1);
});
