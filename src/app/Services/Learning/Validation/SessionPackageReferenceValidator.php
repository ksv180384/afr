<?php

namespace App\Services\Learning\Validation;

use App\DTOs\Learning\PackageValidationReport;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningObjective;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\LearningUnit;
use App\Models\Word\Word;

final class SessionPackageReferenceValidator
{
    public function validate(array $package, PackageValidationReport $report): void
    {
        $this->units($package, $report);
        $this->objectives($package, $report);
        $this->sessions($package, $report);
        $this->lexemesAndMedia($package, $report);
    }

    private function units(array $package, PackageValidationReport $report): void
    {
        $incoming = array_column($package['units'] ?? [], null, 'id');
        $ids = array_unique([...array_keys($incoming), ...array_column($package['sessions'], 'unit_id')]);
        $existing = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (LearningUnit::query()->whereIn('external_id', $chunk)->get() as $unit) {
                $existing[$unit->external_id] = $unit;
            }
        }
        $seen = [];
        foreach ($package['units'] ?? [] as $index => $unit) {
            $path = '/units/'.$index;
            if (isset($existing[$unit['id']]) && $existing[$unit['id']]->getRawOriginal('level') !== $unit['level']) {
                $report->error('reference_level_mismatch', $path.'/level', 'Нельзя перенести существующую тему на другой уровень.');
            }
            foreach (['slug', 'order'] as $field) {
                $key = $unit['level'].':'.$field.':'.$unit[$field];
                if (isset($seen[$key])) {
                    $report->error('catalog_conflict', $path.'/'.$field, 'Значение повторено у другой темы этого уровня.');
                }
                $seen[$key] = true;
            }
            $collisions = LearningUnit::query()->where('level', $unit['level'])->where(function ($query) use ($unit) {
                $query->where('slug', $unit['slug'])->orWhere('sort_order', $unit['order']);
            })->get(['external_id', 'slug', 'sort_order']);
            foreach ($collisions as $other) {
                if (! isset($incoming[$other->external_id])) {
                    $field = $other->slug === $unit['slug'] ? 'slug' : 'order';
                    $report->error('catalog_conflict', $path.'/'.$field, 'Адрес или порядок занят существующей темой.');
                }
            }
        }
        foreach ($package['sessions'] as $index => $session) {
            $id = $session['unit_id'];
            $level = $incoming[$id]['level'] ?? (($existing[$id] ?? null)?->getRawOriginal('level') ?? null);
            if ($level === null) {
                $report->error('reference_not_found', '/sessions/'.$index.'/unit_id', 'Тема не найдена в пакете или базе данных.');
            } elseif ($level !== $session['level']) {
                $report->error('reference_level_mismatch', '/sessions/'.$index.'/unit_id', 'Тема и сессия должны иметь один уровень.');
            }
        }
    }

    private function objectives(array $package, PackageValidationReport $report): void
    {
        $defined = [];
        $paths = [];
        $ids = [];
        foreach ($package['sessions'] as $si => $session) {
            foreach ($session['objectives'] as $oi => $objective) {
                $id = $objective['id'];
                $path = '/sessions/'.$si.'/objectives/'.$oi.'/id';
                if (isset($defined[$id]) && $defined[$id]['level'] !== $session['level']) {
                    $report->error('reference_level_mismatch', $path, 'Один stable objective ID не может относиться к разным уровням.');
                }
                if (isset($defined[$id]) && $defined[$id]['title'] !== $objective['title']) {
                    $report->warning('objective_meaning_review', $path, 'Одинаковый ID цели имеет разные формулировки: проверьте неизменность смысла.');
                }
                $defined[$id] = ['level' => $session['level'], 'title' => $objective['title']];
                $paths[$id] = $path;
                $ids[] = $id;
            }
        }
        foreach ($package['curricula'] ?? [] as $curriculum) {
            $ids = [...$ids, ...$curriculum['required_objective_ids']];
        }
        $existing = [];
        foreach (array_chunk(array_unique($ids), 500) as $chunk) {
            foreach (LearningObjective::query()->whereIn('stable_id', $chunk)->get() as $objective) {
                $existing[$objective->stable_id] = $objective;
            }
        }
        foreach ($defined as $id => $objective) {
            if (! isset($existing[$id])) {
                continue;
            }
            if ($existing[$id]->getRawOriginal('level') !== $objective['level']) {
                $report->error('reference_level_mismatch', $paths[$id], 'ID цели уже используется на другом уровне.');
            } elseif ($existing[$id]->title !== $objective['title']) {
                $report->warning('objective_meaning_review', $paths[$id], 'Формулировка существующей цели изменилась: редактор должен подтвердить прежний смысл.');
            }
        }
        foreach ($package['curricula'] ?? [] as $index => $curriculum) {
            foreach ($curriculum['required_objective_ids'] as $oi => $id) {
                $level = $defined[$id]['level'] ?? (($existing[$id] ?? null)?->getRawOriginal('level') ?? null);
                if ($level === null) {
                    $report->error('reference_not_found', '/curricula/'.$index.'/required_objective_ids/'.$oi, 'Цель программы не найдена.');
                } elseif ($level !== $curriculum['level']) {
                    $report->error('reference_level_mismatch', '/curricula/'.$index.'/required_objective_ids/'.$oi, 'Цель относится к другому уровню.');
                }
            }
            $previous = LearningCurriculum::query()->where('external_id', $curriculum['id'])->where('version', $curriculum['version'])->first();
            if ($previous && ($previous->getRawOriginal('level') !== $curriculum['level'] || $previous->required_objective_ids !== $curriculum['required_objective_ids'] || $previous->checkpoint_session_ids !== $curriculum['checkpoint_session_ids'])) {
                $report->error('version_conflict', '/curricula/'.$index.'/version', 'Содержимое этой версии программы уже существует и отличается.');
            }
            if (count($curriculum['checkpoint_session_ids']) < 2 || $curriculum['required_objective_ids'] === []) {
                $report->warning('incomplete_curriculum', '/curricula/'.$index, 'Для автоповышения нужны обязательные цели и минимум два разных готовых контроля.');
            }
        }
    }

    private function sessions(array $package, PackageValidationReport $report): void
    {
        $incoming = array_column($package['sessions'], null, 'id');
        $state = [];
        $rows = [];
        $queue = [];
        $origins = [];
        foreach ($package['sessions'] as $index => $session) {
            $state[$session['id']] = ['level' => $session['level'], 'kind' => $session['kind'], 'prerequisites' => $session['prerequisite_ids']];
            $queue[] = $session['id'];
            foreach (['prerequisite_ids' => $session['prerequisite_ids'], 'related_session_ids' => $session['public_page']['related_session_ids'] ?? []] as $field => $references) {
                foreach ($references as $ri => $id) {
                    $queue[] = $id;
                    $origins[$id] ??= '/sessions/'.$index.'/'.($field === 'related_session_ids' ? 'public_page/' : '').$field.'/'.$ri;
                }
            }
        }
        foreach ($package['curricula'] ?? [] as $ci => $curriculum) {
            foreach ($curriculum['checkpoint_session_ids'] as $ri => $id) {
                $queue[] = $id;
                $origins[$id] ??= '/curricula/'.$ci.'/checkpoint_session_ids/'.$ri;
            }
        }
        $visited = [];
        while ($queue !== []) {
            $batch = array_values(array_unique(array_splice($queue, 0, 500)));
            $batch = array_values(array_filter($batch, fn (string $id): bool => ! isset($visited[$id])));
            if ($batch === []) {
                continue;
            }
            if (count($visited) + count($batch) > config('daily_practice.import.max_context_nodes')) {
                $report->error('context_limit_exceeded', '', 'Граф связанных сессий превышает предел проверки; увеличьте лимит осознанно.');

                return;
            }
            foreach ($batch as $id) {
                $visited[$id] = true;
            }
            $found = LearningSession::query()->whereIn('external_id', $batch)->with(['unit:id,external_id', 'activeVersion', 'latestVersion'])->get();
            foreach ($found as $row) {
                $id = $row->external_id;
                $rows[$id] = $row;
                if (isset($incoming[$id])) {
                    continue;
                }
                $version = $row->activeVersion ?? $row->latestVersion;
                $payload = $version?->payload ?? [];
                $dependencies = array_values(array_filter((array) ($payload['prerequisite_ids'] ?? []), 'is_string'));
                $state[$id] = ['level' => $row->getRawOriginal('level'), 'kind' => $payload['kind'] ?? null, 'prerequisites' => $dependencies];
                foreach ($dependencies as $target) {
                    if (! isset($visited[$target])) {
                        $queue[] = $target;
                        $origins[$target] ??= $origins[$id] ?? '';
                    }
                }
                if ($row->archived_at !== null) {
                    $report->warning('archived_dependency', $origins[$id] ?? '', 'Связанная сессия архивирована; перед публикацией согласуйте зависимости.');
                }
            }
        }
        foreach ($package['sessions'] as $index => $session) {
            $path = '/sessions/'.$index;
            if (isset($rows[$session['id']])) {
                $existing = $rows[$session['id']];
                if ($existing->getRawOriginal('level') !== $session['level'] || $existing->unit->external_id !== $session['unit_id']) {
                    $report->error('session_identity_conflict', $path.'/id', 'Существующий session ID нельзя перенести в другую тему или на другой уровень.');
                }
                $previous = LearningSessionVersion::query()->where('session_id', $existing->id)->where('version', $session['version'])->first();
                if ($previous && CanonicalJson::hash($previous->payload) !== CanonicalJson::hash($session)) {
                    $report->error('version_conflict', $path.'/version', 'Тот же ID и version уже имеют другое неизменяемое содержимое; увеличьте version.');
                }
            }
            foreach (['prerequisite_ids' => $session['prerequisite_ids'], 'public_page/related_session_ids' => $session['public_page']['related_session_ids'] ?? []] as $field => $targets) {
                foreach ($targets as $ri => $id) {
                    $this->sessionReference($id, $session['level'], $state, $path.'/'.$field.'/'.$ri, $report, $field === 'prerequisite_ids');
                }
            }
        }
        foreach ($package['curricula'] ?? [] as $index => $curriculum) {
            foreach ($curriculum['checkpoint_session_ids'] as $ri => $id) {
                $path = '/curricula/'.$index.'/checkpoint_session_ids/'.$ri;
                $this->sessionReference($id, $curriculum['level'], $state, $path, $report, true);
                if (isset($state[$id]) && $state[$id]['kind'] !== 'checkpoint') {
                    $report->error('invalid_checkpoint', $path, 'Ссылка должна вести на checkpoint-сессию, а не обычную практику.');
                }
            }
        }
        foreach ($state as $id => $session) {
            if (isset($incoming[$id])) {
                continue;
            }
            foreach ($session['prerequisites'] as $target) {
                if (! isset($state[$target])) {
                    $report->error('reference_not_found', $origins[$id] ?? '', 'В цепочке существующей зависимости не найдена сессия '.$target.'.');
                } elseif ($state[$target]['level'] !== $session['level']) {
                    $report->error('reference_level_mismatch', $origins[$id] ?? '', 'В цепочке существующей зависимости обнаружен другой уровень.');
                }
            }
        }
        $this->cycles($state, $package['sessions'], $origins, $report);
    }

    private function sessionReference(string $id, string $level, array $state, string $path, PackageValidationReport $report, bool $sameLevel): void
    {
        if (! isset($state[$id])) {
            $report->error('reference_not_found', $path, 'Сессия не найдена в пакете или базе данных.');
        } elseif ($sameLevel && $state[$id]['level'] !== $level) {
            $report->error('reference_level_mismatch', $path, 'Зависимость или контроль должен относиться к тому же уровню.');
        }
    }

    private function cycles(array $graph, array $sessions, array $origins, PackageValidationReport $report): void
    {
        $colors = [];
        foreach (array_keys($graph) as $start) {
            if (isset($colors[$start])) {
                continue;
            }
            $stack = [[$start, 0]];
            $trail = [];
            $active = [];
            while ($stack !== []) {
                $top = count($stack) - 1;
                [$id, $edge] = $stack[$top];
                if (! isset($colors[$id])) {
                    $colors[$id] = 1;
                    $active[$id] = count($trail);
                    $trail[] = $id;
                }
                $edges = $graph[$id]['prerequisites'];
                if ($edge >= count($edges)) {
                    $colors[$id] = 2;
                    unset($active[$id]);
                    array_pop($stack);
                    array_pop($trail);

                    continue;
                }
                $stack[$top][1]++;
                $next = $edges[$edge];
                if (! isset($graph[$next])) {
                    continue;
                }
                if (($colors[$next] ?? 0) === 1) {
                    $cycle = [...array_slice($trail, $active[$next]), $next];
                    $message = 'Цикл prerequisites: '.implode(' → ', $cycle).'.';
                    $located = false;
                    foreach ($sessions as $si => $session) {
                        foreach ($session['prerequisite_ids'] as $pi => $target) {
                            if (in_array($session['id'], $cycle, true) && in_array($target, $cycle, true)) {
                                $report->error('prerequisite_cycle', '/sessions/'.$si.'/prerequisite_ids/'.$pi, $message);
                                $located = true;
                            }
                        }
                    }
                    if (! $located) {
                        $report->error('prerequisite_cycle', $origins[$start] ?? '', $message);
                    }
                } elseif (! isset($colors[$next])) {
                    $stack[] = [$next, 0];
                }
            }
        }
    }

    private function lexemesAndMedia(array $package, PackageValidationReport $report): void
    {
        $lexemes = [];
        $dictionaryIds = [];
        $assets = [];
        foreach ($package['sessions'] as $si => $session) {
            foreach ($session['vocabulary'] as $vi => $word) {
                $key = $word['lexeme_key'];
                $path = '/sessions/'.$si.'/vocabulary/'.$vi;
                $meaning = [$word['fr'], $word['ru']];
                if (isset($lexemes[$key]) && $lexemes[$key]['meaning'] !== $meaning) {
                    $report->warning('lexeme_meaning_review', $path.'/lexeme_key', 'Один lexeme_key имеет разные тексты/переводы: подтвердите одинаковый смысл.');
                }
                $lexemes[$key] = ['meaning' => $meaning, 'path' => $path.'/lexeme_key'];
                if (isset($word['dictionary_word_id'])) {
                    $dictionaryIds[$word['dictionary_word_id']][] = $path.'/dictionary_word_id';
                }
            }
            $this->audioReferences($session, '/sessions/'.$si, $assets, $report);
            foreach ($session['tasks'] as $ti => $task) {
                if ($task['skill'] === 'listening' && empty($task['prompt']['audio'])) {
                    $report->warning('listening_audio_required', '/sessions/'.$si.'/tasks/'.$ti.'/prompt/audio', 'Аудирование требует аудио; материал пока не готов к публикации.');
                }
            }
        }
        foreach (array_chunk(array_keys($dictionaryIds), 500) as $chunk) {
            $found = Word::query()->whereIn('id', $chunk)->pluck('id')->all();
            foreach ($chunk as $id) {
                if (! in_array($id, $found, false)) {
                    foreach ($dictionaryIds[$id] as $path) {
                        $report->error('reference_not_found', $path, 'Необязательная ссылка words указывает на отсутствующую запись.');
                    }
                }
            }
        }
        $seenVersions = [];
        foreach (array_chunk(array_keys($lexemes), 100) as $keys) {
            $versions = LearningSessionVersion::query()->where(function ($query) use ($keys) {
                foreach ($keys as $key) {
                    $query->orWhere('payload', 'like', '%"lexeme_key"%"'.$key.'"%');
                }
            })->limit(config('daily_practice.import.max_context_nodes') + 1)->get(['id', 'payload']);
            if ($versions->count() > config('daily_practice.import.max_context_nodes')) {
                $report->error('context_limit_exceeded', '', 'Слишком много версий для проверки словарных значений.');

                break;
            }
            foreach ($versions as $version) {
                if (isset($seenVersions[$version->id])) {
                    continue;
                }
                $seenVersions[$version->id] = true;
                foreach ($version->payload['vocabulary'] ?? [] as $word) {
                    $key = $word['lexeme_key'] ?? null;
                    if (isset($lexemes[$key]) && [$word['fr'] ?? null, $word['ru'] ?? null] !== $lexemes[$key]['meaning']) {
                        $report->warning('lexeme_meaning_review', $lexemes[$key]['path'], 'Существующие источники этого ключа отличаются: редактор должен проверить смысл, автоматическое склеивание запрещено.');
                    }
                }
            }
        }
        $media = [];
        foreach (array_chunk(array_keys($assets), 500) as $chunk) {
            foreach (LearningMedia::query()->whereIn('id', $chunk)->get() as $asset) {
                $media[$asset->id] = $asset;
            }
        }
        foreach ($assets as $id => $paths) {
            foreach ($paths as $path) {
                if (! isset($media[$id])) {
                    $report->error('reference_not_found', $path, 'Аудиофайл не найден в контролируемой медиатеке.');
                } elseif (! str_starts_with($media[$id]->mime_type, 'audio/')) {
                    $report->error('invalid_media_type', $path, 'Выбранный ресурс не является аудио.');
                } elseif ($media[$id]->review_status !== 'approved' || $media[$id]->archived_at !== null) {
                    $report->warning('media_review_required', $path, 'Аудиофайл должен пройти редакторскую проверку и быть доступен для публикации.');
                }
            }
        }
    }

    private function audioReferences(array $value, string $path, array &$assets, PackageValidationReport $report): void
    {
        if (isset($value['audio']) && is_array($value['audio'])) {
            $asset = $value['audio']['asset_id'];
            if (is_string($asset)) {
                $assets[$asset][] = $path.'/audio/asset_id';
            } else {
                $report->warning('browser_tts', $path.'/audio', 'Озвучка зависит от доступного французского голоса браузера; предусмотрите текстовую помощь и технический пропуск.', false);
            }
        }
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $this->audioReferences($child, $path.'/'.$key, $assets, $report);
            }
        }
    }
}
