<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningObjective extends Model
{
    protected $table = 'learning_objectives';

    protected $fillable = [
        'stable_id',
        'level',
        'title',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'level' => LevelCode::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['stable_id', 'level', 'definition'])) {
                throw new \LogicException('Objective identity and meaning are immutable; use a new stable ID.');
            }
        });
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function curricula(): BelongsToMany
    {
        return $this->belongsToMany(LearningCurriculum::class, 'learning_curriculum_objectives', 'objective_id', 'curriculum_id');
    }
}
