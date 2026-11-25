<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Источник выражения, вычисленный из изученного набора и личного словаря.
 *
 * @property string $id Составной идентификатор слова и выражения занятия.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property int $personal_word_id Запись слова в личном словаре.
 * @property int $session_id Занятие, к которому относится запись.
 * @property int $session_lexeme_id Выражение в изученном занятии.
 * @property \Carbon\CarbonImmutable $first_added_at Момент первого добавления слова.
 */
class PersonalVocabularySource extends Model
{
    use UsesUtcDates;

    protected $table = 'studied_vocabulary_sources';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'personal_word_id' => 'integer',
            'session_id' => 'integer',
            'session_lexeme_id' => 'integer',
            'first_added_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        $readOnly = fn () => throw new LogicException('Studied sources are derived from lesson completions.');
        static::saving($readOnly);
        static::deleting($readOnly);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SessionLexeme::class, 'session_lexeme_id');
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(PersonalVocabulary::class, 'personal_word_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
