<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class VocabularyEventEffect extends Model
{
    use UsesUtcDates;

    protected $table = 'vocabulary_event_effects';

    protected $fillable = [
        'user_id',
        'event_id',
        'personal_word_id',
        'lexeme_key',
        'effect',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'personal_word_id' => 'integer',
            'effect' => 'array',
            'applied_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new LogicException('VocabularyEventEffect is an append-only record.');
        });
        static::deleting(function (self $model): void {
            throw new LogicException('VocabularyEventEffect is an append-only record.');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LearningAttempt::class, 'event_id', 'id');
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(PersonalVocabulary::class, 'personal_word_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
