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
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Services\Admin\Song\ArtistService;
use App\Services\Admin\Song\SongService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
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

    public function store(CreateSongRequest $request): RedirectResponse
    {
        $artist = PlayerArtistsSong::findOrFail($request->artist_id);
        $data = [
            'artist_id' => $request->artist_id,
            'artist_name' => $artist->name,
            'title' => $request->title,
            'text_fr' => $request->text_fr,
            'text_ru' => $request->text_ru,
            'text_transcription' => $request->text_transcription,
            'hidden' => $request->boolean('hidden'),
            'user_id' => Helper::getUserData()['id'] ?? null,
            'duration' => Helper::durationMmSsToDecimal($request->duration),
        ];

        $song = PlayerSongs::create($data);

        return redirect()->route('admin.song.edit', ['id' => $song->id]);
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

    public function update(int $id, UpdateSongRequest $request): RedirectResponse
    {
        $song = PlayerSongs::findOrFail($id);
        $artist = PlayerArtistsSong::findOrFail($request->artist_id);
        $data = [
            'artist_id' => $request->artist_id,
            'artist_name' => $artist->name,
            'title' => $request->title,
            'text_fr' => $request->text_fr,
            'text_ru' => $request->text_ru,
            'text_transcription' => $request->text_transcription,
            'hidden' => $request->boolean('hidden'),
            'duration' => Helper::durationMmSsToDecimal($request->duration),
        ];

        $song->update($data);

        return redirect()->route('admin.song.edit', ['id' => $song->id]);
    }
}
