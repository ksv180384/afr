<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class GuestSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['level_selection_event_id' => 'sometimes|uuid', 'guest_id' => 'required|uuid', 'episode_id' => 'nullable|uuid', 'batch_id' => 'required|uuid',
            'level_events' => 'present|array|max:100', 'level_events.*' => 'array', 'runs' => 'present|array|max:5', 'runs.*' => 'array',
            'review_snooze' => 'sometimes|array:until,timezone,expected_revision',
            'review_snooze.until' => ['required_with:review_snooze', 'date_format:Y-m-d\\TH:i:sP'],
            'review_snooze.timezone' => 'required_with:review_snooze|timezone:all',
            'review_snooze.expected_revision' => 'required_with:review_snooze|integer|min:1',
            'marks' => 'present|array|max:500', 'marks.*' => 'array'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_diff(array_keys($this->all()), array_filter(array_keys($this->rules()), fn ($key) => ! str_contains($key, '.'))) as $key) {
                $validator->errors()->add($key, 'Неизвестное поле запроса.');
            }
            if (strlen($this->getContent()) > 2 * 1024 * 1024) {
                $validator->errors()->add('batch', 'Пакет превышает допустимый размер.');
            }
        });
    }

    public function messages(): array
    {
        return ['runs.max' => 'Переносите не больше пяти прохождений одним пакетом.', 'guest_id.uuid' => 'Некорректный ID гостевого набора.'];
    }
}
