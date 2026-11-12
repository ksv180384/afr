<?php

namespace PracticeAudit;

/** Local editorial heuristics. No database, writes, grading or language-quality verdicts. */
final class PedagogyAudit
{
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(str_replace('’', "'", \Normalizer::normalize($text)))));
    }

    private static function wordCount(string $text): int
    {
        return preg_match_all("/[^\\W\\d_]+(?:[’'\\-][^\\W\\d_]+)*|\\d+/u", $text);
    }

    private static function sources(array $task): array
    {
        $prompt = $task['prompt'] ?? [];
        $sources = [];
        if (($task['skill'] ?? '') === 'reading' && ($prompt['text_lang'] ?? '') === 'fr') {
            $sources['reading'] = $prompt['text'];
        }
        if (($task['skill'] ?? '') === 'listening' && ! empty($prompt['audio']['text'])) {
            $sources['listening'] = $prompt['audio']['text'];
        }

        return $sources;
    }

    private static function fingerprint(array $task): string
    {
        $content = array_intersect_key($task, array_flip(['type', 'input_mode', 'instruction', 'prompt', 'choices', 'left_items', 'right_items', 'tokens', 'text_before', 'text_after']));
        $clean = function (mixed $node) use (&$clean): mixed {
            if (is_string($node)) {
                return self::normalize($node);
            }
            if (! is_array($node)) {
                return $node;
            }
            $out = [];
            foreach ($node as $key => $value) {
                if (in_array($key, ['id', 'transcription_ru', 'asset_id'], true)) {
                    continue;
                }
                $out[$key] = $clean($value);
            }

            if (! array_is_list($out)) {
                ksort($out, SORT_STRING);
            }

            return $out;
        };

        return hash('sha256', json_encode($clean($content), JSON_THROW_ON_ERROR));
    }

    public function analyze(array $package, array $previousPackages = [], bool $newMaterial = false): array
    {
        $warnings = [];
        $emit = function (string $code, array $where, string $message, array $evidence = [], string $confidence = 'candidate') use (&$warnings): void {
            $warnings[] = ['code' => $code, 'severity' => 'editorial_warning', 'confidence' => $confidence, ...$where, 'message' => $message, 'evidence' => $evidence];
        };
        $sources = [];
        $questions = [];
        $introduced = [];
        $groups = [...array_map(fn ($p) => ['package' => $p, 'report' => false], $previousPackages), ['package' => $package, 'report' => true]];
        foreach ($groups as $group) {
            foreach ($group['package']['sessions'] ?? [] as $si => $session) {
                $level = $session['level'];
                $vm = array_column($session['vocabulary'], null, 'id');
                $checkpoint = $session['kind'] === 'checkpoint';
                $sourceCount = 0;
                $reusedCount = 0;
                $positions = [];
                $answers = [];
                $flaggedKeys = [];
                $taskSources = [];
                foreach ($session['tasks'] as $ti => $task) {
                    $where = ['session_id' => $session['id'], 'task_id' => $task['id'], 'path' => '/sessions/'.$si.'/tasks/'.$ti];
                    foreach ($session['introductions'] ?? [] as $intro) {
                        if ($intro['before_task_id'] === $task['id']) {
                            foreach ($intro['vocabulary_ids'] as $vid) {
                                if (isset($vm[$vid])) {
                                    $introduced[$level][$vm[$vid]['lexeme_key']] = true;
                                }
                            }
                        }
                    }
                    foreach (self::sources($task) as $skill => $text) {
                        $normalized = self::normalize($text);
                        $taskSources[$normalized] = true;
                        $sourceCount++;
                        if (isset($sources[$level][$normalized])) {
                            $reusedCount++;
                            if ($group['report'] && $checkpoint) {
                                $short = self::wordCount($text) <= 4;
                                $emit($short ? 'common_short_source_reuse' : 'checkpoint_source_reuse', $where,
                                    $short ? 'Короткая фраза уже встречалась; возможное допустимое совпадение, проверьте новую задачу.' : 'Источник контроля дословно повторяет более раннее обычное задание.',
                                    ['skill' => $skill, 'words' => self::wordCount($text), 'previous' => $sources[$level][$normalized]], $short ? 'low_short_phrase' : 'exact_match');
                            }
                        }
                        if (! $checkpoint) {
                            $sources[$level][$normalized] ??= $where;
                        }
                    }
                    $fingerprint = self::fingerprint($task);
                    if ($group['report'] && isset($questions[$level][$fingerprint])) {
                        $emit('repeated_question', $where, 'Совпадают условие и материал вопроса; повторение может быть намеренным извлечением из памяти.', ['previous' => $questions[$level][$fingerprint]], 'exact_match');
                    }
                    $questions[$level][$fingerprint] ??= $where;
                    if (! $group['report']) {
                        continue;
                    }
                    if (($task['skill'] ?? '') === 'listening' && empty($task['prompt']['audio']['text'])) {
                        $emit('listening_source_missing', $where, 'Для аудирования отсутствует текст озвучки; проверьте источник и транскрипцию.');
                    }
                    foreach ($task['assessed_vocabulary_ids'] as $vid) {
                        $key = $vm[$vid]['lexeme_key'];
                        if (! isset($introduced[$level][$key]) && ! isset($flaggedKeys[$key])) {
                            $emit($checkpoint ? 'checkpoint_without_prior_intro_link' : 'assessment_without_prior_intro_link', $where,
                                'До оценки ключа не найдена ссылка из вводной карточки в данном контексте. Это не доказательство отсутствия объяснения или входных знаний.', ['lexeme_key' => $key]);
                            $flaggedKeys[$key] = true;
                        }
                    }
                    $this->inputWarning($task, $where, $newMaterial, $emit);
                    $choices = $task['choices'] ?? [];
                    if ($choices && isset($task['correct_choice_id'])) {
                        $ids = array_column($choices, 'id');
                        $correctIndex = array_search($task['correct_choice_id'], $ids, true);
                        $positions[$correctIndex][] = $task['id'];
                        $answer = self::normalize($choices[$correctIndex]['text']);
                        $answers[$answer][] = $task['id'];
                        $rightLength = self::wordCount($choices[$correctIndex]['text']);
                        $otherLengths = array_map(fn ($c) => self::wordCount($c['text']), array_filter($choices, fn ($c) => $c['id'] !== $task['correct_choice_id']));
                        $longestOther = max($otherLengths);
                        if ($rightLength >= $longestOther * 1.5 && $rightLength - $longestOther >= 3) {
                            $emit('correct_option_length_cue', $where, 'Правильный вариант заметно длиннее остальных: возможна подсказка формой.', ['correct_words' => $rightLength, 'longest_distractor_words' => $longestOther]);
                        }
                    }
                }
                if (! $group['report']) {
                    continue;
                }
                $where = ['session_id' => $session['id'], 'task_id' => null, 'path' => '/sessions/'.$si];
                if ($sourceCount > 0 && $sourceCount === $reusedCount) {
                    $emit('no_new_source_context', $where, 'Все источники чтения/аудирования уже встречались. Проверяется только точный текст; новая задача или цель могут оправдывать повтор.', ['sources' => $sourceCount]);
                }
                $choiceCount = array_sum(array_map('count', $positions));
                foreach ($positions as $index => $ids) {
                    if ($choiceCount >= 5 && count($ids) / $choiceCount >= 0.8) {
                        $emit('correct_option_position_bias', $where, 'Правильный вариант часто на одной авторской позиции; учитывайте перемешивание вариантов плеером.', ['position' => $index + 1, 'tasks' => $ids, 'choice_tasks' => $choiceCount]);
                    }
                }
                foreach ($answers as $text => $ids) {
                    if (count($ids) >= 3) {
                        $emit('repeated_correct_answer', $where, 'В нескольких вопросах один и тот же правильный текст; проверьте разнообразие смысловых действий.', ['answer' => $text, 'tasks' => $ids]);
                    }
                }
                $keyIds = array_unique(array_merge(...array_map(fn ($t) => $t['assessed_vocabulary_ids'], $session['tasks'])));
                foreach ($session['introductions'] ?? [] as $intro) {
                    $keyIds = array_unique([...$keyIds, ...$intro['vocabulary_ids']]);
                }
                foreach ($keyIds as $vid) {
                    $word = $vm[$vid];
                    $at = [...$where, 'path' => $where['path'].'/vocabulary/'.array_search($word, $session['vocabulary'], true)];
                    if (empty($word['review_items'])) {
                        $emit('missing_review_variant', $at, 'У вводимого или оцениваемого выражения нет подготовленного варианта повторения.', ['lexeme_key' => $word['lexeme_key']]);
                    } else {
                        $alternative = false;
                        foreach ($word['review_items'] as $review) {
                            foreach (self::sources([...$review, 'skill' => isset($review['prompt']['audio']) ? 'listening' : 'reading']) as $text) {
                                $alternative = $alternative || ! isset($taskSources[self::normalize($text)]);
                            }
                        }
                        if (! $alternative) {
                            $emit('review_context_not_demonstrated', $at, 'Автоматически не найден альтернативный французский источник повторения; переводной вопрос может быть достаточным для значения, применение проверяет редактор.', ['lexeme_key' => $word['lexeme_key']], 'low');
                        }
                    }
                }
                foreach ($session['vocabulary'] as $vi => $word) {
                    foreach ($word['review_items'] as $ri => $review) {
                        $this->inputWarning($review, [...$where, 'path' => $where['path'].'/vocabulary/'.$vi.'/review_items/'.$ri], $newMaterial, $emit);
                    }
                }
            }
        }

        return ['status' => 'heuristics_completed', 'new_material_rules' => $newMaterial, 'warning_count' => count($warnings), 'warnings' => $warnings,
            'language_quality' => 'not_assessed', 'learning_effectiveness' => 'not_measured',
            'limitations' => ['Точные совпадения не устанавливают смысл, естественность или уровень языка.', 'Intro-ссылка не доказывает объяснение или усвоение; отсутствие ссылки не доказывает незнакомое слово.', 'Длины и доли — эвристики, не квоты CEFR и не обязательный запрет публикации.']];
    }

    private function inputWarning(array $task, array $where, bool $newMaterial, callable $emit): void
    {
        if ($task['type'] === 'short_answer' || ($task['type'] === 'fill_blank' && ($task['input_mode'] ?? '') === 'text')) {
            $emit($newMaterial ? 'typed_input_in_new_material' : 'legacy_typed_input', $where,
                $newMaterial ? 'Новый материал требует печати; используйте уже поддерживаемые ответы без клавиатуры.' : 'Старый текстовый тип поддерживается, но не подходит для нового материала без печати.');
        }
    }
}
