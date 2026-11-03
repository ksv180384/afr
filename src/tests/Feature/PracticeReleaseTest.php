<?php

use App\Models\User\Rang;
use App\Models\User\User;
use Database\Seeders\Learning\LearningLevelSeeder;
use Illuminate\Support\Facades\Http;

it('routes App and Admin SSR separately and resets the endpoint on the following public request', function () {
    config(['daily_practice.enabled' => true, 'inertia.ssr.enabled' => true,
        'inertia.ssr.public_url' => 'http://127.0.0.1:5010', 'inertia.ssr.admin_url' => 'http://127.0.0.1:13714']);
    $this->withoutVite();
    $this->seed(LearningLevelSeeder::class);
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:5010/render' => Http::response(['head' => [], 'body' => '<div>public-ssr-probe</div>']),
        'http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div>admin-ssr-probe</div>']),
    ]);
    $this->get('/daily')->assertOk()->assertSee('public-ssr-probe', false)->assertDontSee('admin-ssr-probe');
    $rank = Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $rank->id]);
    $this->actingAs($admin)->get('/admin/daily-practice')->assertOk()->assertSee('admin-ssr-probe', false)->assertDontSee('public-ssr-probe');
    $this->get('/daily')->assertOk()->assertSee('public-ssr-probe', false)->assertDontSee('admin-ssr-probe');
    Http::assertSentCount(3);
    expect(config('inertia.ssr.url'))->toBe('http://127.0.0.1:5010');
});
