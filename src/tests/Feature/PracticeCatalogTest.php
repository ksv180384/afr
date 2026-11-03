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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['daily_practice.enabled' => true, 'app.url' => 'http://localhost:5000']);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    LearningLevel::where('code', 'PRE_A1')->update(['published_at' => now('UTC'), 'summary' => 'Начните знакомство с французским.']);
    $data = json_decode(file_get_contents(resource_path('learning/templates/pre-a1-u01-examples.json')), true);
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
