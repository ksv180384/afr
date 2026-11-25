<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Enums\Learning\SessionVersionStatus;
use App\Services\Learning\PracticeDictionary;
use App\Support\Learning\EncodesPracticeJson;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Карточка занятия и его текущее содержимое.
 *
 * @property int $id Первичный ключ записи.
 * @property string $external_id Стабильный идентификатор из пакета контента.
 * @property LevelCode $level Код уровня CEFR.
 * @property int $unit_id Тема, к которой относится запись.
 * @property int $sort_order Позиция в порядке показа.
 * @property string|null $slug Часть публичного адреса.
 * @property array|null $payload Актуальное содержимое занятия.
 * @property string|null $checksum Контрольная сумма содержимого.
 * @property SessionVersionStatus $status Редакторский статус занятия.
 * @property \Carbon\CarbonImmutable|null $published_at Момент первой публикации.
 * @property \Carbon\CarbonImmutable|null $archived_at Момент архивации или снятия с публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningSession extends Model
{
    use EncodesPracticeJson;
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_sessions';

    protected $fillable = [
        'external_id',
        'level',
        'unit_id',
        'sort_order',
        'slug',
        'archived_at',
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
    ];

    protected $attributes = ['status' => 'draft', 'schema_version' => 1];

    protected function casts(): array
    {
        return [
            'level' => LevelCode::class,
            'unit_id' => 'integer',
            'sort_order' => 'integer',
            'archived_at' => 'immutable_datetime',
            'schema_version' => 'integer',
            'status' => SessionVersionStatus::class,
            'review_checks' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected function payload(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?array => $value === null ? null : PracticeDictionary::expand((int) $this->id,
                json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
            set: fn (?array $value): ?string => $value === null ? null
                : $this->asJson($value),
        );
    }

    public function save(array $options = []): bool
    {
        if (! $this->isDirty('payload')) {
            return parent::save($options);
        }
        $full = $this->attributes['payload'];
        $payload = $full === null ? null : json_decode($full, true, 512, JSON_THROW_ON_ERROR);
        $words = $payload['vocabulary'] ?? [];
        try {
            return DB::transaction(function () use ($options, $words, $payload) {
                if (isset($payload['vocabulary'])) {
                    $payload['vocabulary'] = array_column($words, 'lexeme_key');
                    $this->attributes['payload'] = $this->asJson($payload);
                }
                $saved = parent::save($options);
                if ($saved) {
                    app(PracticeDictionary::class)->sync($this->id, $words, $this->status === SessionVersionStatus::Published);
                }

                return $saved;
            });
        } catch (\Throwable $error) {
            $this->attributes['payload'] = $full;
            throw $error;
        }
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['external_id', 'level', 'unit_id'])
                || ($model->getRawOriginal('published_at') !== null && $model->isDirty('published_at'))) {
                throw new LogicException('Lesson identity and first publication cannot be rewritten.');
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('learning_sessions.status', SessionVersionStatus::Published->value)
            ->whereNull('learning_sessions.archived_at');
    }

    protected static function newFactory(): LearningSessionFactory
    {
        return LearningSessionFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'unit_id', 'id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(SessionImportBatch::class, 'import_batch_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(LearningRun::class, 'session_id');
    }

    public function vocabularySources(): HasMany
    {
        return $this->hasMany(PersonalVocabularySource::class, 'session_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(LearningContentReport::class, 'session_id');
    }
}
