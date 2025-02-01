<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\DictionaryWordsFrResource;
use App\Http\Resources\App\DictionaryWordsRuResource;
use App\Http\Resources\PaginateResource;
use App\Services\App\WordService;
use App\Services\App\WordsPartOfSpeechService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DictionaryController extends Controller
{
    public function index(
        Request $request,
        WordService $wordService,
        WordsPartOfSpeechService $wordsPartOfSpeechService,
    )
    {
        $authUser = Helper::getUserData();

        $partsOfSpeech = $request->parts_of_speech ?? null;
        $lang = $request->lang ?? 'fr';

        $dictionaryWordsList = $wordService->getWordsPaginate($partsOfSpeech, $lang);
        $partOfSpeechList = $wordsPartOfSpeechService->getAll();
        $pagination = PaginateResource::make($dictionaryWordsList);

        $dictionaryWords = $lang === 'ru'
            ? DictionaryWordsRuResource::collection($dictionaryWordsList->items())
            : DictionaryWordsFrResource::collection($dictionaryWordsList->items());


        return Inertia::render('Dictionary/Dictionary', [
            'authUser' => $authUser,
            'dictionaryWords' => $dictionaryWords,
            'partOfSpeechList' => $partOfSpeechList,
            'lang' => $lang,
            'pagination' => $pagination,
            'query' => request()->all(),
        ]);
    }

    public function show(int $id, WordService $wordService)
    {
        $word = $wordService->getById($id);
        $authUser = Helper::getUserData();

        return Inertia::render('Dictionary/DictionaryShow', [
            'authUser' => $authUser,
            'word' => $word,
        ]);
    }
}
