<?php

namespace App\Services\Learning;

final class PracticeHomeRoute
{
    /** Present existing access decisions as compact screen states. */
    public function make(array $outline, array $access, string $profileLevel, array $selection, ?array $active, ?array $candidate, array $lessonRuns): array
    {
        $levelId = $selection['level'] ?? $profileLevel;
        if (! isset($selection['level']) && isset($selection['unit'])) {
            foreach ($outline as $level) {
                if (in_array($selection['unit'], array_column($level['units'], 'id'), true)) {
                    $levelId = $level['id'];
                    break;
                }
            }
        }
        $level = collect($outline)->firstWhere('id', $levelId);
        $units = ($level['published'] ?? false) ? $level['units'] : [];
        $unitId = $selection['unit'] ?? null;
        if ($unitId === null) {
            foreach ([$active['session_id'] ?? null, $candidate['id'] ?? null] as $sessionId) {
                if ($sessionId === null) {
                    continue;
                }
                foreach ($units as $unit) {
                    if (in_array($sessionId, array_column($unit['sessions'], 'id'), true)) {
                        $unitId = $unit['id'];
                        break 2;
                    }
                }
            }
            $unitId ??= $units[0]['id'] ?? null;
        }
        $unit = collect($units)->firstWhere('id', $unitId);
        $lessonCard = function ($session) use ($lessonRuns, $access) {
            $state = $access['sessions'][$session['id']] ?? null;
            $runId = $lessonRuns[$session['id']] ?? null;
            $card = $this->card($session, $state);
            if ($runId !== null) {
                $card['status'] = 'active';
                $card['reason'] = '';
            }

            return [...$card,
                'completed' => $state['completed'] ?? false, 'review_only' => $state['review_only'] ?? false, 'run_id' => $runId];
        };
        $lessons = array_map($lessonCard, $unit['sessions'] ?? []);
        $topics = array_map(fn ($topic) => [
            ...$this->card($topic, $access['units'][$topic['id']] ?? null, $topic['sessions'] !== []),
            'background_url' => $topic['background_url'] ?? null,
            'lessons' => array_map($lessonCard, $topic['sessions']),
        ], $units);
        $contents = array_map(function ($entry) use ($access) {
            $units = $entry['published'] ? $entry['units'] : [];
            $unitCards = array_map(fn ($unit) => [...$this->card($unit, $access['units'][$unit['id']] ?? null, $unit['sessions'] !== []),
                'session_count' => count($unit['sessions'])], $units);

            return [...$this->card($entry, $access['levels'][$entry['id']] ?? null, array_sum(array_column($unitCards, 'session_count')) > 0),
                'units' => $unitCards];
        }, $outline);

        return [
            'level' => $this->card($level ?? ['id' => $levelId], $access['levels'][$levelId] ?? null, collect($units)->contains(fn ($entry) => $entry['sessions'] !== [])),
            'unit' => $unitId === null ? null : $this->card($unit ?? ['id' => $unitId], $access['units'][$unitId] ?? null, ! empty($unit['sessions'])),
            'lessons' => $lessons,
            'topics' => $topics,
            'current_lesson_id' => $active['session_id'] ?? $candidate['id'] ?? null,
            'contents' => $contents,
        ];
    }

    public function search(array $outline, array $access, string $query, bool $completedOnly): array
    {
        $matches = [];
        foreach ($outline as $level) {
            if (! $level['published']) {
                continue;
            }
            foreach ($level['units'] as $unit) {
                foreach ($unit['sessions'] as $session) {
                    $state = $access['sessions'][$session['id']] ?? null;
                    if (($completedOnly && ! ($state['completed'] ?? false))
                        || ($query !== '' && mb_stripos($session['title'], $query) === false)) {
                        continue;
                    }
                    $matches[] = [...$this->card($session, $state), 'level' => $level['id'], 'unit' => $unit['title']];
                    if (count($matches) === 41) {
                        return ['items' => array_slice($matches, 0, 40), 'more' => true];
                    }
                }
            }
        }

        return ['items' => $matches, 'more' => false];
    }

    private function status(?array $state): string
    {
        return match (true) {
            $state === null => 'unavailable',
            $state['completed'] => 'completed',
            $state['locked'] => 'locked',
            default => 'available',
        };
    }

    private function card(array $entry, ?array $state, bool $published = true): array
    {
        $status = $published ? $this->status($state) : 'unavailable';

        return ['id' => $entry['id'], 'title' => $entry['title'] ?? null, 'path' => $entry['path'] ?? null,
            'status' => $status, 'reason' => $status === 'unavailable' ? 'Материалы ещё недоступны.' : ($state['reason'] ?? '')];
    }
}
