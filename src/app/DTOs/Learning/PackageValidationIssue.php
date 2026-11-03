<?php

namespace App\DTOs\Learning;

final readonly class PackageValidationIssue
{
    public function __construct(
        public string $code,
        public string $path,
        public string $message,
        public ?string $sessionId = null,
        public ?string $taskId = null,
        public bool $blocksPublication = false,
    ) {}

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'path' => $this->path,
            'message' => $this->message,
            'session_id' => $this->sessionId,
            'task_id' => $this->taskId,
            'blocks_publication' => $this->blocksPublication,
        ];
    }
}
