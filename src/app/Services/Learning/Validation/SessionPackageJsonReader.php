<?php

namespace App\Services\Learning\Validation;

use App\DTOs\Learning\PackageValidationReport;
use JsonException;
use OverflowException;
use stdClass;

final class SessionPackageJsonReader
{
    public function read(string $json, PackageValidationReport $report): ?stdClass
    {
        $limits = config('daily_practice.import');
        if (strlen($json) > $limits['max_bytes']) {
            $report->error('file_too_large', '', 'Размер JSON превышает допустимый предел.');

            return null;
        }
        if (! mb_check_encoding($json, 'UTF-8') || str_starts_with($json, "\xEF\xBB\xBF")) {
            $report->error('invalid_encoding', '', 'Требуется UTF-8 без BOM.');

            return null;
        }
        $offset = 0;
        try {
            $this->value($json, $offset, '', 0, $limits, $report);
            $this->whitespace($json, $offset);
            if ($offset !== strlen($json)) {
                throw new JsonException('Лишние данные после JSON.');
            }
            $decoded = json_decode($json, false, $limits['max_json_depth'] + 2, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (OverflowException) {
            return null;
        } catch (JsonException) {
            $report->error('invalid_json', '', 'Некорректный синтаксис JSON около позиции '.$offset.'.');

            return null;
        }
        if (! $decoded instanceof stdClass) {
            $report->error('invalid_root', '', 'Корень пакета должен быть JSON-объектом.');

            return null;
        }
        $report->setPackage(json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));

        return $report->errors() === [] ? $decoded : null;
    }

    private function value(string $json, int &$offset, string $path, int $depth, array $limits, PackageValidationReport $report): void
    {
        $this->whitespace($json, $offset);
        $token = $json[$offset] ?? '';
        if ($token === '{' || $token === '[') {
            if ($depth >= $limits['max_json_depth']) {
                $report->error('depth_limit_exceeded', $path, 'Превышена допустимая глубина JSON.');
                throw new OverflowException;
            }
            $object = $token === '{';
            $closing = $object ? '}' : ']';
            $offset++;
            $this->whitespace($json, $offset);
            if (($json[$offset] ?? '') === $closing) {
                $offset++;

                return;
            }
            $seen = [];
            $count = 0;
            do {
                if ($count >= ($object ? $limits['max_object_properties'] : $limits['max_array_items'])) {
                    $report->error($object ? 'object_limit_exceeded' : 'array_limit_exceeded', $path, 'Превышен допустимый размер JSON-контейнера.');
                    throw new OverflowException;
                }
                $this->whitespace($json, $offset);
                if ($object) {
                    $key = $this->string($json, $offset);
                    if (mb_strlen($key) > $limits['max_id_length']) {
                        $report->error('property_name_too_long', $path, 'Слишком длинное имя свойства JSON.');
                        throw new OverflowException;
                    }
                    $child = $path.'/'.str_replace(['~', '/'], ['~0', '~1'], $key);
                    if (isset($seen[$key])) {
                        $report->error('duplicate_json_key', $child, 'Повторный ключ JSON-объекта недопустим.');
                    }
                    $seen[$key] = true;
                    $this->whitespace($json, $offset);
                    if (($json[$offset++] ?? '') !== ':') {
                        throw new JsonException;
                    }
                } else {
                    $child = $path.'/'.$count;
                }
                $this->value($json, $offset, $child, $depth + 1, $limits, $report);
                $count++;
                $this->whitespace($json, $offset);
                $separator = $json[$offset++] ?? '';
                if ($separator === $closing) {
                    return;
                }
                if ($separator !== ',') {
                    throw new JsonException;
                }
            } while (true);
        }
        if ($token === '"') {
            $text = $this->string($json, $offset);
            if (str_contains($text, '<') || str_contains($text, '>')) {
                $report->error('invalid_text', $path, 'Разметка HTML в пакете недопустима.');
            }
            if (mb_strlen($text) > $limits['max_text_length']) {
                $report->error('string_limit_exceeded', $path, 'Строка превышает допустимую длину.');
            }

            return;
        }
        if (! preg_match('/\\G(?:true|false|null|-?(?:0|[1-9][0-9]*)(?:\\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/A', $json, $match, 0, $offset)) {
            throw new JsonException;
        }
        if (preg_match('/^[-0-9]/', $match[0]) && ! is_finite((float) $match[0])) {
            $report->error('invalid_number', $path, 'Число не представимо безопасно на сервере.');
            throw new OverflowException;
        }
        $offset += strlen($match[0]);
    }

    private function string(string $json, int &$offset): string
    {
        if (($json[$offset] ?? '') !== '"') {
            throw new JsonException;
        }
        $start = $offset++;
        $length = strlen($json);
        while ($offset < $length) {
            $character = $json[$offset++];
            if ($character === '"') {
                return json_decode(substr($json, $start, $offset - $start), true, 512, JSON_THROW_ON_ERROR);
            }
            if ($character === '\\') {
                $offset++;
            }
        }

        throw new JsonException;
    }

    private function whitespace(string $json, int &$offset): void
    {
        $offset += strspn($json, " \t\r\n", $offset);
    }
}
