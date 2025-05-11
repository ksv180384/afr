<?php

namespace App\Services\App\SongComment;

use App\Models\Player\PlayerSongComment;
use Illuminate\Database\Eloquent\Collection;

class SongCommentService
{
    /**
     * @param int $songId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSongCommentsBySongId(int $songId): Collection
    {
        $comments = PlayerSongComment::query()
            ->with(['user:id,name,avatar'])
            ->where('song_id', $songId)
            ->where('is_hidden', false)
            ->orderByDesc('created_at')
            ->get();

        return $comments;
    }

    /**
     * @param array $songCommentData
     * @return PlayerSongComment
     */
    public function create(array $songCommentData): PlayerSongComment
    {
        $songComment = PlayerSongComment::query()->create($songCommentData);

        return $songComment;
    }
}
