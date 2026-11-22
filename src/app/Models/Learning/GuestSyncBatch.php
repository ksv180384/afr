<?php

namespace App\Models\Learning;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Хранит пакет гостевых событий и результат его обработки для безопасной повторной отправки.
 *
 * @property string $id Первичный ключ записи.
 * @property string $episode_id Эпизод переноса гостевого прогресса.
 * @property string $guest_id UUID гостевого профиля на клиенте.
 * @property int $owner_user_id Аккаунт, в который переносится гостевой прогресс.
 * @property string $batch_id UUID пакета синхронизации, заданный клиентом.
 * @property string $payload_hash SHA-256 входных данных для проверки, что повторная отправка содержит те же данные.
 * @property string $status Состояние обработки пакета гостевых событий.
 * @property array|null $receipt Сохранённый результат обработки для повторных запросов.
 * @property \Carbon\CarbonImmutable $received_at Момент получения события или запроса сервером.
 * @property \Carbon\CarbonImmutable|null $completed_at Момент окончания обработки.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
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
        'receipt',
        'received_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
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
