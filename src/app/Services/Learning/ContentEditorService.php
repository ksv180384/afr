<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContentEditorService
{
    public function versions(array $filters): LengthAwarePaginator
    {
        $query = LearningSession::query()->whereNotNull('payload')->with('unit:id,external_id')
            ->select('id', 'external_id', 'level', 'unit_id', 'status', 'archived_at', 'created_at', 'content_title', 'task_count');
        foreach (['status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (! empty($filters['unit'])) {
            $query->whereHas('unit', fn ($q) => $q->where('external_id', $filters['unit']));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('external_id', 'like', $search)
                ->orWhere('content_title', 'like', $search));
        }

        return $query->orderByDesc('id')->paginate(20)->withQueryString();
    }

    public function versionRow(LearningSession $version): array
    {
        return ['id' => $version->id, 'session_id' => $version->id, 'external_id' => $version->external_id, 'title' => $version->content_title ?? $version->payload['title'],
            'level' => $version->getRawOriginal('level'), 'unit' => $version->unit->external_id,
            'version' => 1, 'status' => $version->status->value, 'steps' => (int) ($version->task_count ?? count($version->payload['tasks'] ?? [])),
            'created_at' => $version->created_at->toIso8601String(), 'active' => $version->status->value === 'published' && $version->archived_at === null];
    }

    public function detail(User $actor, int $id, SessionPackageValidator $validator, SessionExportService $export): array
    {
        $version = LearningSession::query()->with('unit')->findOrFail($id);
        $package = $export->version($actor, $id);
        $report = $validator->validate(CanonicalJson::encode($package))->toArray();
        $unit = $package['units'][0];
        $page = $version->payload['public_page'] ?? [];
        $levelSlug = strtolower(str_replace('_', '-', $version->getRawOriginal('level')));
        $path = '/practice/'.$levelSlug.'/'.($version->unit->slug ?? $unit['slug']).'/'.($version->slug ?? $page['slug'] ?? '');
        $missing = array_values(array_filter(['slug', 'summary', 'seo_title', 'seo_description', 'explanation_blocks'], fn ($field) => empty($page[$field])));
        $audioUrls = app(MediaLibraryService::class)->forContent($version->payload)->mapWithKeys(fn ($media) => [$media->id => route('admin.daily-practice.media.file', $media->id, false)])->all();

        return ['version' => [...$this->versionRow($version), 'payload' => $version->payload, 'editor_notes' => $version->editor_notes,
            'import_batch_id' => $version->import_batch_id, 'review_checks' => $version->review_checks, 'checksum' => $version->checksum],
            'validation' => $report,
            'audioUrls' => $audioUrls, 'publicMeta' => ['canonical' => url($path), 'path' => $path, 'session_slug' => $version->slug, 'unit_id' => $version->unit_id, 'unit_slug' => $version->unit->slug, 'missing' => $missing,
                'breadcrumbs' => ['Практика', $version->getRawOriginal('level'), $unit['title'], $version->payload['title']]]];
    }

    public function catalog(array $filters): array
    {
        $revisions = LearningCatalogRevision::query()->with('unit');
        if (! empty($filters['level'])) {
            $revisions->where(fn ($q) => $q->where('level_code', $filters['level'])->orWhereHas('unit', fn ($u) => $u->where('level', $filters['level'])));
        }
        $curricula = LearningCurriculum::query();
        if (! empty($filters['level'])) {
            $curricula->where('level', $filters['level']);
        }
        $published = [];
        foreach (LearningSession::query()->published()->lazyById(100) as $session) {
            foreach ($session->payload['tasks'] as $task) {
                foreach ($task['objective_ids'] as $objective) {
                    $published[$objective][$session->external_id.':'.$task['id']] = true;
                }
            }
        }
        $programs = $curricula->orderByDesc('id')->paginate(20, ['*'], 'curricula_page')->withQueryString();
        $programs->through(function ($program) use ($published) {
            return [...$program->toArray(), 'coverage' => array_map(fn ($id) => ['objective_id' => $id, 'published_tasks' => count($published[$id] ?? [])], $program->required_objective_ids),
                'checkpoints' => LearningSession::query()->whereIn('external_id', $program->checkpoint_session_ids)->get()->map(fn ($s) => [
                    'id' => $s->external_id, 'ready' => $s->archived_at === null && $s->status->value === 'published' && ($s->payload['kind'] ?? '') === 'checkpoint'])->all()];
        });

        return ['units' => \App\Models\Learning\LearningUnit::query()->orderBy('level')->orderBy('sort_order')->get()->each->append('background_url'), 'levels' => LearningLevel::query()->orderBy('sort_order')->get(),
            'revisions' => $revisions->orderByDesc('id')->paginate(20)->withQueryString(), 'curricula' => $programs,
            'coverage' => ['published_sessions' => LearningSession::query()->published()->count(),
                'published_objectives' => count($published)]];
    }

    public function storeReport(User $actor, array $data): LearningContentReport
    {
        $version = LearningSession::query()->findOrFail($data['version_id']);
        if (! empty($data['task_id']) && ! collect($version->payload['tasks'])->contains('id', $data['task_id'])) {
            throw ValidationException::withMessages(['task_id' => 'Задание отсутствует в этом занятии.']);
        }

        return DB::transaction(function () use ($actor, $data, $version) {
            $report = LearningContentReport::create(['session_id' => $version->id, 'task_id' => $data['task_id'] ?? null,
                'user_id' => $actor->id, 'text' => $data['text'], 'status' => 'open']);

            return $report;
        });
    }

    public function updateReport(User $actor, int $id, array $data): LearningContentReport
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $report = LearningContentReport::query()->lockForUpdate()->findOrFail($id);
            $report->update([...$data, 'processed_by' => $actor->id, 'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now('UTC') : null]);

            return $report;
        });
    }

    public function importPlan(array $package): array
    {
        $existing = LearningSession::query()->whereNotNull('payload')->whereIn('external_id', array_column($package['sessions'], 'id'))
            ->pluck('external_id')->flip();

        return ['sessions' => array_map(fn ($s) => ['id' => $s['id'], 'version' => $s['version'],
            'action' => $existing->has($s['id']) ? 'replace' : 'create'], $package['sessions']),
            'catalog_revisions' => count($package['levels'] ?? []) + count($package['units'] ?? []),
            'curricula' => array_map(fn ($c) => ['id' => $c['id'], 'version' => $c['version'],
                'action' => LearningCurriculum::query()->where('external_id', $c['id'])->where('version', $c['version'])->exists() ? 'skip' : 'create'], $package['curricula'] ?? [])];
    }
}
