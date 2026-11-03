<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAssignment extends Model
{
    use UsesUtcDates;

    protected $table = 'daily_assignments';

    protected $fillable = [
        'user_id',
        'local_date',
        'selected_run_id',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'local_date' => 'immutable_date:Y-m-d',
            'revision' => 'integer',
        ];
    }

    public function selectedRun(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'selected_run_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
