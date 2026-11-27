<?php

namespace App\Http\Controllers\App\Info;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\App\ProverbService;
use App\Services\App\WordService;
use App\Support\LegalDocuments;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class InfoController extends Controller
{
    public function show(string $document, WordService $words, ProverbService $proverbs): Response
    {
        $title = LegalDocuments::TITLES[$document];

        return Inertia::render('Info/LegalDocument', [
            'title' => $title,
            'document' => $document,
            'revision' => CarbonImmutable::parse(LegalDocuments::VERSION)->locale('ru')->isoFormat('D MMMM YYYY [года]'),
            'content' => view('legal.'.$document)->render(),
            'words' => $words->wordsRandom(),
            'proverb' => $proverbs->proverbRandom()->first(),
            'authUser' => Helper::getUserData(),
        ]);
    }
}
