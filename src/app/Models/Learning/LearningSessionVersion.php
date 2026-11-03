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
            if ($model->getRawOriginal('published_at') !== null && $model->isDirty('published_at')) {
                throw new LogicException('Publication timestamp cannot be cleared or rewritten.');
            }
            if ($model->isDirty(['session_id', 'version', 'schema_version', 'payload', 'checksum', 'import_batch_id'])) {
                throw new LogicException('LearningSessionVersion identity and snapshot cannot be changed.');
            }
        });
        static::deleting(function (self $model): void {
            if ($model->published_at !== null) {
                throw new LogicException('Published versions must be archived, not deleted.');
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
        return $this->belongsToMany(LearningMedia::class, 'learning_session_version_media', 'session_version_id', 'media_id');
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
