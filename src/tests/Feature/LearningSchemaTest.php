<?php

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Enums\Learning\RunStatus;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\DailyAssignment;
use App\Models\Learning\GuestEventMapping;
use App\Models\Learning\GuestSyncBatch;
use App\Models\Learning\GuestSyncEpisode;
use App\Models\Learning\LearningActivityDay;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningCurriculumEnrollment;
use App\Models\Learning\LearningEditorialEvent;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningLevelEvent;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningObjectiveEvidence;
use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\LearningUrlAlias;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PersonalVocabularySource;
use App\Models\Learning\SessionImportBatch;
use App\Models\Learning\VocabularyEventEffect;
use App\Models\Learning\VocabularyRunEffect;
use App\Models\User\User;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(LearningLevelSeeder::class);
});

function schemaAttempt(User $user, ?LearningSessionVersion $version = null): LearningAttempt
{
    $run = LearningRun::factory()->create([
        'user_id' => $user->id,
        ...($version ? ['session_version_id' => $version->id] : []),
    ]);
    $presentation = LearningPresentation::factory()->create(['run_id' => $run->id, 'user_id' => $user->id]);

    return LearningAttempt::factory()->create(['presentation_id' => $presentation->id]);
}

function schemaEpisode(User $user): GuestSyncEpisode
{
    return GuestSyncEpisode::create([
        'guest_id' => (string) Str::uuid(),
        'owner_user_id' => $user->id,
        'baseline_profile_revision' => 1,
        'baseline_current_level' => 'PRE_A1',
        'started_at' => now('UTC'),
    ]);
}

function schemaBatch(GuestSyncEpisode $episode, ?string $batchId = null): GuestSyncBatch
{
    return GuestSyncBatch::create([
        'episode_id' => $episode->id,
        'guest_id' => $episode->guest_id,
        'owner_user_id' => $episode->owner_user_id,
        'batch_id' => $batchId ?? (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'same-events'),
        'received_at' => now('UTC'),
        'receipt' => ['accepted' => true],
    ]);
}

function schemaEvidence(LearningAttempt $event, LearningCurriculumEnrollment $enrollment, LearningObjective $objective): array
{
    $version = $event->run->sessionVersion;

    return [
        'user_id' => $event->user_id,
        'enrollment_id' => $enrollment->id,
        'objective_id' => $objective->id,
        'event_id' => $event->id,
        'run_id' => $event->run_id,
        'session_id' => $version->session_id,
        'session_version_id' => $version->id,
        'task_id' => 'Q01',
        'skill' => 'vocabulary',
        'eligible' => true,
        'occurred_at' => now('UTC'),
    ];
}

it('seeds seven ordered levels idempotently without users or overwriting editorial descriptions', function () {
    $level = LearningLevel::query()->where('code', 'PRE_A1')->firstOrFail();
    $level->update(['title' => 'Редакторское название', 'summary' => 'Сохранённое описание']);
    $this->seed(LearningLevelSeeder::class);
    $this->seed(LearningLevelSeeder::class);

    expect(LearningLevel::query()->orderBy('sort_order')->pluck('code')->all())->toBe(array_column(LevelCode::cases(), 'value'))
        ->and($level->fresh()->title)->toBe('Редакторское название')
        ->and($level->fresh()->summary)->toBe('Сохранённое описание')
        ->and(User::query()->count())->toBe(0);
});

it('creates a unique account profile with PRE_A1 and no initial level', function () {
    $user = User::factory()->create();
    $profile = LearningProfile::create(['user_id' => $user->id])->refresh();

    expect($profile->current_level)->toBe(LevelCode::PreA1)
        ->and($profile->timezone)->toBe('UTC')
        ->and($profile->revision)->toBe(1)
        ->and($profile->user->is($user))->toBeTrue()
        ->and(Schema::hasColumn('learning_profiles', 'initial_level'))->toBeFalse();

    expect(fn () => LearningProfile::create(['user_id' => $user->id]))->toThrow(QueryException::class);
});

it('requires account ownership rather than creating anonymous server users', function () {
    expect(fn () => LearningProfile::create(['user_id' => null]))->toThrow(QueryException::class);
    expect(fn () => LearningRun::factory()->create(['user_id' => null]))->toThrow(QueryException::class);
    expect(User::query()->count())->toBe(0);
});

