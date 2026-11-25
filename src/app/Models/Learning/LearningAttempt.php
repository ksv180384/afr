<?php

namespace App\Models\Learning;

use App\Enums\Learning\RunStatus;
use App\Models\User\User;
use App\Support\Learning\EncodesPracticeJson;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningAttemptFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
    use EncodesPracticeJson;
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

    public function requestEnvelope(int $revision): array
    {
        return ['client_event_id' => $this->client_event_id, 'presentation_id' => $this->presentation_id,
            'event_type' => $this->event_type, 'expected_revision' => $revision - 1];
    }

    private function resultMetadata(int $policyVersion, string $taskId): array
    {
        return ['checking_policy_version' => $policyVersion, 'presentation_id' => $this->presentation_id,
            'task_id' => $taskId, 'event_type' => $this->event_type, 'is_first_answer' => (bool) $this->is_first_answer];
    }

    public function compactDetails(int $policyVersion, string $taskId, bool $archived = false): void
    {
        $result = $this->result;
        if (! is_int($result['run_revision'] ?? null) || array_key_exists('_v', $result)) {
            return;
        }
        $metadata = $this->resultMetadata($policyVersion, $taskId);
        foreach ($metadata as $key => $value) {
            if (($result[$key] ?? null) !== $value) {
                return;
            }
        }
        if (! ($archived && $this->payload === [])) {
            $payload = $this->payload;
            $envelope = $this->requestEnvelope($result['run_revision']);
            foreach ($envelope as $key => $value) {
                if (($payload[$key] ?? null) !== $value) {
                    return;
                }
            }
            $this->payload = array_diff_key($payload, $envelope);
        }
        $this->result = ['_v' => 1] + array_diff_key($result, $metadata);
    }

    public function gradingResult(int $policyVersion, ?string $taskId = null): ?array
    {
        $result = $this->result;
        if (($result['_v'] ?? null) !== 1) {
            return $result;
        }
        unset($result['_v']);
        $taskId ??= $this->presentation()->value('task_id');

        return $this->resultMetadata($policyVersion, $taskId) + $result;
    }

    public static function trimClosedRun(LearningRun $run): void
    {
        $changes = ['result' => DB::raw("json_remove(result, '$.feedback', '$.solution', '$.pairs', '$.objective_ids')")];
        if ($run->status === RunStatus::Completed) {
            $changes['payload'] = '[]';
        }
        DB::table('learning_attempts')->where('run_id', $run->id)->update($changes);
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


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
