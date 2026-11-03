<?php

use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\Rang;
use App\Models\User\User;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    Storage::fake('learning_private');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->editor = User::factory()->create(['rang_id' => $rank->id]);
    $this->actingAs($this->editor);
});

function statusImport($test, bool $catalog = true, bool $incomplete = false, bool $omitRelated = false): array
{
    $package = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    if ($omitRelated) {
        foreach ($package['sessions'] as &$session) {
            unset($session['public_page']['related_session_ids']);
        }
        unset($session);
    }
    if ($incomplete) {
        unset($package['sessions'][0]['public_page']);
    }
    $preview = $test->post('/admin/daily-practice/imports/preview', ['file' => UploadedFile::fake()->createWithContent('topic.json', json_encode($package))], ['Accept' => 'application/json'])->assertCreated()->json();
    $receipt = $test->postJson('/admin/daily-practice/imports/'.$preview['batch_id'].'/commit', ['token' => $preview['token'], 'file_hash' => $preview['file_hash']])->assertOk()->json();
    if ($catalog) {
        foreach ($receipt['catalog_revision_ids'] as $id) {
            $test->postJson('/admin/daily-practice/catalog/'.$id.'/publish-direct')->assertOk()->assertJsonPath('status', 'published');
        }
    }

    return $receipt['created_version_ids'];
}

it('publishes whole draft sessions without checklist attestations and replays the same status safely', function () {
    $ids = statusImport($this);
    $input = ['version_ids' => $ids, 'status' => 'published'];
    $this->postJson('/admin/daily-practice/versions/status', $input)->assertOk()->assertJsonPath('0.status', 'published');
    $this->postJson('/admin/daily-practice/versions/status', $input)->assertOk();
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(2);
    foreach (LearningSessionVersion::findMany($ids) as $version) {
        expect($version->reviewed_at)->toBeNull()->and($version->review_checks)->toBeNull()->and($version->published_at)->not->toBeNull();
    }
    $this->assertDatabaseCount('learning_attempts', 0);
});

it('changes published sessions to draft ready and archive atomically without rewriting snapshots or publication dates', function () {
    $ids = statusImport($this);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => 'published'])->assertOk();
    $first = LearningSessionVersion::findOrFail($ids[0]);
    $publishedAt = $first->published_at;
    $checksum = $first->checksum;
    $run = LearningRun::factory()->create(['session_version_id' => $first->id, 'snapshot' => $first->payload]);
    $this->travel(1)->hours();
    foreach (['draft', 'reviewed', 'published', 'archived', 'published'] as $status) {
        $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => $status])->assertOk()->assertJsonPath('0.status', $status);
        expect($first->fresh()->published_at->equalTo($publishedAt))->toBeTrue()->and($first->fresh()->checksum)->toBe($checksum)
            ->and($run->fresh()->snapshot)->toBe($first->payload);
    }
    $this->travelBack();
});

it('retains dependency and public content validation when publishing without manual checks', function () {
    $ids = statusImport($this, false);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => 'published', 'notes' => 'Не сохранять при отказе'])->assertStatus(422);
    expect(LearningSessionVersion::where('status', 'draft')->count())->toBe(2)->and(LearningSessionVersion::find($ids[0])->editor_notes)->toBeNull();
});

it('blocks incomplete public material even through the simplified publication endpoint', function () {
    $ids = statusImport($this, true, true);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => 'published'])->assertStatus(422);
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(0);
});

it('rejects a partial withdrawal that would break another published session', function () {
    $ids = statusImport($this);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => 'published'])->assertOk();
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => [$ids[0]], 'status' => 'draft'])->assertStatus(409);
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(2);
});

it('validates status requests and denies ordinary users all simplified actions', function () {
    $ids = statusImport($this);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $ids, 'status' => 'anything'])->assertStatus(422);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => [$ids[0], $ids[0]], 'status' => 'draft'])->assertStatus(422);
    $this->actingAs(User::factory()->create());
    foreach (['versions/status', 'catalog/1/publish-direct', 'curricula/1/publish-direct'] as $path) {
        $this->postJson('/admin/daily-practice/'.$path, ['version_ids' => $ids, 'status' => 'published'])->assertForbidden();
    }
});

it('publishes a complete programme without a checklist but still requires its published controls', function () {
    $package = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
    foreach ($package['sessions'] as &$session) {
        $session['kind'] = 'checkpoint';
    }
    unset($session);
    $package['curricula'] = [['id' => 'PRE_A1-ADMIN-PROGRAM', 'version' => 1, 'level' => 'PRE_A1',
        'required_objective_ids' => array_column($package['sessions'][0]['objectives'], 'id'),
        'checkpoint_session_ids' => array_column($package['sessions'], 'id')]];
    $preview = $this->post('/admin/daily-practice/imports/preview', ['file' => UploadedFile::fake()->createWithContent('controls.json', json_encode($package))], ['Accept' => 'application/json'])->assertCreated()->json();
    $receipt = $this->postJson('/admin/daily-practice/imports/'.$preview['batch_id'].'/commit', ['token' => $preview['token'], 'file_hash' => $preview['file_hash']])->assertOk()->json();
    $id = $receipt['curriculum_ids'][0];
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/publish-direct')->assertStatus(422);
    foreach ($receipt['catalog_revision_ids'] as $revision) {
        $this->postJson('/admin/daily-practice/catalog/'.$revision.'/publish-direct')->assertOk();
    }
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $receipt['version_ids'], 'status' => 'published'])->assertOk();
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/publish-direct')->assertOk()->assertJsonPath('status', 'published');
    $publishedAt = App\Models\Learning\LearningCurriculum::findOrFail($id)->published_at;
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/archive')->assertOk();
    $this->travel(1)->hours();
    $this->postJson('/admin/daily-practice/curricula/'.$id.'/publish-direct')->assertOk();
    expect(App\Models\Learning\LearningCurriculum::findOrFail($id)->published_at->equalTo($publishedAt))->toBeTrue();
    $this->travelBack();
});

it('identifies the unpublished prerequisite and explains how to publish dependent sessions together', function () {
    $ids = statusImport($this);
    $previous = LearningSessionVersion::findOrFail($ids[0]);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => [$ids[1]], 'status' => 'published'])
        ->assertStatus(422)
        ->assertJsonPath('details.session_id', $previous->session->external_id)
        ->assertJsonPath('details.session_title', $previous->payload['title'])
        ->assertJsonPath('message', fn ($message) => str_contains($message, $previous->payload['title'])
            && str_contains($message, $previous->session->external_id)
            && str_contains($message, 'одной группой'));
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(0);
});

it('publishes a selected dependency group regardless of its selection order', function () {
    $ids = statusImport($this);
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => array_reverse($ids), 'status' => 'published'])->assertOk();
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(2);
});

it('publishes valid sessions when optional related session links are omitted', function (bool $group) {
    $ids = statusImport($this, omitRelated: true);
    $selected = $group ? array_reverse($ids) : [$ids[0]];
    $before = LearningSessionVersion::findMany($selected)->keyBy('id');
    $this->postJson('/admin/daily-practice/versions/status', ['version_ids' => $selected, 'status' => 'published'])->assertOk();
    expect(LearningSession::whereNotNull('active_version_id')->count())->toBe(count($selected));
    foreach (LearningSessionVersion::findMany($selected) as $version) {
        expect($version->status->value)->toBe('published')
            ->and($version->payload)->toBe($before[$version->id]->payload)
            ->and($version->checksum)->toBe($before[$version->id]->checksum);
    }
})->with(['one session' => false, 'dependency group' => true]);
