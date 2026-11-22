<?php

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\LearningUrlAlias;
use App\Models\User\Rang;
use App\Models\User\User;
use App\Services\Learning\PracticeUrls;
use App\Services\Learning\PublicPracticeCatalog;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['daily_practice.enabled' => true, 'app.url' => 'http://localhost:5000']);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC'), 'summary' => 'Начните знакомство с французским.']);
    $data = json_decode(file_get_contents(resource_path('practice/templates/pre-a1-u01-examples.json')), true);
    $this->unit = LearningUnit::factory()->create(['slug' => $data['units'][0]['slug'], 'published_at' => now('UTC')]);
    $this->sessions = collect($data['sessions'])->map(function ($payload) {
        $session = LearningSession::factory()->create(['external_id' => $payload['id'], 'slug' => $payload['public_page']['slug'], 'unit_id' => $this->unit->id]);
        $version = LearningSessionVersion::factory()->create(['session_id' => $session->id, 'payload' => $payload, 'status' => 'published', 'published_at' => now('UTC')]);
        $session->update(['active_version_id' => $version->id]);

        return $session->fresh()->load('unit', 'activeVersion');
    });
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $this->admin = User::factory()->create(['rang_id' => $rank->id]);
});

it('returns real readable initial HTML at every public depth with metadata and no answer keys', function () {
    $session = $this->sessions[0];
    foreach (['/practice', PracticeUrls::level('PRE_A1'), PracticeUrls::unit($this->unit), PracticeUrls::session($session)] as $path) {
        $html = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'index, follow')->getContent();
        expect(substr_count($html, '<h1'))->toBe(1)->and($html)->toContain('<main', 'rel="canonical"', 'BreadcrumbList', 'name="description"', 'property="og:url"')->not->toContain('<noscript', '&quot;tasks&quot;', '&quot;review_items&quot;', '&quot;correct_answer&quot;');
    }
    $html = $this->get(PracticeUrls::session($session))->getContent();
    expect($html)->toContain('Bonjour', 'lang="fr"', '/daily?session=', $session->activeVersion->payload['public_page']['summary']);
    $this->get(PracticeUrls::unit($this->unit))->assertDontSee('href="'.PracticeUrls::session($this->sessions[1]).'"', false)->assertSee('🔒');
});

it('blocks account links to locked sessions while guest access is resolved from browser progress', function () {
    $second = $this->sessions[1];
    $this->get(PracticeUrls::session($second))->assertOk();
    $this->get('/daily?session='.$second->external_id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedSession.id', $second->external_id));
    $this->actingAs($this->admin)->get(PracticeUrls::session($second))->assertRedirect('/practice');
    $this->get('/daily?session='.$second->external_id)->assertRedirect('/practice');
    expect(LearningProfile::count())->toBe(0)->and(LearningRun::count())->toBe(0);
});

it('keeps tracking parameters out of canonical and makes filter URLs self canonical and noindex', function () {
    $path = PracticeUrls::session($this->sessions[0]);
    $this->get($path.'?utm_source=test&gclid=abc&page=1')->assertOk()->assertSee('href="http://localhost:5000'.$path.'"', false)->assertHeader('X-Robots-Tag', 'index, follow');
    $this->get('/practice?q=bonjour')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')->assertSee('href="http://localhost:5000/practice?q=bonjour"', false);
    $this->get('/practice?page=2')->assertNotFound();
});

it('serves every catalog depth through inertia without forcing a document reload', function () {
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request())];
    foreach (['/practice', PracticeUrls::level('PRE_A1'), PracticeUrls::unit($this->unit), PracticeUrls::session($this->sessions[0]), '/practice?q=bonjour'] as $path) {
        $this->get($path, $headers)->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertHeaderMissing('X-Inertia-Location')
            ->assertJsonPath('component', 'Practice/Index')->assertJsonPath('props.public_practice', true)
            ->assertJsonStructure(['props' => ['catalog' => ['seo' => ['canonicalUrl']]]]);
    }
});

