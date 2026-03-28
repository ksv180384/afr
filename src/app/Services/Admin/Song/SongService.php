<?php

namespace App\Services\Admin\Song;

use App\Helpers\Helper;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class SongService
{
    const SONGS_PAGINATE = 40;

    public function __construct(
        private readonly ArtistService $artistService,
    ) {}

    /**
     * @param int $limit
     * @param bool $isHidden
     * @return LengthAwarePaginator
     */
    public function getSongsPagination(int $limit, bool $isHidden = false): LengthAwarePaginator
    {
        return PlayerSongs::query()
            ->select([
                'player_songs.id',
                'player_songs.artist_id',
                'player_songs.artist_name',
                'player_songs.title',
                'player_songs.hidden',
                'player_songs.user_id',
            ])
            ->with(['artist:id,name'])
            ->with(['user:id,name'])
            ->when(!$isHidden, function ($q) {
                $q->where('hidden', false);
            })
            ->orderBy('artist_name', 'ASC')
            ->orderBy('title', 'ASC')
            ->paginate($limit);
    }

    /**
     * @return PlayerSongs
     */
    public function getById(int $id): PlayerSongs
    {
        $columns = ['id', 'artist_id', 'title', 'text_fr', 'text_ru', 'text_transcription', 'hidden'];
        if (Schema::hasColumn('player_songs', 'duration')) {
            $columns[] = 'duration';
        }

        return PlayerSongs::query()
            ->select($columns)
            ->with(['artist:id,name'])
            ->where('id', $id)
            ->first();
    }

    public function store(Request $request): PlayerSongs
    {
        $artist = $this->artistService->resolveArtist(
            $request->input('artist_id'),
            $request->input('artist_name'),
        );

        return PlayerSongs::create([
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'title' => $request->input('title'),
            'text_fr' => $request->input('text_fr'),
            'text_ru' => $request->input('text_ru'),
            'text_transcription' => $request->input('text_transcription'),
            'hidden' => $request->boolean('hidden'),
            'user_id' => Helper::getUserData()['id'] ?? null,
            'duration' => Helper::durationMmSsToDecimal($request->input('duration')),
        ]);
    }

    public function update(int $id, Request $request): PlayerSongs
    {
        $song = PlayerSongs::findOrFail($id);

        $artist = $this->artistService->resolveArtist(
            $request->input('artist_id'),
            $request->input('artist_name'),
        );

        $song->update([
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'title' => $request->input('title'),
            'text_fr' => $request->input('text_fr'),
            'text_ru' => $request->input('text_ru'),
            'text_transcription' => $request->input('text_transcription'),
            'hidden' => $request->boolean('hidden'),
            'duration' => Helper::durationMmSsToDecimal($request->input('duration')),
        ]);

        return $song;
    }
}
