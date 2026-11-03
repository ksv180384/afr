<?php

namespace App\Http\Requests\Learning;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AccountRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $revision = ['required', 'integer', 'min:1', function ($attribute, $value, $fail) {
            if (! is_int($value)) {
                $fail('Ревизия должна быть целым JSON-числом.');
            }
        }];
        $event = ['client_event_id' => ['required', 'uuid'], 'expected_revision' => $revision];

        return match ($this->route()->getActionMethod()) {
            'start' => [...$event, 'kind' => ['required', Rule::in(['daily', 'extra', 'review', 'vocabulary_test'])],
                'session_id' => ['nullable', 'string', 'max:128'], 'replace_assignment' => ['sometimes', 'boolean'], 'timezone' => ['nullable', 'timezone:all'],
                'word_ids' => ['sometimes', 'array', 'min:1', 'max:5000'], 'word_ids.*' => ['integer', 'min:1', 'distinct'], 'filter' => ['sometimes', Rule::in(['all', 'errors'])], 'source_session' => ['sometimes', 'string', 'max:128'], 'continue_run_id' => ['sometimes', 'uuid']],
            'profileUpdate' => [...$event, 'current_level' => ['required', Rule::in(array_column(config('daily_practice.levels'), 'code'))], 'timezone' => ['required', 'timezone:all']],
            'answer', 'help' => [...$event, 'presentation_id' => ['required', 'uuid'], 'event_type' => ['required', Rule::in($this->route()->getActionMethod() === 'answer' ? ['answer'] : ['help', 'unknown', 'technical_skip'])],
                'answer' => ['sometimes', 'array'], 'help_kind' => ['sometimes', 'string'], 'reason' => ['sometimes', 'string'], 'client_occurred_at' => ['sometimes', 'string', 'max:40']],
            'pause', 'resume', 'finish' => $event,
            'advance', 'introduction' => [...$event, 'presentation_id' => ['required', 'uuid']],
            'history' => ['page' => ['sometimes', 'integer', 'min:1', 'max:100000']],
            default => [],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('get')) {
                return;
            }
            $allowed = array_filter(array_keys($this->rules()), fn ($key) => ! str_contains($key, '.'));
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'Неизвестное поле запроса.');
            }
        });
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json(['code' => 'validation_failed', 'message' => 'Проверьте данные запроса.', 'errors' => $validator->errors()->toArray()], 422));
    }
}
