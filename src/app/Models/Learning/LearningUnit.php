<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class, 'unit_id', 'id');
    }
}
