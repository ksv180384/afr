<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningRun;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class TrainingSelection
{
    public function accountProgress(User $actor): array
    {
        $access = app(LearningAccessService::class);
        $completed = $access->completed($actor);
        $recent = LearningRun::where('user_id', $actor->id)->whereIn('kind', ['daily', 'extra'])
            ->where('snapshot->level', $access->profileLevel($actor))->latest('started_at')->first();
        $current = $recent?->snapshot['id'] ?? null;
        $seen = [];
        if ($recent && ! in_array($current, $completed, true)
            && ($recent->content_checksum === null || $recent->session?->checksum === $recent->content_checksum)) {
            $content = $recent->content();
            $introductions = collect($content['introductions'] ?? [])->groupBy('before_task_id');
            foreach ($recent->presentations()->select('id', 'task_id', 'snapshot')->withExists('firstAttempt')->get() as $step) {
                if ($step->first_attempt_exists) {
                    array_push($seen, ...($step->taskSnapshot($content)['task']['vocabulary_ids'] ?? []));
                }
                if (in_array($step->id, $recent->introductions_seen ?? [], true)) {
                    foreach ($introductions->get($step->task_id, []) as $intro) {
                        array_push($seen, ...($intro['vocabulary_ids'] ?? []));
                    }
                }
            }
        }
        return [$completed, $current, array_unique($seen)];
    }

    public function select(Builder $published, string $mode, array $completed, ?string $current, array $seen, array $previous): array
    {
        $pool = $this->withTraining($published, $mode)
            ->whereIn('learning_sessions.external_id', array_unique(array_filter([...$completed, $current])));
        $recentItems = [];
        $olderItems = [];
        // Only fetch a handful of published sources, even for a completed course.
        $lessons = $pool->reorder()->orderByRaw('CASE WHEN learning_sessions.external_id = ? THEN 0 ELSE 1 END', [$current ?? ''])
            ->inRandomOrder()->limit(7)->get();
        foreach ($lessons as $lesson) {
            $source = $lesson->payload;
            $complete = in_array($source['id'], $completed, true);
            $items = $this->sourceItems($source, $mode, $complete ? null : $seen);
            if ($source['id'] === $current) {
                array_push($recentItems, ...$items);
            } else {
                array_push($olderItems, ...$items);
            }
        }
        $recentItems = $this->order($recentItems, $previous);
        $olderItems = $this->order($olderItems, $previous);
        $items = $recentItems->take(3)->concat($olderItems->take(3))
            ->concat($recentItems->slice(3))->concat($olderItems->slice(3))->take(6)->shuffle()->values();
        return $items->all();
    }

    public function selectTopic(Builder $published, string $mode, string $unit, array $previous): array
    {
        $lessons = $this->withTraining($published, $mode)->where('learning_units.external_id', $unit)->get();
        $perLesson = [];
        $extra = [];
        foreach ($lessons as $lesson) {
            $items = $this->order($this->sourceItems($lesson->payload, $mode), $previous);
            if ($items->isNotEmpty()) {
                $perLesson[] = $items->shift();
                array_push($extra, ...$items);
            }
        }

        $remaining = max(0, 6 - count($perLesson));

        return collect($perLesson)->concat($this->order($extra, $previous)->take($remaining))->shuffle()->all();
    }

    private function withTraining(Builder $published, string $mode): Builder
    {
        return $published->whereJsonLength('payload->training_tasks->'.$mode, '>', 0);
    }

    private function sourceItems(array $source, string $mode, ?array $seen = null): array
    {
        $items = [];
        foreach ($source['training_tasks'][$mode] ?? [] as $task) {
            if ($seen !== null && (empty($task['vocabulary_ids']) || array_diff($task['vocabulary_ids'], $seen))) {
                continue;
            }
            $items[] = ['source' => $source['id'], 'task' => $task, 'level' => $source['level'],
                'vocabulary' => collect($source['vocabulary'])->whereIn('id', $task['vocabulary_ids'])
                    ->map(fn ($word) => Arr::except($word, 'review_items'))->values()->all()];
        }
        return $items;
    }

    private function order(array $items, array $previous): Collection
    {
        return collect($items)->shuffle()->sortBy(fn ($item) => in_array($item['source'].'/'.$item['task']['id'], $previous, true))->values();
    }
}
