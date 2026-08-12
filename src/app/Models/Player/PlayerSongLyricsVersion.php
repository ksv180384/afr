<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSongLyricsVersion extends Model
{
    protected $fillable = [
        'song_id',
        'duration',
        'text_fr',
        'text_ru',
        'text_transcription',
    ];

    protected $casts = [
        'duration' => 'float',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(PlayerSongs::class, 'song_id');
    }
}