it('supports two runs and two studied sources for one personal lexeme', function () {
    $owner = User::factory()->create();
    $first = LearningRun::factory()->create(['user_id' => $owner->id]);
    $second = LearningRun::factory()->create(['user_id' => $owner->id, 'session_version_id' => $first->session_version_id]);
    $word = PersonalVocabulary::factory()->create(['user_id' => $owner->id, 'lexeme_key' => 'fr:bonjour:greeting']);
    PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id, 'session_version_id' => $first->session_version_id]);
    PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id]);

    expect($first->id)->not->toBe($second->id)
        ->and($word->sources()->count())->toBe(2)
        ->and($first->sessionVersion->runs()->count())->toBe(2)
        ->and(PersonalVocabulary::query()->count())->toBe(1);
});

it('enforces unique version numbers and active versions belonging to the same session', function () {
    $version = LearningSessionVersion::factory()->create();
    $session = $version->session;
    $session->update(['active_version_id' => $version->id]);
    expect($session->fresh()->activeVersion->is($version))->toBeTrue();

    expect(fn () => LearningSessionVersion::factory()->create(['session_id' => $session->id, 'version' => 1]))->toThrow(QueryException::class);
    $other = LearningSessionVersion::factory()->create();
    expect(fn () => $session->update(['active_version_id' => $other->id]))->toThrow(QueryException::class);
});

it('rejects units and curricula from another level at the database boundary', function () {
    $unit = LearningUnit::factory()->create(['level' => 'A1']);
    expect(fn () => LearningSession::factory()->create(['unit_id' => $unit->id, 'level' => 'PRE_A1']))->toThrow(QueryException::class);
    $curriculum = LearningCurriculum::factory()->create(['level' => 'A1']);
    expect(fn () => LearningCurriculumEnrollment::factory()->create(['curriculum_version_id' => $curriculum->id, 'level' => 'PRE_A1']))->toThrow(QueryException::class);
});

it('preserves pinned runs and vocabulary when publishing and archiving content versions', function () {
    $owner = User::factory()->create();
    $old = LearningSessionVersion::factory()->create(['status' => 'published', 'published_at' => now('UTC')]);
    $run = LearningRun::factory()->create(['user_id' => $owner->id, 'session_version_id' => $old->id]);
    $source = PersonalVocabularySource::factory()->create(['session_version_id' => $old->id]);
    $new = LearningSessionVersion::factory()->create(['session_id' => $old->session_id, 'version' => 2, 'status' => 'published', 'published_at' => now('UTC'), 'payload' => ['fixture' => 'v2']]);
    $old->session->update(['active_version_id' => $new->id]);
    $old->update(['status' => 'archived', 'archived_at' => now('UTC')]);

    expect($run->fresh()->sessionVersion->id)->toBe($old->id)
        ->and($run->fresh()->snapshot)->toBe(['fixture' => true])
        ->and($source->fresh()->sessionVersion->status)->toBe(SessionVersionStatus::Archived)
        ->and($old->session->fresh()->activeVersion->id)->toBe($new->id);
    expect(fn () => $old->delete())->toThrow(LogicException::class);
    expect(fn () => LearningSessionVersion::query()->whereKey($old->id)->delete())->toThrow(QueryException::class);
    expect(fn () => $old->session->delete())->toThrow(QueryException::class);
});

it('guards imported payloads and started snapshots against in-place mutation', function () {
    $version = LearningSessionVersion::factory()->create();
    expect(fn () => $version->update(['payload' => ['changed' => true]]))->toThrow(LogicException::class);
    $version = $version->fresh();
    $version->update(['status' => 'reviewed', 'editor_notes' => 'Проверено']);
    expect($version->status)->toBe(SessionVersionStatus::Reviewed);

    $run = LearningRun::factory()->create();
    expect(fn () => $run->update(['checking_policy_version' => 2]))->toThrow(LogicException::class);
    $presentation = LearningPresentation::factory()->create();
    expect(fn () => $presentation->update(['choice_order' => ['b', 'a']]))->toThrow(LogicException::class);
    $source = PersonalVocabularySource::factory()->create();
    expect(fn () => $source->update(['snapshot' => ['changed' => true]]))->toThrow(LogicException::class);
    $enrollment = LearningCurriculumEnrollment::factory()->create();
    expect(fn () => $enrollment->update(['promotion_policy_version' => 2]))->toThrow(LogicException::class);
});

