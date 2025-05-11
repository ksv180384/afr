<?php

namespace App\Models\Player;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Casts\PurifyHtmlOnGet;

class PlayerSongComment extends Model
{
    protected $fillable = [
        'song_id',
        'parent_post_comment_id',
        'user_id',
        'comment',
        'is_hidden',
    ];

    protected $casts = [
        'comment' => PurifyHtmlOnGet::class,
        'is_hidden' => 'boolean',
    ];

    public function song()
    {
        return $this->belongsTo(PlayerSongs::class, 'song_id');
    }

    public function children()
    {
        return $this->hasMany(PlayerSongComment::class, 'parent_comment_id');
    }

    public function parent()
    {
        return $this->belongsTo(PlayerSongComment::class, 'parent_comment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
