<?php

namespace App\Models\Learning;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class GuestEventMapping extends Model
{
    use \App\Support\Learning\UsesUtcDates;

    protected $table = 'guest_event_mappings';

    protected $fillable = [
        'episode_id',
        'guest_id',
        'owner_user_id',
        'entity_type',
        'guest_entity_id',
        'server_run_id',
        'server_event_id',
        'server_level_event_id',
        'receipt',
    ];

    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'receipt' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['episode_id', 'guest_id', 'owner_user_id', 'entity_type', 'guest_entity_id', 'server_run_id', 'server_event_id', 'server_level_event_id'])) {
                throw new LogicException('GuestEventMapping identity and snapshot cannot be changed.');
            }
        });
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(GuestSyncEpisode::class, 'episode_id', 'id');
    }

    public function serverRun(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'server_run_id', 'id');
    }

    public function serverEvent(): BelongsTo
    {
        return $this->belongsTo(LearningAttempt::class, 'server_event_id', 'id');
    }

    public function serverLevelEvent(): BelongsTo
    {
        return $this->belongsTo(LearningLevelEvent::class, 'server_level_event_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'id');
    }
}