it('does not change a completed result or a published curriculum snapshot', function () {
    $run = LearningRun::factory()->create();
    $run->update(['status' => 'completed', 'finished_at' => now('UTC'), 'result' => ['correct' => 4, 'total' => 6]]);
    expect($run->status)->toBe(RunStatus::Completed);
    expect(fn () => $run->update(['result' => ['correct' => 6, 'total' => 6]]))->toThrow(LogicException::class);
    $curriculum = LearningCurriculum::factory()->create(['status' => 'published', 'published_at' => now('UTC')]);
    expect(fn () => $curriculum->update(['required_objective_ids' => ['NEW']]))->toThrow(LogicException::class);
});

it('rejects cross-account links even with otherwise valid entity IDs', function (string $kind) {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $attempt = schemaAttempt($owner);
    $word = PersonalVocabulary::factory()->create(['user_id' => $owner->id]);
    $operation = match ($kind) {
        'assignment' => fn () => DailyAssignment::create(['user_id' => $other->id, 'local_date' => '2026-10-24', 'selected_run_id' => $attempt->run_id]),
        'presentation' => fn () => LearningPresentation::factory()->create(['run_id' => $attempt->run_id, 'user_id' => $other->id, 'position' => 2]),
        'attempt' => fn () => LearningAttempt::factory()->create(['presentation_id' => $attempt->presentation_id, 'user_id' => $other->id, 'sequence' => 2, 'is_first_answer' => null]),
        'source' => fn () => PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id, 'user_id' => $other->id]),
        'effect' => fn () => VocabularyEventEffect::create(['user_id' => $other->id, 'event_id' => $attempt->id, 'personal_word_id' => $word->id, 'lexeme_key' => $word->lexeme_key, 'effect' => [], 'applied_at' => now('UTC')]),
    };
    expect($operation)->toThrow(QueryException::class);
})->with(['assignment', 'presentation', 'attempt', 'source', 'effect']);

it('deduplicates client events first answers and ordered events without blocking help or corrections', function () {
    $owner = User::factory()->create();
    $first = schemaAttempt($owner);
    $attributes = ['presentation_id' => $first->presentation_id, 'is_first_answer' => null];
    $help = LearningAttempt::factory()->create([...$attributes, 'sequence' => 2, 'event_type' => 'help']);
    LearningAttempt::factory()->create([...$attributes, 'sequence' => 3, 'event_type' => 'correction']);
    expect($first->presentation->firstAttempt->id)->toBe($first->id)
        ->and($first->presentation->attempts()->count())->toBe(3);
    expect(fn () => LearningAttempt::factory()->create([...$attributes, 'sequence' => 4, 'is_first_answer' => true]))->toThrow(QueryException::class);
    expect(fn () => LearningAttempt::factory()->create([...$attributes, 'sequence' => 2]))->toThrow(QueryException::class);
    expect(fn () => LearningAttempt::factory()->create([...$attributes, 'sequence' => 4, 'client_event_id' => $help->client_event_id]))->toThrow(QueryException::class);
    expect(fn () => $first->update(['result' => ['correct' => false]]))->toThrow(LogicException::class);
});

it('deduplicates personal words and their versioned sources', function () {
    $word = PersonalVocabulary::factory()->create();
    expect(fn () => PersonalVocabulary::factory()->create(['user_id' => $word->user_id, 'lexeme_key' => $word->lexeme_key]))->toThrow(QueryException::class);
    $source = PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id]);
    expect(fn () => PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id, 'session_version_id' => $source->session_version_id]))->toThrow(QueryException::class);
});

it('applies only one event and one run success effect per lexeme', function () {
    $owner = User::factory()->create();
    $attempt = schemaAttempt($owner);
    $word = PersonalVocabulary::factory()->create(['user_id' => $owner->id]);
    $data = ['user_id' => $owner->id, 'event_id' => $attempt->id, 'personal_word_id' => $word->id, 'lexeme_key' => $word->lexeme_key, 'effect' => ['success' => true], 'applied_at' => now('UTC')];
    VocabularyEventEffect::create($data);
    expect(fn () => VocabularyEventEffect::create($data))->toThrow(QueryException::class);
    $run = VocabularyRunEffect::create(['user_id' => $owner->id, 'lexeme_key' => $word->lexeme_key, 'run_id' => $attempt->run_id, 'success_applied' => true, 'applied_at' => now('UTC')]);
    expect(fn () => $run->update(['success_applied' => false]))->toThrow(LogicException::class);
    expect(fn () => VocabularyRunEffect::create(['user_id' => $owner->id, 'lexeme_key' => $word->lexeme_key, 'run_id' => $attempt->run_id]))->toThrow(QueryException::class);
});

