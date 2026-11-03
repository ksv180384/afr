<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningCatalogRevision;
use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContentEditorService
{
    public function versions(array $filters): LengthAwarePaginator
    {
        $query = LearningSessionVersion::query()->with('session.unit');
        foreach (['status', 'version'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['level'])) {
            $query->whereHas('session', fn ($q) => $q->where('level', $filters['level']));
        }
        if (! empty($filters['unit'])) {
            $query->whereHas('session.unit', fn ($q) => $q->where('external_id', $filters['unit']));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->whereHas('session', fn ($s) => $s->where('external_id', 'like', $search))
                ->orWhere('payload->title', 'like', $search));
        }

        return $query->orderByDesc('id')->paginate(20)->withQueryString();
    }

    public function versionRow(LearningSessionVersion $version): array
    {
        return ['id' => $version->id, 'session_id' => $version->session_id, 'external_id' => $version->session->external_id, 'title' => $version->payload['title'],
            'level' => $version->session->getRawOriginal('level'), 'unit' => $version->session->unit->external_id,
            'version' => $version->version, 'status' => $version->status->value, 'steps' => count($version->payload['tasks']),
            'created_at' => $version->created_at->toIso8601String(), 'active' => $version->session->active_version_id === $version->id];
    }

    public function detail(User $actor, int $id, SessionPackageValidator $validator, SessionExportService $export): array
    {
        $version = LearningSessionVersion::query()->with('session.unit', 'media')->findOrFail($id);
        $package = $export->version($actor, $id);
        $report = $validator->validate(CanonicalJson::encode($package))->toArray();
        $unit = $package['units'][0];
        $page = $version->payload['public_page'] ?? [];
        $levelSlug = strtolower(str_replace('_', '-', $version->session->getRawOriginal('level')));
        $path = '/practice/'.$levelSlug.'/'.($version->session->unit->slug ?? $unit['slug']).'/'.($version->session->slug ?? $page['slug'] ?? '');
        $missing = array_values(array_filter(['slug', 'summary', 'seo_title', 'seo_description', 'explanation_blocks'], fn ($field) => empty($page[$field])));
        $audioUrls = $version->media->mapWithKeys(fn ($media) => [$media->id => route('admin.daily-practice.media.file', $media->id, false)])->all();

        return ['version' => [...$this->versionRow($version), 'payload' => $version->payload, 'editor_notes' => $version->editor_notes,
            'import_batch_id' => $version->import_batch_id, 'review_checks' => $version->review_checks, 'checksum' => $version->checksum],
            'validation' => $report,
            'audioUrls' => $audioUrls, 'publicMeta' => ['canonical' => url($path), 'path' => $path, 'session_slug' => $version->session->slug, 'unit_id' => $version->session->unit_id, 'unit_slug' => $version->session->unit->slug, 'missing' => $missing,
                'breadcrumbs' => ['Практика', $version->session->getRawOriginal('level'), $unit['title'], $version->payload['title']]]];
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
        foreach (LearningSession::query()->whereNull('archived_at')->whereNotNull('active_version_id')->with('activeVersion')->lazyById(100) as $session) {
            if ($session->activeVersion?->status->value !== 'published') {
                continue;
            }
            foreach ($session->activeVersion->payload['tasks'] as $task) {
                foreach ($task['objective_ids'] as $objective) {
                    $published[$objective][$session->external_id.':'.$task['id']] = true;
                }
            }
        }
        $programs = $curricula->orderByDesc('id')->paginate(20, ['*'], 'curricula_page')->withQueryString();
        $programs->through(function ($program) use ($published) {
            return [...$program->toArray(), 'coverage' => array_map(fn ($id) => ['objective_id' => $id, 'published_tasks' => count($published[$id] ?? [])], $program->required_objective_ids),
                'checkpoints' => LearningSession::query()->whereIn('external_id', $program->checkpoint_session_ids)->with('activeVersion')->get()->map(fn ($s) => [
                    'id' => $s->external_id, 'ready' => $s->archived_at === null && $s->activeVersion?->status->value === 'published' && ($s->activeVersion->payload['kind'] ?? '') === 'checkpoint'])->all()];
        });

        return ['levels' => LearningLevel::query()->orderBy('sort_order')->get(),
            'revisions' => $revisions->orderByDesc('id')->paginate(20)->withQueryString(), 'curricula' => $programs,
            'coverage' => ['published_sessions' => LearningSession::query()->whereNull('archived_at')->whereHas('activeVersion', fn ($q) => $q->where('status', 'published'))->count(),
                'published_objectives' => count($published)]];
    }

    public function storeReport(User $actor, array $data): LearningContentReport
    {
        $version = LearningSessionVersion::query()->findOrFail($data['version_id']);
        if (! empty($data['task_id']) && ! collect($version->payload['tasks'])->contains('id', $data['task_id'])) {
            throw ValidationException::withMessages(['task_id' => 'Задание отсутствует в этой версии.']);
        }

        return DB::transaction(function () use ($actor, $data, $version) {
            $report = LearningContentReport::create(['session_version_id' => $version->id, 'task_id' => $data['task_id'] ?? null,
                'user_id' => $actor->id, 'text' => $data['text'], 'status' => 'open']);
            app(ContentAccess::class)->audit($actor, 'content_report_created', ['session_version_id' => $version->id], [], ['report_id' => $report->id]);

            return $report;
        });
    }

    public function updateReport(User $actor, int $id, array $data): LearningContentReport
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $report = LearningContentReport::query()->lockForUpdate()->findOrFail($id);
            $before = $report->only(['status', 'editor_notes']);
            $report->update([...$data, 'processed_by' => $actor->id, 'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now('UTC') : null]);
            app(ContentAccess::class)->audit($actor, 'content_report_updated', ['session_version_id' => $report->session_version_id], $before, ['report_id' => $id, ...$data]);

            return $report;
        });
    }

    public function importPlan(array $package): array
    {
        $existing = LearningSessionVersion::query()->with('session')->whereHas('session', fn ($q) => $q->whereIn('external_id', array_column($package['sessions'], 'id')))
            ->get()->keyBy(fn ($v) => $v->session->external_id.':'.$v->version);

        return ['sessions' => array_map(fn ($s) => ['id' => $s['id'], 'version' => $s['version'],
            'action' => isset($existing[$s['id'].':'.$s['version']]) ? 'skip' : 'create'], $package['sessions']),
            'catalog_revisions' => count($package['levels'] ?? []) + count($package['units'] ?? []),
            'curricula' => array_map(fn ($c) => ['id' => $c['id'], 'version' => $c['version'],
                'action' => LearningCurriculum::query()->where('external_id', $c['id'])->where('version', $c['version'])->exists() ? 'skip' : 'create'], $package['curricula'] ?? [])];
    }
}
