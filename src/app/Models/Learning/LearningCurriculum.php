<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningCurriculumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Хранит версию программы уровня с обязательными целями и контрольными занятиями.
 *
 * @property int $id Первичный ключ записи.
 * @property string $external_id Стабильный идентификатор из пакета контента.
 * @property int $version Порядковый номер версии.
 * @property LevelCode $level Код уровня CEFR.
 * @property array $required_objective_ids Список стабильных идентификаторов обязательных учебных целей.
 * @property array $checkpoint_session_ids Список внешних идентификаторов контрольных занятий.
 * @property int $promotion_policy_version Версия правил перехода на следующий уровень.
 * @property array $promotion_policy_snapshot Зафиксированные параметры оценки и перехода на следующий уровень.
 * @property string $checksum SHA-256 содержимого для проверки целостности и совпадения версий.
 * @property SessionVersionStatus $status Состояние версии программы: черновик, проверена, опубликована или архивирована.
 * @property int|null $created_by Пользователь, создавший запись.
 * @property int|null $published_by Редактор, выполнивший публикацию.
 * @property \Carbon\CarbonImmutable|null $published_at Момент публикации.
 * @property \Carbon\CarbonImmutable|null $archived_at Момент архивации или снятия с публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningCurriculum extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_curricula';

    protected $fillable = [
        'external_id',
        'version',
        'level',
        'required_objective_ids',
        'checkpoint_session_ids',
        'promotion_policy_version',
        'promotion_policy_snapshot',
        'checksum',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'level' => LevelCode::class,
            'required_objective_ids' => 'array',
            'checkpoint_session_ids' => 'array',
            'promotion_policy_version' => 'integer',
            'promotion_policy_snapshot' => 'array',
            'created_by' => 'integer',
            'published_by' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'status' => SessionVersionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty([
                'external_id',
                'version',
                'level',
                'required_objective_ids',
                'checkpoint_session_ids',
                'promotion_policy_version',
                'promotion_policy_snapshot',
                'checksum',
            ])
                || ($model->getRawOriginal('published_at') !== null && $model->isDirty('published_at'))
            ) {
                throw new LogicException('Published curriculum is immutable.');
            }
        });
    }

    protected static function newFactory(): LearningCurriculumFactory
    {
        return LearningCurriculumFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'id');
    }
}
