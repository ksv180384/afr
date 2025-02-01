<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\App\GrammarService;
use Inertia\Inertia;

class GrammarController extends Controller
{
    public function index(GrammarService $grammarService)
    {
        $authUser = Helper::getUserData();
        $grammars = $grammarService->getTitles();

        return Inertia::render('Grammar/Grammar', [
            'authUser' => $authUser,
            'menu' => $grammars,
        ]);
    }

    public function show(int $id, GrammarService $grammarService)
    {
        $authUser = Helper::getUserData();
        $grammars = $grammarService->getTitles();
        $grammarContent = $grammarService->getById($id);

        return Inertia::render('Grammar/GrammarShow', [
            'authUser' => $authUser,
            'menu' => $grammars,
            'grammarContent' => $grammarContent,
        ]);
    }
}
