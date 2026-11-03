<?php

namespace App\Services\Admin\Artist;

use App\Models\Player\PlayerArtistLineup;
use App\Models\Player\PlayerArtistsSong;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ArtistProfileService
{
    public const ARTISTS_PAGINATE = 40;

    public function getArtistsPagination(): LengthAwarePaginator
    {
        $text = trim((string) request()->input('text', ''));

        return PlayerArtistsSong::query()
            ->withCount('songs')
            ->withCount([
                'lineups as lineups_count' => fn ($query) => $query->whereHas('songs'),
            ])
            ->when($text !== '', fn ($query) => $query->where('name', 'like', '%'.$text.'%'))
            ->orderBy('name')
            ->paginate(self::ARTISTS_PAGINATE)
            ->withQueryString();
    }

    public function getArtist(int $id): PlayerArtistsSong
    {
        return PlayerArtistsSong::query()
            ->withCount('songs')
            ->findOrFail($id);
    }

    /**
     * @return Collection<int, PlayerArtistLineup>
     */
    public function getLineupsForArtist(PlayerArtistsSong $artist): Collection
    {
        return PlayerArtistLineup::query()
            ->whereHas('artists', fn ($query) => $query->whereKey($artist->id))
            ->whereHas('songs')
            ->with([
                'artists:id,name,image_path',
                'songs:id,artist_lineup_id,artist_name,title',
            ])
            ->orderBy('id')
            ->get();
    }

    public function updateArtist(PlayerArtistsSong $artist, array $data, ?UploadedFile $image): void
    {
        $oldPath = $artist->image_path;
        $newPath = null;

        if ($image) {
            $newPath = app(ArtistImageService::class)->store($image, 'artists');
        }

        $artist->update([
            'description' => $data['description'] ?? null,
            'image_path' => $newPath
                ?? ($data['remove_image'] ?? false ? null : $artist->image_path),
        ]);

        if ($oldPath && $oldPath !== $artist->image_path) {
            app(ArtistImageService::class)->delete($oldPath);
        }
    }

    public function updateLineup(PlayerArtistLineup $lineup, array $data, ?UploadedFile $image): void
    {
        $oldPath = $lineup->image_path;
        $newPath = null;

        if ($image) {
            $newPath = app(ArtistImageService::class)->store($image, 'artist-lineups');
        }

        $lineup->update([
            'description' => $data['description'] ?? null,
            'image_path' => $newPath
                ?? ($data['remove_image'] ?? false ? null : $lineup->image_path),
        ]);

        if ($oldPath && $oldPath !== $lineup->image_path) {
            app(ArtistImageService::class)->delete($oldPath);
        }
    }
}
