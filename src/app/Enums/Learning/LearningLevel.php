<?php

namespace App\Enums\Learning;

enum LearningLevel: string
{
    case PreA1 = 'PRE_A1';
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function title(): string
    {
        return $this === self::PreA1 ? 'С нуля' : $this->value;
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->rank() + 1] ?? null;
    }
}
