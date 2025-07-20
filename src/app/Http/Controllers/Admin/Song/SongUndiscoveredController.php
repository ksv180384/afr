<?php

namespace App\Http\Controllers\Admin\Song;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Song\SongResource;
use App\Http\Resources\Admin\Song\SongSearchResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\Song\SongSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SongUndiscoveredController extends Controller
{
    public function index(SongSearchService $searchService)
    {
        $authUser = Helper::getUserData();
        $songsSearch = $searchService->searchSongsPagination(SongSearchService::SONGS_PAGINATE);

        return Inertia::render('SongSearch/Songs', [
            'authUser' => $authUser,
            'songs' => SongSearchResource::collection($songsSearch->items()),
            'pagination' => PaginateResource::make($songsSearch),
        ]);
    }
}
