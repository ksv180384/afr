<?php

namespace App\Services\Learning;

use App\DTOs\Learning\PackageValidationReport;
use App\Services\Learning\Validation\SessionPackageJsonReader;
use App\Services\Learning\Validation\SessionPackagePronunciationValidator;
use App\Services\Learning\Validation\SessionPackageReferenceValidator;
use App\Services\Learning\Validation\SessionPackageSchemaValidator;
use App\Services\Learning\Validation\SessionPackageTaskValidator;

final class SessionPackageValidator
{
    public function __construct(
        private readonly SessionPackageJsonReader $reader,
        private readonly SessionPackageSchemaValidator $schema,
        private readonly SessionPackageTaskValidator $tasks,
        private readonly SessionPackagePronunciationValidator $pronunciation,
        private readonly SessionPackageReferenceValidator $references,
    ) {}

    public function validate(string $json): PackageValidationReport
    {
        $report = new PackageValidationReport(
            strlen($json) <= config('daily_practice.import.max_bytes') ? hash('sha256', $json) : '',
            config('daily_practice.import.max_validation_errors'),
        );
        $object = $this->reader->read($json, $report);
        if ($object === null) {
            return $report;
        }
        $errors = $this->schema->validate($object);
        foreach ($errors as $error) {
            $report->error($error['code'], $error['path'], $error['message']);
        }
        if ($errors !== []) {
            return $report;
        }
        $package = $report->package();
        $this->tasks->validate($package, $report);
        $this->pronunciation->validate($package, $report);
        $this->references->validate($package, $report);

        return $report;
    }

    public function validateFile(string $path): PackageValidationReport
    {
        $limit = config('daily_practice.import.max_bytes');
        if (! is_file($path) || ! is_readable($path)) {
            $report = new PackageValidationReport('');
            $report->error('upload_unreadable', '/file', 'Не удалось прочитать загруженный файл.');

            return $report;
        }
        if (filesize($path) > $limit) {
            $report = new PackageValidationReport('');
            $report->error('file_too_large', '/file', 'Загруженный JSON превышает допустимый размер.');

            return $report;
        }
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            $report = new PackageValidationReport('');
            $report->error('upload_unreadable', '/file', 'Не удалось прочитать загруженный файл.');

            return $report;
        }
        try {
            $json = stream_get_contents($stream, $limit + 1);
        } finally {
            fclose($stream);
        }
        if ($json === false) {
            $report = new PackageValidationReport('');
            $report->error('upload_unreadable', '/file', 'Не удалось прочитать загруженный файл.');

            return $report;
        }

        return $this->validate($json);
    }
}
