<?php

namespace App\Http\Resources\Admin\Song;

use App\Support\ViewerTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'artist' => $this->artist,
            'title' => $this->title,
            'title_file' => $this->title_file,
            'song_id' => $this->song_id,
            'created_at' => ViewerTime::format($this->created_at, $request) ?? '',
            'created' => $this->created_at?->diffForHumans() ?? '',
            'updated_at' => ViewerTime::format($this->updated_at, $request) ?? '',
            'updated' => $this->updated_at?->diffForHumans() ?? '',
        ];
    }
}
