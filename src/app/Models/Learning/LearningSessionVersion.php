<?php

namespace App\Models\Learning;

use App\Enums\Learning\SessionVersionStatus;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningSessionVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Версия содержимого занятия, которую импортируют, проверяют и публикуют.
 *
 * @property int $id Первичный ключ записи.
 * @property int $session_id Занятие, к которому относится запись.
 * @property int $version Порядковый номер версии.
 * @property int $schema_version Версия схемы формата задания и содержимого занятия.
 * @property array $payload Полное содержимое версии занятия из пакета.
 * @property string $checksum SHA-256 содержимого для проверки целостности и совпадения версий.
 * @property SessionVersionStatus $status Редакторский статус версии занятия.
 * @property string|null $import_batch_id Пакет, из которого импортирована запись.
 * @property int|null $created_by Пользователь, создавший запись.
 * @property int|null $reviewed_by Редактор, выполнивший проверку.
 * @property int|null $published_by Редактор, выполнивший публикацию.
 * @property string|null $editor_notes Заметки редактора.
 * @property array|null $review_checks Отметки редактора по пунктам проверки контента.
 * @property \Carbon\CarbonImmutable|null $reviewed_at Момент редакторской проверки.
 * @property \Carbon\CarbonImmutable|null $published_at Момент публикации.
 * @property \Carbon\CarbonImmutable|null $archived_at Момент архивации или снятия с публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningSessionVersion extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_session_versions';

    protected $fillable = [
        'session_id',
        'version',
        'schema_version',
        'payload',
        'checksum',
        'status',
        'import_batch_id',
        'created_by',
        'reviewed_by',
        'published_by',
        'editor_notes',
        'review_checks',
        'reviewed_at',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'version' => 'integer',
            'schema_version' => 'integer',
            'payload' => 'array',
            'created_by' => 'integer',
            'reviewed_by' => 'integer',
            'published_by' => 'integer',
            'review_checks' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'status' => SessionVersionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty([
                'session_id',
                'version',
            ])
                || ($model->getRawOriginal('published_at') !== null && $model->isDirty('published_at'))
            ) {
                throw new LogicException('Content identity and publication timestamp cannot be rewritten.');
            }
        });
    }

    protected static function newFactory(): LearningSessionVersionFactory
    {
        return LearningSessionVersionFactory::new();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(SessionImportBatch::class, 'import_batch_id', 'id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(LearningRun::class, 'session_version_id', 'id');
    }

    public function vocabularySources(): HasMany
    {
        return $this->hasMany(PersonalVocabularySource::class, 'session_version_id', 'id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(LearningContentReport::class, 'session_version_id', 'id');
    }

    public function editorialEvents(): HasMany
    {
        return $this->hasMany(LearningEditorialEvent::class, 'session_version_id', 'id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            LearningMedia::class,
            'learning_session_version_media',
            'session_version_id',
            'media_id',
        );
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
