<?php

namespace App\Models\Learning;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class GuestSyncBatch extends Model
{
    use \App\Support\Learning\UsesUtcDates;
    use HasUuids;

    protected $table = 'guest_sync_batches';

    protected $fillable = [
        'episode_id',
        'guest_id',
        'owner_user_id',
        'batch_id',
        'payload_hash',
        'status',
        'accepted',
        'rejected',
        'receipt',
        'received_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'accepted' => 'array',
            'rejected' => 'array',
            'receipt' => 'array',
            'received_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['episode_id', 'guest_id', 'owner_user_id', 'batch_id', 'payload_hash'])) {
                throw new LogicException('GuestSyncBatch identity and snapshot cannot be changed.');
            }
        });
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(GuestSyncEpisode::class, 'episode_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'id');
    }
}
