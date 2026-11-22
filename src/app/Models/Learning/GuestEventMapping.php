<?php

namespace App\Models\Learning;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Связывает гостевые события и прохождения с серверными записями, чтобы повторная синхронизация возвращала прежний результат.
 *
 * @property int $id Первичный ключ записи.
 * @property string $episode_id Эпизод переноса гостевого прогресса.
 * @property string $guest_id UUID гостевого профиля на клиенте.
 * @property int $owner_user_id Аккаунт, в который переносится гостевой прогресс.
 * @property string $entity_type Тип сопоставляемой гостевой сущности.
 * @property string $guest_entity_id Исходный UUID сущности на клиенте.
 * @property string|null $server_run_id Серверное прохождение, соответствующее гостевому.
 * @property string|null $server_event_id Серверное учебное событие, соответствующее гостевому.
 * @property array|null $receipt Сохранённый результат обработки для повторных запросов.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
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
            if ($model->isDirty([
                'episode_id',
                'guest_id',
                'owner_user_id',
                'entity_type',
                'guest_entity_id',
                'server_run_id',
                'server_event_id',
            ])) {
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


    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'id');
    }
}
