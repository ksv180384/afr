<?php

namespace App\Models\Learning;

use App\Enums\Learning\LearningSkill;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись об ответе, которая позволяет оценить освоение учебной цели в рамках программы.
 *
 * @property int $id Первичный ключ записи.
 * @property int $user_id Пользователь, к которому относится запись.
 * @property int $enrollment_id Запись о программе, закреплённой за учеником.
 * @property int $objective_id Учебная цель, которую проверяло задание.
 * @property string $event_id Учебное событие, породившее запись.
 * @property string $run_id Прохождение, к которому относится запись.
 * @property int $session_id Занятие, к которому относится запись.
 * @property int $session_version_id Версия занятия, к которой относится запись.
 * @property string $task_id Стабильный идентификатор задания внутри версии занятия.
 * @property LearningSkill $skill Навык, проверявшийся заданием.
 * @property bool $eligible Можно ли учитывать запись при оценке освоения цели.
 * @property \Carbon\CarbonImmutable $occurred_at Момент события.
 * @property \Illuminate\Support\Carbon|null $created_at Момент создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Момент последнего обновления записи.
 */
class LearningObjectiveEvidence extends Model
{
    use \App\Support\Learning\UsesUtcDates;

    protected $table = 'learning_objective_evidence';

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'objective_id',
        'event_id',
        'run_id',
        'session_id',
        'session_version_id',
        'task_id',
        'skill',
        'eligible',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'enrollment_id' => 'integer',
            'objective_id' => 'integer',
            'session_id' => 'integer',
            'session_version_id' => 'integer',
            'skill' => LearningSkill::class,
            'eligible' => 'boolean',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Objective evidence is an append-only record.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Objective evidence is an append-only record.');
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningCurriculumEnrollment::class, 'enrollment_id', 'id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id', 'id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LearningAttempt::class, 'event_id', 'id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LearningRun::class, 'run_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'session_id', 'id');
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
