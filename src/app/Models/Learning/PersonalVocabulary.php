<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\PersonalVocabularyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalVocabulary extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'personal_vocabulary';

    protected $fillable = [
        'native_revision', 'user_id',
        'lexeme_key',
        'snapshot',
        'needs_review',
        'streak',
        'errors',
        'review_step',
        'first_added_at',
        'next_review_at',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'native_revision' => 'integer', 'user_id' => 'integer',
            'snapshot' => 'array',
            'needs_review' => 'boolean',
            'streak' => 'integer',
            'errors' => 'integer',
            'review_step' => 'integer',
            'first_added_at' => 'immutable_datetime',
            'next_review_at' => 'immutable_datetime',
            'last_reviewed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PersonalVocabularyFactory
    {
        return PersonalVocabularyFactory::new();
    }

    public function sources(): HasMany
    {
        return $this->hasMany(PersonalVocabularySource::class, 'personal_word_id', 'id');
    }

    public function eventEffects(): HasMany
    {
        return $this->hasMany(VocabularyEventEffect::class, 'personal_word_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'native_revision', 'user_id', 'id');
    }
}
