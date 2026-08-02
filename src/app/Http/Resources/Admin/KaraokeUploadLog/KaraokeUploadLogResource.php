<?php

namespace App\Http\Resources\Admin\KaraokeUploadLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KaraokeUploadLogResource extends JsonResource
{
    private function formatDuration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }
        $totalSeconds = (int) round($seconds);
        $minutes = (int) floor($totalSeconds / 60);
        $secs = $totalSeconds % 60;

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'song_id' => $this->song_id,
            'song_title' => $this->song_title,
            'song_artist' => $this->song_artist,
            'file_name' => $this->file_name,
            'has_file' => (bool) $this->file_path,
            'file_url' => $this->file_path ? route('admin.karaoke-upload-logs.file', ['log' => $this->id]) : null,
            'file_size_formatted' => $this->file_size ? number_format($this->file_size / 1024 / 1024, 1, ',', ' ').' МБ' : null,
            'file_duration_seconds' => $this->file_duration_seconds,
            'file_duration_formatted' => $this->formatDuration($this->file_duration_seconds),
            'db_duration_seconds' => $this->db_duration_seconds,
            'db_duration_formatted' => $this->formatDuration($this->db_duration_seconds),
            'duration_matched' => $this->duration_matched,
            'user_id' => $this->user_id,
            'user' => $this->user ? $this->user->only(['id', 'name']) : null,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created' => $this->created_at->diffForHumans(),
        ];
    }
}
