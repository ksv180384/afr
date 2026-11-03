<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\SessionImportBatch;
use App\Models\User\User;

final class SessionExportService
{
    public function __construct(private readonly ContentAccess $access) {}

    public function envelope(array $resources = [], string $packageId = 'export'): array
    {
        return ['schema_version' => 1, 'package_id' => $packageId, 'language_pair' => 'fr-ru', 'sessions' => [], ...$resources];
    }

    public function unit(LearningUnit $unit): array
    {
        return ['id' => $unit->external_id, 'level' => $unit->getRawOriginal('level'), 'slug' => $unit->slug,
            'title' => $unit->title, 'summary' => $unit->summary ?? '', 'learning_outcomes' => $unit->learning_outcomes ?? [],
            'pronunciation_notes' => $unit->pronunciation_notes ?? [], 'order' => $unit->sort_order];
    }

    public function curriculum(LearningCurriculum $curriculum): array
    {
        return ['id' => $curriculum->external_id, 'version' => $curriculum->version, 'level' => $curriculum->getRawOriginal('level'),
            'required_objective_ids' => $curriculum->required_objective_ids, 'checkpoint_session_ids' => $curriculum->checkpoint_session_ids];
    }

    public function version(User $actor, int $id): array
    {
        $this->access->check($actor);
        $version = LearningSessionVersion::query()->with('session.unit')->findOrFail($id);

        $unitContent = LearningCatalogRevision::query()->where('import_batch_id', $version->import_batch_id)->where('unit_id', $version->session->unit_id)->orderByDesc('id')->first()?->content
            ?? $this->unit($version->session->unit);

        return $this->envelope(['units' => [$unitContent], 'sessions' => [$version->payload]], 'version-'.$version->id);
    }

    public function batch(User $actor, string $id): array
    {
        $this->access->check($actor);
        $batch = SessionImportBatch::query()->findOrFail($id);
        if ($batch->status !== 'committed') {
            throw new ContentOperationException(\App\Enums\Learning\PracticeErrorCode::RevisionConflict, 'Пакет ещё не импортирован.');
        }
        $receipt = $batch->receipt;
        $versions = LearningSessionVersion::query()->whereIn('id', $receipt['version_ids'])->get()->keyBy('id');
        $resources = ['sessions' => array_map(fn (int $id): array => $versions[$id]->payload, $receipt['version_ids'])];
        foreach (LearningCatalogRevision::query()->whereIn('id', $receipt['catalog_revision_ids'])->orderBy('id')->get() as $revision) {
            $resources[$revision->unit_id === null ? 'levels' : 'units'][] = $revision->content;
        }
        if ($receipt['curriculum_ids'] !== []) {
            $curricula = LearningCurriculum::query()->whereIn('id', $receipt['curriculum_ids'])->get()->keyBy('id');
            $resources['curricula'] = array_map(fn (int $id): array => $this->curriculum($curricula[$id]), $receipt['curriculum_ids']);
        }

        return $this->envelope($resources, $receipt['package_id']);
    }
}
