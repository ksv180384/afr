<?php

namespace App\Http\Resources\App;

use App\Helpers\Helper;
use App\Services\PlayerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $playerService = new PlayerService();

        $durationSeconds = null;
        $durationFormatted = null;
        if (isset($this->duration) && $this->duration !== null) {
            $durationSeconds = (int) round((float) $this->duration * 60);
            $durationFormatted = Helper::durationDecimalToMmSs((float) $this->duration);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist_name' => $this->artist_name,
            'duration_seconds' => $durationSeconds,
            'duration_formatted' => $durationFormatted,
            'text_fr' => explode("\n", $playerService->formatText($this->text_fr)),
            'text_ru' => explode("\n", $playerService->formatText($this->text_ru)),
            'text_transcription' => explode("\n", $playerService->formatText($this->text_transcription)),
            'text_raw_fr' => $this->text_fr ?? '',
            'text_raw_ru' => $this->text_ru ?? '',
            'text_raw_transcription' => $this->text_transcription ?? '',
        ];
    }
}
