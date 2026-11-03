<?php

use App\Enums\Learning\LearningLevel;
use App\Enums\Learning\LearningSkill;
use App\Enums\Learning\SessionKind;
use App\Enums\Learning\TaskType;
use Opis\JsonSchema\CompliantValidator;

function practiceContractDirectory(): string
{
    return getenv('PRACTICE_CONTRACT_ROOT') ?: dirname(__DIR__, 3).'/docs/daily-practice';
}

function practiceContractJson(string $path): array
{
    return json_decode(file_get_contents(practiceContractDirectory().'/'.$path), true, 512, JSON_THROW_ON_ERROR);
}

function practiceContractValidator(): CompliantValidator
{
    static $validator;

    return $validator ??= new CompliantValidator;
}

function practiceContractValid(array $package): bool
{
    static $schema;
    $schema ??= json_decode(file_get_contents(practiceContractDirectory().'/schema/session-package-v1.schema.json'), false, 512, JSON_THROW_ON_ERROR);

    return practiceContractValidator()->validate(
        json_decode(json_encode($package, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        $schema,
    )->isValid();
}

function practiceContractNormalize(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', Normalizer::normalize(str_replace('’', "'", $value))));
}

/**
 * Fixture-only oracle for the intended semantic negative cases, not a production validator.
 */
function practiceFixtureErrors(array $package, ?array $existing = null): array
{
    $errors = [];
    $sessions = array_column($package['sessions'], null, 'id');
    $previous = [];
    foreach ($existing['sessions'] ?? [] as $session) {
        $previous[$session['id']][$session['version']] = $session;
    }
    foreach ($package['sessions'] as $si => $session) {
        $seen = [];
        foreach ($session['tasks'] as $ti => $task) {
            if (isset($seen[$task['id']])) {
                $errors[] = ['code' => 'duplicate_id', 'path' => "/sessions/$si/tasks/$ti/id"];
            }
            $seen[$task['id']] = true;
            foreach ($task['assessed_vocabulary_ids'] as $vi => $id) {
                if (! in_array($id, $task['vocabulary_ids'], true)) {
                    $errors[] = ['code' => 'invalid_assessed_vocabulary', 'path' => "/sessions/$si/tasks/$ti/assessed_vocabulary_ids/$vi"];
                }
            }
        }
        foreach ($session['prerequisite_ids'] as $pi => $id) {
            if (! isset($sessions[$id])) {
                $errors[] = ['code' => 'reference_not_found', 'path' => "/sessions/$si/prerequisite_ids/$pi"];
            }
        }
        if (isset($previous[$session['id']][$session['version']]) && $session != $previous[$session['id']][$session['version']]) {
            $errors[] = ['code' => 'version_conflict', 'path' => "/sessions/$si/version"];
        }
    }
    $reaches = function (string $from, string $target, array $visited) use (&$reaches, $sessions): bool {
        if ($from === $target) {
            return true;
        }
        if (isset($visited[$from]) || ! isset($sessions[$from])) {
            return false;
        }
        $visited[$from] = true;
        foreach ($sessions[$from]['prerequisite_ids'] as $id) {
            if ($reaches($id, $target, $visited)) {
                return true;
            }
        }

        return false;
    };
    foreach ($package['sessions'] as $si => $session) {
        foreach ($session['prerequisite_ids'] as $pi => $id) {
            if ($reaches($id, $session['id'], [])) {
                $errors[] = ['code' => 'prerequisite_cycle', 'path' => "/sessions/$si/prerequisite_ids/$pi"];
            }
        }
    }

    return $errors;
}

function practiceContractWalk(array $value, array $path = []): Generator
{
    yield [$path, $value];
    foreach ($value as $key => $child) {
        if (is_array($child)) {
            yield from practiceContractWalk($child, [...$path, $key]);
        }
    }
}

beforeEach(function () {
    $autoload = getenv('PRACTICE_SCHEMA_AUTOLOAD');
    if (! class_exists(CompliantValidator::class) && $autoload && is_file($autoload)) {
        $loader = require $autoload;
        $loader->unregister();
        $loader->register(false);
    }
    if (! is_dir(practiceContractDirectory()) || ! class_exists(CompliantValidator::class)) {
        $this->markTestSkipped('Contract fixtures/isolated JSON Schema tooling unavailable; see docs/daily-practice/FORMAT_V1.md.');
    }
});

it('keeps the schema aligned with stage 01 enums and configured limits', function () {
    $schema = practiceContractJson('schema/session-package-v1.schema.json');
    $config = require __DIR__.'/../../config/daily_practice.php';

    expect($schema['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema['properties']['schema_version']['const'])->toBe($config['schema_version'])
        ->and($schema['properties']['language_pair']['const'])->toBe($config['language_pair'])
        ->and($schema['$defs']['level']['enum'])->toBe(array_column(LearningLevel::cases(), 'value'))
        ->and($schema['$defs']['session']['properties']['skills']['items']['enum'])->toBe(array_column(LearningSkill::cases(), 'value'))
        ->and($schema['$defs']['session']['properties']['kind']['enum'])->toBe(array_column(SessionKind::cases(), 'value'))
        ->and($schema['properties']['sessions']['maxItems'])->toBe($config['import']['max_sessions'])
        ->and($schema['$defs']['text']['maxLength'])->toBe($config['import']['max_text_length'])
        ->and($schema['$defs']['id']['maxLength'])->toBe($config['import']['max_id_length'])
        ->and($schema['$defs']['session']['properties']['tasks']['minItems'])->toBe($config['session']['min_tasks'])
        ->and($schema['$defs']['session']['properties']['tasks']['maxItems'])->toBe($config['session']['max_tasks']);

    $types = [];
    foreach ($schema['$defs']['task']['oneOf'] as $reference) {
        $definition = basename($reference['$ref']);
        $types[] = $schema['$defs'][$definition]['properties']['type']['const'];
    }
    expect(array_values(array_unique($types)))->toEqualCanonicalizing(array_column(TaskType::cases(), 'value'));
});

it('validates both complete examples against the actual schema', function (string $file) {
    $package = practiceContractJson('examples/'.$file);
    expect(practiceContractValid($package))->toBeTrue()
        ->and(practiceFixtureErrors($package))->toBe([]);

    $sessions = array_column($package['sessions'], null, 'id');
    $units = array_column($package['units'], null, 'id');
    foreach ($package['sessions'] as $session) {
        expect($units[$session['unit_id']]['level'])->toBe($session['level']);
        foreach ([...$session['prerequisite_ids'], ...($session['public_page']['related_session_ids'] ?? [])] as $id) {
            expect(isset($sessions[$id]))->toBeTrue();
        }
        $vocabulary = array_column($session['vocabulary'], 'id');
        $objectives = array_column($session['objectives'], 'id');
        $tasks = array_column($session['tasks'], 'id');
        expect(count($tasks))->toBe(count(array_unique($tasks)));
        foreach ($session['introductions'] as $introduction) {
            expect($tasks)->toContain($introduction['before_task_id'])
                ->and(array_diff($introduction['vocabulary_ids'], $vocabulary))->toBe([]);
        }
        foreach ($session['tasks'] as $task) {
            expect($task['objective_ids'])->not->toBeEmpty()
                ->and(array_diff($task['objective_ids'], $objectives))->toBe([])
                ->and(array_diff($task['vocabulary_ids'], $vocabulary))->toBe([])
                ->and(array_diff($task['assessed_vocabulary_ids'], $task['vocabulary_ids']))->toBe([])
                ->and($session['skills'])->toContain($task['skill']);
            if ($task['type'] === 'matching_pairs') {
                expect(array_column($task['correct_pairs'], 'left_id'))->toEqualCanonicalizing(array_column($task['left_items'], 'id'))
                    ->and(array_column($task['correct_pairs'], 'right_id'))->toEqualCanonicalizing(array_column($task['right_items'], 'id'));
                $assessed = array_merge(...array_column($task['correct_pairs'], 'assessed_vocabulary_ids'));
                expect($task['assessed_vocabulary_ids'])->toEqualCanonicalizing(array_values(array_unique($assessed)));
            }
            if ($task['type'] === 'word_order') {
                foreach ($task['accepted_orders'] as $order) {
                    expect($order)->toEqualCanonicalizing(array_column($task['tokens'], 'id'));
                }
            }
            if ($task['type'] === 'fill_blank') {
                $answers = $task['accepted_answers'] ?? [
                    array_column($task['choices'], 'text', 'id')[$task['correct_choice_id']],
                ];
                foreach ($answers as $answer) {
                    expect(array_column($task['completed_phrases'], 'fr'))->toContain($task['text_before'].$answer.$task['text_after']);
                }
            }
        }
    }
})->with(['pre-a1-u01-examples.json', 'task-types.json']);

it('preserves every F step and introduces vocabulary before its first assessed use', function () {
    $sessions = practiceContractJson('examples/pre-a1-u01-examples.json')['sessions'];
    $skills = [
        ['listening', 'vocabulary', 'vocabulary', 'grammar', 'reading', 'sentence_building'],
        ['listening', 'vocabulary', 'listening', 'grammar', 'listening', 'reading'],
    ];
    foreach ($sessions as $index => $session) {
        expect($session['id'])->toBe('PRE_A1-U01-L0'.($index + 1))
            ->and(array_column($session['tasks'], 'id'))->toBe(['Q01', 'Q02', 'Q03', 'Q04', 'Q05', 'Q06'])
            ->and(array_column($session['tasks'], 'skill'))->toBe($skills[$index])
            ->and($session['vocabulary'])->toHaveCount(6)
            ->and($session['public_page']['explanation_blocks'])->toHaveCount(3);

        $positions = array_flip(array_column($session['tasks'], 'id'));
        $introduced = [];
        foreach ($session['introductions'] as $introduction) {
            foreach ($introduction['vocabulary_ids'] as $id) {
                $introduced[$id] = $positions[$introduction['before_task_id']];
            }
        }
        foreach ($session['tasks'] as $task) {
            foreach ($task['vocabulary_ids'] as $id) {
                expect($introduced[$id])->toBeLessThanOrEqual($positions[$task['id']]);
            }
            if (in_array($task['type'], ['fill_blank', 'word_order'], true)) {
                expect($task['assessed_vocabulary_ids'])->toBe([]);
            }
        }
    }
    expect($sessions[1]['prerequisite_ids'])->toBe([$sessions[0]['id']])
        ->and($sessions[1]['tasks'][2]['assessed_vocabulary_ids'])->toBe(['ca-va-bien']);
});

it('gives every lexeme a real review item and covers French accepted answers', function () {
    $meanings = [];
    foreach (['pre-a1-u01-examples.json', 'task-types.json'] as $file) {
        foreach (practiceContractJson('examples/'.$file)['sessions'] as $session) {
            foreach ($session['vocabulary'] as $entry) {
                expect($entry['review_items'])->not->toBeEmpty();
                $meaning = [$entry['fr'], $entry['ru']];
                if (isset($meanings[$entry['lexeme_key']])) {
                    expect($meaning)->toBe($meanings[$entry['lexeme_key']]);
                }
                $meanings[$entry['lexeme_key']] = $meaning;
            }
            foreach (practiceContractWalk($session) as [$path, $value]) {
                if (isset($value['choices'])) {
                    $ids = array_column($value['choices'], 'id');
                    expect(count($ids))->toBe(count(array_unique($ids)))
                        ->and($ids)->toContain($value['correct_choice_id']);
                }
                if (isset($value['pronunciation_notes'])) {
                    $notes = array_map(fn (array $note): string => practiceContractNormalize($note['fr']), $value['pronunciation_notes']);
                    expect(count($notes))->toBe(count(array_unique($notes)));
                }
                if (($value['answer_lang'] ?? null) === 'fr') {
                    foreach ($value['accepted_answers'] as $answer) {
                        expect($notes)->toContain(practiceContractNormalize($answer));
                    }
                }
            }
        }
    }
    expect($meanings)->toHaveCount(12);
});

it('retains both fill modes and distinct IDs for identical word tokens', function () {
    $tasks = practiceContractJson('examples/task-types.json')['sessions'][0]['tasks'];
    expect(array_values(array_unique(array_column($tasks, 'type'))))->toEqualCanonicalizing(array_column(TaskType::cases(), 'value'))
        ->and($tasks[2]['input_mode'])->toBe('choice')
        ->and($tasks[3]['input_mode'])->toBe('text')
        ->and(array_column($tasks[5]['tokens'], 'text'))->toBe(['très', 'très', 'bien'])
        ->and(array_column($tasks[5]['tokens'], 'id'))->toBe(['t1', 't2', 't3'])
        ->and($tasks[5]['accepted_orders'])->toBe([['t1', 't2', 't3'], ['t2', 't1', 't3']]);
});

it('rejects the documented invalid package for the specified reason', function (int $index) {
    $case = practiceContractJson('examples/invalid/manifest.json')['cases'][$index];
    $package = practiceContractJson('examples/invalid/'.$case['file']);
    if ($case['layer'] === 'schema') {
        expect(practiceContractValid($package))->toBeFalse();
    } else {
        expect(practiceContractValid($package))->toBeTrue();
        $existing = isset($case['context']) ? practiceContractJson('examples/invalid/'.$case['context']['existing_package']) : null;
        $errors = practiceFixtureErrors($package, $existing);
        foreach ($case['expected_errors'] as $expected) {
            expect($errors)->toContain($expected);
        }
        expect(array_diff(array_column($errors, 'code'), array_column($case['expected_errors'], 'code')))->toBe([]);
    }
})->with(range(0, 9));

it('distinguishes valid drafts and catalog packages from publication readiness', function () {
    $package = practiceContractJson('examples/pre-a1-u01-examples.json');
    foreach ($package['sessions'] as &$session) {
        unset($session['public_page']);
        foreach ($session['vocabulary'] as &$entry) {
            $entry['review_items'] = [];
        }
        unset($entry);
    }
    unset($session);
    expect(practiceContractValid($package))->toBeTrue();
    $package['sessions'] = [];
    expect(practiceContractValid($package))->toBeTrue();
    $package['levels'] = [];
    $package['units'] = [];
    expect(practiceContractValid($package))->toBeFalse();
});

it('rejects unknown fields in every nested object', function () {
    $package = practiceContractJson('examples/pre-a1-u01-examples.json');
    foreach (practiceContractWalk($package) as [$path, $value]) {
        if (array_is_list($value)) {
            continue;
        }
        $mutated = $package;
        $target = &$mutated;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target['unexpected_field'] = true;
        unset($target);
        expect(practiceContractValid($mutated))->toBeFalse();
    }
});

it('rejects missing French transcriptions and incompatible answer fields', function () {
    $package = practiceContractJson('examples/task-types.json');
    foreach (practiceContractWalk($package) as [$path, $value]) {
        if (($value['text_lang'] ?? null) !== 'fr') {
            continue;
        }
        $mutated = $package;
        $target = &$mutated;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        unset($target['transcription_ru'], $target);
        expect(practiceContractValid($mutated))->toBeFalse();
    }
    $package['sessions'][0]['tasks'][0]['accepted_answers'] = ['wrong'];
    expect(practiceContractValid($package))->toBeFalse();
});

function practiceContractStrings(array $value): string
{
    $text = [];
    foreach ($value as $key => $child) {
        if ($key === 'pronunciation_notes') {
            continue;
        }
        if (is_string($child)) {
            $text[] = $child;
        } elseif (is_array($child)) {
            $text[] = practiceContractStrings($child);
        }
    }

    return implode(' ', $text);
}

function practiceAssertFrenchCovered(string $text, array $notes): void
{
    usort($notes, fn (array $a, array $b): int => mb_strlen($b['fr']) <=> mb_strlen($a['fr']));
    $remainder = practiceContractNormalize($text);
    foreach ($notes as $note) {
        $remainder = str_replace(practiceContractNormalize($note['fr']), '', $remainder);
    }
    $remainder = preg_replace('/\b(?:ID|JSON)\b/u', '', $remainder);
    expect(preg_match('/[A-Za-zÀ-ÖØ-öø-ÿŒœ]/u', $remainder))->toBe(0);
}

it('covers mixed French fragments with real local pronunciation notes', function () {
    foreach (['pre-a1-u01-examples.json', 'task-types.json'] as $file) {
        $package = practiceContractJson('examples/'.$file);
        foreach ([...$package['levels'], ...$package['units']] as $entry) {
            practiceAssertFrenchCovered($entry['title'].' '.$entry['summary'], $entry['pronunciation_notes'] ?? []);
        }
        foreach ($package['sessions'] as $session) {
            practiceAssertFrenchCovered($session['title'].' '.$session['description'], $session['pronunciation_notes'] ?? []);
            foreach (practiceContractWalk($session) as [$path, $value]) {
                $notes = $value['pronunciation_notes'] ?? [];
                foreach ($notes as $note) {
                    expect(practiceContractNormalize(practiceContractStrings($value)))->toContain(practiceContractNormalize($note['fr']));
                }
                if (isset($value['before_task_id'])) {
                    practiceAssertFrenchCovered($value['text'], $notes);
                }
                if (isset($value['correct'], $value['incorrect'])) {
                    practiceAssertFrenchCovered($value['correct'].' '.$value['incorrect'], $notes);
                }
                if (isset($value['paragraphs'])) {
                    practiceAssertFrenchCovered($value['heading'].' '.implode(' ', $value['paragraphs']), $notes);
                }
                if (isset($value['instruction'])) {
                    practiceAssertFrenchCovered($value['instruction'].' '.($value['hint'] ?? ''), $notes);
                    if ($value['prompt']['text_lang'] === 'ru') {
                        practiceAssertFrenchCovered($value['prompt']['text'], $notes);
                    }
                }
            }
        }
    }
});

it('enforces text limits blank fragments and checkpoint objective requirements', function () {
    $original = practiceContractJson('examples/task-types.json');
    $package = $original;
    $package['sessions'][0]['tasks'][2]['text_before'] = '';
    expect(practiceContractValid($package))->toBeFalse();
    $package['sessions'][0]['tasks'][2]['text_before_transcription_ru'] = '';
    expect(practiceContractValid($package))->toBeTrue();
    foreach (['<script>alert(1)</script>', '   ', str_repeat('а', 20001)] as $text) {
        $package = $original;
        $package['sessions'][0]['tasks'][0]['instruction'] = $text;
        expect(practiceContractValid($package))->toBeFalse();
    }
    $package = $original;
    $package['sessions'][0]['kind'] = 'checkpoint';
    $package['sessions'][0]['tasks'][0]['objective_ids'] = [];
    expect(practiceContractValid($package))->toBeFalse();
    $package = $original;
    $package['sessions'][0]['tasks'][3]['pronunciation_notes'] = [];
    expect(practiceContractValid($package))->toBeFalse();
    $package = $original;
    $package['sessions'][0]['tasks'][4]['accepted_answers'] = [];
    expect(practiceContractValid($package))->toBeFalse();
    $package = $original;
    array_pop($package['sessions'][0]['tasks']);
    expect(practiceContractValid($package))->toBeFalse();
});

it('keeps every JSON fixture UTF-8 LF and within preparse limits', function () {
    $config = require __DIR__.'/../../config/daily_practice.php';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(practiceContractDirectory().'/examples', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'json') {
            continue;
        }
        $raw = file_get_contents($file->getPathname());
        expect(mb_check_encoding($raw, 'UTF-8'))->toBeTrue()
            ->and(str_starts_with($raw, "\xEF\xBB\xBF"))->toBeFalse()
            ->and(str_contains($raw, "\r"))->toBeFalse()
            ->and(str_ends_with($raw, "\n"))->toBeTrue()
            ->and(strlen($raw))->toBeLessThanOrEqual($config['import']['max_bytes']);
        json_decode($raw, true, $config['import']['max_json_depth'], JSON_THROW_ON_ERROR);
    }
});
