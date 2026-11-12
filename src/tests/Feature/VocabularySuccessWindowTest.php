<?php

use App\Models\Learning\GuestEventMapping;
use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningRun;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\VocabularyEventEffect;
use App\Models\Learning\VocabularyRunEffect;
use App\Models\User\User;
use App\Services\Learning\GuestVocabularyProjector;
use App\Services\Learning\VocabularyProgressService;
use App\Services\Learning\VocabularySuccessWindow;
use Carbon\CarbonImmutable;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
    $this->learner = User::factory()->create();
    $this->word = PersonalVocabulary::factory()->create(['user_id' => $this->learner->id, 'lexeme_key' => 'fr:bonjour:greeting', 'needs_review' => true,
        'snapshot' => ['id' => 'bonjour', 'lexeme_key' => 'fr:bonjour:greeting', 'fr' => 'bonjour'],
        'streak' => 0, 'errors' => 0, 'review_step' => 0, 'next_review_at' => '2026-03-28 23:00:00']);
});

function windowAttempt($test, LearningRun $run, string $at, string $kind, int $sequence = 1): LearningAttempt
{
    $presentation = LearningPresentation::factory()->create(['user_id' => $test->learner->id, 'run_id' => $run->id, 'position' => $sequence]);

    return LearningAttempt::factory()->create(['user_id' => $test->learner->id, 'run_id' => $run->id, 'presentation_id' => $presentation->id,
        'is_first_answer' => null, 'sequence' => $sequence, 'received_at' => CarbonImmutable::parse($at),
        'result' => ['assessed_lexemes' => [['lexeme_key' => $test->word->lexeme_key, 'effect' => $kind]]]]);
}

it('matches the 19-case guest fixture across run kinds exact boundaries errors and idempotent delivery', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/Learning/vocabulary-window-parity.json')), true);
    $runs = [];
    foreach ($fixture['events'] as $index => $entry) {
        $runs[$entry['run']] ??= LearningRun::factory()->create(['user_id' => $this->learner->id, 'kind' => $entry['run_kind']]);
        $run = $runs[$entry['run']];
        $event = windowAttempt($this, $run, $entry['at'], $entry['kind'], $index + 1);
        DB::transaction(function () use ($run, $event) {
            app(VocabularyProgressService::class)->accepted($run, $event);
            app(VocabularyProgressService::class)->accepted($run, $event);
        });
        $word = $this->word->fresh();
        foreach (['streak', 'review_step', 'needs_review', 'errors'] as $key) {
            expect($word->$key)->toBe($entry[$key]);
        }
        expect(VocabularyEventEffect::where('event_id', $event->id)->sole()->effect['success_applied'])->toBe($entry['success_applied']);
        expect(VocabularySuccessWindow::latest($word)?->toIso8601String())->toBe(CarbonImmutable::parse($entry['last_independent_success_at'])->toIso8601String());
        expect($word->next_review_at->toIso8601String())->toBe(CarbonImmutable::parse($entry['next_review_at'])->toIso8601String());
    }
    $this->assertDatabaseCount('vocabulary_event_effects', 19);
});

it('uses reliable historical credits without resetting mastered progress or substituting the last view', function (bool $hasCredit) {
    $at = CarbonImmutable::parse('2026-11-06T12:00:00Z');
    $this->travelTo(\Carbon\Carbon::parse($at));
    $this->word->update(['review_step' => 4, 'streak' => 3, 'needs_review' => false, 'last_reviewed_at' => $at,
        'next_review_at' => $at->addDays(14)]);
    if ($hasCredit) {
        $old = LearningRun::factory()->create(['user_id' => $this->learner->id]);
        VocabularyRunEffect::create(['user_id' => $this->learner->id, 'lexeme_key' => $this->word->lexeme_key, 'run_id' => $old->id,
            'success_applied' => true, 'applied_at' => $at->subMinutes(10)]);
    }
    $run = LearningRun::factory()->create(['user_id' => $this->learner->id]);
    $event = windowAttempt($this, $run, $at->toIso8601String(), 'success');
    DB::transaction(fn () => app(VocabularyProgressService::class)->accepted($run, $event));
    expect($this->word->fresh()->review_step)->toBe($hasCredit ? 4 : 5);
    expect($this->word->fresh()->streak)->toBe(3);
    expect(VocabularyEventEffect::where('event_id', $event->id)->sole()->effect['success_applied'])->toBe(! $hasCredit);
})->with([true, false]);

it('retains pre-rule imported credits while new projector effects obey the window and do not reapply', function () {
    $at = CarbonImmutable::parse('2026-11-06T12:00:00Z');
    $this->travelTo(\Carbon\Carbon::parse($at));
    $this->word->update(['native_revision' => 0, 'first_added_at' => $at->subDay(), 'review_step' => 2, 'streak' => 2,
        'last_reviewed_at' => $at->addMinutes(5), 'next_review_at' => $at->addMinutes(5)->addDays(3)]);
    $episode = GuestSyncEpisode::create(['guest_id' => (string) Str::uuid(), 'owner_user_id' => $this->learner->id, 'status' => 'pending',
        'baseline_profile_revision' => null, 'baseline_current_level' => null, 'baseline_snapshot' => ['word_ids' => []],
        'created_word_ids' => [$this->word->id], 'accepted_level_event_ids' => [], 'started_at' => $at]);
    $events = [];
    foreach ([0, 5, 10] as $index => $minutes) {
        $run = LearningRun::factory()->create(['user_id' => $this->learner->id, 'snapshot' => ['vocabulary' => [$this->word->snapshot]]]);
        $event = windowAttempt($this, $run, $at->addMinutes($minutes)->toIso8601String(), 'success');
        $events[] = $event;
        if ($index < 2) {
            VocabularyRunEffect::create(['user_id' => $this->learner->id, 'lexeme_key' => $this->word->lexeme_key, 'run_id' => $run->id,
                'success_applied' => true, 'applied_at' => $event->received_at]);
            VocabularyEventEffect::create(['user_id' => $this->learner->id, 'event_id' => $event->id, 'personal_word_id' => $this->word->id,
                'lexeme_key' => $this->word->lexeme_key, 'effect' => ['kind' => 'success', 'success_applied' => true], 'applied_at' => $event->received_at]);
        }
        foreach (['answer', 'finish'] as $position => $action) {
            GuestEventMapping::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $this->learner->id,
                'entity_type' => 'event', 'guest_entity_id' => (string) Str::uuid(), 'server_run_id' => $run->id,
                'server_event_id' => $action === 'answer' ? $event->id : null, 'receipt' => ['sequence' => $index * 2 + $position + 1, 'action' => $action]]);
        }
    }
    DB::transaction(fn () => app(GuestVocabularyProjector::class)->project($episode));
    $word = $this->word->fresh();
    expect($word->review_step)->toBe(2);expect($word->streak)->toBe(2);
    expect($word->next_review_at->toIso8601String())->toBe($at->addMinutes(5)->addDays(3)->toIso8601String());
    expect(VocabularyEventEffect::where('event_id', $events[2]->id)->sole()->effect['success_applied'])->toBeFalse();
    $before = $word->getAttributes();
    DB::transaction(fn () => app(GuestVocabularyProjector::class)->project($episode));
    expect($word->fresh()->getAttributes())->toBe($before);
    expect(VocabularyRunEffect::where('success_applied', true)->count())->toBe(2);
});