it('counts one assignment per day and never counts a run on two activity days', function () {
    $run = LearningRun::factory()->create();
    $assignment = ['user_id' => $run->user_id, 'local_date' => '2026-10-24', 'selected_run_id' => $run->id];
    DailyAssignment::create($assignment);
    expect(fn () => DailyAssignment::create($assignment))->toThrow(QueryException::class);
    LearningActivityDay::create(['user_id' => $run->user_id, 'local_date' => '2026-10-24', 'qualifying_run_id' => $run->id]);
    expect(fn () => LearningActivityDay::create(['user_id' => $run->user_id, 'local_date' => '2026-10-25', 'qualifying_run_id' => $run->id]))->toThrow(QueryException::class);
});

it('deduplicates sync batches and source events when the same events are repackaged', function () {
    $owner = User::factory()->create();
    $episode = schemaEpisode($owner);
    $batch = schemaBatch($episode);
    expect(fn () => schemaBatch($episode, $batch->batch_id))->toThrow(QueryException::class);
    $secondBatch = schemaBatch($episode);
    $event = schemaAttempt($owner);
    $data = ['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $owner->id, 'entity_type' => 'event', 'guest_entity_id' => (string) Str::uuid(), 'server_event_id' => $event->id, 'receipt' => ['accepted' => true]];
    GuestEventMapping::create($data);
    expect($secondBatch->id)->not->toBe($batch->id)
        ->and($batch->receipt)->toBe(['accepted' => true]);
    expect(fn () => GuestEventMapping::create($data))->toThrow(QueryException::class);
    expect(GuestEventMapping::query()->count())->toBe(1);
});

it('binds guest identity and server mappings to one account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $episode = schemaEpisode($owner);
    expect(fn () => GuestSyncEpisode::create(['guest_id' => $episode->guest_id, 'owner_user_id' => $other->id, 'started_at' => now('UTC')]))->toThrow(QueryException::class);
    $run = LearningRun::factory()->create(['user_id' => $other->id]);
    expect(fn () => GuestEventMapping::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $owner->id, 'entity_type' => 'run', 'guest_entity_id' => (string) Str::uuid(), 'server_run_id' => $run->id]))->toThrow(QueryException::class);
});

it('retains objective identity independently from changing content versions', function () {
    $owner = User::factory()->create();
    $enrollment = LearningCurriculumEnrollment::factory()->create(['user_id' => $owner->id]);
    $objective = LearningObjective::create(['stable_id' => 'PRE_A1.TEST', 'level' => 'PRE_A1', 'title' => 'Тестовая цель']);
    $enrollment->curriculum->objectives()->attach($objective->id);
    $version = LearningSessionVersion::factory()->create();
    $enrollment->curriculum->checkpoints()->attach($version->session_id);
    $first = schemaAttempt($owner, $version);
    $data = schemaEvidence($first, $enrollment, $objective);
    LearningObjectiveEvidence::create($data);
    expect(fn () => LearningObjectiveEvidence::create($data))->toThrow(QueryException::class);
    $next = LearningSessionVersion::factory()->create(['session_id' => $version->session_id, 'version' => 2]);
    $second = schemaAttempt($owner, $next);
    LearningObjectiveEvidence::create(schemaEvidence($second, $enrollment, $objective));

    $evidence = $enrollment->evidence()->get();
    expect($evidence)->toHaveCount(2)
        ->and($evidence->unique(fn (LearningObjectiveEvidence $row): string => $row->session_id.':'.$row->task_id))->toHaveCount(1)
        ->and($enrollment->curriculum->objectives()->count())->toBe(1);
});

