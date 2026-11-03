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
            'marks' => 'present|array|max:500', 'marks.*' => 'array'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
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
