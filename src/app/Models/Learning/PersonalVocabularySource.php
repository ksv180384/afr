<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\PersonalVocabularySourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PersonalVocabularySource extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'personal_vocabulary_sources';

    protected $fillable = [
        'user_id',
        'personal_word_id',
        'session_version_id',
        'vocabulary_id',
        'snapshot',
        'first_added_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'personal_word_id' => 'integer',
            'session_version_id' => 'integer',
            'snapshot' => 'array',
            'first_added_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['user_id', 'personal_word_id', 'session_version_id', 'vocabulary_id', 'snapshot', 'first_added_at'])) {
                throw new LogicException('PersonalVocabularySource identity and snapshot cannot be changed.');
            }
        });
    }

    protected static function newFactory(): PersonalVocabularySourceFactory
    {
        return PersonalVocabularySourceFactory::new();
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(PersonalVocabulary::class, 'personal_word_id', 'id');
    }

    public function sessionVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'session_version_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
