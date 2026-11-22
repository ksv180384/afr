<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Справочник учебных целей, которые связывают задания с требованиями программы.
 *
 * @property int $id Первичный ключ записи.
 * @property string $stable_id Стабильный идентификатор учебной цели, на который ссылаются пакеты.
 * @property LevelCode $level Код уровня CEFR.
 * @property string $title Название, отображаемое в интерфейсе.
 * @property string|null $definition Определение учебной цели.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningObjective extends Model
{
    protected $table = 'learning_objectives';

    protected $fillable = [
        'stable_id',
        'level',
        'title',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'level' => LevelCode::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['stable_id', 'level', 'definition'])) {
                throw new \LogicException('Objective identity and meaning are immutable; use a new stable ID.');
            }
        });
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

}
