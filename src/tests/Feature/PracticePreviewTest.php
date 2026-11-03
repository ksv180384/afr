<?php

use App\Models\Learning\LearningRun;
use Inertia\Testing\AssertableInertia as Assert;

it('offers the two examples and five task types locally without writing learning state', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->withoutVite();
    $this->get('/practice/preview')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page->component('Practice/Preview', false)->has('sessions', 3));
    expect(LearningRun::count())->toBe(0);
    $this->assertDatabaseCount('personal_vocabulary', 0);
});

it('never exposes the local demo outside the local environment', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    $this->get('/practice/preview')->assertNotFound();
})->with(['production', 'testing', 'staging']);
