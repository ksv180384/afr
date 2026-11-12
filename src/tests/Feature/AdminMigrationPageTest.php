<?php

test('migration administration routes require authentication', function () {
    $this->get(route('admin.migrations'))
        ->assertRedirect(route('login'));

    $this->post(route('admin.migrations.run'))
        ->assertRedirect(route('login'));
});

test('a verified administrator can log in and apply the pending practice migration', function () {
    $this->withoutVite();
    $name = '2026_11_06_120000_add_review_suggestions_to_learning_profiles';
    (require database_path('migrations/'.$name.'.php'))->down();
    \Illuminate\Support\Facades\DB::table('migrations')->where('migration', $name)->delete();

    $rank = \App\Models\User\Rang::firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = \App\Models\User\User::factory()->create(['rang_id' => $rank->id]);
    $profile = \App\Models\Learning\LearningProfile::factory()->create(['user_id' => $admin->id, 'revision' => 4]);

    $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($admin);
    $this->get(route('admin.migrations'))->assertOk()->assertSee($name);
    $this->post(route('admin.migrations.run'))->assertRedirect(route('admin.migrations'))
        ->assertSessionHas('migrationResult.success', true);
    $this->assertDatabaseHas('migrations', ['migration' => $name]);
    expect($profile->refresh()->revision)->toBe(4)
        ->and($profile->review_suggestions_enabled)->toBeTrue()
        ->and($profile->review_suggestions_snoozed_until)->toBeNull();
});
