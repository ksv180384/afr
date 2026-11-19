<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\User\User;
use Database\Factories\Learning\LearningCurriculumEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Закрепляет за учеником версию программы и правила перехода между уровнями.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property LevelCode $level Код уровня CEFR.
 * @property int $curriculum_version_id Версия программы, закреплённая за учеником.
 * @property int $promotion_policy_version Версия правил перехода на следующий уровень.
 * @property array $promotion_policy_snapshot Зафиксированные параметры оценки и перехода на следующий уровень.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property \Carbon\CarbonImmutable $enrolled_at Момент закрепления программы за учеником.
 * @property \Carbon\CarbonImmutable|null $superseded_at Момент, когда эту запись заменила другая программа.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningCurriculumEnrollment extends Model
{
    use \App\Support\Learning\UsesUtcDates;
    use HasFactory;

    protected $table = 'learning_curriculum_enrollments';

    protected $fillable = [
        'user_id',
        'level',
        'curriculum_version_id',
        'promotion_policy_version',
        'promotion_policy_snapshot',
        'revision',
        'enrolled_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'level' => LevelCode::class,
            'curriculum_version_id' => 'integer',
            'promotion_policy_version' => 'integer',
            'promotion_policy_snapshot' => 'array',
            'revision' => 'integer',
            'enrolled_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty([
                'user_id',
                'level',
                'curriculum_version_id',
                'promotion_policy_version',
                'promotion_policy_snapshot',
                'enrolled_at',
            ])) {
                throw new LogicException('LearningCurriculumEnrollment identity and snapshot cannot be changed.');
            }
        });
    }

    protected static function newFactory(): LearningCurriculumEnrollmentFactory
    {
        return LearningCurriculumEnrollmentFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(LearningCurriculum::class, 'curriculum_version_id', 'id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(LearningObjectiveEvidence::class, 'enrollment_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
