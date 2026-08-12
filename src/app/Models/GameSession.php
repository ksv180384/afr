<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'game_key', 'status', 'score', 'correct_count', 'wrong_count',
        'missed_count', 'streak', 'best_streak', 'metadata', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'finished_at' => 'datetime',
        ];
    }
}
