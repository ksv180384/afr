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

test('lyrics index fallback contains crawlable song links', function () {
    $html = view('seo.lyrics-index-noscript', [
        'artists' => [[
            'name' => 'Zaz',
            'songs' => [[
                'id' => 408,
                'title' => 'Je veux',
            ]],
        ]],
    ])->render();

    expect($html)
        ->toContain('/lyrics/item/408"')
        ->toContain('Je veux');
});

test('lyrics and dictionary detail routes accept numeric identifiers only', function () {
    $this->get('/lyrics/item/408.')->assertNotFound();
    $this->get('/dictionary/word/417.')->assertNotFound();
});
