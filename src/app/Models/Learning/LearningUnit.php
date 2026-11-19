<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Тема уровня: группирует занятия и задаёт оформление и описание темы.
 *
 * @property int $id Первичный ключ записи.
 * @property string $external_id Стабильный идентификатор из пакета контента.
 * @property LevelCode $level Код уровня CEFR.
 * @property int $sort_order Позиция в порядке показа.
 * @property string $slug Часть публичного адреса.
 * @property string $title Название, отображаемое в интерфейсе.
 * @property string|null $summary Краткое описание для каталога.
 * @property array|null $learning_outcomes Список ожидаемых результатов обучения.
 * @property array|null $pronunciation_notes Список рекомендаций по произношению.
 * @property int $revision Номер ревизии записи, изменяемый при обновлении её состояния.
 * @property \Carbon\CarbonImmutable|null $published_at Момент публикации.
 * @property \Carbon\CarbonImmutable|null $archived_at Момент архивации или снятия с публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property string|null $background_path Путь загруженного изображения фона темы.
 */
class LearningUnit extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_units';

    protected $fillable = [
        'external_id',
        'level',
        'sort_order',
        'slug',
        'title',
        'summary',
        'learning_outcomes',
        'pronunciation_notes',
        'revision',
        'published_at',
        'archived_at',
        'background_path',
    ];

    protected function casts(): array
    {
        return [
            'level' => LevelCode::class,
            'sort_order' => 'integer',
            'learning_outcomes' => 'array',
            'pronunciation_notes' => 'array',
            'revision' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): LearningUnitFactory
    {
        return LearningUnitFactory::new();
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class, 'unit_id', 'id');
    }
}
