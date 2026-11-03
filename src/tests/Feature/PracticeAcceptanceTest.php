<?php

use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\CatalogPublicationService;
use App\Services\Learning\ContentAccess;
use App\Services\Learning\ContentOperationException;
use App\Services\Learning\PracticeUrls;
use App\Services\Learning\SessionImportService;
use App\Services\Learning\SessionPublicationService;
use App\Services\Learning\Validation\CanonicalJson;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['daily_practice.enabled' => true, 'app.url' => 'http://localhost:5000']);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->editor = User::factory()->create(['rang_id' => $rank->id]);
});

it('accepts the real five-session package through publication and public HTML without learner effects', function () {
    $package = json_decode(file_get_contents(resource_path('learning/programme/packages/PRE_A1/PRE_A1-U01.v1.json')), true);
    $importer = app(SessionImportService::class);
    $preview = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('first-topic.json', CanonicalJson::encode($package)));
    $receipt = $importer->commit($this->editor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    expect($receipt['created_version_ids'])->toHaveCount(5);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    $publisher = app(SessionPublicationService::class);
    foreach ($receipt['created_version_ids'] as $id) {
        $publisher->review($this->editor, $id, $checks);
    }
    expect(fn () => $publisher->publishMany($this->editor, $receipt['created_version_ids']))->toThrow(ContentOperationException::class);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($this->editor, $id, $checks);
        app(CatalogPublicationService::class)->publish($this->editor, $id);
    }
    $publisher->publishMany($this->editor, $receipt['created_version_ids']);
    $this->getJson('/daily/api/content')->assertOk()->assertJsonCount(5, 'sessions');
    foreach (LearningSession::with('unit', 'activeVersion')->get() as $session) {
        $this->get(PracticeUrls::session($session))->assertOk()->assertSee($session->activeVersion->payload['title'])
            ->assertHeader('X-Robots-Tag', 'index, follow')->assertDontSee('correct_choice_id');
        $this->getJson('/daily/api/content/'.$session->external_id)->assertOk()->assertJsonCount(6, 'snapshot.tasks');
    }
    $again = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('formatted.json', json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
    $replayed = $importer->commit($this->editor, $again['batch_id'], $again['token'], $again['file_hash']);
    expect($replayed['created_version_ids'])->toBe([])->and($replayed['skipped_version_ids'])->toHaveCount(5);
    expect(LearningProfile::count())->toBe(0)->and(LearningRun::count())->toBe(0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
});

it('publishes an explicitly reviewed browser TTS checkpoint under the agreed audio policy', function () {
    $package = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    $package['sessions'][0]['kind'] = 'checkpoint';
    $importer = app(SessionImportService::class);
    $preview = $importer->preview($this->editor, UploadedFile::fake()->createWithContent('checkpoint.json', CanonicalJson::encode($package)));
    $receipt = $importer->commit($this->editor, $preview['batch_id'], $preview['token'], $preview['file_hash']);
    $checks = array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    foreach ($receipt['catalog_revision_ids'] as $id) {
        app(CatalogPublicationService::class)->review($this->editor, $id, $checks);
        app(CatalogPublicationService::class)->publish($this->editor, $id);
    }
    $publisher = app(SessionPublicationService::class);
    foreach ($receipt['created_version_ids'] as $id) {
        $publisher->review($this->editor, $id, $checks);
    }
    $publisher->publishMany($this->editor, $receipt['created_version_ids']);
    $this->getJson('/daily/api/content/'.$package['sessions'][0]['id'])->assertOk()->assertJsonPath('snapshot.kind', 'checkpoint');
    $this->assertDatabaseCount('learning_media', 0);
});

it('keeps existing lessons readable and unchanged when practice is disabled or enabled', function () {
    $lesson = App\Models\Lesson::create(['title' => 'Независимый урок', 'description' => 'Описание старого урока', 'content' => '<p>Содержание урока</p>']);
    foreach ([false, true] as $enabled) {
        config(['daily_practice.enabled' => $enabled]);
        $this->get('/lessons')->assertOk()->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Lessons/Lessons', false)->where('menu.0.title', $lesson->title));
        $this->get('/lessons/item/'.$lesson->id)->assertOk()->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Lessons/LessonShow', false)->where('lessonContent.content', $lesson->content));
    }
    expect($lesson->fresh()->content)->toBe('<p>Содержание урока</p>')->and(LearningRun::count())->toBe(0);
});
