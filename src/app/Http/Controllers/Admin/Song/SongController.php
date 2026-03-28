<?php

namespace App\Http\Controllers\Admin\Song;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Song\CreateSongRequest;
use App\Http\Requests\Admin\Song\UpdateSongRequest;
use App\Http\Resources\Admin\Song\ArtistForSelectResource;
use App\Http\Resources\Admin\Song\SongEditResource;
use App\Http\Resources\Admin\Song\SongResource;
use App\Http\Resources\PaginateResource;
use App\Services\Admin\Song\ArtistService;
use App\Services\Admin\Song\SongService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function __construct(
        private readonly SongService $songService,
        private readonly ArtistService $artistService,
    ) {}

    public function index(): Response
    {
        $songs = $this->songService->getSongsPagination(SongService::SONGS_PAGINATE, true);

        return Inertia::render('Song/Songs', [
            'authUser' => Helper::getUserData(),
            'songs' => SongResource::collection($songs->items()),
            'pagination' => PaginateResource::make($songs),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Song/SongCreate', [
            'authUser' => Helper::getUserData(),
            'artists' => ArtistForSelectResource::collection($this->artistService->getArtistsForSelect()),
        ]);
    }

    public function store(CreateSongRequest $request): RedirectResponse
    {
        $song = $this->songService->store($request);

        return redirect()->route('admin.song.edit', ['id' => $song->id]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('Song/SongEdit', [
            'authUser' => Helper::getUserData(),
            'song' => SongEditResource::make($this->songService->getById($id)),
            'artists' => ArtistForSelectResource::collection($this->artistService->getArtistsForSelect()),
        ]);
    }

    public function update(int $id, UpdateSongRequest $request): RedirectResponse
    {
        $this->songService->update($id, $request);

        return redirect()->route('admin.song.edit', ['id' => $id]);
    }
}