it('marks initial page metadata for replacement during client navigation', function () {
    foreach (['/grammar', '/practice'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        foreach (['description', 'canonical', 'og:url', 'og:title', 'og:description', 'twitter:title', 'twitter:description'] as $key) {
            expect($html)->toContain('inertia="'.$key.'"');
        }
    }
});

it('excludes unprepared levels and empty units from indexing and sitemap', function () {
    LearningLevel::where('code', 'A1')->update(['published_at' => now('UTC')]);
    $empty = LearningUnit::factory()->create(['external_id' => 'PRE_A1_U02', 'slug' => 'empty-topic', 'published_at' => now('UTC')]);
    $this->get('/practice/a1')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    $this->get(PracticeUrls::unit($empty))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    $paths = array_column(app(PublicPracticeCatalog::class)->sitemapEntries(), 'path');
    expect($paths)->not->toContain('/practice/a1', PracticeUrls::unit($empty));
    foreach ($paths as $path) {
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'index, follow');
    }
    $this->get('/sitemap.xml')->assertOk()->assertSee('http://localhost:5000'.PracticeUrls::session($this->sessions[0]), false)->assertDontSee('/daily');
});

it('returns 404 for unknown or never published and 410 for removed published material', function () {
    $first = $this->sessions[0];
    $this->get('/practice/pre-a1/missing')->assertNotFound();
    $draft = LearningSession::factory()->create(['external_id' => 'PRE_A1_U01_S99', 'unit_id' => $this->unit->id, 'slug' => 'never-published']);
    $this->get(PracticeUrls::session($draft))->assertNotFound();

    $first->activeVersion->update(['status' => 'archived']);
    $first->update(['active_version_id' => null, 'archived_at' => now('UTC')]);
    $this->get(PracticeUrls::session($first))->assertStatus(410);
    $this->unit->update(['archived_at' => now('UTC')]);
    $this->get(PracticeUrls::unit($this->unit))->assertStatus(410);
});

it('hides a topic and its published lessons until an administrator restores it', function () {
    $session = $this->sessions[0];
    $unitPath = PracticeUrls::unit($this->unit);
    $sessionPath = PracticeUrls::session($session);
    $endpoint = '/admin/daily-practice/catalog/units/'.$this->unit->id.'/visibility';
    $publishedAt = $this->unit->getRawOriginal('published_at');

    $this->actingAs($this->admin)->postJson($endpoint, ['published' => false])->assertOk();
    expect(app(PublicPracticeCatalog::class)->page('pre-a1', null, null)['cards'])->toBe([]);
    $this->get($unitPath)->assertStatus(410);
    $this->get($sessionPath)->assertStatus(410);
    $this->getJson(route('daily.content.show', ['session' => $session->external_id]))->assertNotFound();
    $this->get('/sitemap.xml')->assertOk()->assertDontSee($sessionPath, false);
    expect($this->unit->fresh()->getRawOriginal('published_at'))->toBe($publishedAt)
        ->and($session->fresh()->active_version_id)->toBe($session->active_version_id);

    $this->postJson($endpoint, ['published' => true])->assertOk()->assertJsonPath('archived_at', null);
    $this->get($unitPath)->assertOk();
    $this->get($sessionPath)->assertOk();
    $this->get('/sitemap.xml')->assertOk()->assertSee($sessionPath, false);
});

