<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningActivityDay extends Model
{
    use UsesUtcDates;

    protected $table = 'learning_activity_days';

    protected $fillable = [
        'user_id',
        'local_date',
        'qualifying_run_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'local_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function qualifyingRun(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'qualifying_run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
