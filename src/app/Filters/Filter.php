<?php

namespace App\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Filter
{
    public const KEYS_TO_BOOL = [];
    public const KEYS_TO_INT = [
        'status_id',
    ];
    public const KEYS_TO_DATE = [];
    public const KEYS_STRING_TO_ARRAY = [];
    public const KEYS_TO_ARRAY = [];

    protected Builder $builder;

    /**
     * @param FormRequest $request
     */
    public function __construct(protected readonly Request $request)
    {
    }

    /**
     * Применение фильтров к запросу
     *
     * @param Builder $builder
     * @return Builder
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->input() as $method => $value) {
            $methodName = Str::camel($method);

            if (null === $value) {
                continue;
            }

            if (method_exists($this, $methodName)) {
                if (in_array($method, static::KEYS_TO_BOOL, true)) {
                    $value = (bool)$value;
                }

                if (in_array($method, static::KEYS_TO_INT, true)) {
                    $value = (int)$value;
                }

                if (in_array($method, static::KEYS_TO_DATE, true)) {
                    $value = CarbonImmutable::parse($value);
                }

                if (in_array($method, static::KEYS_TO_ARRAY, true)) {
                    $value = is_array($value) ? $value : [$value];
                }

                if (in_array($method, static::KEYS_STRING_TO_ARRAY, true)) {
                    $value = explode(',', $value);
                }

                $this->builder = $this->{$methodName}($value);
            }
        }

        return $this->builder;
    }
}
