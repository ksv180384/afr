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
    /**
     * @param CreateSongCommentRequest $request
     * @param SongCommentService $songCommentService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateSongCommentRequest $request, SongCommentService $songCommentService): RedirectResponse
    {
        $songCommentData = $request->validated();
        $songCommentService->create($songCommentData);
        $song = PlayerSongs::query()->find($songCommentData['song_id']);

        SendTelegramLogJob::dispatch('Добавлен комментарий к посту "' . $song->artist_name . ' - ' . $song->title  . '": ' . route('lyrics.show', ['id' => $song->id]))->afterResponse();

        return back()->with('status', 'Комментарий успешно отправлен.');
    }
}
