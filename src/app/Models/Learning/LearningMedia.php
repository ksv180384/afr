<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningMedia extends Model
{
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_media';

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size',
        'duration_ms',
        'checksum',
        'review_status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration_ms' => 'integer',
            'created_by' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(LearningSessionVersion::class, 'learning_session_version_media', 'media_id', 'session_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}
