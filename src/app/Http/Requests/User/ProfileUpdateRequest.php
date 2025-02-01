<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'birthday' => 'nullable|date|before_or_equal:today',
            'gender_id' => 'nullable|exists:genders,id',
            'info' => 'nullable|string',
            'odnoklassniki' => 'nullable|string',
            'residence' => 'nullable|string',
            'skype' => 'nullable|string',
            'telegram' => 'nullable|string',
            'vk' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'youtube' => 'nullable|string',
            'view_email' => 'nullable|exists:user_configs_views,id',
            'view_info' => 'nullable|exists:user_configs_views,id',
            'view_odnoklassniki' => 'nullable|exists:user_configs_views,id',
            'view_residence' => 'nullable|exists:user_configs_views,id',
            'view_sex' => 'nullable|exists:user_configs_views,id',
            'view_skype' => 'nullable|exists:user_configs_views,id',
            'view_telegram' => 'nullable|exists:user_configs_views,id',
            'view_vk' => 'nullable|exists:user_configs_views,id',
            'view_whatsapp' => 'nullable|exists:user_configs_views,id',
            'view_youtube' => 'nullable|exists:user_configs_views,id',
            'day_birthday' => 'required|boolean',
            'yar_birthday' => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'birthday.before_or_equal' => 'Дата рождения должна быть не позже текущей даты.',
        ];
    }

    public function attributes()
    {
        return [
            'birthday' => 'Дата рождения',
            'today' => 'текущей дате',
        ];
    }
}
