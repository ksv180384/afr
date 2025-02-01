<?php

namespace App\Http\Controllers\App\Word;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Word\WordResource;
use App\Http\Resources\Api\V1\Word\WordTestResource;
use App\Http\Resources\App\SearchWordResource;
use App\Http\Resources\PaginateResource;
use App\Services\App\ProverbService;
use App\Services\App\WordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WordController extends Controller
{
    public function search(Request $request, WordService $wordService, ProverbService $proverbService)
    {
        $searchText = $request->text;
        $lang = Helper::isLatin($searchText) ? 'fr' : 'ru';
        $authUser = Helper::getUserData();
        $wordsSearch = Str::length($searchText) > 2 ? $wordService->getWordsPaginate(null, $lang) : null;
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();

        return Inertia::render('Word/SearchWord', [
            'authUser' => $authUser,
            'words' => $words,
            'proverb' => $proverb,
            'searchText' => $searchText,
            'wordSearch' => $wordsSearch ? SearchWordResource::collection($wordsSearch->items()) : null,
            'pagination' => $wordsSearch ? PaginateResource::make($wordsSearch) : null,
        ]);
    }

    /**
     * @param WordService $wordService
     * @return \Illuminate\Http\JsonResponse
     */
    public function randomList(WordService $wordService): JsonResponse
    {
        $words = $wordService->wordsRandom(['id', 'word', 'translation', 'transcription']);

        return response()->json([
            'words' => WordResource::collection($words),
        ]);
    }

    /**
     * @param WordService $wordService
     * @return JsonResponse
     */
    public function testYourSelf(WordService $wordService): JsonResponse
    {

        $wordsList = $wordService->wordsRandom(['id', 'word', 'translation', 'transcription'], 50);
        $testWords = $wordService->wordsListToWordsTestYourSelf($wordsList->toArray());

        return response()->json(['words' => WordTestResource::collection($testWords)]);
    }
}
