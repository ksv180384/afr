<?php

namespace App\Http\Requests\Admin\Song;

use App\Models\Player\PlayerArtistsSong;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Нормализует выбранных и новых исполнителей перед выполнением валидации.
     */
    protected function prepareForValidation(): void
    {
        $artists = collect($this->input('artists', []))
            ->filter(fn ($artist) => is_array($artist))
            ->map(function (array $artist): array {
                $id = $artist['id'] ?? null;
                $name = $artist['name'] ?? null;

                return [
                    'id' => is_numeric($id) && (int) $id > 0 ? (int) $id : null,
                    'name' => is_string($name) && trim($name) !== '' ? trim($name) : null,
                ];
            })
            ->filter(fn (array $artist) => $artist['id'] !== null || $artist['name'] !== null)
            ->unique(fn (array $artist) => $artist['id'] !== null
                ? 'id:' . $artist['id']
                : 'name:' . mb_strtolower($artist['name']))
            ->values()
            ->all();

        $this->merge(['artists' => $artists]);
    }

    /**
     * Возвращает правила проверки обновляемой песни и списка исполнителей.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artists' => [
                'bail',
                'required',
                'array',
                'min:1',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $namesById = PlayerArtistsSong::query()
                        ->whereIn('id', collect($value)->pluck('id')->filter())
                        ->pluck('name', 'id');
                    $artistName = collect($value)->map(fn (array $artist) => $artist['id'] !== null
                        ? $namesById->get($artist['id'], '')
                        : $artist['name'])->filter()->implode(', ');

                    if (mb_strlen($artistName) > 255) {
                        $fail('Общая длина имён исполнителей не должна превышать 255 символов.');
                    }
                },
            ],
            'artists.*' => ['required', 'array'],
            'artists.*.id' => ['nullable', 'integer', 'exists:player_artists_songs,id'],
            'artists.*.name' => ['nullable', 'string', 'min:1', 'max:255'],
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