it('retains import media catalog and editorial records with real typed relationships', function () {
    $creator = User::factory()->create();
    $import = SessionImportBatch::create(['file_hash' => hash('sha256', 'file'), 'preview_token_hash' => hash('sha256', 'token'), 'created_by' => $creator->id, 'private_path' => 'imports/fixture.json', 'original_filename' => 'fixture.json', 'file_size' => 100, 'expires_at' => now('UTC')->addMinutes(30)]);
    expect(fn () => SessionImportBatch::create(['file_hash' => hash('sha256', 'file2'), 'preview_token_hash' => $import->preview_token_hash, 'private_path' => 'imports/fixture2.json', 'original_filename' => 'fixture2.json', 'file_size' => 100, 'expires_at' => now('UTC')->addMinutes(30)]))->toThrow(QueryException::class);
    $version = LearningSessionVersion::factory()->create(['import_batch_id' => $import->id]);
    $media = LearningMedia::create(['disk' => 'local', 'path' => 'audio/fixture.mp3', 'mime_type' => 'audio/mpeg', 'size' => 100, 'checksum' => hash('sha256', 'audio')]);
    $version->media()->attach($media->id);
    $revision = LearningCatalogRevision::create(['level_code' => 'PRE_A1', 'content' => ['summary' => 'Редакция'], 'base_revision' => 0, 'import_batch_id' => $import->id]);
    $audit = LearningEditorialEvent::create(['actor_id' => $creator->id, 'session_version_id' => $version->id, 'operation' => 'import', 'new_state' => ['status' => 'draft'], 'occurred_at' => now('UTC')]);
    LearningContentReport::create(['session_version_id' => $version->id, 'text' => 'Проверить формулировку']);
    LearningUrlAlias::create(['old_path' => '/practice/old', 'target_path' => '/practice/new', 'session_id' => $version->session_id]);
    expect($version->media()->first()->is($media))->toBeTrue()
        ->and($audit->sessionVersion->is($version))->toBeTrue()
        ->and($revision->importBatch->is($import))->toBeTrue();
    expect(fn () => $media->delete())->toThrow(QueryException::class);
    expect(fn () => $import->delete())->toThrow(QueryException::class);
});

it('cleans personal data on account deletion while preserving studied content', function () {
    $owner = User::factory()->create();
    $levelEvent = LearningLevelEvent::create(['user_id' => $owner->id, 'old_level' => 'PRE_A1', 'new_level' => 'A1', 'source' => 'manual', 'source_event_id' => (string) Str::uuid(), 'occurred_at' => now('UTC')]);
    LearningProfile::factory()->create(['user_id' => $owner->id, 'last_manual_level_event_id' => $levelEvent->id]);
    $attempt = schemaAttempt($owner);
    $word = PersonalVocabulary::factory()->create(['user_id' => $owner->id]);
    PersonalVocabularySource::factory()->create(['personal_word_id' => $word->id, 'session_version_id' => $attempt->run->session_version_id]);
    VocabularyEventEffect::create(['user_id' => $owner->id, 'event_id' => $attempt->id, 'personal_word_id' => $word->id, 'lexeme_key' => $word->lexeme_key, 'effect' => [], 'applied_at' => now('UTC')]);
    VocabularyRunEffect::create(['user_id' => $owner->id, 'lexeme_key' => $word->lexeme_key, 'run_id' => $attempt->run_id]);
    DailyAssignment::create(['user_id' => $owner->id, 'local_date' => '2026-10-24', 'selected_run_id' => $attempt->run_id]);
    LearningActivityDay::create(['user_id' => $owner->id, 'local_date' => '2026-10-24', 'qualifying_run_id' => $attempt->run_id]);
    $enrollment = LearningCurriculumEnrollment::factory()->create(['user_id' => $owner->id]);
    $objective = LearningObjective::create(['stable_id' => 'PRE_A1.DELETE', 'level' => 'PRE_A1', 'title' => 'Цель']);
    LearningObjectiveEvidence::create(schemaEvidence($attempt, $enrollment, $objective));
    $episode = schemaEpisode($owner);
    schemaBatch($episode);
    GuestEventMapping::create(['episode_id' => $episode->id, 'guest_id' => $episode->guest_id, 'owner_user_id' => $owner->id, 'entity_type' => 'event', 'guest_entity_id' => (string) Str::uuid(), 'server_event_id' => $attempt->id]);
    $versionId = $attempt->run->session_version_id;
    $owner->delete();

    foreach ([LearningProfile::class, LearningLevelEvent::class, LearningRun::class, LearningPresentation::class, LearningAttempt::class, PersonalVocabulary::class, PersonalVocabularySource::class, VocabularyEventEffect::class, VocabularyRunEffect::class, DailyAssignment::class, LearningActivityDay::class, LearningCurriculumEnrollment::class, LearningObjectiveEvidence::class, GuestSyncEpisode::class, GuestSyncBatch::class, GuestEventMapping::class] as $model) {
        expect($model::query()->count())->toBe(0);
    }
    expect(LearningSessionVersion::query()->find($versionId))->not->toBeNull();
});

