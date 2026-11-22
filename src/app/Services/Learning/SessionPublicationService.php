<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Support\Facades\Storage;

final class SessionPublicationService
{
    public function __construct(
        private readonly ContentAccess $access,
        private readonly ContentTransaction $transaction,
        private readonly SessionPackageValidator $validator,
        private readonly SessionExportService $export,
    ) {}

    private function validate(LearningSession $version): void
    {
        if ($version->checksum === null || ! hash_equals($version->checksum, CanonicalJson::hash($version->payload))) {
            throw new ContentOperationException(PracticeErrorCode::VersionConflict, 'Контрольная сумма занятия не совпадает.');
        }
        $report = $this->validator->validate(CanonicalJson::encode($this->export->envelope(['sessions' => [$version->payload]])));
        $this->access->validateReport($report);
        $editorial = ['editorial_review_required', 'pronunciation_coverage_review', 'lexeme_meaning_review', 'objective_meaning_review', 'archived_dependency'];
        $blocking = array_values(array_filter($report->warnings(), fn ($issue): bool => $issue->blocksPublication && ! in_array($issue->code, $editorial, true)));
        if ($blocking !== []) {
            throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Материал не готов к публикации.', ['warnings' => array_map(fn ($issue): array => $issue->toArray(), $blocking)]);
        }
    }

