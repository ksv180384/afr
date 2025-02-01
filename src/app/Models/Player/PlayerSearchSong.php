<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerSearchSong extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist',
        'title',
        'title_file',
    ];
}
