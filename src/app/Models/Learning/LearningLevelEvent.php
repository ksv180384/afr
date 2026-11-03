<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LearningLevelEvent extends Model
{
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_level_events';

    protected $fillable = [
        'user_id',
        'old_level',
        'new_level',
        'source',
        'reason',
        'source_event_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'old_level' => LevelCode::class,
            'new_level' => LevelCode::class,
            'reason' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new LogicException('LearningLevelEvent is an append-only record.');
        });
        static::deleting(function (self $model): void {
            throw new LogicException('LearningLevelEvent is an append-only record.');
        });
    }

    public function previousLevel(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'old_level', 'code');
    }

    public function nextLevel(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'new_level', 'code');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
