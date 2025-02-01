<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomCurrentPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
//        dump(bcrypt($value));
//        dd(Auth::user()->password);
        // Проверяем, совпадает ли введенный пароль с паролем пользователя
//        dd(Hash::check(md5($value), Auth::user()->password));
        if(!Auth::check() || !Hash::check(md5($value), Auth::user()->password)){
            $fail('Пароль неверен.');
        }
    }
}
