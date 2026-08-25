<?php

namespace App\Http\Controllers\App\SongComment;

use App\Http\Controllers\Controller;
use App\Http\Requests\SongComment\CreateSongCommentRequest;
use App\Jobs\SendTelegramLogJob;
use App\Models\Player\PlayerSongs;
use App\Services\App\SongComment\SongCommentService;
use Illuminate\Http\RedirectResponse;

class SongCommentController extends Controller
{
    public function store(CreateSongCommentRequest $request, SongCommentService $songCommentService): RedirectResponse
    {
        $songCommentData = $request->validated();
        $songCommentService->create($songCommentData);
        $song = PlayerSongs::query()->find($songCommentData['song_id']);

        SendTelegramLogJob::dispatch('Добавлен комментарий к песне "'.$song->artist_name.' - '.$song->title.'"')->afterResponse();

        return back()->with('status', 'Комментарий успешно отправлен.');
    }
}
