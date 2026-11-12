<?php

namespace App\Models\Learning;

use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningLevel extends Model
{
    use UsesUtcDates;

    protected $table = 'learning_levels';

    protected $fillable = [
        'code',
        'sort_order',
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
            'sort_order' => 'integer',
            'learning_outcomes' => 'array',
            'pronunciation_notes' => 'array',
            'revision' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereNull('archived_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->archived_at === null;
    }

    public function units(): HasMany
    {
        return $this->hasMany(LearningUnit::class, 'level', 'code');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class, 'level', 'code');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'level', 'code');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(LearningCurriculum::class, 'level', 'code');
    }
}
