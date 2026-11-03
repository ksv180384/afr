<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningCurriculumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LearningCurriculum extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_curricula';

    protected $fillable = [
        'external_id',
        'version',
        'level',
        'required_objective_ids',
        'checkpoint_session_ids',
        'promotion_policy_version',
        'promotion_policy_snapshot',
        'checksum',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'level' => LevelCode::class,
            'required_objective_ids' => 'array',
            'checkpoint_session_ids' => 'array',
            'promotion_policy_version' => 'integer',
            'promotion_policy_snapshot' => 'array',
            'created_by' => 'integer',
            'published_by' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'status' => SessionVersionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['external_id', 'version', 'level', 'required_objective_ids', 'checkpoint_session_ids', 'promotion_policy_version', 'promotion_policy_snapshot', 'checksum']) || ($model->getRawOriginal('published_at') !== null && $model->isDirty('published_at'))) {
                throw new LogicException('Published curriculum is immutable.');
            }
        });
    }

    protected static function newFactory(): LearningCurriculumFactory
    {
        return LearningCurriculumFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level', 'code');
    }

    public function objectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'learning_curriculum_objectives', 'curriculum_id', 'objective_id');
    }

    public function checkpoints(): BelongsToMany
    {
        return $this->belongsToMany(LearningSession::class, 'learning_curriculum_checkpoints', 'curriculum_id', 'session_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningCurriculumEnrollment::class, 'curriculum_version_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'id');
    }
}
