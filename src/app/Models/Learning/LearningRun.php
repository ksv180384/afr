<?php

namespace App\Models\Learning;

use App\Enums\Learning\RunStatus;
use App\Enums\Learning\PracticeErrorCode;
use App\Models\User\User;
use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\SessionContentCache;
use App\Support\Learning\CompletedVocabularyRun;
use App\Support\Learning\LessonResult;
use App\Support\Learning\PracticeJson;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningRunFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
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
 * @property int|null $session_id Занятие, к которому относится запись.
 * @property int|null $vocabulary_set_id Общий набор источников, впервые изученных в этом уроке.
 * @property \Carbon\CarbonImmutable|null $vocabulary_source_at Исходное время завершения гостевого урока.
 * @property array|null $snapshot Содержимое персонального словарного теста или повторения.
 * @property int $checking_policy_version Версия правил проверки ответов.
 * @property string $timezone Часовой пояс IANA для расчёта локальных дат.
 * @property \Carbon\CarbonImmutable $local_date Календарная дата в часовом поясе ученика.
 * @property \Carbon\CarbonImmutable $started_at Момент запуска прохождения.
 * @property \Carbon\CarbonImmutable|null $paused_at Момент приостановки прохождения.
 * @property \Carbon\CarbonImmutable|null $finished_at Момент завершения прохождения.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property array|null $result Итоги завершённого прохождения.
 * @property string|null $promoted_to_level Уровень, открытый при завершении этого прохождения.
 * @property array|null $corrections Позиции заданий с ошибками и ход их повторения.
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
    use MassPrunable;
    use UsesUtcDates;

    protected $table = 'learning_runs';

    public const LESSON_RESULT_FIELDS = LessonResult::FIELDS;

    protected $fillable = [
        'import_meta',
        'user_id',
        'kind',
        'status',
        'session_id',
        'content_checksum',
        'snapshot',
        'checking_policy_version',
        'timezone',
        'local_date',
        'started_at',
        'paused_at',
        'finished_at',
        'revision',
        'result',
        'promoted_to_level',
        'corrections',
        'cursor_position',
        'introductions_seen',
    ];

    protected function casts(): array
    {
        return [
            'import_meta' => 'array',
            'user_id' => 'integer',
            'session_id' => 'integer',
            'vocabulary_set_id' => 'integer',
            'vocabulary_source_at' => 'immutable_datetime',
            'snapshot' => 'array',
            'checking_policy_version' => 'integer',
            'local_date' => 'immutable_date:Y-m-d',
            'started_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'revision' => 'integer',
            'cursor_position' => 'integer',
            'introductions_seen' => 'array',
            'result' => 'array',
            'corrections' => 'array',
            'status' => RunStatus::class,
        ];
    }

    protected function castAttributeAsJson($key, $value): string
    {
        return $key === 'snapshot' ? PracticeJson::encode($value) : parent::castAttributeAsJson($key, $value);
    }

    protected function snapshot(): Attribute
    {
        return Attribute::get(function (?string $stored) {
            $snapshot = $this->fromJson($stored);

            return is_array($snapshot) ? CompletedVocabularyRun::expandSnapshot($snapshot) : $snapshot;
        });
    }

    protected function result(): Attribute
    {
        return Attribute::get(function (?string $stored) {
            $result = $this->fromJson($stored);

            return is_array($result) ? CompletedVocabularyRun::expandResult(LessonResult::expand($result)) : $result;
        });
    }

    private function compactCompletedRun(): void
    {
        if ($this->status !== RunStatus::Completed) {
            return;
        }
        if (in_array($this->kind, ['daily', 'extra'], true)) {
            $this->content_checksum = null;
            if ($this->result !== null) {
                $this->result = LessonResult::compact($this->result);
            }
        } elseif (in_array($this->kind, ['review', 'vocabulary_test'], true)) {
            if ($this->snapshot !== null) {
                $this->snapshot = CompletedVocabularyRun::compactSnapshot($this->snapshot, $this->kind);
            }
            if ($this->result !== null) {
                $this->result = CompletedVocabularyRun::compactResult($this->result);
            }
        }
    }

    public static function cancelledReviewSnapshot(?string $title): ?array
    {
        return $title === null ? null : ['title' => $title];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $run) => $run->compactCompletedRun());
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === RunStatus::Cancelled
                || $model->getOriginal('status') === RunStatus::Completed
                || $model->getRawOriginal('status') === RunStatus::Completed->value
            ) {
                throw new LogicException('Completed run cannot be changed.');
            }
            if ($model->isDirty([
                'import_meta',
                'user_id',
                'kind',
                'session_id',
                'content_checksum',
                'checking_policy_version',
                'timezone',
                'local_date',
                'started_at',
            ]) || ($model->isDirty(['snapshot', 'vocabulary_set_id', 'vocabulary_source_at']) && $model->status !== RunStatus::Completed)) {
                throw new LogicException('LearningRun identity and snapshot cannot be changed.');
            }
            if ($model->status === RunStatus::Cancelled && in_array($model->kind, ['review', 'vocabulary_test'], true)) {
                $model->snapshot = self::cancelledReviewSnapshot($model->snapshot['title'] ?? null);
            }
            $model->compactCompletedRun();
        });
    }

    protected static function newFactory(): LearningRunFactory
    {
        return LearningRunFactory::new();
    }

    public function prunable(): Builder
    {
        return static::query()->whereIn('kind', ['daily', 'extra'])
            ->whereIn('status', [RunStatus::InProgress->value, RunStatus::Paused->value])
            ->where('updated_at', '<=', \App\Support\Learning\PracticeTime::now()->subDay());
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function content(): array
    {
        if ($this->status === RunStatus::Completed) {
            return $this->summaryContent();
        }
        if ($this->session_id === null || ($this->content_checksum === null && $this->snapshot !== null)) {
            return $this->snapshot ?? [];
        }

        $session = $this->lesson();
        if ($session?->checksum === null || ! hash_equals($this->content_checksum, $session->checksum)) {
            throw new AnswerCheckingException(PracticeErrorCode::VersionConflict, 'Занятие обновилось. Начните его заново.');
        }

        return app(SessionContentCache::class)->payload($session);
    }

    public function lesson(): ?LearningSession
    {
        if ($this->session_id === null) {
            return null;
        }

        $this->loadMissing('session:id,external_id,level,unit_id,checksum,content_title,content_duration,content_goal');

        return $this->session;
    }

    public function summaryContent(): array
    {
        $lesson = $this->lesson();

        return $lesson ? ['id' => $lesson->external_id, 'level' => $lesson->getRawOriginal('level'),
            'title' => $lesson->content_title, 'duration_minutes' => (int) ($lesson->content_duration ?? 3),
            'goal' => $lesson->content_goal] : ($this->snapshot ?? []);
    }

    public function completedResult(): ?array
    {
        $result = $this->result;
        if ($result === null || !in_array($this->kind, ['daily', 'extra'], true)) {
            return $result;
        }

        $total = $result['total_steps'] ?? 0;
        $correct = $result['independent_correct_steps'] ?? 0;

        return $result + [
            'checking_policy_version' => $this->checking_policy_version,
            'processed_steps' => $total,
            'score_numerator' => $correct,
            'score_denominator' => $total,
            'score_percent' => $total ? $correct * 100 / $total : null,
            'all_steps_processed' => $total > 0,
            'promotion' => $this->promoted_to_level ? [
                'old_level' => $this->lesson()?->getRawOriginal('level') ?? $this->snapshot['level'] ?? null,
                'new_level' => $this->promoted_to_level,
                'reason' => ['method' => 'published_route_completion'],
            ] : null,
        ];
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
