<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;

final class CatalogPublicationService
{
    public function __construct(private readonly ContentAccess $access, private readonly ContentTransaction $transaction, private readonly SessionPackageValidator $validator, private readonly SessionExportService $export) {}

    public function diff(User $actor, int $id): array
    {
        $this->access->check($actor);
        $revision = LearningCatalogRevision::query()->findOrFail($id);
        $current = $this->target($revision);

        return ['id' => $id, 'status' => $revision->status, 'base_revision' => $revision->base_revision, 'current_revision' => $current->revision,
            'before' => $revision->unit_id ? $this->export->unit($current) : $current->only(['code', 'title', 'summary', 'learning_outcomes', 'pronunciation_notes']),
            'after' => $revision->content];
    }

    private function target(LearningCatalogRevision $revision): LearningUnit|LearningLevel
    {
        return $revision->unit_id === null
            ? LearningLevel::query()->where('code', $revision->level_code)->firstOrFail()
            : LearningUnit::query()->findOrFail($revision->unit_id);
    }

    private function validate(LearningCatalogRevision $revision): void
    {
        $resources = [$revision->unit_id === null ? 'levels' : 'units' => [$revision->content]];
        $this->access->validateReport($this->validator->validate(CanonicalJson::encode($this->export->envelope($resources))));
        if ($this->target($revision)->revision !== $revision->base_revision) {
            throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Каталог изменён после импорта; создайте новый preview.');
        }
    }

    public function review(User $actor, int $id, array $checks): LearningCatalogRevision
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $checks): LearningCatalogRevision {
            $this->access->review($checks);
            $revision = LearningCatalogRevision::query()->findOrFail($id);
            if (! in_array($revision->status, ['pending', 'reviewed'], true)) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Редакция уже опубликована или архивирована.');
            }
            $this->validate($revision);
            $revision->update(['status' => 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now('UTC')]);
            $this->access->audit($actor, 'catalog_review', ['catalog_revision_id' => $id], [], ['checks' => $checks]);

            return $revision;
        });
    }

    public function publish(User $actor, int $id, bool $requireReview = true): LearningCatalogRevision
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $requireReview): LearningCatalogRevision {
            $revision = LearningCatalogRevision::query()->findOrFail($id);
            if ($revision->status === 'published') {
                return $revision;
            }
            if ($requireReview && ($revision->status !== 'reviewed' || ! $revision->reviewed_at)) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Перед публикацией каталога требуется review.');
            }
            $this->validate($revision);
            $target = $this->target($revision);
            if ($revision->unit_id && $target->published_at && $target->slug !== $revision->content['slug']) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Измените публичный адрес отдельным действием с перенаправлением, затем импортируйте актуальную редакцию.');
            }
            if ($revision->unit_id && \App\Models\Learning\LearningUrlAlias::where('old_path', PracticeUrls::level($target->getRawOriginal('level')).'/'.$revision->content['slug'])->exists()) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Адрес темы зарезервирован прежней публикацией.');
            }
            $before = $target->toArray();
            $content = $revision->content;
            $target->fill([
                'title' => $content['title'], 'summary' => $content['summary'],
                'learning_outcomes' => $content['learning_outcomes'], 'pronunciation_notes' => $content['pronunciation_notes'] ?? [],
                'revision' => $target->revision + 1, 'published_at' => now('UTC'),
                ...($revision->unit_id ? ['slug' => $content['slug'], 'sort_order' => $content['order']] : []),
            ])->save();
            $revision->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now('UTC')]);
            $this->access->audit($actor, 'catalog_publish', ['catalog_revision_id' => $id], $before, $target->toArray());

            return $revision;
        });
    }
}
