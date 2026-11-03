<?php

namespace App\Console\Commands;

use App\Models\Learning\LearningSession;
use App\Services\Learning\PublicPracticeCatalog;
use App\Services\Learning\SessionPackageValidator;
use App\Services\Learning\Validation\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PracticeContentStatus extends Command
{
    protected $signature = 'daily-practice:content-status {--json : Emit machine-readable authoring/publication inventory}';

    protected $description = 'Compare the 140-topic authoring plan, real packages, objective coverage and actual publication';

    public function handle(SessionPackageValidator $validator, PublicPracticeCatalog $catalog): int
    {
        $root = resource_path('learning/programme');
        $map = json_decode(file_get_contents($root.'/map.v1.json'), true, 512, JSON_THROW_ON_ERROR);
        $levels = [];
        $packages = [];
        foreach ($map['levels'] as $level) {
            $prepared = [];
            $valid = [];
            $evidence = [];
            $planned = [];
            foreach ($level['units'] as $unit) {
                $planned = [...$planned, ...array_column($unit['sessions'], 'id')];
                $path = realpath($root.'/'.$unit['package_path']);
                if (! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $report = $validator->validateFile($path)->toArray();
                $package = json_decode(file_get_contents($path), true);
                $packages[] = ['path' => $unit['package_path'], 'valid' => $report['valid'],
                    'errors' => $report['errors'], 'warning_codes' => array_values(array_unique(array_column($report['warnings'], 'code')))];
                foreach ($package['sessions'] ?? [] as $session) {
                    if ($session['level'] !== $level['code'] || $session['unit_id'] !== $unit['id']) {
                        continue;
                    }
                    $prepared[$session['id']] = CanonicalJson::hash($session);
                    if ($report['valid']) {
                        $valid[$session['id']] = $prepared[$session['id']];
                    }
                    foreach ($session['tasks'] as $task) {
                        foreach ($task['objective_ids'] as $id) {
                            $evidence[$id]['tasks'][$session['id'].':'.$task['id']] = true;
                            $evidence[$id]['sessions'][$session['id']] = true;
                            $evidence[$id]['skills'][$task['skill']] = true;
                        }
                    }
                }
            }
            $covered = array_values(array_filter($level['required_objective_ids'], function ($id) use ($evidence): bool {
                $goal = $evidence[$id] ?? [];

                return count($goal['tasks'] ?? []) >= 2 && count($goal['sessions'] ?? []) >= 2
                    && isset($goal['skills']['vocabulary']) && count($goal['skills'] ?? []) >= 2;
            }));
            $publishedRows = $catalog->sessions()->where('level', $level['code'])->whereIn('external_id', $planned)->with('activeVersion:id,session_id,checksum')->get();
            $published = $publishedRows->pluck('external_id')->all();
            $matchingPublished = $publishedRows->filter(fn ($row) => isset($valid[$row->external_id]) && $valid[$row->external_id] === $row->activeVersion->checksum)->count();
            $matchingReviewed = LearningSession::where('level', $level['code'])->whereIn('external_id', array_keys($valid))
                ->with('versions:id,session_id,checksum,reviewed_at')->get()->filter(fn ($row) => $row->versions->contains(
                    fn ($version) => $version->reviewed_at && $version->checksum === $valid[$row->external_id]))->count();
            $reviewed = LearningSession::where('level', $level['code'])->whereIn('external_id', $planned)
                ->whereHas('versions', fn ($query) => $query->whereNotNull('reviewed_at')->whereIn('status', ['reviewed', 'published', 'archived']))->count();
            $levels[] = ['level' => $level['code'], 'target' => $level['target_sessions'], 'topics' => count($level['units']),
                'file_sessions' => count($prepared), 'machine_valid' => count($valid), 'db_reviewed' => $reviewed, 'db_published' => count($published), 'published_matching_files' => $matchingPublished, 'reviewed_matching_files' => $matchingReviewed,
                'authoring_remaining' => count(array_diff($planned, array_keys($valid))), 'required_objectives' => count($level['required_objective_ids']),
                'objectives_with_two_sessions_and_skills' => count($covered), 'objectives_missing_evidence' => array_values(array_diff($level['required_objective_ids'], $covered)),
                'planned_checkpoints' => count($level['checkpoint_session_ids']), 'file_checkpoints' => count(array_intersect(array_keys($prepared), $level['checkpoint_session_ids'])),
                'published_checkpoints' => count(array_intersect($published, $level['checkpoint_session_ids']))];
        }
        $complete = collect($levels)->every(fn ($level) => $level['authoring_remaining'] === 0 && $level['reviewed_matching_files'] >= $level['target']
            && $level['published_matching_files'] >= $level['target'] && $level['objectives_missing_evidence'] === [] && $level['published_checkpoints'] === $level['planned_checkpoints']);
        $report = ['generated_at_utc' => CarbonImmutable::now('UTC')->toIso8601String(), 'stage_17_complete' => $complete,
            'target_sessions' => 700, 'file_sessions' => array_sum(array_column($levels, 'file_sessions')), 'machine_valid_sessions' => array_sum(array_column($levels, 'machine_valid')),
            'db_reviewed_sessions' => array_sum(array_column($levels, 'db_reviewed')), 'db_published_sessions' => array_sum(array_column($levels, 'db_published')),
            'authoring_remaining' => array_sum(array_column($levels, 'authoring_remaining')), 'levels' => $levels, 'packages' => $packages,
            'notice' => 'Machine validation is not language/pronunciation review. Planned slots are never counted as prepared or published.'];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Level', 'Target', 'Files', 'Valid', 'Reviewed DB', 'Published DB', 'To author', 'Goals covered', 'Checkpoints published'], array_map(fn ($l) => [
                $l['level'], $l['target'], $l['file_sessions'], $l['machine_valid'], $l['db_reviewed'], $l['db_published'], $l['authoring_remaining'],
                $l['objectives_with_two_sessions_and_skills'].'/'.$l['required_objectives'], $l['published_checkpoints'].'/'.$l['planned_checkpoints']], $levels));
            $this->warn($complete ? 'Stage 17 complete.' : 'Stage 17 remains incomplete. See --json for real gaps and package errors.');
        }

        return $complete ? self::SUCCESS : self::FAILURE;
    }
}
