<?php

namespace App\Services\Admin\Song;

use App\Filters\SongFilters;
use App\Helpers\Helper;
use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        $filter = new SongFilters(request());

        return PlayerSongs::query()
            ->select([
                'player_songs.id',
                'player_songs.artist_name',
                'player_songs.title',
                'player_songs.hidden',
                'player_songs.user_id',
            ])
            ->with(['artists:id,name'])
            ->with(['user:id,name'])
            ->filter($filter)
            ->when(!$isHidden, function ($q) {
                $q->where('hidden', false);
            })
            ->orderBy('artist_name', 'ASC')
            ->orderBy('title', 'ASC')
            ->paginate($limit)
            ->withQueryString();
    }

    /**
     * @return PlayerSongs
     */
    public function getById(int $id): PlayerSongs
    {
        $columns = ['id', 'artist_name', 'title', 'text_fr', 'text_ru', 'text_transcription', 'hidden'];
        if (Schema::hasColumn('player_songs', 'duration')) {
            $columns[] = 'duration';
        }

        return PlayerSongs::query()
            ->select($columns)
            ->with(['artists:id,name'])
            ->where('id', $id)
            ->first();
    }

    /**
     * Создаёт песню и атомарно сохраняет упорядоченные связи с исполнителями.
     */
    public function store(Request $request): PlayerSongs
    {
        $artists = $this->artistService->resolveArtists($request->input('artists'));

        return DB::transaction(function () use ($request, $artists): PlayerSongs {
            $song = PlayerSongs::create([
                'artist_name' => $artists->pluck('name')->implode(', '),
                'title' => $request->input('title'),
                'text_fr' => $request->input('text_fr'),
                'text_ru' => $request->input('text_ru'),
                'text_transcription' => $request->input('text_transcription'),
                'hidden' => $request->boolean('hidden'),
                'user_id' => Helper::getUserData()['id'] ?? null,
                'duration' => Helper::durationMmSsToDecimal($request->input('duration')),
            ]);

            $song->artists()->sync($artists->mapWithKeys(
                fn (PlayerArtistsSong $artist, int $position) => [$artist->id => ['position' => $position]],
            )->all());

            return $song->load('artists');
        });
    }

    /**
     * Обновляет песню, строку artist_name и связи с исполнителями в одной транзакции.
     */
    public function update(int $id, Request $request): PlayerSongs
    {
        $song = PlayerSongs::findOrFail($id);

        $artists = $this->artistService->resolveArtists($request->input('artists'));

        return DB::transaction(function () use ($song, $request, $artists): PlayerSongs {
            $song->update([
                'artist_name' => $artists->pluck('name')->implode(', '),
                'title' => $request->input('title'),
                'text_fr' => $request->input('text_fr'),
                'text_ru' => $request->input('text_ru'),
                'text_transcription' => $request->input('text_transcription'),
                'hidden' => $request->boolean('hidden'),
                'duration' => Helper::durationMmSsToDecimal($request->input('duration')),
            ]);

            $song->artists()->sync($artists->mapWithKeys(
                fn (PlayerArtistsSong $artist, int $position) => [$artist->id => ['position' => $position]],
            )->all());

            return $song->load('artists');
        });
    }
}
