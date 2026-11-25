<?php

namespace App\Support\Learning;

use UnexpectedValueException;

final class OperationReceipt
{
    public const MIN_COMPRESSION_BYTES = 512;

    public static function encode(array $receipt): string
    {
        $json = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) < self::MIN_COMPRESSION_BYTES) {
            return $json;
        }

        $compressed = gzencode($json, 3);
        if ($compressed === false) {
            return $json;
        }

        $wrapped = json_encode(['gzip_v1' => base64_encode($compressed)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return strlen($wrapped) < strlen($json) ? $wrapped : $json;
    }

    public static function decode(string $stored): array
    {
        $receipt = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        if (! isset($receipt['gzip_v1'])) {
            return $receipt;
        }

        $compressed = base64_decode($receipt['gzip_v1'], true);
        $json = $compressed !== false ? gzdecode($compressed) : false;
        if ($json === false) {
            throw new UnexpectedValueException('Invalid compressed learning operation receipt.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