it('hides a level and restores only its independently published topics', function () {
    $level = LearningLevel::where('code', 'PRE_A1')->firstOrFail();
    $session = $this->sessions[0];
    $unitPath = PracticeUrls::unit($this->unit);
    $sessionPath = PracticeUrls::session($session);
    $endpoint = '/admin/daily-practice/catalog/levels/'.$level->id.'/visibility';
    $draftTopic = LearningUnit::factory()->create(['sort_order' => 90, 'slug' => 'hidden-topic',
        'published_at' => now('UTC'), 'archived_at' => now('UTC')]);

    $this->actingAs($this->admin)->postJson($endpoint, ['published' => false])->assertOk();
    $this->postJson($endpoint, ['published' => false])->assertOk();
    $this->assertDatabaseCount('learning_editorial_events', 1);
    $this->get('/practice')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('levels', fn ($levels) => ! collect($levels)->contains('code', 'PRE_A1'))
        ->where('catalog.cards', fn ($cards) => ! collect($cards)->contains('path', '/practice/pre-a1')));
    foreach (['/practice/pre-a1', $unitPath, $sessionPath] as $path) {
        $this->get($path)->assertStatus(410);
    }
    $this->getJson(route('daily.content.index'))->assertOk()->assertJsonCount(0, 'sessions')
        ->assertJsonMissing(['id' => 'PRE_A1']);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee($sessionPath, false);

    $this->postJson($endpoint, ['published' => true])->assertOk()->assertJsonPath('archived_at', null);
    $this->get($unitPath)->assertOk();
    $this->get($sessionPath)->assertOk();
    $this->get(PracticeUrls::unit($draftTopic))->assertStatus(410);
});

it('offers only visible levels in profile settings while keeping a saved hidden level', function () {
    $levels = LearningLevel::whereIn('code', ['C1', 'C2']);
    $levels->update(['published_at' => now('UTC')]);
    \App\Models\Proverb::create(['text' => 'Bonjour', 'translation' => 'Здравствуйте']);
    $learner = User::factory()->create();
    $profile = LearningProfile::factory()->create(['user_id' => $learner->id, 'current_level' => 'C2']);

    $this->actingAs($this->admin);
    foreach ($levels->get() as $level) {
        $this->postJson('/admin/daily-practice/catalog/levels/'.$level->id.'/visibility', ['published' => false])->assertOk();
    }

    $this->actingAs($learner)->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('practiceLevels', fn ($choices) => array_column($choices->all(), 'code') === ['PRE_A1', 'A1', 'A2', 'B1', 'B2']));
    $this->getJson('/daily/api/profile')->assertOk()->assertJsonPath('current_level', 'C2');

    $c1 = $levels->where('code', 'C1')->firstOrFail();
    $this->actingAs($this->admin)->postJson('/admin/daily-practice/catalog/levels/'.$c1->id.'/visibility', ['published' => true])->assertOk();
    $this->actingAs($learner)->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('practiceLevels', fn ($choices) => array_column($choices->all(), 'code') === ['PRE_A1', 'A1', 'A2', 'B1', 'B2', 'C1']));
    expect($profile->fresh()->getRawOriginal('current_level'))->toBe('C2');

    $input = ['client_event_id' => (string) Str::uuid(), 'expected_revision' => $profile->revision, 'timezone' => $profile->timezone];
    $this->patchJson('/daily/api/profile', [...$input, 'current_level' => 'C2'])->assertUnprocessable();
    $this->patchJson('/daily/api/profile', [...$input, 'current_level' => 'B2'])->assertOk()->assertJsonPath('profile.current_level', 'B2');
});

it('keeps lastmod stable on reads and excludes pending material from the public page', function () {
    $before = app(PublicPracticeCatalog::class)->sitemapEntries();
    $first = $this->sessions[0];
    $payload = $first->activeVersion->payload;
    $payload['title'] = 'Неопубликованный новый заголовок';
    LearningSessionVersion::factory()->create(['session_id' => $first->id, 'version' => 2, 'payload' => $payload]);
    $this->travel(2)->hours();
    $this->get(PracticeUrls::session($first))->assertOk()->assertDontSee($payload['title']);
    expect(app(PublicPracticeCatalog::class)->sitemapEntries())->toBe($before);
});

