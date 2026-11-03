<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class GuestSyncEpisode extends Model
{
    use \App\Support\Learning\UsesUtcDates;
    use HasUuids;

    protected $table = 'guest_sync_episodes';

    protected $fillable = [
        'baseline_snapshot', 'created_word_ids', 'guest_id',
        'owner_user_id',
        'status',
        'baseline_profile_revision',
        'baseline_current_level',
        'accepted_level_event_ids',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'baseline_snapshot' => 'array', 'created_word_ids' => 'array', 'owner_user_id' => 'integer',
            'baseline_profile_revision' => 'integer',
            'baseline_current_level' => LevelCode::class,
            'accepted_level_event_ids' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['baseline_snapshot', 'guest_id', 'owner_user_id', 'baseline_profile_revision', 'baseline_current_level', 'started_at'])) {
                throw new LogicException('GuestSyncEpisode identity and snapshot cannot be changed.');
            }
        });
    }

    public function batches(): HasMany
    {
        return $this->hasMany(GuestSyncBatch::class, 'episode_id', 'id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(GuestEventMapping::class, 'episode_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'id');
    }
}
