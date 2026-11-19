<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Журнал редакторских действий: импорт, проверка, публикация и другие изменения контента. Записи добавляются, а штатное удаление связанных материалов может удалить их события.
 *
 * @property int $id Первичный ключ записи.
 * @property int|null $actor_id Пользователь, от имени которого выполнено действие.
 * @property int|null $session_version_id Версия занятия, к которой относится запись.
 * @property int|null $curriculum_id Версия программы, затронутая действием.
 * @property int|null $catalog_revision_id Редакция метаданных уровня или темы, затронутая действием.
 * @property string|null $media_id Медиафайл, затронутый действием.
 * @property string|null $import_batch_id Пакет, из которого импортирована запись.
 * @property string $operation Код редакторского действия, например session_import или session_publish.
 * @property array|null $previous_state Поля или значения до действия, если они записаны.
 * @property array|null $new_state Поля или результат после действия, если они записаны.
 * @property \Carbon\CarbonImmutable $occurred_at Момент события.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningEditorialEvent extends Model
{
    use UsesUtcDates;

    protected $table = 'learning_editorial_events';

    protected $fillable = [
        'actor_id',
        'session_version_id',
        'curriculum_id',
        'catalog_revision_id',
        'media_id',
        'import_batch_id',
        'operation',
        'previous_state',
        'new_state',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'session_version_id' => 'integer',
            'curriculum_id' => 'integer',
            'catalog_revision_id' => 'integer',
            'previous_state' => 'array',
            'new_state' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new LogicException('LearningEditorialEvent is an append-only record.');
        });
        static::deleting(function (self $model): void {
            throw new LogicException('LearningEditorialEvent is an append-only record.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'id');
    }

    public function sessionVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'session_version_id', 'id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(LearningCurriculum::class, 'curriculum_id', 'id');
    }

    public function catalogRevision(): BelongsTo
    {
        return $this->belongsTo(LearningCatalogRevision::class, 'catalog_revision_id', 'id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(LearningMedia::class, 'media_id', 'id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(SessionImportBatch::class, 'import_batch_id', 'id');
    }
}
