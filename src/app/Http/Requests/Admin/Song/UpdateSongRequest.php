<?php

namespace App\Http\Requests\Admin\Song;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('artist_id');
        $artistId = (is_numeric($id) && (int) $id > 0) ? (int) $id : null;

        $name = $this->input('artist_name');
        $artistName = (is_string($name) && trim($name) !== '') ? trim($name) : null;

        $this->merge([
            'artist_id' => $artistId,
            'artist_name' => $artistName,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $artistId = $this->input('artist_id');
            $artistName = $this->input('artist_name');

            $hasId = $artistId !== null;
            $hasName = $artistName !== null;

            if (! $hasId && ! $hasName) {
                $validator->errors()->add(
                    'artist_id',
                    'Выберите исполнителя из списка или введите имя нового исполнителя.'
                );
            }
        });
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artist_id' => ['nullable', 'integer', 'exists:player_artists_songs,id'],
            'artist_name' => ['nullable', 'string', 'min:1', 'max:255'],
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
