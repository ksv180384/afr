<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class VocabularyRunEffect extends Model
{
    use UsesUtcDates;

    protected $table = 'vocabulary_run_effects';

    protected $fillable = [
        'user_id',
        'lexeme_key',
        'run_id',
        'success_applied',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'success_applied' => 'boolean',
            'applied_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('success_applied') && ! $model->success_applied) {
                throw new LogicException('A recorded run success cannot be cleared by a later error.');
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
