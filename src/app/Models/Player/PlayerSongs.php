<?php

namespace App\Models\Player;

use App\Models\User\User;
use App\Traits\Filterable;
use Database\Factories\Song\SongFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerSongs extends Model
{
    use HasFactory;
    use Filterable;

    protected $fillable = [
        'artist_name',
        'title',
        'duration',
        'text_fr',
        'text_ru',
        'text_transcription',
        'user_id',
        'hidden',
    ];

    protected $casts = [
        'hidden' => 'boolean',
    ];

    /**
     * Возвращает исполнителей песни в порядке, заданном в pivot-таблице.
     */
    public function artists()
    {
        return $this->belongsToMany(
            PlayerArtistsSong::class,
            'player_artist_song',
            'song_id',
            'artist_id',
        )->withPivot('position')->orderByPivot('position');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function newFactory(): SongFactory
    {
        return SongFactory::new();
    }
}
