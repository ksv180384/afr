<?php

namespace App\Services\Learning\Validation;

use LogicException;
use stdClass;

final class SessionPackageSchemaValidator
{
    private array $schema;

    private int $maxErrors;

    public function __construct()
    {
        $path = config('daily_practice.schema_path');
        if (! is_string($path) || ! is_readable($path)) {
            throw new LogicException('Daily practice schema is unavailable.');
        }
        $this->schema = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->maxErrors = config('daily_practice.import.max_validation_errors');
        $this->checkVocabulary($this->schema);
    }

    public function validate(stdClass $package): array
    {
        return $this->node($package, $this->schema, '');
    }

    private function resolve(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $reference = $schema['$ref'];
            if (! str_starts_with($reference, '#/$defs/')) {
                throw new LogicException('Only local package schema references are supported.');
            }
            $name = substr($reference, strlen('#/$defs/'));
            if (! isset($this->schema['$defs'][$name])) {
                throw new LogicException('Unknown package schema definition.');
            }

            return $this->schema['$defs'][$name];
        }

        return $schema;
    }

    private function checkVocabulary(array $schema): void
    {
        $supported = ['$schema', '$id', '$comment', '$defs', '$ref', 'title', 'description', 'type', 'const', 'enum', 'required', 'properties', 'additionalProperties', 'items', 'minItems', 'maxItems', 'uniqueItems', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'allOf', 'anyOf', 'oneOf', 'if', 'then', 'else'];
        foreach (array_keys($schema) as $keyword) {
            if (! in_array($keyword, $supported, true)) {
                throw new LogicException('Unsupported package schema keyword: '.$keyword);
            }
        }
        foreach (['properties', '$defs'] as $map) {
            foreach ($schema[$map] ?? [] as $child) {
                $this->checkVocabulary($child);
            }
        }
        foreach (['items', 'if', 'then', 'else'] as $keyword) {
            if (isset($schema[$keyword])) {
                $this->checkVocabulary($schema[$keyword]);
            }
        }
        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            foreach ($schema[$keyword] ?? [] as $child) {
                $this->checkVocabulary($child);
            }
        }
    }

    private function node(mixed $value, array $schema, string $path): array
    {
        $schema = $this->resolve($schema);
        $errors = [];
        $add = function (string $code, string $at, string $message) use (&$errors): void {
            $errors[] = ['code' => $code, 'path' => $at, 'message' => $message];
        };
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $matches = false;
            foreach ($types as $type) {
                $matches = $matches || match ($type) {
                    'object' => $value instanceof stdClass,
                    'array' => is_array($value),
                    'string' => is_string($value),
                    'integer' => is_int($value) || (is_float($value) && is_finite($value) && $value < PHP_INT_MAX && $value >= PHP_INT_MIN && floor($value) === $value),
                    'number' => is_int($value) || is_float($value),
                    'boolean' => is_bool($value),
                    'null' => $value === null,
                    default => throw new LogicException('Unsupported schema type.'),
                };
            }
            if (! $matches) {
                $add('invalid_type', $path, 'Неверный тип поля; ожидается '.implode(' или ', $types).'.');

                return $errors;
            }
        }
        if (array_key_exists('const', $schema) && CanonicalJson::encode($value) !== CanonicalJson::encode($schema['const'])) {
            $code = match (basename($path)) {
                'schema_version' => 'unsupported_schema_version',
                'language_pair' => 'unsupported_language_pair',
                'type' => 'unsupported_task_type',
                default => 'invalid_value',
            };
            $add($code, $path, 'Значение не поддерживается данным форматом.');
        }
        if (isset($schema['enum'])) {
            $valid = false;
            foreach ($schema['enum'] as $allowed) {
                $valid = $valid || CanonicalJson::encode($allowed) === CanonicalJson::encode($value);
            }
            if (! $valid) {
                $add('invalid_enum', $path, 'Значение отсутствует в допустимом справочнике.');
            }
        }
        foreach ($schema['allOf'] ?? [] as $child) {
            $errors = [...$errors, ...$this->node($value, $child, $path)];
        }
        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (! isset($schema[$keyword])) {
                continue;
            }
            $branches = $schema[$keyword];
            if ($keyword === 'oneOf' && $value instanceof stdClass && isset($this->resolve($branches[0])['properties']['type']['const'])) {
                if (! isset($value->type)) {
                    $add('required_field', $path.'/type', 'Обязательно укажите тип задания.');

                    return $errors;
                }
                $branches = array_values(array_filter($branches, fn (array $branch): bool => $this->resolve($branch)['properties']['type']['const'] === $value->type));
                if ($branches === []) {
                    $add('unsupported_task_type', $path.'/type', 'Этот тип задания не поддерживается.');

                    return $errors;
                }
                if ($value->type === 'fill_blank' && isset($value->input_mode)) {
                    $branches = array_values(array_filter($branches, fn (array $branch): bool => $this->resolve($branch)['properties']['input_mode']['const'] === $value->input_mode));
                    if ($branches === []) {
                        $add('invalid_task_fields', $path.'/input_mode', 'Режим пропуска должен быть choice или text.');

                        return $errors;
                    }
                }
            }
            $valid = 0;
            $best = null;
            foreach ($branches as $branch) {
                $issues = $this->node($value, $branch, $path);
                $valid += $issues === [] ? 1 : 0;
                if ($best === null || count($issues) < count($best)) {
                    $best = $issues;
                }
            }
            if ($valid === 0) {
                $errors = [...$errors, ...($best ?: [['code' => 'invalid_value', 'path' => $path, 'message' => 'Объект не соответствует допустимой структуре.']])];
            } elseif ($keyword === 'oneOf' && $valid !== 1) {
                $add('invalid_task_fields', $path, 'Структуры разных типов нельзя смешивать.');
            }
        }
        if (isset($schema['if'])) {
            $branch = $this->node($value, $schema['if'], $path) === [] ? 'then' : 'else';
            if (isset($schema[$branch])) {
                $errors = [...$errors, ...$this->node($value, $schema[$branch], $path)];
            }
        }
        if ($value instanceof stdClass) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! property_exists($value, $key)) {
                    $code = str_contains($key, 'transcription') || $key === 'pronunciation_notes' ? 'transcription_required' : 'required_field';
                    $add($code, $this->pointer($path, $key), 'Обязательное поле отсутствует: '.$key.'.');
                }
            }
            foreach (get_object_vars($value) as $key => $child) {
                $at = $this->pointer($path, (string) $key);
                if (isset($schema['properties'][$key])) {
                    $errors = [...$errors, ...$this->node($child, $schema['properties'][$key], $at)];
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $answerFields = ['accepted_answers', 'answer_lang', 'choices', 'correct_choice_id', 'tokens', 'accepted_orders', 'correct_pairs', 'input_mode'];
                    $code = in_array($key, $answerFields, true) && preg_match('~/(tasks|review_items)/~', $path) ? 'invalid_task_fields' : 'unknown_field';
                    $add($code, $at, 'Неизвестное или недопустимое здесь поле: '.$key.'.');
                }
                if (count($errors) > $this->maxErrors) {
                    break;
                }
            }
        }
        if (is_array($value)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $add('too_few_items', $path, 'Недостаточно элементов: минимум '.$schema['minItems'].'.');
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $add('too_many_items', $path, 'Слишком много элементов: максимум '.$schema['maxItems'].'.');
            }
            $seen = [];
            foreach ($value as $index => $child) {
                if ($schema['uniqueItems'] ?? false) {
                    $key = CanonicalJson::encode($child);
                    if (isset($seen[$key])) {
                        $add('duplicate_value', $path.'/'.$index, 'Значение повторяется в массиве.');
                    }
                    $seen[$key] = true;
                }
                if (isset($schema['items'])) {
                    $errors = [...$errors, ...$this->node($child, $schema['items'], $path.'/'.$index)];
                }
                if (count($errors) > $this->maxErrors) {
                    break;
                }
            }
        }
        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) {
                $add('empty_text', $path, 'Текст не должен быть пустым.');
            }
            if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
                $add('text_too_long', $path, 'Превышена допустимая длина текста.');
            }
            if (isset($schema['pattern']) && preg_match('~'.str_replace('~', '\\~', $schema['pattern']).'~u', $value) !== 1) {
                $add('invalid_text', $path, 'Текст имеет недопустимый формат или содержит разметку.');
            }
        }
        if ((is_int($value) || is_float($value)) && isset($schema['minimum']) && $value < $schema['minimum']) {
            $add('invalid_number', $path, 'Число меньше допустимого минимума.');
        }

        if ((is_int($value) || is_float($value)) && isset($schema['maximum']) && $value > $schema['maximum']) {
            $add('invalid_number', $path, 'Число больше допустимого максимума.');
        }

        return array_slice($errors, 0, $this->maxErrors + 1);
    }

    private function pointer(string $path, string $key): string
    {
        return $path.'/'.str_replace(['~', '/'], ['~0', '~1'], $key);
    }
}
