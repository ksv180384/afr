<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Отмечает учтённый успешный ответ для пары «прохождение — слово».
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $lexeme_key Стабильный ключ слова или выражения для объединения одинаковых слов.
 * @property string $run_id Прохождение, к которому относится запись.
 * @property bool $success_applied Был ли уже учтён успех по этому слову в прохождении.
 * @property \Carbon\CarbonImmutable|null $applied_at Момент применения изменения к слову.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class VocabularyRunEffect extends Model
{
    use UsesUtcDates;

    protected $table = 'vocabulary_run_effects';

    protected $fillable = [
        'user_id',
        'lexeme_key',
        'run_id',
        'success_applied',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'success_applied' => 'boolean',
            'applied_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('success_applied') && ! $model->success_applied) {
                throw new LogicException('A recorded run success cannot be cleared by a later error.');
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
