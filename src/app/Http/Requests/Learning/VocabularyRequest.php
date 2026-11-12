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
        $filters = [
            'filter' => ['sometimes', Rule::in(['all', 'errors', 'due'])],
            'source_session' => ['sometimes', 'string', 'max:128'],
            'q' => ['sometimes', 'string', 'max:100'],
        ];

        return match ($this->route()->getActionMethod()) {
            'mark' => ['needs_review' => ['required', 'boolean']],
            'reviewPlan' => [...$filters, 'mode' => ['sometimes', Rule::in(['manual', 'suggested'])]],
            default => [...$filters, 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']],
        };
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
