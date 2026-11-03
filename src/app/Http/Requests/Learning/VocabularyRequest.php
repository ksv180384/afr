<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VocabularyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return $this->route()->getActionMethod() === 'mark'
            ? ['needs_review' => ['required', 'boolean']]
            : ['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'filter' => ['sometimes', Rule::in(['all', 'errors'])], 'source_session' => ['sometimes', 'string', 'max:128'], 'q' => ['sometimes', 'string', 'max:100']];
    }

    public function withValidator($validator): void
    {
        if (! $this->isMethod('GET')) {
            $validator->after(function ($validator): void {
                foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                    $validator->errors()->add($key, 'Неизвестное поле запроса.');
                }
            });
        }
    }

    public function messages(): array
    {
        return ['needs_review.required' => 'Укажите отметку повторения.', 'filter.in' => 'Выберите доступный фильтр.'];
    }
}
