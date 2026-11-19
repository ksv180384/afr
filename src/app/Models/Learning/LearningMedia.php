<?php

namespace App\Models\Learning;

use App\Models\User\User;
use App\Support\Learning\UsesUtcDates;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Карточка загруженного аудиофайла для занятий с результатом редакторской проверки.
 *
 * @property string $id Первичный ключ записи.
 * @property string $disk Имя диска Laravel, на котором хранится файл.
 * @property string $path Путь файла относительно диска.
 * @property string $mime_type MIME-тип файла.
 * @property int $size Размер файла в байтах.
 * @property int|null $duration_ms Длительность аудио в миллисекундах, если известна.
 * @property string $checksum SHA-256 содержимого для проверки целостности и совпадения версий.
 * @property string $review_status Статус редакторской проверки медиафайла.
 * @property int|null $created_by Пользователь, создавший запись.
 * @property int|null $reviewed_by Редактор, выполнивший проверку.
 * @property \Carbon\CarbonImmutable|null $reviewed_at Момент редакторской проверки.
 * @property \Carbon\CarbonImmutable|null $archived_at Момент архивации или снятия с публикации.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningMedia extends Model
{
    use HasUuids;
    use UsesUtcDates;

    protected $table = 'learning_media';

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size',
        'duration_ms',
        'checksum',
        'review_status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration_ms' => 'integer',
            'created_by' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(
            LearningSessionVersion::class,
            'learning_session_version_media',
            'media_id',
            'session_version_id',
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}
