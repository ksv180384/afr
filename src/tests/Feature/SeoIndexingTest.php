<?php

use App\Support\InertiaSeoMeta;
use Illuminate\Http\Request;

test('dictionary word pages are marked noindex', function () {
    $meta = InertiaSeoMeta::make([
        'component' => 'Dictionary/DictionaryShow',
        'props' => [
            'word' => [
                'word' => 'bonjour',
                'translation' => 'привет',
                'transcription' => '[bɔ̃ʒuʁ]',
            ],
        ],
    ], Request::create('/dictionary/word/417'));

    expect($meta['noIndex'])->toBeTrue();
});

test('default seo description does not promote the removed lyrics section', function () {
    $meta = InertiaSeoMeta::make([
        'component' => 'Index/Index',
        'props' => [],
    ], Request::create('/'));

    expect($meta['description'])->not->toContain('тексты песен');
});

test('removed lyrics routes and malformed dictionary routes are unavailable', function () {
    $this->get('/lyrics/item/408.')->assertNotFound();
    $this->get('/dictionary/word/417.')->assertNotFound();
});
