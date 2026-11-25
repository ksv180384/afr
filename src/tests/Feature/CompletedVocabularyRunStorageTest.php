<?php

use App\Models\Learning\LearningRun;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('round trips completed vocabulary history including old missing fields and nonstandard results', function () {
    $snapshot = [
        'id' => (string) Str::uuid(), 'title' => 'Тест изученных выражений', 'level' => 'A2',
        'description' => 'Начато до входа.', 'duration_minutes' => 3,
        'remaining_word_ids' => [53, 11], 'self_check_words' => [['id' => 91, 'ru' => ['выражение']]],
        'reviewed_items' => ['fr:bonjour' => ['id' => 'R09', 'category' => 'meaning']],
    ];
    $result = [
        'total_steps' => 3, 'independent_correct_steps' => 2, 'technical_skips' => 0,
        'checking_policy_version' => 2, 'score_percent' => 66.67, 'objectives' => ['mastered' => 1],
        'promotion' => null, 'integrations_pending' => [],
    ];
    $run = LearningRun::factory()->create(['kind' => 'vocabulary_test', 'status' => 'completed',
        'session_id' => null, 'snapshot' => $snapshot, 'result' => $result]);
    $this->actingAs($run->user);
    config(['daily_practice.enabled' => true]);
    $row = DB::table('learning_runs')->where('id', $run->id);
    $created = (clone $row)->first();
    expect(json_decode($created->snapshot, true)[0])->toBe('ts1')
        ->and(json_decode($created->result, true)[0])->toBe('vr1');
    $legacy = ['snapshot' => json_encode($snapshot), 'result' => json_encode($result)];
    $row->update($legacy);
    $before = $this->getJson('/daily/api/runs/'.$run->id)->assertOk()->json();
    $history = $this->getJson('/daily/api/history')->assertOk()->json('data');
    $others = [];
    foreach ([['review', 'in_progress'], ['review', 'paused'], ['review', 'cancelled'], ['daily', 'completed']] as [$kind, $status]) {
        $other = LearningRun::factory()->create(['user_id' => $run->user_id, 'kind' => $kind, 'status' => $status, 'session_id' => null]);
        $others[] = $other->id;
    }
    $untouched = DB::table('learning_runs')->whereIn('id', $others)->orderBy('id');
    (clone $untouched)->update($legacy);
    $originalRows = $untouched->get()->toJson();
    $migration = require database_path('migrations/2026_11_25_230000_compact_completed_vocabulary_runs.php');
    $migration->up();
    $packed = $row->first();
    $migration->up();
    expect($row->first())->toEqual($packed)
        ->and(json_decode($packed->snapshot, true)[0])->toBe('ts1')
        ->and(json_decode($packed->result, true)[0])->toBe('vr1')
        ->and($untouched->get()->toJson())->toBe($originalRows)
        ->and(CanonicalJson::encode($this->getJson('/daily/api/runs/'.$run->id)->assertOk()->json()))->toBe(CanonicalJson::encode($before));
    $entry = collect($this->getJson('/daily/api/history')->assertOk()->json('data'))->firstWhere('id', $run->id);
    expect(CanonicalJson::encode($entry))->toBe(CanonicalJson::encode($history[0]));
    $migration->down();
    expect(CanonicalJson::encode(json_decode($row->value('snapshot'))))->toBe(CanonicalJson::encode((object) $snapshot))
        ->and(CanonicalJson::encode(json_decode($row->value('result'))))->toBe(CanonicalJson::encode((object) $result))
        ->and($untouched->get()->toJson())->toBe($originalRows);
});

it('backfills multiple batches and preserves unrecognised historical JSON', function () {
    $template = LearningRun::factory()->create(['kind' => 'review', 'status' => 'completed', 'session_id' => null,
        'snapshot' => null, 'result' => null]);
    $oldResult = '{"total_steps":1,"independent_correct_steps":1,"technical_skips":0,"details":{}}';
    $snapshot = ['id' => (string) Str::uuid(), 'title' => 'Повторение', 'level' => 'A1', 'duration_minutes' => 2,
        'reviewed_items' => ['fr:salut' => ['id' => 'R01', 'category' => 'context']]];
    $rows = [];
    for ($i = 0; $i < 105; $i++) {
        $rows[] = array_replace($template->getAttributes(), ['id' => (string) Str::uuid(),
            'snapshot' => json_encode($snapshot), 'result' => $oldResult]);
    }
    foreach (array_chunk($rows, 20) as $batch) {
        DB::table('learning_runs')->insert($batch);
    }
    $migration = require database_path('migrations/2026_11_25_230000_compact_completed_vocabulary_runs.php');
    $migration->up();
    foreach (DB::table('learning_runs')->whereIn('id', array_column($rows, 'id'))->get() as $row) {
        expect(json_decode($row->snapshot, true)[0])->toBe('rs1')
            ->and($row->result)->toBe($oldResult);
    }
    $migration->down();
    $recovered = DB::table('learning_runs')->whereIn('id', array_column($rows, 'id'))->get();
    expect($recovered)->toHaveCount(105);
    foreach ($recovered as $row) {
        expect(CanonicalJson::encode(json_decode($row->snapshot)))->toBe(CanonicalJson::encode($snapshot))
            ->and($row->result)->toBe($oldResult);
    }
    expect($template->fresh()->getRawOriginal('snapshot'))->toBeNull()
        ->and($template->fresh()->getRawOriginal('result'))->toBeNull();
});
