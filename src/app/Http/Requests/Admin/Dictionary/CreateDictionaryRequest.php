<?php

namespace App\Http\Requests\Admin\Dictionary;

use Illuminate\Foundation\Http\FormRequest;

class CreateDictionaryRequest extends FormRequest
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
            'id_part_of_speech' => 'nullable|exists:words_part_of_speeches,id',
            'word' => 'required|min:2',
            'translation' => 'required|min:2',
            'transcription' => 'required|min:2',
            'example' => 'required|min:2',
        ];
    }
}