it('rolls back only learning tables without changing lessons or games', function () {
    $legacy = [
        'lessons' => Schema::getColumnListing('lessons'),
        'game_sessions' => Schema::getColumnListing('game_sessions'),
    ];
    $paths = glob(database_path('migrations/2026_10_24_030*_create_learning_*_tables.php'));
    expect($paths)->toHaveCount(4);
    foreach (array_reverse($paths) as $path) {
        (require $path)->down();
    }
    expect(Schema::hasTable('learning_levels'))->toBeFalse()
        ->and(Schema::getColumnListing('lessons'))->toBe($legacy['lessons'])
        ->and(Schema::getColumnListing('game_sessions'))->toBe($legacy['game_sessions']);
    foreach ($paths as $path) {
        (require $path)->up();
    }
    $this->seed(LearningLevelSeeder::class);
    expect(LearningLevel::query()->count())->toBe(7);
});

it('keeps opaque external and lexeme identifiers case-sensitive like the JSON contract', function () {
    $owner = User::factory()->create();
    $first = LearningSession::factory()->create(['external_id' => 'PRE_A1-CASE']);
    $second = LearningSession::factory()->create(['external_id' => 'PRE_A1-case']);
    PersonalVocabulary::factory()->create(['user_id' => $owner->id, 'lexeme_key' => 'fr:case:ONE']);
    PersonalVocabulary::factory()->create(['user_id' => $owner->id, 'lexeme_key' => 'fr:case:one']);

    expect($first->id)->not->toBe($second->id)
        ->and(PersonalVocabulary::query()->count())->toBe(2);
});

it('rejects objective evidence pointing to a different session or run version', function () {
    $owner = User::factory()->create();
    $enrollment = LearningCurriculumEnrollment::factory()->create(['user_id' => $owner->id]);
    $objective = LearningObjective::create(['stable_id' => 'PRE_A1.CONSISTENT', 'level' => 'PRE_A1', 'title' => 'Цель']);
    $event = schemaAttempt($owner);
    $other = LearningSessionVersion::factory()->create();
    $data = schemaEvidence($event, $enrollment, $objective);
    $data['session_version_id'] = $other->id;
    expect(fn () => LearningObjectiveEvidence::create($data))->toThrow(QueryException::class);
    $data['session_id'] = $other->session_id;
    expect(fn () => LearningObjectiveEvidence::create($data))->toThrow(QueryException::class);
});

it('uses NULL for nonfirst answers so several corrections remain possible', function () {
    $event = schemaAttempt(User::factory()->create());
    foreach ([2, 3] as $sequence) {
        LearningAttempt::factory()->create(['presentation_id' => $event->presentation_id, 'sequence' => $sequence, 'is_first_answer' => false]);
    }
    expect($event->presentation->attempts()->count())->toBe(3)
        ->and($event->presentation->attempts()->whereNull('is_first_answer')->count())->toBe(2);
});

it('supports immutable vocabulary-test snapshots without a session version', function () {
    $snapshot = ['fixture' => true, 'lexeme_keys' => ['fr:bonjour:greeting'], 'order' => ['R01']];
    $run = LearningRun::factory()->create(['kind' => 'vocabulary_test', 'session_version_id' => null, 'snapshot' => $snapshot]);
    expect($run->fresh()->session_version_id)->toBeNull()
        ->and($run->fresh()->snapshot)->toBe($snapshot);
    expect(fn () => $run->update(['snapshot' => ['order' => ['different']]]))->toThrow(LogicException::class);
});

it('cannot erase publication timestamps to bypass immutable content', function () {
    $version = LearningSessionVersion::factory()->create(['published_at' => now('UTC'), 'status' => 'published']);
    expect(fn () => $version->update(['published_at' => null]))->toThrow(LogicException::class);
    $curriculum = LearningCurriculum::factory()->create(['published_at' => now('UTC'), 'status' => 'published']);
    expect(fn () => $curriculum->update(['published_at' => null]))->toThrow(LogicException::class);
});

it('keeps accepted event ledgers append-only without blocking account cleanup', function () {
    $event = schemaAttempt(User::factory()->create());
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    $profile = LearningProfile::factory()->create(['user_id' => $event->user_id]);
    expect($profile->levelDefinition->code)->toBe('PRE_A1');
});
