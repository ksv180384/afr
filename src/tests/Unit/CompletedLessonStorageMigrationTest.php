<?php

use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningRunOperation;
use App\Support\Learning\OperationReceipt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

it('round trips historical lesson results, full SHA-256 digests and only matching run pointers', function () {
    config(['database.default' => 'lesson_storage', 'database.connections.lesson_storage' => [
        'driver' => 'sqlite', 'database' => ':memory:',
    ]]);
    Schema::create('learning_runs', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('kind');
        $t->string('status');
        $t->char('content_checksum', 64)->nullable();
        $t->json('result')->nullable();
    });
    Schema::create('learning_run_operations', function (Blueprint $t) {
        $t->id();
        $t->uuid('run_id')->nullable();
        $t->char('payload_hash', 64);
        $t->json('receipt');
    });
    $runId = (string) Str::uuid();
    $hash = '00ff'.str_repeat('f080', 15);
    $result = array_combine(LearningRun::LESSON_RESULT_FIELDS, [7, 3, 0, 2, 2, 5, [987, 654]]);
    $legacy = ['total_steps' => 2, 'old_field' => 'ancien'];
    $seed = fn ($id, $kind, $status, $data) => DB::table('learning_runs')->insert([
        'id' => $id, 'kind' => $kind, 'status' => $status, 'result' => json_encode($data), 'content_checksum' => $hash,
    ]);
    $seed($runId, 'extra', 'completed', $result);
    $seed($old = (string) Str::uuid(), 'daily', 'completed', $legacy);
    $seed($paused = (string) Str::uuid(), 'daily', 'paused', null);
    $seed($review = (string) Str::uuid(), 'review', 'completed', $result);
    $receipts = [
        ['run_id' => $runId], ['run_id' => $runId, 'state' => []], ['run_id' => $old],
        ['state' => str_repeat('long receipt ', 100)], ['profile' => ['revision' => 2]],
    ];
    foreach ($receipts as $i => $receipt) {
        DB::table('learning_run_operations')->insert(['run_id' => $i === 4 ? null : $runId,
            'payload_hash' => $hash, 'receipt' => OperationReceipt::encode($receipt)]);
    }
    $migration = require database_path('migrations/2026_11_25_220000_compact_finished_lessons_and_operations.php');
    $migration->up();
    $migration->up();
    expect(LearningRun::findOrFail($runId)->result)->toBe($result)
        ->and(LearningRun::findOrFail($runId)->content_checksum)->toBeNull()
        ->and(LearningRun::findOrFail($old)->result)->toBe($legacy)
        ->and(LearningRun::findOrFail($paused)->content_checksum)->toBe($hash)
        ->and(LearningRun::findOrFail($review)->getRawOriginal('result'))->toBe(json_encode($result))
        ->and(DB::table('learning_run_operations')->whereNull('receipt')->count())->toBe(1);
    foreach (LearningRunOperation::orderBy('id')->get() as $i => $op) {
        expect($op->receipt)->toBe($receipts[$i])->and($op->payload_hash)->toBe($hash)
            ->and($op->getRawOriginal('payload_hash'))->toBe(hex2bin($hash));
    }
    $migration->down();
    expect(json_decode(DB::table('learning_runs')->where('id', $runId)->value('result'), true))->toBe($result);
    foreach (DB::table('learning_run_operations')->orderBy('id')->get() as $i => $op) {
        expect(OperationReceipt::decode($op->receipt))->toBe($receipts[$i])->and($op->payload_hash)->toBe($hash);
    }
});
