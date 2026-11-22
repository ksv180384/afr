<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class PublicPracticeCatalog
{
    public function sessions(): Builder
    {
        return LearningSession::query()->published()->whereNotNull('slug')->whereNotNull('published_at')->where('public_explanation_count', '>', 0)->whereNotNull('public_summary')
            ->whereHas('unit', fn ($query) => $query->whereNotNull('published_at')->whereNull('archived_at'))
            ->whereHas('levelDefinition', fn ($query) => $query->published());
    }

    private function summaries(Builder $query)
    {
        return $query->with('unit')->select('id', 'unit_id', 'slug', 'external_id', 'content_title', 'content_duration', 'public_summary')
            ->orderBy('sort_order')->orderBy('external_id')->get();
    }

    private function row(LearningSession $session): array
    {
        return ['id' => $session->external_id, 'title' => $session->content_title,
            'summary' => $session->public_summary, 'duration' => (int) $session->content_duration,
            'path' => PracticeUrls::session($session)];
    }

    private function assertLevel(LearningLevel $level): void
    {
        abort_unless($level->published_at, 404);
        abort_if($level->archived_at, 410, 'Уровень снят с публикации.');
    }

    public function assertUnit(LearningUnit $unit): void
    {
        $this->assertLevel($unit->levelDefinition);
        abort_unless($unit->published_at, 404);
        abort_if($unit->archived_at, 410, 'Тема снята с публикации.');
    }

    public function assertSession(LearningSession $session): void
    {
        abort_unless($session->published_at, 404);
        $this->assertUnit($session->unit);
        abort_if($session->archived_at, 410, 'Материал снят с публикации.');
        abort_unless($session->payload, 503, 'Материал временно недоступен.');
        abort_if($session->status->value !== 'published', 410, 'Материал снят с публикации.');
    }

    public function modified(?string $level = null, ?int $unitId = null): ?string
    {
        $levels = LearningLevel::whereNotNull('published_at')->when($level, fn ($query) => $query->where('code', $level));
        $units = LearningUnit::whereNotNull('published_at')->when($level, fn ($query) => $query->where('level', $level))->when($unitId, fn ($query) => $query->whereKey($unitId));
        $sessions = LearningSession::whereNotNull('published_at')
            ->when($level, fn ($query) => $query->where('level', $level))->when($unitId, fn ($query) => $query->where('unit_id', $unitId));
        $date = collect([$levels->max('updated_at'), $units->max('updated_at'), $sessions->max('updated_at')])->filter()->max();

        return $date ? CarbonImmutable::parse($date, 'UTC')->toIso8601String() : null;
    }

    public function page(?string $levelSlug, ?string $unitSlug, ?string $sessionSlug): array
    {
        $breadcrumbs = [['title' => 'Практика', 'path' => '/practice']];
        if (! $levelSlug) {
            $levels = LearningLevel::whereNull('archived_at')->orderBy('sort_order')->get();
            $cards = $levels->map(function ($level) {
                $count = $this->sessions()->where('level', $level->code)->count();

                return ['title' => $level->title, 'summary' => $level->published_at ? $level->summary : 'Материалы готовятся к публикации.',
                    'path' => $level->published_at ? PracticeUrls::level($level->code) : null, 'count' => $count];
            })->all();

            return $this->dto('index', 'Практика французского по уровням', 'Выбирайте уровень и тему. Читайте объяснения, выражения и примеры до начала занятия.', '/practice', $breadcrumbs,
                $cards, array_sum(array_column($cards, 'count')) > 0, $this->modified());
        }
        $code = strtoupper(str_replace('-', '_', $levelSlug));
        $level = LearningLevel::where('code', $code)->firstOrFail();
        abort_unless(PracticeUrls::level($level->code) === '/practice/'.$levelSlug, 404);
        $this->assertLevel($level);
        $breadcrumbs[] = ['title' => $level->title, 'path' => PracticeUrls::level($code)];
        if (! $unitSlug) {
            $units = $level->units()->whereNotNull('published_at')->whereNull('archived_at')->orderBy('sort_order')->orderBy('external_id')->get();
            $cards = $units->map(fn ($unit) => ['title' => $unit->title, 'summary' => $unit->summary, 'path' => PracticeUrls::unit($unit),
                'count' => $this->sessions()->where('unit_id', $unit->id)->count()])->all();

            return $this->dto('level', $level->title.' — практика французского', $level->summary, PracticeUrls::level($code), $breadcrumbs,
                $cards, array_sum(array_column($cards, 'count')) > 0, $this->modified($code), $level->learning_outcomes ?? [], $level->pronunciation_notes ?? []);
        }
        $unit = $level->units()->where('slug', $unitSlug)->firstOrFail();
        $this->assertUnit($unit);
        $breadcrumbs[] = ['title' => $unit->title, 'path' => PracticeUrls::unit($unit)];
        $sessions = $this->summaries($this->sessions()->where('unit_id', $unit->id));
        if (! $sessionSlug) {
            return $this->dto('unit', $unit->title, $unit->summary, PracticeUrls::unit($unit), $breadcrumbs, $sessions->map(fn ($row) => $this->row($row))->all(),
                $sessions->isNotEmpty(), $this->modified($code, $unit->id), $unit->learning_outcomes ?? [], $unit->pronunciation_notes ?? []);
        }
        $session = $unit->sessions()->where('slug', $sessionSlug)->with('unit.levelDefinition')->firstOrFail();
        $this->assertSession($session);
        $payload = $session->payload;
        $public = $payload['public_page'] ?? [];
        abort_unless(! empty($public['explanation_blocks']) && ! empty($public['summary']), 404);
        $path = PracticeUrls::session($session);
        $breadcrumbs[] = ['title' => $payload['title'], 'path' => $path];
        $links = $this->summaries($this->sessions()->whereIn('external_id', array_unique([...($public['related_session_ids'] ?? []), ...$payload['prerequisite_ids']])))->map(fn ($row) => $this->row($row))->all();
        $index = $sessions->search(fn ($row) => $row->id === $session->id);
        $date = collect([$session->updated_at, $session->published_at, $unit->updated_at, $level->updated_at])->filter()->max()?->toIso8601String();
        $page = $this->dto('session', $payload['title'], $public['summary'], $path, $breadcrumbs, [], true, $date);
        $page['seo']['title'] = $public['seo_title'];
        $page['seo']['description'] = $public['seo_description'];
        $page['session'] = ['id' => $payload['id'], 'title' => $payload['title'], 'description' => $payload['description'],
            'level' => $code, 'duration_minutes' => $payload['duration_minutes'], 'objectives' => $payload['objectives'],
            'pronunciation_notes' => $payload['pronunciation_notes'] ?? [], 'public_page' => array_intersect_key($public, array_flip(['summary', 'explanation_blocks'])),
            'vocabulary' => array_map(fn ($word) => array_diff_key($word, ['review_items' => true]), $payload['vocabulary'])];
        $page['related'] = $links;
        $page['previous'] = $index !== false && $index > 0 ? $this->row($sessions[$index - 1]) : null;
        $page['next'] = $index !== false && $index + 1 < $sessions->count() ? $this->row($sessions[$index + 1]) : null;

        return $page;
    }

    private function dto(string $kind, string $title, ?string $summary, string $path, array $breadcrumbs, array $cards, bool $indexable, ?string $modified, array $outcomes = [], array $notes = []): array
    {
        $canonical = PracticeUrls::absolute($path);

        return ['kind' => $kind, 'title' => $title, 'summary' => $summary ?? '', 'path' => $path, 'breadcrumbs' => $breadcrumbs, 'cards' => $cards,
            'outcomes' => $outcomes, 'notes' => $notes, 'session' => null, 'related' => [], 'previous' => null, 'next' => null,
            'indexable' => $indexable, 'modified' => $modified, 'seo' => ['title' => $title, 'description' => $summary ?? '', 'canonicalUrl' => $canonical, 'noIndex' => ! $indexable,
                'jsonLd' => ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(
                    fn ($item, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['title'], 'item' => PracticeUrls::absolute($item['path'])],
                    $breadcrumbs, array_keys($breadcrumbs))]]];
    }

    public function sitemapEntries(): array
    {
        if (! config('daily_practice.enabled')) {
            return [];
        }
        $entries = [];
        if (! $this->sessions()->exists()) {
            return [];
        }
        $entries[] = ['path' => '/practice', 'modified' => $this->modified()];
        foreach (LearningLevel::published()->get() as $level) {
            if (! $this->sessions()->where('level', $level->code)->exists()) {
                continue;
            }
            $entries[] = ['path' => PracticeUrls::level($level->code), 'modified' => $this->modified($level->code)];
            foreach ($level->units()->whereNotNull('published_at')->whereNull('archived_at')->get() as $unit) {
                if (! $this->sessions()->where('unit_id', $unit->id)->exists()) {
                    continue;
                }
                $entries[] = ['path' => PracticeUrls::unit($unit), 'modified' => $this->modified($level->code, $unit->id)];
                foreach ($this->sessions()->where('unit_id', $unit->id)->get(['id', 'unit_id', 'slug', 'updated_at', 'published_at']) as $session) {
                    $session->setRelation('unit', $unit);
                    $date = collect([$session->updated_at, $session->published_at, $unit->updated_at, $level->updated_at])->filter()->max();
                    $entries[] = ['path' => PracticeUrls::session($session), 'modified' => $date?->toIso8601String()];
                }
            }
        }

        return $entries;
    }
}
