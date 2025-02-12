<?php

namespace App\Filters\Admin;

use App\Filters\Filter;
use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Builder;

class DictionaryFilter extends Filter
{
    protected function text(string $value): Builder
    {
        return $this->builder
            ->where('word', 'LIKE', '%' . $value . '%')
            ->where('example', 'LIKE', '%' . $value . '%');
    }

    protected function partOfSpeeches(int $value): Builder
    {
        return $this->builder->where('id_part_of_speech', $value);
    }
}
