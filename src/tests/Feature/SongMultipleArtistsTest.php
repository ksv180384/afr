<?php

use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Services\Admin\Song\SongService as AdminSongService;
use App\Services\App\Song\SongService as AppSongService;
use Illuminate\Http\Request;

/** Формирует запрос с валидными данными песни для тестирования сервиса. */
function songPayload(array $artists): Request
{
    return Request::create('/admin/song/store', 'POST', [
        'artists' => $artists,
        'title' => 'Duet',
        'duration' => '2:36',
        'text_fr' => '[00:00]Bonjour',
        'text_ru' => '[00:00]Привет',
        'text_transcription' => '[00:00]Бонжур',
        'hidden' => false,
    ]);
}

test('a song stores multiple artists in order and builds artist name', function () {
    $first = PlayerArtistsSong::create(['name' => 'First Artist']);
    $second = PlayerArtistsSong::create(['name' => 'Second Artist']);

    $song = app(AdminSongService::class)->store(songPayload([
        ['id' => $second->id, 'name' => null],
        ['id' => $first->id, 'name' => null],
        ['id' => null, 'name' => 'New Artist'],
    ]));

    expect($song->artist_name)->toBe('Second Artist, First Artist, New Artist')
        ->and($song->artists->pluck('name')->all())
        ->toBe(['Second Artist', 'First Artist', 'New Artist']);

    $this->assertDatabaseHas('player_artist_song', [
        'song_id' => $song->id,
        'artist_id' => $second->id,
        'position' => 0,
    ]);
});

test('admin and public filters find a song by any linked artist', function () {
    $first = PlayerArtistsSong::create(['name' => 'Alpha']);
    $second = PlayerArtistsSong::create(['name' => 'Beta']);

    $song = PlayerSongs::create([
        'artist_name' => 'Alpha, Beta',
        'title' => 'Shared Song',
        'text_fr' => 'Bonjour',
        'text_ru' => 'Привет',
        'text_transcription' => 'Бонжур',
        'hidden' => false,
    ]);
    $song->artists()->attach([
        $first->id => ['position' => 0],
        $second->id => ['position' => 1],
    ]);

    request()->merge(['text' => 'Beta']);
    $adminSongs = app(AdminSongService::class)->getSongsPagination(40, true);
    $publicSongs = app(AppSongService::class)->search('Beta');

    expect($adminSongs->pluck('id')->all())->toBe([$song->id])
        ->and($publicSongs->pluck('id')->all())->toBe([$song->id]);
});

test('admin songs can be sorted by creation date in both directions', function () {
    $older = PlayerSongs::create([
        'artist_name' => 'Zulu',
        'title' => 'Older Song',
        'text_fr' => 'Bonjour',
        'text_ru' => 'Привет',
        'text_transcription' => 'Бонжур',
        'hidden' => false,
    ]);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $newer = PlayerSongs::create([
        'artist_name' => 'Alpha',
        'title' => 'Newer Song',
        'text_fr' => 'Salut',
        'text_ru' => 'Привет',
        'text_transcription' => 'Салю',
        'hidden' => false,
    ]);

    request()->replace(['sort' => 'created_desc']);
    expect(app(AdminSongService::class)->getSongsPagination(40, true)->pluck('id')->all())
        ->toBe([$newer->id, $older->id]);

    request()->replace(['sort' => 'created_asc']);
    expect(app(AdminSongService::class)->getSongsPagination(40, true)->pluck('id')->all())
        ->toBe([$older->id, $newer->id]);
});
