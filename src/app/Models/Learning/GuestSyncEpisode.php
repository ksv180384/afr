<?php

namespace App\Models\Learning;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Фиксирует исходное состояние аккаунта и прогресс переноса данных конкретного гостя.
 *
 * @property string $id Первичный ключ записи.
 * @property string $guest_id UUID гостевого профиля на клиенте.
 * @property int $owner_user_id Аккаунт, в который переносится гостевой прогресс.
 * @property string $status Состояние переноса гостевых данных.
 * @property \Carbon\CarbonImmutable $started_at Момент начала переноса гостевых данных.
 * @property \Carbon\CarbonImmutable|null $completed_at Момент окончания обработки.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property array|null $created_word_ids Идентификаторы слов, добавленных в рамках этого переноса.
 */
class GuestSyncEpisode extends Model
{
    use \App\Support\Learning\UsesUtcDates;
    use HasUuids;

    protected $table = 'guest_sync_episodes';

    protected $fillable = [
        'created_word_ids',
        'guest_id',
        'owner_user_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_word_ids' => 'array',
            'owner_user_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty([
                'guest_id',
                'owner_user_id',
                'started_at',
            ])) {
                throw new LogicException('GuestSyncEpisode identity cannot be changed.');
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
