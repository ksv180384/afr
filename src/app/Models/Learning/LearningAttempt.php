<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningAttemptFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Хранит отдельный ответ, запрос подсказки или другое действие внутри прохождения.
 *
 * @property string $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $run_id Прохождение, к которому относится запись.
 * @property string $presentation_id Выдача задания, с которой связано действие.
 * @property string $client_event_id Клиентский UUID действия для обработки повторной отправки.
 * @property string $event_type Тип действия, например ответ или запрос подсказки.
 * @property int $sequence Порядковый номер события в прохождении.
 * @property array $payload Входные данные учебного действия, например ответ пользователя.
 * @property string $payload_hash SHA-256 входных данных для проверки, что повторная отправка содержит те же данные.
 * @property bool|null $is_first_answer Отмечает первый ответ на выданное задание.
 * @property \Carbon\CarbonImmutable $received_at Момент получения события или запроса сервером.
 * @property \Carbon\CarbonImmutable|null $client_occurred_at Момент события по данным клиента.
 * @property array|null $result Результат обработки действия, включая оценку ответа при её наличии.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningAttempt extends Model
{
    use HasFactory;
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_attempts';

    protected $fillable = [
        'user_id',
        'run_id',
        'presentation_id',
        'client_event_id',
        'event_type',
        'sequence',
        'payload',
        'payload_hash',
        'is_first_answer',
        'received_at',
        'client_occurred_at',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sequence' => 'integer',
            'payload' => 'array',
            'is_first_answer' => 'boolean',
            'received_at' => 'immutable_datetime',
            'client_occurred_at' => 'immutable_datetime',
            'result' => 'array',
        ];
    }

    protected function isFirstAnswer(): Attribute
    {
        return Attribute::make(
            set: fn (?bool $value): ?bool => $value ? true : null,
        );
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new LogicException('LearningAttempt is an append-only record.');
        });
        static::deleting(function (self $model): void {
            throw new LogicException('LearningAttempt is an append-only record.');
        });
    }

    protected static function newFactory(): LearningAttemptFactory
    {
        return LearningAttemptFactory::new();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(LearningPresentation::class, 'presentation_id', 'id');
    }

    public function vocabularyEffects(): HasMany
    {
        return $this->hasMany(VocabularyEventEffect::class, 'event_id', 'id');
    }

    public function objectiveEvidence(): HasMany
    {
        return $this->hasMany(LearningObjectiveEvidence::class, 'event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
