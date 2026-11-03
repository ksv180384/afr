<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LearningCatalogRevision extends Model
{
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
