<?php

use App\Models\Learning\LearningSession;
use App\Services\Learning\PronunciationCatalog;
use App\Services\Learning\PronunciationRecordings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('discovers copied files for normalized French and keeps distinct phrases separate', function () {
    config(['daily_practice.enabled' => true]);
    $public = sys_get_temp_dir().'/afr-recordings-'.Str::uuid();
    app()->usePublicPath($public);
    $texts = ["  L’été   à Paris ! ", "L'e\u{0301}te\u{0301} à Paris !", "L'été à Paris ?"];
    $key = PronunciationRecordings::key($texts[0]);
    $prefix = '/practice-audio/'.substr($key, 0, 2).'/'.$key;
    File::ensureDirectoryExists(dirname($public.$prefix));
    try {
        File::put($public.$prefix.'-paul.mp3', 'test fixture');
        touch($public.$prefix.'-lea.mp3');
        $result = $this->postJson('/daily/api/content/recordings', ['texts' => $texts])->assertOk()->json('files');
        expect($result[0])->toHaveKeys(['paul'])->not->toHaveKey('lea')
            ->and($result[0]['paul'])->toStartWith($prefix.'-paul.mp3?v=')
            ->and($result[1])->toBe($result[0])->and($result[2])->toBe([]);
    } finally {
        File::deleteDirectory($public);
    }
});

it('includes effective lesson variants and deduplicates spoken examples independently of lexeme ids', function () {
    $base = ['id' => 'hello', 'lexeme_key' => 'fr:hello', 'fr' => 'bonjour', 'ru' => ['здравствуйте'],
        'example' => ['fr' => 'Bonjour, Léa !', 'ru' => 'Здравствуй, Леа!']];
    LearningSession::factory()->withContent(['vocabulary' => [$base]])->create();
    LearningSession::factory()->withContent(['vocabulary' => [array_merge($base, ['fr' => 'salut'])]])->create();
    $rows = (new PronunciationCatalog)->rows();
    expect(array_column($rows, 'text'))->toEqualCanonicalizing(['bonjour', 'Bonjour, Léa !', 'salut']);
});

it('queues only missing voices in bulk and can regenerate a single selected take locally', function () {
    app()->instance('env', 'local');
    \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/daily_practice_admin.php'));
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $route = '/admin/daily-practice/pronunciation';
    Http::fake(['http://pronunciation:8080/jobs*' => Http::response(['added' => 1], 202)]);
    $this->postJson($route.'/generate', ['mode' => 'missing'])->assertUnauthorized();
    $rank = \App\Models\User\Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->actingAs(\App\Models\User\User::factory()->create(['rang_id' => $rank->id]));
    $word = ['id' => 'merci', 'lexeme_key' => 'fr:merci', 'fr' => 'merci', 'ru' => ['спасибо']];
    LearningSession::factory()->withContent(['vocabulary' => [$word]])->create();
    $this->withoutVite();
    $this->get($route)->assertOk()->assertInertia(fn ($page) => $page->component('DailySession/Pronunciation')
        ->where('rows.0.text', 'merci')->where('local_pronunciation', true));
    $key = PronunciationRecordings::key('merci');
    $root = sys_get_temp_dir().'/afr-generation-'.Str::uuid();
    app()->usePublicPath($root);
    $folder = $root.'/practice-audio/'.substr($key, 0, 2);
    File::ensureDirectoryExists($folder);
    try {
        File::put($folder.'/'.$key.'-lea.mp3', 'existing');
        $this->postJson($route.'/generate', ['mode' => 'missing'])->assertAccepted();
        Http::assertSent(fn ($r) => $r['items'] === [['text' => 'merci', 'voices' => ['paul']]] && ! $r['replace']);
        $this->postJson($route.'/generate', ['key' => $key, 'mode' => 'replace', 'voice' => 'lea'])->assertAccepted();
        Http::assertSent(fn ($r) => $r['items'] === [['text' => 'merci', 'voices' => ['lea']]] && $r['replace']);
        $this->postJson($route.'/generate', ['mode' => 'replace'])->assertUnprocessable();
    } finally {
        File::deleteDirectory($root);
    }
});

it('does not expose local generation routes outside local development', function () {
    Http::preventStrayRequests();
    foreach (['generate', 'status'] as $action) {
        $this->postJson('/admin/daily-practice/pronunciation/'.$action)->assertNotFound();
    }
    $this->get('/admin/daily-practice/pronunciation')->assertNotFound();
    Http::assertNothingSent();
});
