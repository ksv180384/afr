<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\User\User;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContentMaintenanceService
{
    public function save(User $actor, int $id, array $content, string $checksum): array
    {
        return app(ContentTransaction::class)->run($actor, function () use ($actor, $id, $content, $checksum) {
            $row = LearningSession::query()->lockForUpdate()->findOrFail($id);
            if (! hash_equals($row->checksum, $checksum)) {
                throw ValidationException::withMessages(['content' => 'Занятие уже изменено. Обновите страницу.']);
            }
            $content['version'] = 1;
            if (($content['id'] ?? null) !== $row->external_id) {
                throw ValidationException::withMessages(['content' => 'Идентификатор занятия менять нельзя.']);
            }
            $package = app(SessionExportService::class)->version($actor, $id);
            $package['sessions'] = [$content];
            app(ContentAccess::class)->validateReport(app(SessionPackageValidator::class)->validate(CanonicalJson::encode($package)));
            $row->update(['sort_order' => $content['order']]);
            foreach ($content['objectives'] as $objective) {
                \App\Models\Learning\LearningObjective::firstOrCreate(['stable_id' => $objective['id']], ['level' => $content['level'], 'title' => $objective['title']]);
            }
            $nextChecksum = CanonicalJson::hash($content);
            if (! hash_equals($checksum, $nextChecksum)) {
                \App\Models\Learning\LearningRun::where('session_id', $row->id)
                    ->whereIn('status', ['in_progress', 'paused'])->delete();
            }
            $row->update(['payload' => $content, 'checksum' => $nextChecksum, 'review_checks' => null]);

            return ['id' => $row->id];
        });
    }

    public function delete(User $actor, string $kind, string $id): array
    {
        return app(ContentTransaction::class)->run($actor, function () use ($kind, $id) {
            if ($kind === 'sessions') {
                $this->sessions([$id]);
            } elseif ($kind === 'units') {
                $unit = LearningUnit::findOrFail($id);
                $this->sessions(LearningSession::where('unit_id', $id)->pluck('id')->all());
                $revisions = DB::table('learning_catalog_revisions')->where('unit_id', $id)->pluck('id');
                DB::table('learning_catalog_revisions')->whereIn('id', $revisions)->delete();
                DB::table('learning_url_aliases')->where('unit_id', $id)->delete();
                $unit->delete();
            } elseif ($kind === 'curricula') {
                LearningCurriculum::findOrFail($id);
                $this->curricula([$id]);
            } elseif ($kind === 'levels') {
                $level = LearningLevel::where('code', $id)->firstOrFail();
                $other = LearningLevel::where('code', '!=', $id)->orderByRaw('ABS(sort_order - ?) ASC', [$level->sort_order])->orderBy('sort_order')->first();
                if (! $other) {
                    throw ValidationException::withMessages(['level' => 'Нельзя удалить последний уровень.']);
                }
                DB::table('learning_profiles')->where('current_level', $id)->update([
                    'current_level' => $other->code, 'revision' => DB::raw('revision + 1'), 'last_level_change_automatic' => false,
                ]);
                $this->curricula(LearningCurriculum::where('level', $id)->pluck('id')->all());
                $this->sessions(LearningSession::where('level', $id)->pluck('id')->all());
                $units = LearningUnit::where('level', $id)->pluck('id');
                $revisions = DB::table('learning_catalog_revisions')->where('level_code', $id)->orWhereIn('unit_id', $units)->pluck('id');
                DB::table('learning_catalog_revisions')->whereIn('id', $revisions)->delete();
                DB::table('learning_url_aliases')->where('level_code', $id)->orWhereIn('unit_id', $units)->delete();
                LearningUnit::whereIn('id', $units)->delete();
                DB::table('learning_objectives')->where('level', $id)->delete();
                $level->delete();
            } else {
                abort(404);
            }

            return ['deleted' => true];
        });
    }

    private function curricula(array $ids): void
    {
        LearningCurriculum::whereIn('id', $ids)->delete();
    }

    private function sessions(array $ids): void
    {
        $sessions = LearningSession::whereIn('id', $ids)->get();
        abort_if(count($ids) && $sessions->count() !== count($ids), 404);
        $external = $sessions->pluck('external_id')->all();
        DB::table('learning_runs')->whereIn('session_id', $ids)->delete();
        foreach (['personal_vocabulary_sources', 'learning_content_reports'] as $table) {
            DB::table($table)->whereIn('session_id', $ids)->delete();
        }
        DB::table('learning_url_aliases')->whereIn('session_id', $ids)->delete();
        LearningSession::whereIn('id', $ids)->delete();
        foreach (LearningSession::whereNotNull('payload')->lazyById(100) as $row) {
            $p = $row->payload;
            $before = $p;
            $p['prerequisite_ids'] = array_values(array_diff($p['prerequisite_ids'] ?? [], $external));
            if (isset($p['public_page']['related_session_ids'])) {
                $p['public_page']['related_session_ids'] = array_values(array_diff($p['public_page']['related_session_ids'], $external));
            }
            if ($p !== $before) {
                $row->update(['payload' => $p, 'checksum' => CanonicalJson::hash($p)]);
            }
        }
        foreach (LearningCurriculum::cursor() as $program) {
            $remaining = array_values(array_diff($program->checkpoint_session_ids, $external));
            if ($remaining !== $program->checkpoint_session_ids) {
                DB::table('learning_curricula')->where('id', $program->id)->update(['checkpoint_session_ids' => json_encode($remaining), 'status' => 'draft']);
            }
        }
    }
}
