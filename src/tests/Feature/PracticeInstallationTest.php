<?php

use App\Models\Learning\LearningLevel;
use App\Models\User\Rang;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('imports on a migrated database without running a seeder', function () {
    $this->withoutVite();
    Storage::fake('learning_private');
    expect(LearningLevel::count())->toBe(7);
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $rank->id]);
    $preview = $this->actingAs($admin)->post('/admin/daily-practice/imports/preview', [
        'file' => UploadedFile::fake()->createWithContent('first.json', file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json'))),
    ], ['Accept' => 'application/json'])->assertCreated()->json();
    $this->postJson('/admin/daily-practice/imports/'.$preview['batch_id'].'/commit', [
        'token' => $preview['token'], 'file_hash' => $preview['file_hash'],
    ])->assertOk();
    $this->assertDatabaseCount('learning_session_versions', 2);
});

it('initializes missing levels repeatedly without overwriting editorial data', function () {
    LearningLevel::where('code', 'PRE_A1')->update(['title' => 'Название редактора', 'summary' => 'Описание', 'published_at' => now('UTC')]);
    LearningLevel::where('code', 'C2')->delete();
    $before = LearningLevel::where('code', 'PRE_A1')->first()->toArray();
    $migration = require database_path('migrations/2026_10_30_100000_initialize_learning_levels.php');
    $migration->up();
    $migration->up();
    expect(LearningLevel::count())->toBe(7)
        ->and(LearningLevel::where('code', 'PRE_A1')->first()->toArray())->toBe($before);
});
