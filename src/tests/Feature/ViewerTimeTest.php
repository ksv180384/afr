<?php

use App\Models\Post\Post;
use App\Models\Post\PostStatus;
use App\Models\PostComment\PostComment;
use App\Models\Proverb;
use App\Models\User\Rang;
use App\Models\User\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('posts and comments display the same viewer time on public and moderation pages', function () {
    $this->withoutVite();
    Proverb::query()->create(['text' => 'Bonjour', 'translation' => 'Привет']);
    $adminRank = Rang::query()->firstOrCreate(['alias' => 'administrator'], ['title' => 'Администратор']);
    $admin = User::factory()->create(['rang_id' => $adminRank->id, 'timezone' => 'Europe/Paris']);
    $author = User::factory()->create(['timezone' => 'America/Los_Angeles']);
    $status = PostStatus::query()->create(['alias' => 'published', 'title' => 'Опубликован']);
    $post = Post::query()->create([
        'user_id' => $author->id,
        'status_id' => $status->id,
        'title' => 'Bonjour',
        'preview' => 'Bonjour !',
        'content' => '<p>Bonjour !</p>',
    ]);
    $comment = PostComment::query()->create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'comment' => 'Salut !',
    ]);

    $created = CarbonImmutable::parse('2026-07-03T22:30:00Z')->setTimezone(date_default_timezone_get());
    $updated = $created->addHour();
    foreach ([$post, $comment] as $record) {
        $record->forceFill(['created_at' => $created, 'updated_at' => $updated])->save();
    }

    $path = route('post.show', ['id' => $post->id]);
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('post.created_at', '04.07.2026 01:30')
        ->where('post.updated_at', '04.07.2026 02:30')
        ->where('comments.0.created_at', '04.07.2026 01:30')
        ->where('post.created_at_iso', $created->toIso8601String()));

    $this->actingAs($admin);
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('posts.0.created_at', '04.07.2026 00:30'));
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('post.created_at', '04.07.2026 00:30')
        ->where('comments.0.created_at', '04.07.2026 00:30')
        ->where('comments.0.updated_at', '04.07.2026 01:30')
        ->where('post.created_at_iso', $created->toIso8601String()));
    $this->get(route('admin.posts'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('posts.0.created_at', '04.07.2026 00:30')
        ->where('posts.0.updated_at', '04.07.2026 01:30'));
    $this->get(route('admin.posts-comments'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('comments.0.created_at', '04.07.2026 00:30')
        ->where('comments.0.updated_at', '04.07.2026 01:30'));
});
