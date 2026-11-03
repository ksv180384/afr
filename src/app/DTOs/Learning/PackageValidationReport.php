<?php

namespace App\DTOs\Learning;

final class PackageValidationReport
{
    private array $errors = [];

    private array $warnings = [];

    private ?array $content = null;

    private bool $truncated = false;

    public function __construct(
        public readonly string $fileHash,
        private readonly int $maxIssues = 1000,
    ) {}

    public function setPackage(array $package): void
    {
        $this->content = $package;
        foreach ($this->errors as $key => $issue) {
            $this->errors[$key] = $this->issue($issue->code, $issue->path, $issue->message, $issue->blocksPublication);
        }
    }

    public function package(): ?array
    {
        return $this->isValid() ? $this->content : null;
    }

    public function isValid(): bool
    {
        return $this->errors === [] && $this->content !== null;
    }

    public function errors(): array
    {
        return array_values($this->errors);
    }

    public function warnings(): array
    {
        return array_values($this->warnings);
    }

    public function error(string $code, string $path, string $message): void
    {
        if (count($this->errors) >= $this->maxIssues) {
            $this->truncated = true;
            $this->errors['limit'] = new PackageValidationIssue('error_limit_exceeded', '', 'Слишком много ошибок: отчёт ограничен. Исправьте показанные ошибки и повторите проверку.');

            return;
        }
        $this->errors[$code.':'.$path] = $this->issue($code, $path, $message, true);
    }

    public function warning(string $code, string $path, string $message, bool $blocksPublication = true): void
    {
        if (count($this->warnings) >= $this->maxIssues) {
            $this->truncated = true;
            $this->warnings['limit'] = new PackageValidationIssue('warning_limit_exceeded', '', 'Список замечаний ограничен; требуется редакторская проверка.', blocksPublication: true);

            return;
        }
        $this->warnings[$code.':'.$path] = $this->issue($code, $path, $message, $blocksPublication);
    }

    private function issue(string $code, string $path, string $message, bool $blocksPublication): PackageValidationIssue
    {
        $sessionId = null;
        $taskId = null;
        if (preg_match('~^/sessions/(\\d+)~', $path, $match)) {
            $session = $this->content['sessions'][(int) $match[1]] ?? [];
            $sessionId = is_string($session['id'] ?? null) ? $session['id'] : null;
            if (preg_match('~/tasks/(\\d+)~', $path, $task)) {
                $taskId = $session['tasks'][(int) $task[1]]['id'] ?? null;
            } elseif (preg_match('~/vocabulary/(\\d+)/review_items/(\\d+)~', $path, $review)) {
                $taskId = $session['vocabulary'][(int) $review[1]]['review_items'][(int) $review[2]]['id'] ?? null;
            }
        }

        return new PackageValidationIssue(
            $code, $path, $message,
            is_string($sessionId) && mb_strlen($sessionId) <= 128 ? $sessionId : null,
            is_string($taskId) && mb_strlen($taskId) <= 128 ? $taskId : null,
            $blocksPublication,
        );
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'file_hash' => $this->fileHash,
            'errors' => array_map(fn (PackageValidationIssue $issue): array => $issue->toArray(), $this->errors()),
            'warnings' => array_map(fn (PackageValidationIssue $issue): array => $issue->toArray(), $this->warnings()),
            'truncated' => $this->truncated,
            'requires_editorial_review' => true,
        ];
    }
}
