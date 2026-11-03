<?php

namespace App\Models\Learning;

use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class LearningRunOperation extends Model
{
    use UsesUtcDates;

    public $timestamps = false;

    protected $fillable = ['user_id', 'client_event_id', 'run_id', 'operation', 'payload_hash', 'receipt', 'received_at'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'receipt' => 'array', 'received_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Accepted account operations are immutable.'));
        static::deleting(fn () => throw new LogicException('Accepted account operations are immutable.'));
    }
}
