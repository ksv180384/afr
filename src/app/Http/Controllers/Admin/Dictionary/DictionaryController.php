<?php

namespace App\Http\Controllers\Admin\Dictionary;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Dictionary\SearchResource;
use App\Http\Resources\Admin\Dictionary\WordEditResource;
use App\Http\Resources\Admin\Dictionary\WordResource;
use App\Http\Resources\Admin\WordsPartOfSpeech\PartOfSpeechFilterResource;
use App\Http\Resources\PaginateResource;
use App\Models\Word\WordsPartOfSpeech;
use App\Services\App\WordService;
use App\Services\App\WordsPartOfSpeechService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DictionaryController extends Controller
{
    /**
     * @param WordService $wordService
     * @return \Inertia\Response
     */
    public function index(WordService $wordService, WordsPartOfSpeechService $wordsPartOfSpeechService): Response
    {
        $authUser = Helper::getUserData();
        $words = $wordService->getWordsPaginate();
        $PartOfSpeeches = $wordsPartOfSpeechService->getAll();

        return Inertia::render('Dictionary/Dictionary', [
            'authUser' => $authUser,
            'filter' => [
                'part_of_speeches' => PartOfSpeechFilterResource::collection($PartOfSpeeches),
            ],
            'words' => WordResource::collection($words->items()),
            'pagination' => PaginateResource::make($words),
        ]);
    }

    public function create()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Dictionary/DictionaryCreate', [
            'authUser' => $authUser,
        ]);
    }

    public function store(WordService $wordService)
    {

    }

    public function edit(int $id, WordService $wordService)
    {
        $authUser = Helper::getUserData();
        $word = $wordService->getById($id);

        return Inertia::render('Dictionary/DictionaryEdit', [
            'authUser' => $authUser,
            'word' => WordEditResource::make($word),
        ]);
    }

    public function update(int $id)
    {

    }

    public function delete(int $id)
    {

    }

    public function search(WordService $wordService)
    {
        $words = $wordService->searchFrAll();

        return response()->json([
            'message' => $words->isEmpty() ? 'Подходящих вариантов не найдено.' : '',
            'words' => SearchResource::collection($words)
        ]);
    }
}
