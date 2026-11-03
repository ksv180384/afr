<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningContentReport extends Model
{
    use UsesUtcDates;

    protected $table = 'learning_content_reports';

    protected $fillable = [
        'client_report_id', 'payload_hash', 'answer_snapshot',
        'session_version_id',
        'task_id',
        'user_id',
        'text',
        'status',
        'processed_by',
        'editor_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_snapshot' => 'array',
            'session_version_id' => 'integer',
            'user_id' => 'integer',
            'processed_by' => 'integer',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function sessionVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'session_version_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }
}
