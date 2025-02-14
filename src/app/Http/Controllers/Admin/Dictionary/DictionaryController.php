<?php

namespace App\Http\Controllers\Admin\Dictionary;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dictionary\CreateDictionaryRequest;
use App\Http\Requests\Admin\Dictionary\UpdateDictionaryRequest;
use App\Http\Resources\Admin\Dictionary\SearchResource;
use App\Http\Resources\Admin\Dictionary\WordEditResource;
use App\Http\Resources\Admin\Dictionary\WordResource;
use App\Http\Resources\Admin\WordsPartOfSpeech\PartOfSpeechFilterResource;
use App\Http\Resources\Admin\WordsPartOfSpeech\PartOfSpeechSelectResource;
use App\Http\Resources\PaginateResource;
use App\Models\Word\WordsPartOfSpeech;
use App\Services\App\WordService;
use App\Services\App\WordsPartOfSpeechService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
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
        $partOfSpeeches = $wordsPartOfSpeechService->getAll();

        return Inertia::render('Dictionary/Dictionary', [
            'authUser' => $authUser,
            'filter' => [
                'part_of_speeches' => PartOfSpeechFilterResource::collection($partOfSpeeches),
            ],
            'words' => WordResource::collection($words->items()),
            'pagination' => PaginateResource::make($words),
        ]);
    }

    public function create(WordsPartOfSpeechService $wordsPartOfSpeechService): Response
    {
        $authUser = Helper::getUserData();
        $partOfSpeeches = $wordsPartOfSpeechService->getAll();

        return Inertia::render('Dictionary/DictionaryCreate', [
            'authUser' => $authUser,
            'partOfSpeeches' => PartOfSpeechSelectResource::collection($partOfSpeeches),
        ]);
    }

    public function store(CreateDictionaryRequest $request, WordService $wordService): RedirectResponse
    {
        $word = $wordService->create($request->validated());

        return Redirect::route('admin.dictionary.edit', ['id' => $word->id]);
    }

    public function edit(int $id, WordService $wordService, WordsPartOfSpeechService $wordsPartOfSpeechService): Response
    {
        $authUser = Helper::getUserData();
        $word = $wordService->getById($id);
        $partOfSpeeches = $wordsPartOfSpeechService->getAll();

        return Inertia::render('Dictionary/DictionaryEdit', [
            'authUser' => $authUser,
            'partOfSpeeches' => PartOfSpeechSelectResource::collection($partOfSpeeches),
            'word' => WordEditResource::make($word),
        ]);
    }

    public function update(int $id, UpdateDictionaryRequest $request, WordService $wordService)
    {
        $wordService->update($id, $request->validated());

        return Redirect::route('admin.dictionary.edit', ['id' => $id]);
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
