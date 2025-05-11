<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Resources\App\SongComment\SongCommentResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\SongShowResource;
use App\Services\App\ProverbService;
use App\Services\App\Song\SongService;
use App\Services\App\SongComment\SongCommentService;
use App\Services\App\WordService;
use Inertia\Inertia;
use Inertia\Response;

class LyricsController extends Controller
{
    /**
     * @param WordService $wordService
     * @param ProverbService $proverbService
     * @param SongService $songService
     * @return Response
     */
    public function index(
        WordService $wordService,
        ProverbService $proverbService,
        SongService $songService,
    ): Response
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

    /**
     * @param int $id
     * @param SongService $songService
     * @param SongCommentService $commentService
     * @return \Inertia\Response
     */
    public function show(int $id, SongService $songService, SongCommentService $commentService): Response
    {
        $authUser = Helper::getUserData();
        $song = $songService->getById($id);
        $comments = $commentService->getSongCommentsBySongId($id);

        return Inertia::render('Lyrics/LyricShow', [
            'authUser' => $authUser,
            'song' => SongShowResource::make($song),
            'comments' => SongCommentResource::collection($comments),
        ]);
    }
}
