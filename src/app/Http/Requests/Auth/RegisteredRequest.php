<?php

namespace App\Http\Requests\Auth;

use App\Models\User\User;
use App\Rules\IsTrue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisteredRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:20|unique:'.User::class,
            'email' => 'required|string|lowercase|email|max:100|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'confirmation_rules' => ['required', 'boolean', new IsTrue('Подтвердите, что принимаете правила и политику сайта.')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Имя/Логин не должно быть пустым.',
            'name.string' => 'Имя/Логин должно быть строкой.',
            'name.max' => 'Имя/Логин максимальное количество символов 20.',
            'name.unique' => 'Пользователь с таким именем уже существует.',
            'email.required' => 'Email не должно быть пустым.',
            'email.string' => 'Email должно быть строкой.',
            'email.lowercase' => 'Email символы должны быть в нижнем регистре.',
            'email.email' => 'Email некорректен.',
            'email.max' => 'Email максимальное количество символов 100.',
            'email.unique' => 'Пользователь с таким Email уже существует.',
            'email.password' => 'Пароль не должно быть пустым.',
            'email.confirmed' => 'Неверно подтвержден пароль.',
            'confirmation_rules.required' => 'Подтвердите, что принимаете правила и политику сайта.',
            'confirmation_rules.boolean' => 'Подтвердите, что принимаете правила и политику сайта.',
        ];
    }
}
