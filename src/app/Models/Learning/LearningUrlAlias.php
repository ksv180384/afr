<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Хранит старые пути страниц практики для перенаправления после переименования.
 *
 * @property int $id Первичный ключ записи.
 * @property string $old_path Прежний публичный путь.
 * @property string $target_path Публичный путь, на который следует перенаправить запрос.
 * @property int|null $session_id Занятие, к которому относится запись.
 * @property int|null $unit_id Тема, к которой относится запись.
 * @property string|null $level_code Код уровня, к которому относится запись.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningUrlAlias extends Model
{
    protected $table = 'learning_url_aliases';

    protected $fillable = [
        'old_path',
        'target_path',
        'session_id',
        'unit_id',
        'level_code',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'unit_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'unit_id', 'id');
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level_code', 'code');
    }
}
