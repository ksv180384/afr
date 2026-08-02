<?php

use App\Http\Resources\App\SongShowResource;
use App\Models\KaraokeUploadLog;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Services\Admin\Song\SongService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function karaokeSong(array $attributes = []): PlayerSongs
{
    return PlayerSongs::create(array_merge([
        'artist_name' => 'Test Artist',
        'title' => 'Test Song',
        'duration' => 3.00,
        'text_fr' => '[00:00]Principal',
        'text_ru' => '[00:00]Основной',
        'text_transcription' => '[00:00]Транскрипция',
        'hidden' => false,
    ], $attributes));
}

test('an unmatched karaoke recording saves its log before the audio upload', function () {
    Storage::fake('local');
    $song = karaokeSong();

    $logResponse = $this->post(route('lyrics.karaoke-upload-log'), [
        'song_id' => $song->id,
        'song_title' => $song->title,
        'song_artist' => $song->artist_name,
        'file_name' => 'other-version.mp3',
        'file_duration_seconds' => 205,
        'db_duration_seconds' => 180,
    ]);

    $logResponse->assertOk()->assertJson([
        'duration_matched' => false,
        'upload_required' => true,
    ]);

    $log = KaraokeUploadLog::firstOrFail();
    expect($log->file_path)->toBeNull()
        ->and($log->upload_token)->not->toBeNull();

    $uploadResponse = $this->post(route('lyrics.karaoke-upload-log.file', ['log' => $log->id]), [
        'upload_token' => $logResponse->json('upload_token'),
        'audio' => UploadedFile::fake()->create('other-version.mp3', 100, 'audio/mpeg'),
    ]);

    $uploadResponse->assertOk();
    $log->refresh();
    expect($log->file_path)->not->toBeNull()
        ->and($log->upload_token)->toBeNull();
    Storage::disk('local')->assertExists($log->file_path);
});

test('an interrupted audio upload still leaves the unmatched-version log', function () {
    $song = karaokeSong();

    $this->post(route('lyrics.karaoke-upload-log'), [
        'song_id' => $song->id,
        'song_title' => $song->title,
        'song_artist' => $song->artist_name,
        'file_name' => 'slow-connection.mp3',
        'file_duration_seconds' => 240,
        'db_duration_seconds' => 180,
    ])->assertOk();

    $this->assertDatabaseHas('karaoke_upload_logs', [
        'song_id' => $song->id,
        'file_name' => 'slow-connection.mp3',
        'duration_matched' => false,
        'file_path' => null,
    ]);
});

test('an additional lyrics version does not request a matching recording upload', function () {
    Storage::fake('local');
    $song = karaokeSong();
    $song->lyricsVersions()->create([
        'duration' => 3.50,
        'text_fr' => '[00:00]Version longue',
        'text_ru' => '[00:00]Длинная версия',
        'text_transcription' => '[00:00]Транскрипция',
    ]);

    $response = $this->post(route('lyrics.karaoke-upload-log'), [
        'song_id' => $song->id,
        'song_title' => $song->title,
        'song_artist' => $song->artist_name,
        'file_name' => 'matching.mp3',
        'file_duration_seconds' => 211,
        'db_duration_seconds' => 180,
    ]);

    $response->assertOk()->assertJson([
        'duration_matched' => true,
        'upload_required' => false,
        'upload_token' => null,
    ]);
    expect(KaraokeUploadLog::firstOrFail()->file_path)->toBeNull();
});

test('an audio upload requires the one-time token for its log', function () {
    $song = karaokeSong();
    $log = KaraokeUploadLog::create([
        'song_id' => $song->id,
        'song_title' => $song->title,
        'song_artist' => $song->artist_name,
        'file_name' => 'protected.mp3',
        'file_duration_seconds' => 240,
        'duration_matched' => false,
        'upload_token' => str_repeat('a', 64),
    ]);

    $this->post(route('lyrics.karaoke-upload-log.file', ['log' => $log->id]), [
        'upload_token' => str_repeat('b', 64),
        'audio' => UploadedFile::fake()->create('protected.mp3', 100, 'audio/mpeg'),
    ])->assertNotFound();
});

test('admin song update creates and removes additional lyrics versions', function () {
    $artist = PlayerArtistsSong::create(['name' => 'Test Artist']);
    $song = karaokeSong();
    $song->artists()->attach($artist->id, ['position' => 0]);

    $payload = [
        'artists' => [['id' => $artist->id, 'name' => null]],
        'title' => $song->title,
        'duration' => '3:00',
        'text_fr' => $song->text_fr,
        'text_ru' => $song->text_ru,
        'text_transcription' => $song->text_transcription,
        'hidden' => false,
        'lyrics_versions' => [[
            'id' => null,
            'duration' => '3:30',
            'text_fr' => '[00:00]Version longue',
            'text_ru' => '[00:00]Длинная версия',
            'text_transcription' => '[00:00]Транскрипция',
        ]],
    ];

    app(SongService::class)->update($song->id, Request::create('/', 'POST', $payload));
    $song->refresh()->load('lyricsVersions');

    expect($song->lyricsVersions)->toHaveCount(1)
        ->and($song->lyricsVersions->first()->duration)->toBe(3.5)
        ->and((new SongShowResource($song))->resolve()['lyrics_versions'])->toHaveCount(2);

    $payload['lyrics_versions'] = [];
    app(SongService::class)->update($song->id, Request::create('/', 'POST', $payload));

    expect($song->lyricsVersions()->count())->toBe(0);
});
