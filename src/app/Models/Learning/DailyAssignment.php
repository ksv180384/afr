<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Хранит выбранное прохождение для календарного дня пользователя и его ревизию.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property \Carbon\CarbonImmutable $local_date Календарная дата в часовом поясе ученика.
 * @property string|null $selected_run_id Выбранное на этот день прохождение.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class DailyAssignment extends Model
{
    use UsesUtcDates;

    protected $table = 'daily_assignments';

    protected $fillable = [
        'user_id',
        'local_date',
        'selected_run_id',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'local_date' => 'immutable_date:Y-m-d',
            'revision' => 'integer',
        ];
    }

    public function selectedRun(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'selected_run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
