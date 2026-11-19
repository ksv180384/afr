<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Хранит предпросмотр импортируемого пакета, проверку и результат подтверждения.
 *
 * @property string $id Первичный ключ записи.
 * @property string $file_hash SHA-256 исходного файла пакета.
 * @property string $preview_token_hash SHA-256 токена подтверждения предпросмотра.
 * @property int|null $created_by Пользователь, создавший запись.
 * @property string $private_path Путь исходного пакета в закрытом хранилище.
 * @property string $original_filename Имя файла при загрузке.
 * @property int $file_size Размер исходного файла в байтах.
 * @property string $status Состояние импорта: предпросмотр или подтверждённый импорт.
 * @property array|null $validation_report Результат проверки структуры и содержания пакета.
 * @property array|null $receipt Сохранённый результат обработки для повторных запросов.
 * @property \Carbon\CarbonImmutable $expires_at Срок действия предпросмотра и токена подтверждения.
 * @property \Carbon\CarbonImmutable|null $committed_at Момент фиксации импорта.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
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
