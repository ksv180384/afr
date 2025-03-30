<?php

namespace App\Http\Controllers\Admin\Grammar;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Grammar\CreateGrammarRequest;
use App\Http\Requests\Admin\Grammar\UpdateGrammarRequest;
use App\Http\Resources\Admin\Grammar\GrammarEditResource;
use App\Http\Resources\Admin\Grammar\GrammarResource;
use App\Services\Admin\Grammar\GrammarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class GrammarController extends Controller
{
    /**
     * @param GrammarService $grammarService
     * @return Response
     */
    public function index(GrammarService $grammarService): Response
    {
        $authUser = Helper::getUserData();
        $grammars = $grammarService->getGrammars();

        return Inertia::render('Grammar/Grammars', [
            'authUser' => $authUser,
            'grammars' => GrammarResource::collection($grammars),
        ]);
    }

    /**
     * @return Response
     */
    public function create(): Response
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Grammar/GrammarCreate', [
            'authUser' => $authUser,
        ]);
    }

    /**
     * @param CreateGrammarRequest $request
     * @param GrammarService $grammarService
     * @return RedirectResponse
     */
    public function store(CreateGrammarRequest $request, GrammarService $grammarService): RedirectResponse
    {
        $grammarData = $request->validated();

        $grammar = $grammarService->create($grammarData);

        return Redirect::route('admin.grammars.edit', ['id' => $grammar->id]);
    }

    /**
     * @param int $id
     * @param GrammarService $grammarService
     * @return Response
     */
    public function edit(int $id, GrammarService $grammarService): Response
    {
        $authUser = Helper::getUserData();
        $grammar = $grammarService->getById($id);

        return Inertia::render('Grammar/GrammarEdit', [
            'authUser' => $authUser,
            'grammar' => GrammarEditResource::make($grammar),
        ]);
    }

    /**
     * @param int $id
     * @param UpdateGrammarRequest $request
     * @param GrammarService $grammarService
     * @return RedirectResponse
     */
    public function update(int $id, UpdateGrammarRequest $request, GrammarService $grammarService): RedirectResponse
    {
        $grammarData = $request->validated();
        $grammar = $grammarService->update($id, $grammarData);

        return Redirect::route('admin.grammars.edit', ['id' => $grammar->id]);
    }

    public function delete()
    {

    }
}
