<?php

namespace App\Http\Resources\Admin\Song;

use App\Http\Resources\Admin\User\UserMiniResource;
use App\Support\ViewerTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    /**
     * Формирует краткие данные песни и список исполнителей для админки.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'artist_name' => $this->artist_name,
            'artist' => ['name' => $this->artist_name],
            'artists' => ArtistForSelectResource::collection($this->whenLoaded('artists')),
            'title' => $this->title,
            'hidden' => $this->hidden,
            'user' => UserMiniResource::make($this->user),
            'created_at' => ViewerTime::format($this->created_at, $request) ?? '',
            'created' => $this->created_at?->diffForHumans() ?? '',
            'updated_at' => ViewerTime::format($this->updated_at, $request) ?? '',
            'updated' => $this->updated_at?->diffForHumans() ?? '',
        ];
    }
}
