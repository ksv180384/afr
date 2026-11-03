<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningLevel as LevelCode;
use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\LearningProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningProfile extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'learning_profiles';

    protected $fillable = [
        'user_id',
        'current_level',
        'timezone',
        'revision',
        'last_manual_level_event_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'current_level' => LevelCode::class,
            'revision' => 'integer',
        ];
    }

    protected static function newFactory(): LearningProfileFactory
    {
        return LearningProfileFactory::new();
    }

    public function levelDefinition(): BelongsTo
    {
        return $this->belongsTo(LearningLevel::class, 'current_level', 'code');
    }

    public function lastManualLevelEvent(): BelongsTo
    {
        return $this->belongsTo(LearningLevelEvent::class, 'last_manual_level_event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
