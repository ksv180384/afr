<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\SessionImportBatch;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class SessionImportService
{
    public function __construct(
        private readonly SessionPackageValidator $validator,
        private readonly ContentAccess $access,
        private readonly ContentTransaction $transaction,
    ) {}

    public function preview(User $actor, UploadedFile $file): array
    {
        $this->access->check($actor);
        // Imports also maintain their temporary files on hosting without cron.
        try {
            $this->pruneExpired();
        } catch (Throwable $error) {
            app(PracticeTelemetry::class)->record('preview_cleanup_failure', ['error_class' => $error::class]);
        }
        $report = $this->validator->validateFile($file->getRealPath());
        $this->access->validateReport($report);
        $json = file_get_contents($file->getRealPath());
        if ($json === false || ! hash_equals($report->fileHash, hash('sha256', $json))) {
            throw new ContentOperationException(PracticeErrorCode::IdempotencyConflict, 'Файл изменился во время проверки.');
        }
        $id = (string) Str::uuid();
        $token = bin2hex(random_bytes(32));
        $path = 'previews/'.$id.'.json';
        if (! Storage::disk('learning_private')->put($path, $json)) {
            throw new ContentOperationException(PracticeErrorCode::Unavailable, 'Не удалось сохранить приватный файл импорта. Повторите загрузку.');
        }
        try {
            $batch = \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $report, $token, $path, $json, $file): SessionImportBatch {
                $batch = SessionImportBatch::create([
                    'file_hash' => $report->fileHash, 'preview_token_hash' => hash('sha256', $token),
                    'created_by' => $actor->id, 'private_path' => $path,
                    'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                    'file_size' => strlen($json), 'status' => 'preview', 'validation_report' => $report->toArray(),
                    'expires_at' => now('UTC')->addSeconds(config('daily_practice.import.preview_ttl_seconds')),
                ]);
                $this->access->audit($actor, 'import_preview', ['import_batch_id' => $batch->id], [], ['file_hash' => $report->fileHash]);

                return $batch;
            });
        } catch (Throwable $exception) {
            Storage::disk('learning_private')->delete($path);
            throw $exception;
        }

        return [...$report->toArray(), 'batch_id' => $batch->id, 'token' => $token, 'expires_at' => $batch->expires_at->toIso8601String()];
    }

    public function commit(User $actor, string $id, string $token, string $fileHash): array
    {
        return $this->transaction->run($actor, function () use ($actor, $id, $token, $fileHash): array {
            $batch = SessionImportBatch::query()->lockForUpdate()->find($id);
            if (! $batch || $batch->created_by !== $actor->id || ! hash_equals($batch->preview_token_hash, hash('sha256', $token))) {
                throw new ContentOperationException(PracticeErrorCode::Forbidden, 'Preview token не принадлежит этому администратору.');
            }
            if (! hash_equals($batch->file_hash, $fileHash)) {
                throw new ContentOperationException(PracticeErrorCode::IdempotencyConflict, 'Hash файла не совпадает с preview.');
            }
            if ($batch->status === 'committed') {
                return $batch->receipt;
            }
            if ($batch->status !== 'preview' || $batch->expires_at->lessThanOrEqualTo(now('UTC'))) {
                throw new ContentOperationException(PracticeErrorCode::RevisionConflict, 'Preview истёк; загрузите файл заново.');
            }
            $disk = Storage::disk('learning_private');
            if (! $disk->exists($batch->private_path) || $disk->size($batch->private_path) > config('daily_practice.import.max_bytes')) {
                throw new ContentOperationException(PracticeErrorCode::IdempotencyConflict, 'Приватный исходник недоступен.');
            }
            $json = $disk->get($batch->private_path);
            if (! hash_equals($fileHash, hash('sha256', $json))) {
                throw new ContentOperationException(PracticeErrorCode::IdempotencyConflict, 'Приватный исходник изменён.');
            }
            $package = $this->access->validateReport($this->validator->validate($json));
            $receipt = [
                'batch_id' => $batch->id, 'file_hash' => $fileHash, 'package_id' => $package['package_id'],
                'version_ids' => [], 'created_version_ids' => [], 'skipped_version_ids' => [], 'catalog_revision_ids' => [], 'curriculum_ids' => [],
                'committed_at' => now('UTC')->toIso8601String(),
            ];
            foreach ($package['levels'] ?? [] as $content) {
                $level = LearningLevel::query()->where('code', $content['code'])->firstOrFail();
                $receipt['catalog_revision_ids'][] = $this->catalogRevision($actor, $batch, $content, $level->revision, ['level_code' => $content['code']])->id;
            }
            foreach ($package['units'] ?? [] as $content) {
                $unit = LearningUnit::query()->firstOrCreate(['external_id' => $content['id']], [
                    'level' => $content['level'], 'slug' => $content['slug'], 'sort_order' => $content['order'], 'title' => $content['title'], 'revision' => 0,
                ]);
                $receipt['catalog_revision_ids'][] = $this->catalogRevision($actor, $batch, $content, $unit->revision, ['unit_id' => $unit->id])->id;
            }
            foreach ($package['sessions'] as $content) {
                $unit = LearningUnit::query()->where('external_id', $content['unit_id'])->firstOrFail();
                $session = LearningSession::query()->firstOrCreate(['external_id' => $content['id']], [
                    'level' => $content['level'], 'unit_id' => $unit->id, 'sort_order' => $content['order'],
                ]);
                $version = $session->versions()->where('version', $content['version'])->first();
                if ($version) {
                    if (! hash_equals(CanonicalJson::hash($version->payload), CanonicalJson::hash($content))) {
                        throw new ContentOperationException(PracticeErrorCode::VersionConflict, 'Версия уже содержит другой материал.');
                    }
                    $receipt['skipped_version_ids'][] = $version->id;
                    $receipt['version_ids'][] = $version->id;

                    continue;
                }
                foreach ($content['objectives'] as $objective) {
                    LearningObjective::query()->firstOrCreate(['stable_id' => $objective['id']], ['level' => $content['level'], 'title' => $objective['title']]);
                }
                $version = $session->versions()->create([
                    'version' => $content['version'], 'schema_version' => $package['schema_version'], 'payload' => $content,
                    'checksum' => CanonicalJson::hash($content), 'status' => 'draft', 'import_batch_id' => $batch->id, 'created_by' => $actor->id,
                ]);
                $assets = [];
                $collect = function (array $node) use (&$collect, &$assets): void {
                    if (is_string($node['audio']['asset_id'] ?? null)) {
                        $assets[] = $node['audio']['asset_id'];
                    }
                    foreach ($node as $child) {
                        if (is_array($child)) {
                            $collect($child);
                        }
                    }
                };
                $collect($content);
                $version->media()->sync(array_unique($assets));
                $receipt['created_version_ids'][] = $version->id;
                $receipt['version_ids'][] = $version->id;
                $this->access->audit($actor, 'session_import', ['session_version_id' => $version->id, 'import_batch_id' => $batch->id], [], ['checksum' => $version->checksum, 'status' => 'draft']);
            }
            foreach ($package['curricula'] ?? [] as $content) {
                $curriculum = LearningCurriculum::query()->where('external_id', $content['id'])->where('version', $content['version'])->first();
                if (! $curriculum) {
                    $policyVersion = config('daily_practice.promotion.current_policy_version');
                    $curriculum = LearningCurriculum::create([
                        'external_id' => $content['id'], 'version' => $content['version'], 'level' => $content['level'],
                        'required_objective_ids' => $content['required_objective_ids'], 'checkpoint_session_ids' => $content['checkpoint_session_ids'],
                        'checksum' => CanonicalJson::hash($content), 'promotion_policy_version' => $policyVersion,
                        'promotion_policy_snapshot' => config('daily_practice.promotion.policies.'.$policyVersion),
                        'status' => 'draft', 'created_by' => $actor->id,
                    ]);
                    $curriculum->objectives()->sync(LearningObjective::query()->whereIn('stable_id', $content['required_objective_ids'])->pluck('id'));
                    $curriculum->checkpoints()->sync(LearningSession::query()->whereIn('external_id', $content['checkpoint_session_ids'])->pluck('id'));
                    $this->access->audit($actor, 'curriculum_import', ['curriculum_id' => $curriculum->id, 'import_batch_id' => $batch->id], [], ['checksum' => $curriculum->checksum]);
                }
                $receipt['curriculum_ids'][] = $curriculum->id;
            }
            $batch->update(['status' => 'committed', 'receipt' => $receipt, 'committed_at' => now('UTC')]);
            $this->access->audit($actor, 'import_commit', ['import_batch_id' => $batch->id], [], $receipt);

            return $receipt;
        });
    }

    private function catalogRevision(User $actor, SessionImportBatch $batch, array $content, int $baseRevision, array $target): LearningCatalogRevision
    {
        $revision = LearningCatalogRevision::create([
            ...$target, 'import_batch_id' => $batch->id, 'content' => $content,
            'base_revision' => $baseRevision, 'status' => 'pending', 'created_by' => $actor->id,
        ]);
        $this->access->audit($actor, 'catalog_import', ['catalog_revision_id' => $revision->id, 'import_batch_id' => $batch->id], [], ['base_revision' => $baseRevision, 'content' => $content]);

        return $revision;
    }

    public function pruneExpired(): int
    {
        $deleted = 0;
        SessionImportBatch::query()->where('expires_at', '<=', now('UTC'))->orderBy('id')->chunkById(100, function ($batches) use (&$deleted): void {
            foreach ($batches as $candidate) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($candidate, &$deleted): void {
                    $batch = SessionImportBatch::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($batch->expires_at->isFuture() || $batch->status === 'expired') {
                        return;
                    }
                    if (Storage::disk('learning_private')->exists($batch->private_path)) {
                        if (! Storage::disk('learning_private')->delete($batch->private_path)) {
                            throw new \RuntimeException('Preview cleanup failed.');
                        }
                        $deleted++;
                    }
                    if ($batch->status === 'preview') {
                        $batch->update(['status' => 'expired']);
                    }
                });
            }
        });

        $disk = Storage::disk('learning_private');
        foreach ($disk->files('previews') as $path) {
            if (preg_match('~^previews/[a-f0-9-]{36}\\.json$~D', $path) && $disk->lastModified($path) <= now('UTC')->timestamp - config('daily_practice.import.preview_ttl_seconds') - 60
                && ! SessionImportBatch::query()->where('private_path', $path)->exists()) {
                if (! $disk->delete($path)) {
                    throw new \RuntimeException('Orphan preview cleanup failed.');
                }
                $deleted++;
            }
        }

        return $deleted;
    }
}
