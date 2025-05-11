<?php

namespace App\Services\Admin\SongComment;

use App\Models\Player\PlayerSongComment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SongCommentService
{
    const PAGINATION = 50;

    /**
     * @param int $pagination
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSongComments(int $pagination = 50): LengthAwarePaginator
    {
        $comments = PlayerSongComment::query()
            ->with([
                'user:id,name,avatar',
                'song:id,artist_name,title'
            ])
            ->orderByDesc('created_at')
            ->paginate($pagination);

        return $comments;
    }
}
