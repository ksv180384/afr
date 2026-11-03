<?php

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningUrlAlias extends Model
{
    protected $table = 'learning_url_aliases';

    protected $fillable = [
        'old_path',
        'target_path',
        'session_id',
        'unit_id',
        'level_code',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'unit_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class, 'unit_id', 'id');
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'level_code', 'code');
    }
}
