<?php

use App\Models\Player\PlayerArtistLineup;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Models\Proverb;
use App\Services\App\Sitemap\SitemapService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Proverb::create([
        'text' => 'Petit à petit, l’oiseau fait son nid.',
        'translation' => 'Маленькими шагами достигается цель.',
    ]);
});

test('lyrics catalog contains only artists with visible songs and accepts missing profile fields', function () {
    $artist = PlayerArtistsSong::create([
        'name' => 'Zaz',
        'description' => null,
        'image_path' => null,
    ]);
    $song = PlayerSongs::create([
        'artist_name' => 'Zaz',
        'title' => 'Je veux',
        'text_fr' => 'Je veux',
        'text_ru' => 'Я хочу',
        'text_transcription' => 'Жё вё',
        'hidden' => false,
    ]);
    $song->artists()->attach($artist->id, ['position' => 0]);

    PlayerArtistsSong::create(['name' => 'Artist without songs']);

    $this->get(route('lyrics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lyrics/Lyrics', false)
            ->has('artists', 1)
            ->where('artists.0.id', $artist->id)
            ->where('artists.0.name', 'Zaz')
            ->where('artists.0.image_url', null)
            ->where('artists.0.songs_count', 1)
            ->where('artists.0.songs.0.id', $song->id));
});

test('artist page renders songs and lineups when descriptions and images are absent', function () {
    $jeck = PlayerArtistsSong::create(['name' => 'Jeck']);
    $carla = PlayerArtistsSong::create(['name' => 'Carla']);
    $lineup = PlayerArtistLineup::create(['signature' => $jeck->id.':'.$carla->id]);
    $lineup->artists()->attach([$jeck->id, $carla->id]);

    $song = PlayerSongs::create([
        'artist_name' => 'Jeck, Carla',
        'title' => 'M’envoler',
        'text_fr' => 'Je veux m’envoler',
        'text_ru' => 'Я хочу улететь',
        'text_transcription' => 'Жё вё манволе',
        'hidden' => false,
    ]);
    $song->artists()->attach([
        $jeck->id => ['position' => 0],
        $carla->id => ['position' => 1],
    ]);
    $song->forceFill(['artist_lineup_id' => $lineup->id])->save();

    $this->get(route('lyrics.artist.show', ['id' => $jeck->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lyrics/ArtistShow', false)
            ->where('artist.id', $jeck->id)
            ->where('artist.description', null)
            ->where('artist.image_url', null)
            ->where('artist.songs.0.id', $song->id)
            ->has('artist.lineups', 1)
            ->where('artist.lineups.0.description', null)
            ->where('artist.lineups.0.image_url', null)
            ->has('artist.lineups.0.artists', 2)
            ->where('artist.lineups.0.songs.0.id', $song->id));
});

test('artist page is unavailable when artist has no visible songs', function () {
    $artist = PlayerArtistsSong::create(['name' => 'Hidden Artist']);
    $song = PlayerSongs::create([
        'artist_name' => 'Hidden Artist',
        'title' => 'Hidden Song',
        'text_fr' => 'Texte',
        'text_ru' => 'Текст',
        'text_transcription' => 'Тэкст',
        'hidden' => true,
    ]);
    $song->artists()->attach($artist->id, ['position' => 0]);

    $this->get(route('lyrics.artist.show', ['id' => $artist->id]))->assertNotFound();
});

test('sitemap contains public artist pages with visible songs', function () {
    if (! class_exists(\Spatie\Sitemap\Sitemap::class)) {
        $this->markTestSkipped('Composer dependencies in the container are not synchronized.');
    }

    $artist = PlayerArtistsSong::create(['name' => 'Clara Luciani']);
    $song = PlayerSongs::create([
        'artist_name' => 'Clara Luciani',
        'title' => 'La grenade',
        'text_fr' => 'Hé toi',
        'text_ru' => 'Эй, ты',
        'text_transcription' => 'Э туа',
        'hidden' => false,
    ]);
    $song->artists()->attach($artist->id, ['position' => 0]);

    $xml = app(SitemapService::class)->renderXml();

    expect($xml)->toContain(route('lyrics.artist.show', ['id' => $artist->id]));
});

test('artist detail route accepts numeric identifiers only', function () {
    $this->get('/lyrics/artist/1.')->assertNotFound();
});
