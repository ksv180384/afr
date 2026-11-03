<?php

use App\Models\KaraokeUploadLog;
use App\Models\Player\PlayerSongs;
use App\Models\User\User;
use App\Services\Admin\KaraokeUploadLog\KaraokeUploadLogService;

test('karaoke upload logs can exclude records created by Admin', function () {
    $song = PlayerSongs::create([
        'artist_name' => 'Test Artist',
        'title' => 'Test Song',
        'duration' => 3.00,
        'text_fr' => '[00:00]Principal',
        'text_ru' => '[00:00]Основной',
        'text_transcription' => '[00:00]Транскрипция',
        'hidden' => false,
    ]);

    [$admin, $user] = User::withoutEvents(fn () => [
        User::create([
            'name' => 'Admin',
            'email' => 'admin-filter@example.com',
            'password' => 'password',
        ]),
        User::create([
            'name' => 'Listener',
            'email' => 'listener-filter@example.com',
            'password' => 'password',
        ]),
    ]);

    foreach ([$admin->id, $user->id, null] as $index => $userId) {
        KaraokeUploadLog::create([
            'song_id' => $song->id,
            'song_title' => $song->title,
            'song_artist' => $song->artist_name,
            'file_name' => "recording-{$index}.mp3",
            'file_duration_seconds' => 240,
            'duration_matched' => false,
            'user_id' => $userId,
        ]);
    }

    $service = app(KaraokeUploadLogService::class);

    expect($service->getLogsPagination(100)->total())->toBe(3);

    $filteredLogs = $service->getLogsPagination(100, ['exclude_admin' => true]);

    expect($filteredLogs->total())->toBe(2)
        ->and($filteredLogs->pluck('user_id')->all())->toContain($user->id, null)
        ->not->toContain($admin->id);
});
