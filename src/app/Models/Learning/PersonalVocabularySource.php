<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Database\Factories\Learning\PersonalVocabularySourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Фиксирует версию занятия и словарный элемент, из которых пользователь добавил слово.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property int $personal_word_id Запись слова в личном словаре.
 * @property int $session_id Занятие, к которому относится запись.
 * @property string $vocabulary_id Идентификатор словарного элемента в версии занятия.
 * @property array $snapshot Снимок исходного словарного элемента в момент добавления.
 * @property \Carbon\CarbonImmutable $first_added_at Момент первого добавления слова.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class PersonalVocabularySource extends Model
{
    use HasFactory;
    use UsesUtcDates;

    protected $table = 'personal_vocabulary_sources';

    protected $fillable = [
        'user_id',
        'personal_word_id',
        'session_id',
        'vocabulary_id',
        'snapshot',
        'first_added_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'personal_word_id' => 'integer',
            'session_id' => 'integer',
            'snapshot' => 'array',
            'first_added_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty([
                'user_id',
                'personal_word_id',
                'session_id',
                'vocabulary_id',
                'snapshot',
                'first_added_at',
            ])) {
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
