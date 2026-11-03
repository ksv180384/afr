<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningPresentationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class LearningPresentation extends Model
{
    use HasFactory;
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_presentations';

    protected $fillable = [
        'user_id',
        'run_id',
        'task_id',
        'position',
        'snapshot',
        'choice_order',
        'status',
        'help_used',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'position' => 'integer',
            'snapshot' => 'array',
            'choice_order' => 'array',
            'help_used' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['user_id', 'run_id', 'task_id', 'position', 'snapshot', 'choice_order'])) {
                throw new LogicException('LearningPresentation identity and snapshot cannot be changed.');
            }
        });
    }

    protected static function newFactory(): LearningPresentationFactory
    {
        return LearningPresentationFactory::new();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LearningAttempt::class, 'presentation_id', 'id');
    }

    public function lastAttempt(): HasOne
    {
        return $this->hasOne(LearningAttempt::class, 'presentation_id', 'id')->latestOfMany('sequence');
    }

    public function firstAttempt(): HasOne
    {
        return $this->hasOne(LearningAttempt::class, 'presentation_id', 'id')->where('is_first_answer', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
