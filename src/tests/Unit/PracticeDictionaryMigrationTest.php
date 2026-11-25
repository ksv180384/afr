<?php

use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\Gender;
use App\Models\User\Rang;
use App\Models\User\UserConfigsView;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('round trips lesson content and personal vocabulary with foreign keys enabled', function () {
    // SQLite schema rebuilds must run outside a test transaction, just as they do in artisan migrate.
    config(['database.default' => 'dictionary_migration', 'database.connections.dictionary_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
    ]]);
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    Gender::create(['title' => 'Не указан']);
    Rang::create(['alias' => 'polzovatel', 'title' => 'Пользователь']);
    UserConfigsView::create(['alias' => UserConfigsView::NE_POKAZYVAT_NIKOMU, 'title' => 'По умолчанию']);
    $expression = ['id' => 'salut', 'lexeme_key' => 'fr:salut', 'fr' => 'salut', 'ru' => ['привет'], 'example' => ['fr' => 'Salut !']];
    $second = ['id' => 'merci', 'lexeme_key' => 'fr:merci', 'fr' => 'merci', 'ru' => ['спасибо']];
    $lesson = LearningSession::factory()->withContent(['vocabulary' => [$expression, $second]])->create();
    $word = PersonalVocabulary::factory()->studied($expression, $lesson)->create(['streak' => 2]);
    $imported = PersonalVocabulary::factory()->studied($second, $lesson)->create(['user_id' => $word->user_id]);
    $date = '2026-01-09 12:00:10';
    $guestDate = '2026-01-07 08:12:00';
    $run = LearningRun::factory()->create(['user_id' => $word->user_id, 'session_id' => $lesson->id,
        'status' => 'completed', 'finished_at' => $date]);
    LearningRun::factory()->count(2)->create(['user_id' => $word->user_id, 'session_id' => $lesson->id,
        'status' => 'completed', 'finished_at' => $guestDate]);
    $sets = require database_path('migrations/2026_11_25_200000_share_studied_vocabulary_sets.php');
    $index = require database_path('migrations/2026_11_25_210000_drop_redundant_personal_vocabulary_owner_id_index.php');
    expect(Schema::hasIndex('personal_vocabulary', 'pv_owner_id_uq'))->toBeFalse();
    $index->down();
    expect(Schema::hasIndex('personal_vocabulary', ['user_id', 'id'], 'unique'))->toBeTrue();
    $sets->down();
    DB::table('personal_vocabulary_sources')->where('personal_word_id', $word->id)->update(['first_added_at' => $date]);
    DB::table('personal_vocabulary_sources')->where('personal_word_id', $imported->id)->update(['first_added_at' => $guestDate]);
    $migration = require database_path('migrations/2026_11_25_160000_normalize_practice_vocabulary.php');
    $migration->down();
    $raw = DB::table('personal_vocabulary_sources')->where('personal_word_id', $word->id)->value('snapshot');
    expect(CanonicalJson::hash(json_decode($raw, true)))->toBe(CanonicalJson::hash($expression));
    $migration->up();
    $sets->up();
    $index->up();
    expect(CanonicalJson::hash($lesson->fresh()->payload))->toBe($lesson->checksum)
        ->and($word->fresh()->streak)->toBe(2)
        ->and($word->fresh()->sources()->firstOrFail()->entry->lexeme_key)->toBe($word->lexeme_key)
        ->and($word->sources()->sole()->getRawOriginal('first_added_at'))->toBe($date)
        ->and($imported->sources()->sole()->getRawOriginal('first_added_at'))->toBe($guestDate)
        ->and($run->fresh()->vocabulary_set_id)->toBeInt()
        ->and(DB::table('learning_vocabulary_legacy_studies')->count())->toBe(1)
        ->and(DB::select('PRAGMA foreign_key_check'))->toBeEmpty();
});
