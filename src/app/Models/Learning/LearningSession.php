<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class LearningSession extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_sessions';

    protected $fillable = [
        'external_id',
        'level',
        'unit_id',
        'sort_order',
        'slug',
        'active_version_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => LevelCode::class,
            'unit_id' => 'integer',
            'sort_order' => 'integer',
            'active_version_id' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['external_id', 'level', 'unit_id'])) {
                throw new LogicException('LearningSession identity and snapshot cannot be changed.');
            }
        });
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

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'active_version_id', 'id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(LearningSessionVersion::class, 'session_id')->ofMany('version', 'max');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LearningSessionVersion::class, 'session_id', 'id');
    }
}
