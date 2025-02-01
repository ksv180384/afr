<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\SongShowResource;
use App\Services\App\ProverbService;
use App\Services\App\Song\SongService;
use App\Services\App\WordService;
use Inertia\Inertia;

class LyricsController extends Controller
{
    public function index(
        WordService $wordService,
        ProverbService $proverbService,
        SongService $songService,
    )
    {
        $authUser = Helper::getUserData();
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $artists = $songService->getArtistsSongs();

        return Inertia::render('Lyrics/Lyrics', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
            'artists' => $artists,
        ]);
    }

    public function show(int $id, SongService $songService)
    {
        $authUser = Helper::getUserData();
        $song = $songService->getById($id);

        return Inertia::render('Lyrics/LyricShow', [
            'song' => SongShowResource::make($song),
            'authUser' => $authUser,
        ]);
    }
}
