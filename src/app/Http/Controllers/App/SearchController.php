<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\App\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /**
     * @param Request $request
     * @param SearchService $searchService
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAll(Request $request, SearchService $searchService): JsonResponse
    {

        $text = $request->input('text', '');
        $type = $request->input('type', '');
        $lang = Str::lower($request->input('lang', ''));

        $result = $searchService->search($text, $type, $lang);

        return response()->json(['search' => $result]);
    }
}