    public function review(User $actor, int $id, array $checks, ?string $notes = null): LearningSession
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $checks, $notes): LearningSession {
            $this->access->review($checks);
            if ($notes !== null && (mb_strlen($notes) > config('daily_practice.import.max_text_length') || strlen($notes) > 65535)) {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Редакторское замечание превышает допустимый размер.');
            }
            $version = LearningSession::query()->findOrFail($id);
            if (! in_array($version->status, [SessionVersionStatus::Draft, SessionVersionStatus::Reviewed], true)) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Проверять можно только неопубликованное занятие.');
            }
            $this->validate($version);
            $version->update(['status' => 'reviewed', 'review_checks' => $checks, 'editor_notes' => $notes, 'reviewed_by' => $actor->id, 'reviewed_at' => now('UTC')]);

            return $version;
        });
    }

    public function changeStatus(User $actor, array $ids, string $status, array $metadata = []): array
    {
        return $this->transaction->run($actor, function () use ($actor, $ids, $status, $metadata): array {
            if (! in_array($status, ['draft', 'reviewed', 'published', 'archived'], true) || $ids === [] || count($ids) > 100 || count(array_unique($ids)) !== count($ids)) {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Выберите занятия и допустимый статус.');
            }
            $versions = LearningSession::query()->whereIn('id', $ids)->get();
            if ($versions->count() !== count($ids)) {
                throw new ContentOperationException(PracticeErrorCode::NotFound, 'Занятие не найдено.');
            }
            if (array_key_exists('notes', $metadata)) {
                $notes = $metadata['notes'];
                if ($notes !== null && (! is_string($notes) || mb_strlen($notes) > 20000 || strlen($notes) > 65535)) {
                    throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Слишком длинное замечание редактора.');
                }
                foreach ($versions as $version) {
                    if ($version->editor_notes !== $notes) {
                        $version->update(['editor_notes' => $notes]);
                    }
                }
            }
            if ($status === 'published') {
                return $this->publishMany($actor, $ids, false);
            }
            $active = $versions->filter(fn ($version) => $version->status === SessionVersionStatus::Published && $version->status->value !== $status);
            if ($active->isNotEmpty()) {
                $this->archive($actor, $active->pluck('id')->all());
            }
            foreach ($versions as $version) {
                $version->refresh();
                if ($version->status->value === $status) {
                    continue;
                }
                if ($status === 'archived') {
                    $this->archiveVersion($actor, $version->id);

                    continue;
                }
                if ($status === 'reviewed') {
                    $this->validate($version);
                }
                $version->update(['status' => $status, 'review_checks' => null, 'reviewed_by' => $status === 'reviewed' ? $actor->id : null,
                    'reviewed_at' => $status === 'reviewed' ? now('UTC') : null, 'archived_at' => null]);
            }

            return LearningSession::query()->whereIn('id', $ids)->get()->all();
        });
    }

    public function publish(User $actor, int $id): LearningSession
    {
        return $this->publishMany($actor, [$id])[0];
    }

    public function publishMany(User $actor, array $ids, bool $requireReview = true): array
    {
        return $this->transaction->run($actor, function () use ($actor, $ids, $requireReview): array {
            $versions = LearningSession::query()->whereIn('id', $ids)->get();
            if ($ids === [] || count($ids) > 100 || $versions->count() !== count(array_unique($ids))) {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Укажите до ста занятий.');
            }
            $planned = $versions->pluck('id')->all();
            $result = [];
            foreach ($versions as $version) {
                $result[] = $this->publishOne($actor, $version->id, $planned, $requireReview);
            }
            $this->access->validateReport($this->validator->validate(CanonicalJson::encode($this->export->envelope(['sessions' => $versions->map(fn ($version): array => $version->payload)->all()]))));

            return $result;
        });
    }

    private function publishOne(User $actor, int $id, array $planned, bool $requireReview): LearningSession
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $planned, $requireReview): LearningSession {
            $version = LearningSession::query()->with('unit')->findOrFail($id);
            $session = $version;
            if ($version->status === SessionVersionStatus::Published && $session->archived_at === null) {
                return $version;
            }
            if ($requireReview && ($version->status !== SessionVersionStatus::Reviewed || ! $version->reviewed_at)) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Перед публикацией требуется review.');
            }
            if ($requireReview) {
                $this->access->review($version->review_checks ?? []);
            }
            $this->validate($version);
            if (! $session->unit->published_at || $session->unit->archived_at || ! LearningLevel::published()->where('code', $session->getRawOriginal('level'))->exists()) {
                throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Сначала опубликуйте описание уровня и темы.');
            }
            foreach ($version->payload['prerequisite_ids'] as $reference) {
                $dependency = LearningSession::query()->where('external_id', $reference)->first();
                if (! $dependency || (! in_array($dependency->id, $planned, true) && ($dependency->archived_at || $dependency->status !== SessionVersionStatus::Published))) {
                    $title = $dependency?->payload['title'] ?? $reference;
                    throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Сначала опубликуйте предыдущее занятие «'.$title.'» ('.$reference.'). Можно выделить его вместе с текущим в списке занятий и опубликовать выбранные одной группой.', ['session_id' => $reference, 'session_title' => $title]);
                }
            }
            foreach ($version->payload['public_page']['related_session_ids'] ?? [] as $reference) {
                $related = LearningSession::query()->where('external_id', $reference)->first();
                if (! $related || (! in_array($related->id, $planned, true) && ($related->archived_at || $related->status !== SessionVersionStatus::Published))) {
                    throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Связанная публичная сессия ещё не опубликована.', ['session_id' => $reference]);
                }
            }
            foreach (app(MediaLibraryService::class)->forContent($version->payload) as $media) {
                if (! Storage::disk($media->disk)->exists($media->path)) {
                    throw new ContentOperationException(PracticeErrorCode::ValidationFailed, 'Проверенный аудиофайл недоступен.');
                }
            }
            if ($version->payload['kind'] !== 'checkpoint') {
                foreach (LearningCurriculum::query()->where('status', 'published')->lazyById(100) as $curriculum) {
                    if (in_array($session->external_id, $curriculum->checkpoint_session_ids, true)) {
                        throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Опубликованная программа использует эту сессию как контроль.');
                    }
                }
            }
            $slug = $version->payload['public_page']['slug'];
            if ($session->slug !== null && $session->slug !== $slug && $session->published_at === null) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Постоянный slug опубликованной сессии нельзя менять.');
            }
            $path = PracticeUrls::unit($session->unit).'/'.($session->slug ?? $slug);
            if (\App\Models\Learning\LearningUrlAlias::where('old_path', $path)->exists()) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Адрес зарезервирован прежним опубликованным материалом.');
            }
            $session->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => $session->published_at ?? now('UTC'),
                'archived_at' => null, 'slug' => $session->slug ?? $slug, 'sort_order' => $session->payload['order']]);

            return $version;
        });
    }

    public function archive(User $actor, array $sessionIds): array
    {
        return $this->transaction->run($actor, function () use ($actor, $sessionIds): array {
            $targets = LearningSession::query()->whereIn('id', $sessionIds)->get();
            if ($targets->count() !== count(array_unique($sessionIds))) {
                throw new ContentOperationException(PracticeErrorCode::NotFound, 'Сессия для архивирования не найдена.');
            }
            $externalIds = $targets->pluck('external_id')->all();
            foreach (LearningSession::query()->published()->lazyById(100) as $other) {
                if (in_array($other->external_id, $externalIds, true)) {
                    continue;
                }
                $payload = $other->payload;
                if (array_intersect($externalIds, [...$payload['prerequisite_ids'], ...($payload['public_page']['related_session_ids'] ?? [])]) !== []) {
                    throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Сначала снимите или исправьте зависимые публичные сессии.', ['dependent_session_id' => $other->external_id]);
                }
            }
            foreach (LearningCurriculum::query()->where('status', 'published')->lazyById(100) as $curriculum) {
                if (array_intersect($externalIds, $curriculum->checkpoint_session_ids) !== []) {
                    throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Сначала архивируйте или замените зависимую программу.', ['curriculum_id' => $curriculum->id]);
                }
            }
            foreach ($targets as $session) {
                $session->update(['status' => 'archived', 'archived_at' => now('UTC')]);
            }

            return ['archived_session_ids' => $targets->modelKeys()];
        });
    }

    public function archiveVersion(User $actor, int $id): LearningSession
    {
        return $this->transaction->run($actor, function () use ($actor, $id): LearningSession {
            $version = LearningSession::query()->findOrFail($id);
            if ($version->status === SessionVersionStatus::Published) {
                $this->archive($actor, [$version->id]);

                return $version->refresh();
            }
            if ($version->status !== SessionVersionStatus::Archived) {
                $version->update(['status' => 'archived', 'archived_at' => now('UTC')]);
            }

            return $version;
        });
    }
}
