<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Services\Learning\PresentationSnapshot;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningPresentationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Фиксирует конкретную выдачу задания пользователю, включая порядок вариантов и состояние.
 *
 * @property string $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $run_id Прохождение, к которому относится запись.
 * @property string $task_id Стабильный идентификатор задания внутри версии занятия.
 * @property int $position Позиция задания в прохождении.
 * @property string $status Состояние выданного задания в прохождении.
 * @property bool $help_used Была ли использована подсказка.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningPresentation extends Model
{
    use HasFactory;
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_presentations';

    protected $fillable = [
        'user_id',
        'run_id',
        'task_id',
        'position',
        'status',
        'help_used',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'position' => 'integer',
            'help_used' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['user_id', 'run_id', 'task_id', 'position'])) {
                throw new LogicException('LearningPresentation identity cannot be changed.');
            }
        });
    }

    protected static function newFactory(): LearningPresentationFactory
    {
        return LearningPresentationFactory::new();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function taskSnapshot(array $content): array
    {
        return PresentationSnapshot::atPosition($content, $this->position, $this->task_id);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LearningAttempt::class, 'presentation_id', 'id');
    }

    public function lastAttempt(): HasOne
    {
        return $this->hasOne(LearningAttempt::class, 'presentation_id', 'id')->latestOfMany('sequence');
    }

    public function firstAttempt(): HasOne
    {
        return $this->hasOne(LearningAttempt::class, 'presentation_id', 'id')->where('is_first_answer', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
