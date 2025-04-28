<?php

namespace App\Traits;

trait Makeable
{
    public static function make(mixed ...$args): static
    {
        return new static(...$args);
    }
}
