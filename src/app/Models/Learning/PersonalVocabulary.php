<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\PersonalVocabularyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Слово или выражение в словаре пользователя и его расписание интервального повторения.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $lexeme_key Стабильный ключ слова или выражения для объединения одинаковых слов.
 * @property array $snapshot Снимок слова или выражения для показа и повторения.
 * @property bool $needs_review Нужно ли включать слово в повторение.
 * @property bool $is_difficult Личная отметка сложного слова.
 * @property int $streak Количество последовательных успешных проверок слова.
 * @property int $errors Накопленное количество ошибок по слову.
 * @property int $review_step Текущая ступень интервального повторения.
 * @property \Carbon\CarbonImmutable $first_added_at Момент первого добавления слова.
 * @property \Carbon\CarbonImmutable $next_review_at Момент следующего запланированного повторения.
 * @property \Carbon\CarbonImmutable|null $last_reviewed_at Момент последней учтённой проверки слова.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property int $native_revision Счётчик изменений слова в аккаунте, защищающий их от перезаписи гостевыми данными.
 */
class PersonalVocabulary extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'personal_vocabulary';

    protected $fillable = [
        'native_revision',
        'user_id',
        'lexeme_key',
        'snapshot',
        'needs_review',
        'is_difficult',
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
            'native_revision' => 'integer',
            'user_id' => 'integer',
            'snapshot' => 'array',
            'needs_review' => 'boolean',
            'is_difficult' => 'boolean',
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
