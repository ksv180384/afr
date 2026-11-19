<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Учебные настройки пользователя: уровень, часовой пояс и предложения повторения.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property LevelCode $current_level Текущий уровень, по которому подбираются занятия.
 * @property string $timezone Часовой пояс IANA для расчёта локальных дат.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property string|null $last_manual_level_event_id Последнее ручное изменение уровня.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property bool $review_suggestions_enabled Разрешено ли предлагать пользователю повторения.
 * @property \Carbon\CarbonImmutable|null $review_suggestions_snoozed_until До какого момента предложения повторения скрыты.
 */
class LearningProfile extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_profiles';

    protected $fillable = [
        'user_id',
        'current_level',
        'timezone',
        'revision',
        'review_suggestions_enabled',
        'review_suggestions_snoozed_until',
        'last_manual_level_event_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'current_level' => LevelCode::class,
            'revision' => 'integer',
            'review_suggestions_enabled' => 'boolean',
            'review_suggestions_snoozed_until' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): LearningProfileFactory
    {
        return LearningProfileFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'current_level', 'code');
    }

    public function lastManualLevelEvent(): BelongsTo
    {
        return $this->belongsTo(LearningLevelEvent::class, 'last_manual_level_event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
