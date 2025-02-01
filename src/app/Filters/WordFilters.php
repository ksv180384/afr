<?php

namespace App\Filters;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Builder;

class WordFilters extends Filter
{
    protected function text(string $value): Builder
    {
        if(!Helper::isLatin($value)){
            return $this->builder->where('translation', 'LIKE', '%' . $value . '%');
        }
        else{
            return $this->builder->where('word', 'LIKE', '%' . $value . '%');
        }
    }
}