it('renames sessions and themes explicitly and resolves old child URLs in one redirect', function () {
    $first = $this->sessions[0];
    $old = PracticeUrls::session($first);
    $oldUnit = PracticeUrls::unit($this->unit);
    $service = app(PracticeUrls::class);
    $new = $service->rename($this->admin, 'session', $first->id, ['expected_slug' => $first->slug, 'slug' => 'new-session'])['path'];
    $this->get($old)->assertStatus(301)->assertRedirect('http://localhost:5000'.$new);
    $renamed = $service->rename($this->admin, 'unit', $this->unit->id, ['expected_slug' => $this->unit->slug, 'slug' => 'new-topic'])['path'];
    $this->get($oldUnit)->assertStatus(301)->assertRedirect('http://localhost:5000'.$renamed);
    $this->get($old)->assertStatus(301)->assertRedirect('http://localhost:5000'.$renamed.'/new-session');
    $this->get($new)->assertStatus(301)->assertRedirect('http://localhost:5000'.$renamed.'/new-session');
    $this->get($renamed.'/new-session')->assertOk();
    $detail = app(\App\Services\Learning\ContentEditorService::class)->detail($this->admin, $first->activeVersion->id, app(\App\Services\Learning\SessionPackageValidator::class), app(\App\Services\Learning\SessionExportService::class));
    expect($detail['publicMeta']['path'])->toBe($renamed.'/new-session');
    expect(array_column(app(PublicPracticeCatalog::class)->sitemapEntries(), 'path'))->not->toContain($old, $oldUnit, $new);
    $first->update(['archived_at' => now('UTC')]);
    $this->get($old)->assertStatus(410);
});

