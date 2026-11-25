<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\EncodesPracticeJson;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Хранит редакцию метаданных уровня или темы до проверки и публикации.
 *
 * @property int $id Первичный ключ записи.
 * @property string|null $level_code Код уровня, к которому относится запись.
 * @property int|null $unit_id Тема, к которой относится запись.
 * @property string|null $import_batch_id Пакет, из которого импортирована запись.
 * @property array $content Метаданные уровня или темы, предложенные к публикации.
 * @property int $base_revision Ревизия уровня или темы, на основе которой подготовлено изменение.
 * @property string $status Состояние редакции в процессе проверки и публикации.
 * @property int|null $created_by Пользователь, создавший запись.
 * @property int|null $reviewed_by Редактор, выполнивший проверку.
 * @property int|null $published_by Редактор, выполнивший публикацию.
 * @property \Carbon\CarbonImmutable|null $reviewed_at Момент редакторской проверки.
 * @property \Carbon\CarbonImmutable|null $published_at Момент публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningCatalogRevision extends Model
{
    use EncodesPracticeJson;
    use UsesUtcDates;

    protected $table = 'learning_catalog_revisions';

    protected $fillable = [
        'level_code',
        'unit_id',
        'import_batch_id',
        'content',
        'base_revision',
        'status',
        'created_by',
        'reviewed_by',
        'published_by',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'content' => 'array',
            'base_revision' => 'integer',
            'created_by' => 'integer',
            'reviewed_by' => 'integer',
            'published_by' => 'integer',
            'reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['level_code', 'unit_id', 'import_batch_id', 'content', 'base_revision'])) {
                throw new LogicException('Catalog revision snapshot cannot be changed.');
            }
        });
        static::saving(function (self $model): void {
            if (($model->level_code === null) === ($model->unit_id === null)) {
                throw new LogicException('Catalog revision must target exactly one level or unit.');
            }
        });
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level_code', 'code');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'unit_id', 'id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(SessionImportBatch::class, 'import_batch_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'id');
    }
}
