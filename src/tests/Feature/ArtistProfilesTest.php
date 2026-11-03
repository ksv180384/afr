<?php

use App\Models\Player\PlayerArtistLineup;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Services\Admin\Artist\ArtistLineupService;
use App\Services\Admin\Artist\ArtistProfileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('artist image is converted to a 312 by 312 webp file', function () {
    Storage::fake('public');
    $artist = PlayerArtistsSong::create(['name' => 'Jeck']);

    app(ArtistProfileService::class)->updateArtist(
        $artist,
        ['description' => 'Описание исполнителя'],
        UploadedFile::fake()->image('photo.png', 900, 500),
    );

    $artist->refresh();

    expect($artist->description)->toBe('Описание исполнителя')
        ->and($artist->image_path)->toStartWith('artists/')
        ->and($artist->image_path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($artist->image_path);
    [$width, $height, $type] = getimagesize(Storage::disk('public')->path($artist->image_path));
    expect($width)->toBe(312)
        ->and($height)->toBe(312)
        ->and($type)->toBe(IMAGETYPE_WEBP);
});

test('exact artist lineups are shared by songs and differ for different member sets', function () {
    $jeck = PlayerArtistsSong::create(['name' => 'Jeck']);
    $carla = PlayerArtistsSong::create(['name' => 'Carla']);
    $mia = PlayerArtistsSong::create(['name' => 'Mia']);

    $duet = PlayerSongs::create([
        'artist_name' => 'Jeck, Carla',
        'title' => 'Duet',
        'text_fr' => 'Bonjour',
        'text_ru' => 'Привет',
        'text_transcription' => 'Бонжур',
    ]);
    $otherDuet = $duet->replicate(['title']);
    $otherDuet->title = 'Second duet';
    $otherDuet->save();
    $trio = $duet->replicate(['title']);
    $trio->title = 'Trio';
    $trio->save();

    $service = app(ArtistLineupService::class);
    $service->syncForSong($duet, collect([$jeck, $carla]));
    $service->syncForSong($otherDuet, collect([$carla, $jeck]));
    $service->syncForSong($trio, collect([$jeck, $carla, $mia]));

    $duet->refresh();
    $otherDuet->refresh();
    $trio->refresh();

    expect($duet->artist_lineup_id)->toBe($otherDuet->artist_lineup_id)
        ->and($trio->artist_lineup_id)->not->toBe($duet->artist_lineup_id)
        ->and(PlayerArtistLineup::count())->toBe(2);
});
