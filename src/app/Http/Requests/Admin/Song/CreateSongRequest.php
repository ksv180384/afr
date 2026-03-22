<?php

namespace App\Http\Requests\Admin\Song;

use Illuminate\Foundation\Http\FormRequest;

class CreateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artist_id' => ['required', 'exists:player_artists_songs,id'],
            'title' => ['required', 'string', 'min:2'],
            'duration' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $value = trim((string) $value);
                    if ($value !== '' && !preg_match('/^\d+:(?:[0-5]\d|[0-9])$/', $value)) {
                        $fail('Продолжительность должна быть в формате минуты:секунды (например, 2:36)');
                    }
                },
            ],
            'text_fr' => ['required', 'string'],
            'text_ru' => ['required', 'string'],
            'text_transcription' => ['required', 'string'],
            'hidden' => ['boolean'],
        ];
    }

}
