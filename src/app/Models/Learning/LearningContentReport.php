<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Хранит обращение о содержании занятия или задания и результат его рассмотрения.
 *
 * @property int $id Первичный ключ записи.
 * @property int $session_version_id Версия занятия, к которой относится запись.
 * @property string|null $task_id Стабильный идентификатор задания внутри версии занятия.
 * @property int|null $user_id Автор обращения, если он связан с аккаунтом.
 * @property string $text Текст обращения пользователя.
 * @property string $status Состояние рассмотрения обращения.
 * @property int|null $processed_by Редактор, обработавший обращение.
 * @property string|null $editor_notes Заметки редактора.
 * @property \Carbon\CarbonImmutable|null $resolved_at Момент закрытия обращения.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 * @property string|null $client_report_id Клиентский UUID обращения для безопасной повторной отправки.
 * @property string|null $payload_hash SHA-256 входных данных для проверки, что повторная отправка содержит те же данные.
 * @property array|null $answer_snapshot Контекст обращения о проверке ответа, сохранённый в момент отправки.
 */
class LearningContentReport extends Model
{
    use UsesUtcDates;

    protected $table = 'learning_content_reports';

    protected $fillable = [
        'client_report_id',
        'payload_hash',
        'answer_snapshot',
        'session_version_id',
        'task_id',
        'user_id',
        'text',
        'status',
        'processed_by',
        'editor_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_snapshot' => 'array',
            'session_version_id' => 'integer',
            'user_id' => 'integer',
            'processed_by' => 'integer',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function sessionVersion(): BelongsTo
    {
        return $this->belongsTo(LearningSessionVersion::class, 'session_version_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }
}
