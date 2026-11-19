<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Фиксирует влияние учебного события на слово и защищает от повторного начисления.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property string $event_id Учебное событие, породившее запись.
 * @property int $personal_word_id Запись слова в личном словаре.
 * @property string $lexeme_key Стабильный ключ слова или выражения для объединения одинаковых слов.
 * @property array $effect Изменение словарного прогресса, вызванное учебным событием.
 * @property \Carbon\CarbonImmutable $applied_at Момент применения изменения к слову.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
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
