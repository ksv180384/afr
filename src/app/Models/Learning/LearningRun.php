<?php

namespace App\Models\Learning;

use App\Enums\Learning\RunStatus;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningRunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Хранит прохождение занятия или словарного повторения и снимки для воспроизводимой проверки.
 *
 * @property string $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $kind Тип прохождения, определяющий его сценарий.
 * @property RunStatus $status Состояние прохождения: в процессе, на паузе или завершено.
 * @property int|null $session_version_id Версия занятия, к которой относится запись.
 * @property array $snapshot Снимок содержимого прохождения, закреплённый при запуске.
 * @property int $checking_policy_version Версия правил проверки ответов.
 * @property array $checking_policy_snapshot Зафиксированные для прохождения параметры проверки ответов.
 * @property string $timezone Часовой пояс IANA для расчёта локальных дат.
 * @property \Carbon\CarbonImmutable $local_date Календарная дата в часовом поясе ученика.
 * @property \Carbon\CarbonImmutable $started_at Момент запуска прохождения.
 * @property \Carbon\CarbonImmutable|null $paused_at Момент приостановки прохождения.
 * @property \Carbon\CarbonImmutable|null $finished_at Момент завершения прохождения.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property array|null $result Итоги завершённого прохождения.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property int $cursor_position Текущая позиция в прохождении для возобновления.
 * @property array|null $introductions_seen Идентификаторы уже просмотренных вводных материалов.
 * @property array|null $import_meta Сведения о переносе прохождения из гостевого режима: исходные UUID и время приёма.
 */
class LearningRun extends Model
{
    use HasFactory;
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_runs';

    protected $fillable = [
        'import_meta',
        'user_id',
        'kind',
        'status',
        'session_version_id',
        'snapshot',
        'checking_policy_version',
        'checking_policy_snapshot',
        'timezone',
        'local_date',
        'started_at',
        'paused_at',
        'finished_at',
        'revision',
        'result',
        'cursor_position',
        'introductions_seen',
    ];

    protected function casts(): array
    {
        return [
            'import_meta' => 'array',
            'user_id' => 'integer',
            'session_version_id' => 'integer',
            'snapshot' => 'array',
            'checking_policy_version' => 'integer',
            'checking_policy_snapshot' => 'array',
            'local_date' => 'immutable_date:Y-m-d',
            'started_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'revision' => 'integer',
            'cursor_position' => 'integer',
            'introductions_seen' => 'array',
            'result' => 'array',
            'status' => RunStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === RunStatus::Completed
                || $model->getRawOriginal('status') === RunStatus::Completed->value
            ) {
                throw new LogicException('Completed run cannot be changed.');
            }
            if ($model->isDirty([
                'import_meta',
                'user_id',
                'kind',
                'session_version_id',
                'snapshot',
                'checking_policy_version',
                'checking_policy_snapshot',
                'timezone',
                'local_date',
                'started_at',
            ])) {
                throw new LogicException('LearningRun identity and snapshot cannot be changed.');
            }
        });
    }

    protected static function newFactory(): LearningRunFactory
    {
        return LearningRunFactory::new();
    }

    public function sessionVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'session_version_id', 'id');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(LearningPresentation::class, 'run_id', 'id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LearningAttempt::class, 'run_id', 'id');
    }

    public function vocabularyEffects(): HasMany
    {
        return $this->hasMany(VocabularyRunEffect::class, 'run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
