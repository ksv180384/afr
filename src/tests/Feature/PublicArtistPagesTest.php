<?php

use App\Models\Player\PlayerArtistLineup;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Models\Proverb;
use App\Services\App\Sitemap\SitemapService;

beforeEach(function () {
    Proverb::create([
        'text' => 'Petit à petit, l’oiseau fait son nid.',
        'translation' => 'Маленькими шагами достигается цель.',
    ]);
});

test('lyrics catalog and song pages are unavailable even for visible songs', function () {
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

    $this->get('/lyrics')->assertNotFound();
    $this->get('/lyrics/item/'.$song->id)->assertNotFound();
});

test('artist pages are unavailable even when visible songs and lineups exist', function () {
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

    $this->get('/lyrics/artist/'.$jeck->id)->assertNotFound();
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

    $this->get('/lyrics/artist/'.$artist->id)->assertNotFound();
});

test('sitemap excludes the lyrics catalog and all artist and song pages', function () {
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

    expect($xml)->not->toContain('/lyrics');
});

test('removed artist detail route stays unavailable for malformed identifiers', function () {
    $this->get('/lyrics/artist/1.')->assertNotFound();
});

test('music player widget is unavailable and absent from the homepage', function () {
    $this->get('/widget/player')->assertNotFound();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('/widget/player', false);
});
