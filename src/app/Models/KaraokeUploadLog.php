<?php

namespace App\Models;

use App\Models\Player\PlayerSongs;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaraokeUploadLog extends Model
{
    protected $fillable = [
        'song_id',
        'song_title',
        'song_artist',
        'file_name',
        'file_duration_seconds',
        'db_duration_seconds',
        'duration_matched',
        'user_id',
    ];

    protected $casts = [
        'file_duration_seconds' => 'float',
        'duration_matched' => 'boolean',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(PlayerSongs::class, 'song_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
