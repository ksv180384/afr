<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningSession;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;

final class CurriculumPublicationService
{
    public function __construct(private readonly ContentAccess $access, private readonly ContentTransaction $transaction, private readonly SessionExportService $export, private readonly SessionPackageValidator $validator) {}

    private function ready(LearningCurriculum $curriculum): void
    {
        $content = $this->export->curriculum($curriculum);
        if (! hash_equals($curriculum->checksum, CanonicalJson::hash($content))) {
            throw new ContentOperationException(PracticeErrorCode::VersionConflict, 'Checksum программы нарушен.');
        }
        $this->access->validateReport($this->validator->validate(CanonicalJson::encode($this->export->envelope(['curricula' => [$content]]))));
        if (count(array_unique($curriculum->checkpoint_session_ids)) < 2 || $curriculum->required_objective_ids === []) {
            throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Программе нужны обязательные цели и минимум два контроля.');
        }
        $objectives = LearningObjective::query()->whereIn('stable_id', $curriculum->required_objective_ids)->where('level', $curriculum->getRawOriginal('level'))->count();
        if ($objectives !== count($curriculum->required_objective_ids)) {
            throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Обязательные цели недоступны.');
        }
        $remaining = array_fill_keys($curriculum->required_objective_ids, true);
        foreach (LearningSession::query()->where('level', $curriculum->getRawOriginal('level'))->whereNull('archived_at')->whereNotNull('active_version_id')->with('activeVersion')->lazyById(100) as $session) {
            if ($session->activeVersion?->status === SessionVersionStatus::Published) {
                foreach ($session->activeVersion->payload['tasks'] as $task) {
                    foreach ($task['objective_ids'] as $id) {
                        unset($remaining[$id]);
                    }
                }
            }
            if ($remaining === []) {
                break;
            }
        }
        if ($remaining !== []) {
            throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Не все обязательные цели представлены опубликованными заданиями.');
        }
        foreach ($curriculum->checkpoint_session_ids as $id) {
            $session = LearningSession::query()->with('activeVersion')->where('external_id', $id)->first();
            if (! $session || $session->archived_at || $session->activeVersion?->status !== SessionVersionStatus::Published || ($session->activeVersion->payload['kind'] ?? null) !== 'checkpoint') {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Контроль программы ещё не опубликован.', ['session_id' => $id]);
            }
        }
    }

    public function review(User $actor, int $id, array $checks): LearningCurriculum
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $checks): LearningCurriculum {
            $this->access->review($checks);
            $curriculum = LearningCurriculum::query()->findOrFail($id);
            if (! in_array($curriculum->status, [SessionVersionStatus::Draft, SessionVersionStatus::Reviewed], true)) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Программа уже опубликована или архивирована.');
            }
            $this->ready($curriculum);
            $curriculum->update(['status' => 'reviewed']);
            $this->access->audit($actor, 'curriculum_review', ['curriculum_id' => $id], [], ['checks' => $checks]);

            return $curriculum;
        });
    }

    public function publish(User $actor, int $id, bool $requireReview = true): LearningCurriculum
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $requireReview): LearningCurriculum {
            $curriculum = LearningCurriculum::query()->findOrFail($id);
            if ($curriculum->status === SessionVersionStatus::Published) {
                return $curriculum;
            }
            if ($requireReview && $curriculum->status !== SessionVersionStatus::Reviewed) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Перед публикацией программы требуется review.');
            }
            $previousStatus = $curriculum->status->value;
            $this->ready($curriculum);
            foreach (LearningCurriculum::query()->where('level', $curriculum->getRawOriginal('level'))->where('status', 'published')->get() as $old) {
                $old->update(['status' => 'archived', 'archived_at' => now('UTC')]);
                $this->access->audit($actor, 'curriculum_supersede', ['curriculum_id' => $old->id], ['status' => 'published'], ['replacement_id' => $id]);
            }
            $curriculum->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => $curriculum->published_at ?? now('UTC'), 'archived_at' => null]);
            $this->access->audit($actor, 'curriculum_publish', ['curriculum_id' => $id], ['status' => $previousStatus], ['status' => 'published']);

            return $curriculum;
        });
    }

    public function archive(User $actor, int $id): LearningCurriculum
    {
        return $this->transaction->run($actor, function () use ($actor, $id): LearningCurriculum {
            $curriculum = LearningCurriculum::query()->findOrFail($id);
            if ($curriculum->status !== SessionVersionStatus::Archived) {
                $before = $curriculum->status->value;
                $curriculum->update(['status' => 'archived', 'archived_at' => now('UTC')]);
                $this->access->audit($actor, 'curriculum_archive', ['curriculum_id' => $id], ['status' => $before], ['status' => 'archived']);
            }

            return $curriculum;
        });
    }
}
