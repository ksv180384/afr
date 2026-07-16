<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerArtistsSong extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    /**
     * Возвращает все песни, в которых участвует исполнитель.
     */
    public function songs()
    {
        return $this->belongsToMany(
            PlayerSongs::class,
            'player_artist_song',
            'artist_id',
            'song_id',
        )->withPivot('position');
    }
}
