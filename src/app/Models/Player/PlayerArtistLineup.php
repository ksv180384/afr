<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlayerArtistLineup extends Model
{
    use HasFactory;

    protected $fillable = [
        'signature',
        'description',
        'image_path',
    ];

    protected $appends = [
        'image_url',
    ];

    public function artists()
    {
        return $this->belongsToMany(
            PlayerArtistsSong::class,
            'player_artist_lineup_members',
            'artist_lineup_id',
            'artist_id',
        );
    }

    public function songs()
    {
        return $this->hasMany(PlayerSongs::class, 'artist_lineup_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }
}
