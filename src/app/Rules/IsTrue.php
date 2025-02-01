<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsTrue implements ValidationRule
{

    public function __construct(private ?string $errorMessage)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $errorMessage = 'Поле :attribute должно быть активно.';

        if($value !== true){
            if($this->errorMessage){
                $errorMessage = $this->errorMessage;
            }
            $fail($errorMessage);
        }
    }
}
