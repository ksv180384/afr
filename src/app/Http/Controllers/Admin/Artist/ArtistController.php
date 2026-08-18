<?php

namespace App\Http\Controllers\Admin\Artist;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Artist\UpdateArtistLineupRequest;
use App\Http\Requests\Admin\Artist\UpdateArtistRequest;
use App\Http\Resources\Admin\Artist\ArtistLineupResource;
use App\Http\Resources\Admin\Artist\ArtistResource;
use App\Models\Player\PlayerArtistLineup;
use App\Services\Admin\Artist\ArtistProfileService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function index(ArtistProfileService $artistProfileService): Response
    {
        $artists = $artistProfileService->getArtistsPagination();

        return Inertia::render('Artist/Artists', [
            'authUser' => Helper::getUserData(),
            'artists' => ArtistResource::collection($artists->items()),
            'pagination' => [
                'current_page' => $artists->currentPage(),
                'last_page' => $artists->lastPage(),
                'per_page' => $artists->perPage(),
                'total' => $artists->total(),
            ],
            'filters' => request()->only('text'),
        ]);
    }

    public function edit(int $id, ArtistProfileService $artistProfileService): Response
    {
        $artist = $artistProfileService->getArtist($id);

        return Inertia::render('Artist/ArtistEdit', [
            'authUser' => Helper::getUserData(),
            'artist' => ArtistResource::make($artist),
            'lineups' => ArtistLineupResource::collection($artistProfileService->getLineupsForArtist($artist)),
        ]);
    }

    public function update(int $id, UpdateArtistRequest $request, ArtistProfileService $artistProfileService): RedirectResponse
    {
        $artist = $artistProfileService->getArtist($id);
        $artistProfileService->updateArtist($artist, $request->validated(), $request->file('image'));

        return redirect()->route('admin.artists.edit', ['id' => $artist->id]);
    }

    public function updateLineup(int $id, UpdateArtistLineupRequest $request, ArtistProfileService $artistProfileService): RedirectResponse
    {
        $lineup = PlayerArtistLineup::query()->findOrFail($id);
        $artistProfileService->updateLineup($lineup, $request->validated(), $request->file('image'));

        return back();
    }
}
