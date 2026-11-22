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
 * Учебные настройки пользователя: уровень и предложения повторения.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property LevelCode $current_level Текущий уровень, по которому подбираются занятия.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property \Carbon\CarbonImmutable|null $last_manual_level_changed_at Последнее ручное изменение уровня.
 * @property bool $last_level_change_automatic Последнее изменение уровня было автоматическим.
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
        'revision',
        'review_suggestions_enabled',
        'review_suggestions_snoozed_until',
        'last_manual_level_changed_at',
        'last_level_change_automatic',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'current_level' => LevelCode::class,
            'revision' => 'integer',
            'last_manual_level_changed_at' => 'immutable_datetime',
            'last_level_change_automatic' => 'boolean',
            'review_suggestions_enabled' => 'boolean',
            'review_suggestions_snoozed_until' => 'immutable_datetime',
        ];
    }

    protected $attributes = ['last_level_change_automatic' => false];

    protected static function newFactory(): LearningProfileFactory
    {
        return LearningProfileFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'current_level', 'code');
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
