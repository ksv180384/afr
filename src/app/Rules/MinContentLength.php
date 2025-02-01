<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MinContentLength implements ValidationRule
{
    const MIN_CONTENT_LEN = 2;

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = strip_tags($value);
        if (mb_strlen($text) < self::MIN_CONTENT_LEN) {
            $fail('Контент должен быть не менее 2-х символов.');
        }
    }
}
