<?php

namespace App\Models\Learning;

use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Хранит результат команды прохождения, чтобы повтор запроса не применял изменение дважды.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $client_event_id Клиентский UUID действия для обработки повторной отправки.
 * @property string|null $run_id Прохождение, к которому относится запись.
 * @property string $operation Код команды над прохождением, например запуск или завершение.
 * @property string $payload_hash SHA-256 входных данных для проверки, что повторная отправка содержит те же данные.
 * @property array $receipt Сохранённый результат обработки для повторных запросов.
 * @property \Carbon\CarbonImmutable $received_at Момент получения события или запроса сервером.
 */
class LearningRunOperation extends Model
{
    use UsesUtcDates;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'client_event_id',
        'run_id',
        'operation',
        'payload_hash',
        'receipt',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'receipt' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Accepted account operations are immutable.'));
        static::deleting(fn () => throw new LogicException('Accepted account operations are immutable.'));
    }
}
