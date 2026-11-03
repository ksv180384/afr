<?php

namespace App\DTOs\Learning;

final readonly class AnswerCheckResult
{
    public function __construct(public bool $correct, public array $pairs, public array $solution) {}

    public function toArray(): array
    {
        return ['correct' => $this->correct, 'pairs' => $this->pairs, 'solution' => $this->solution];
    }
}
