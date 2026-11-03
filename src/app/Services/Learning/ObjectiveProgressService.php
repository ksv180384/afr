<?php

namespace App\Services\Learning;

use App\Enums\Learning\LearningSkill;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningCurriculumEnrollment;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningObjectiveEvidence;
use App\Models\Learning\LearningRun;
use App\Models\User\User;
use App\Support\Learning\PracticeTime;

final class ObjectiveProgressService
{
    public function current(User $actor, string $level): ?LearningCurriculumEnrollment
    {
        return LearningCurriculumEnrollment::query()->where('user_id', $actor->id)->where('level', $level)
            ->whereNull('superseded_at')->with('curriculum')->orderBy('id')->first();
    }

    public function published(string $level): ?LearningCurriculum
    {
        return LearningCurriculum::query()->where('level', $level)->where('status', 'published')
            ->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public function pin(User $actor, string $level): ?LearningCurriculumEnrollment
    {
        if ($existing = $this->current($actor, $level)) {
            return $existing;
        }
        $curriculum = $this->published($level);
        if (! $curriculum) {
            return null;
        }

        return LearningCurriculumEnrollment::firstOrCreate([
            'user_id' => $actor->id, 'level' => $level, 'curriculum_version_id' => $curriculum->id,
        ], [
            'promotion_policy_version' => $curriculum->promotion_policy_version,
            'promotion_policy_snapshot' => $curriculum->promotion_policy_snapshot,
            'revision' => 1, 'enrolled_at' => PracticeTime::now(),
        ]);
    }

    public function accepted(LearningRun $run, LearningAttempt $attempt): void
    {
        if (! $run->session_version_id || ! in_array($run->kind, ['daily', 'extra'], true)
            || ! $attempt->is_first_answer || ! ($attempt->result['objective_evidence_eligible'] ?? false)
            || ! ($attempt->result['first_independent_success'] ?? false) || ($attempt->result['help_used'] ?? true)) {
            return;
        }
        $version = $run->sessionVersion;
        $presentation = $attempt->presentation;
        $task = $presentation?->snapshot['task'] ?? [];
        $skill = $task['skill'] ?? null;
        if (! $version?->published_at || $presentation?->firstAttempt?->id !== $attempt->id
            || ! LearningSkill::tryFrom($skill ?? '') || ($attempt->result['skill'] ?? null) !== $skill
            || ! in_array($skill, $run->snapshot['skills'] ?? [], true)) {
            return;
        }
        $level = $run->snapshot['level'];
        $enrollment = $this->pin($run->user, $level);
        if (! $enrollment) {
            return;
        }
        $ids = array_intersect($task['objective_ids'] ?? [], $enrollment->curriculum->required_objective_ids);
        foreach (LearningObjective::query()->where('level', $level)->whereIn('stable_id', $ids)->get() as $objective) {
            LearningObjectiveEvidence::firstOrCreate([
                'event_id' => $attempt->id, 'enrollment_id' => $enrollment->id, 'objective_id' => $objective->id,
            ], [
                'user_id' => $run->user_id, 'run_id' => $run->id, 'session_id' => $version->session_id,
                'session_version_id' => $version->id, 'task_id' => $task['id'], 'skill' => $skill,
                'eligible' => true, 'occurred_at' => $attempt->received_at,
            ]);
        }
    }

    public function goals(?LearningCurriculumEnrollment $enrollment, ?LearningCurriculum $curriculum): array
    {
        if (! $curriculum) {
            return [];
        }
        $evidence = $enrollment ? $enrollment->evidence()->where('eligible', true)->get()->groupBy('objective_id') : collect();
        $names = LearningObjective::query()->whereIn('stable_id', $curriculum->required_objective_ids)->get()->keyBy('stable_id');
        $goals = [];
        foreach ($curriculum->required_objective_ids as $id) {
            $objective = $names->get($id);
            $rows = $objective ? $evidence->get($objective->id, collect()) : collect();
            $tasks = $rows->map(fn ($row) => $row->session_id.':'.$row->task_id)->unique()->count();
            $runs = $rows->pluck('run_id')->unique()->count();
            $goals[] = ['id' => $id, 'title' => $objective?->title ?? $id,
                'distinct_tasks' => $tasks, 'distinct_runs' => $runs, 'mastered' => $tasks >= 2 && $runs >= 2];
        }

        return $goals;
    }
}
