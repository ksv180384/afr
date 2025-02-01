<?php

namespace App\Http\Controllers\Admin\Song;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Song\ArtistForSelectResource;
use App\Http\Resources\Admin\Song\SongEditResource;
use App\Http\Resources\Admin\Song\SongResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\Song\ArtistService;
use App\Services\Admin\Song\SongService;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    /**
     * @param SongService $songService
     * @return \Inertia\Response
     */
    public function index(SongService $songService): Response
    {
        $authUser = Helper::getUserData();
        $songs = $songService->getSongsPagination(SongService::SONGS_PAGINATE, true);

        return Inertia::render('Song/Songs', [
            'authUser' => $authUser,
            'songs' => SongResource::collection($songs->items()),
            'pagination' => PaginateResource::make($songs),
        ]);
    }

    public function create(ArtistService $artistService)
    {
        $authUser = Helper::getUserData();
        $artists = $artistService->getArtistsForSelect();

        return Inertia::render('Song/SongCreate', [
            'authUser' => $authUser,
            'artists' => ArtistForSelectResource::collection($artists),
        ]);
    }

    public function store()
    {

    }

    public function edit(int $id, SongService $songService, ArtistService $artistService)
    {
        $authUser = Helper::getUserData();
        $song = $songService->getById($id);
        $artists = $artistService->getArtistsForSelect();

        return Inertia::render('Song/SongEdit', [
            'authUser' => $authUser,
            'song' => SongEditResource::make($song),
            'artists' => ArtistForSelectResource::collection($artists),
        ]);
    }

    public function update()
    {

    }
}
