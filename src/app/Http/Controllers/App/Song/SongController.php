<?php

namespace App\Http\Controllers\App\Song;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SongText\SearchSongResource;
use App\Http\Resources\Api\V1\SongText\SearchSongTextResource;
use App\Http\Resources\PaginateResource;
use App\Models\Player\PlayerSearchSong;
use App\Services\App\ProverbService;
use App\Services\App\Song\SongService;
use App\Services\App\WordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    /**
     * @param Request $request
     * @param SongService $songService
     * @param WordService $wordService
     * @param ProverbService $proverbService
     * @return \Inertia\Response
     */
    public function search(
        Request $request,
        SongService $songService,
        WordService $wordService,
        ProverbService $proverbService
    ): Response
    {
        $searchText = $request->text;

        $authUser = Helper::getUserData();
        $songsSearch = $songService->getSongsPagination(SongService::SONGS_PAGINATE);
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();

        return Inertia::render('Song/SearchSong', [
            'authUser' => $authUser,
            'words' => $words,
            'proverb' => $proverb,
            'searchText' => $searchText,
            'songsSearch' => SearchSongResource::collection($songsSearch->items()),
            'pagination' => PaginateResource::make($songsSearch),
        ]);
    }

    /**
     * @param Request $request
     * @param SongService $songService
     * @return JsonResponse
     */
    public function searchByArtistAndTitle(Request $request, SongService $songService): JsonResponse
    {
        $artist = $request->query('artist', '');
        $title = $this->removeSymbols($request->query('title', ''));
        $fileName = $request->query('file_name', '');

        $song = $songService->searchByArtistAndTitle($artist, $title, $fileName);

        if(empty($song)){
            PlayerSearchSong::create([
                'artist' => $artist,
                'title' => $title,
                'title_file' => $fileName,
            ]);
        }

        return response()->json([
            'song' => $song ? SearchSongTextResource::make($song) : null,
        ]);
    }

    public function searchHints(Request $request, SongService $songService): JsonResponse
    {
        $search = $request->query('search', '');

        $songs = $songService->search($search);

        return response()->json([
            'songs' => $songs ? SearchSongResource::collection($songs) : null,
        ]);
    }

    public function show(int $id, SongService $songService): JsonResponse
    {
        $song = $songService->getById($id);

        return response()->json([
            'song' => $song ? SearchSongTextResource::make($song) : null,
        ]);
    }

    private function removeSymbols(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}\s\']/u', '', $text));
    }
}