it('rejects stale and colliding URL changes atomically', function () {
    $first = $this->sessions[0];
    expect(fn () => app(PracticeUrls::class)->rename($this->admin, 'session', $first->id, ['expected_slug' => 'stale', 'slug' => 'new']))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(PracticeUrls::class)->rename($this->admin, 'session', $first->id, ['expected_slug' => $first->slug, 'slug' => $this->sessions[1]->slug]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($first->fresh()->slug)->toBe($first->slug)->and(LearningUrlAlias::count())->toBe(0);
});

it('requires administrator verification and explicit confirmation for HTTP renaming', function () {
    $first = $this->sessions[0];
    $path = '/admin/daily-practice/sessions/'.$first->id.'/rename';
    $input = ['expected_slug' => $first->slug, 'slug' => 'explicit-new', 'confirmed' => true];
    $this->actingAs(User::factory()->create())->postJson($path, $input)->assertForbidden();
    $this->actingAs($this->admin)->postJson($path, [...$input, 'confirmed' => false])->assertUnprocessable();
    $this->postJson($path, $input)->assertOk();
    $this->get(PracticeUrls::session($first))->assertStatus(301);
});

it('closes public routes and sitemap with the feature flag', function () {
    config(['daily_practice.enabled' => false]);
    foreach (['/practice', '/practice/pre-a1', PracticeUrls::unit($this->unit), PracticeUrls::session($this->sessions[0])] as $path) {
        $this->get($path)->assertNotFound();
    }
    expect(app(PublicPracticeCatalog::class)->sitemapEntries())->toBe([]);
});

it('sends private and search noindex in the original HTML and headers', function () {
    $this->get('/daily')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex, follow');
    \App\Models\Proverb::create(['text' => 'Bonjour', 'translation' => 'Здравствуйте']);
    $this->actingAs($this->admin)->get('/profile')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
it('allows lower level sessions as extra practice without changing the profile level', function () {
    $profile = LearningProfile::factory()->create(['user_id' => $this->admin->id, 'current_level' => 'B1']);
    $service = app(\App\Services\Learning\AccountRunService::class);
    $input = ['client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $profile->revision, 'kind' => 'extra', 'session_id' => $this->sessions[0]->external_id];
    $result = $service->start($this->admin, $input);
    expect($result['state']['kind'])->toBe('extra')->and($profile->fresh()->getRawOriginal('current_level'))->toBe('B1');
    $repeat = $service->start($this->admin, [...$input, 'client_event_id' => (string) \Illuminate\Support\Str::uuid(), 'expected_revision' => $profile->fresh()->revision, 'session_id' => $this->sessions[1]->external_id]);
    expect($repeat['state']['kind'])->toBe('extra')->and($profile->fresh()->getRawOriginal('current_level'))->toBe('B1');
});

it('uses 503 for an interrupted active publication rather than claiming the page was removed', function () {
    $first = $this->sessions[0];
    $first->update(['active_version_id' => null]);
    $this->get(PracticeUrls::session($first))->assertStatus(503);
});

it('passes the current header identity at every catalog depth without exposing account details or caching it', function () {
    $other = User::factory()->create(['name' => 'Другой читатель']);
    $paths = ['/practice', PracticeUrls::level('PRE_A1'), PracticeUrls::unit($this->unit), PracticeUrls::session($this->sessions[0])];
    foreach ($paths as $path) {
        foreach ([$this->admin, $other] as $user) {
            $this->actingAs($user)->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertInertia(fn (Assert $page) => $page->component('Practice/Index', false)
                    ->where('authUser.id', $user->id)->where('authUser.name', $user->name)
                    ->where('authUser.avatar_link', $user->avatar_link)->where('authUser.is_admin', $user->is_admin)
                    ->where('auth.user.id', $user->id)->missing('authUser.email')->missing('auth.user.email')
                    ->missing('authUser.password')->missing('auth.user.password'));
        }
        auth()->forgetGuards();
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->where('authUser', null)->where('auth.user', null));
    }
    expect(LearningProfile::count())->toBe(0)->and(LearningRun::count())->toBe(0);
});

it('accepts login and logout POST requests from public practice without losing authentication state', function () {
    $this->admin->update(['password' => Illuminate\Support\Facades\Hash::make(md5('Practice-check-42!'))]);
    $this->post('/login', ['email' => $this->admin->email, 'password' => 'Practice-check-42!'], ['Referer' => 'http://localhost:5000/practice'])->assertRedirect('/practice');
    $this->assertAuthenticatedAs($this->admin);
    $this->get('/practice')->assertOk()->assertInertia(fn (Assert $page) => $page->where('authUser.id', $this->admin->id));
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
    $this->get('/practice')->assertOk()->assertInertia(fn (Assert $page) => $page->where('authUser', null));
});

it('shares a route selection from the public entry with the clean canonical', function () {
    $query = http_build_query(['level' => 'PRE_A1', 'unit' => $this->unit->external_id]);
    $this->get('/practice?'.$query)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('href="http://localhost:5000/practice"', false)
        ->assertSee('Учиться')->assertSee('Повторять')->assertSee('Мой прогресс')
        ->assertInertia(fn (Assert $page) => $page->component('Practice/Index')
            ->where('routeSelection.level', 'PRE_A1')->where('routeSelection.unit', $this->unit->external_id)
            ->where('accessOutline', []));
    foreach (['/daily?'.$query => 'Daily/Index', '/daily/vocabulary' => 'Daily/Vocabulary', '/daily/progress' => 'Daily/Index'] as $path => $component) {
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
    expect(LearningRun::count())->toBe(0)->and(LearningProfile::count())->toBe(0);
});

it('searches published lesson titles without granting access or completion', function () {
    $first = $this->sessions[0];
    $second = $this->sessions[1];
    $search = '/daily/api/route/search?'.http_build_query(['q' => $second->activeVersion->payload['title']]);
    $reference = fn ($lesson) => '/daily/api/route/reference?'.http_build_query(['path' => PracticeUrls::session($lesson)]);

    $this->getJson($search)->assertUnauthorized();
    $this->getJson($reference($first))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonMissingPath('tasks')->assertJsonMissingPath('vocabulary');
    $this->actingAs($this->admin)->getJson($search)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('items.0.id', $second->external_id)->assertJsonPath('items.0.status', 'locked');
    $this->getJson($reference($second))->assertNotFound();

    LearningProfile::factory()->create(['user_id' => $this->admin->id, 'current_level' => 'B1']);
    $this->getJson($search)->assertOk()->assertJsonPath('items.0.status', 'available');
    $this->getJson($search.'&completed=1')->assertOk()->assertJsonCount(0, 'items');
    $this->getJson($reference($second))->assertOk()->assertJsonPath('title', $second->activeVersion->payload['title']);
    $this->getJson('/daily/api/route/reference?path=https://example.com')->assertUnprocessable();
    expect(LearningRun::count())->toBe(0)->and(LearningProfile::first()->getRawOriginal('current_level'))->toBe('B1');
});
