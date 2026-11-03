<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionImportBatch extends Model
{
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'session_import_batches';

    protected $fillable = [
        'file_hash',
        'preview_token_hash',
        'created_by',
        'private_path',
        'original_filename',
        'file_size',
        'status',
        'validation_report',
        'receipt',
        'expires_at',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'file_size' => 'integer',
            'validation_report' => 'array',
            'receipt' => 'array',
            'expires_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LearningSessionVersion::class, 'import_batch_id', 'id');
    }

    public function catalogRevisions(): HasMany
    {
        return $this->hasMany(LearningCatalogRevision::class, 'import_batch_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
