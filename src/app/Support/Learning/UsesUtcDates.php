<?php

namespace App\Support\Learning;

use Illuminate\Support\Carbon;

trait UsesUtcDates
{
    protected function asDateTime($value): Carbon
    {
        if (is_string($value) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $value)) {
            return Carbon::createFromFormat('!Y-m-d', $value, 'UTC');
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/D', $value)) {
            return Carbon::parse($value, 'UTC');
        }

        return parent::asDateTime($value);
    }

    public function fromDateTime($value): mixed
    {
        return empty($value) ? $value : parent::fromDateTime($this->asDateTime($value)->utc());
    }

    public function freshTimestamp(): Carbon
    {
        return Carbon::now('UTC');
    }
}
