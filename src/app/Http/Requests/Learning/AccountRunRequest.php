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
            'today' => ['level' => ['sometimes', 'string', 'max:128'], 'unit' => ['sometimes', 'string', 'max:128']],
            'start' => [...$event, 'expected_owner_user_id' => ['sometimes', 'integer', 'min:1'], 'kind' => ['required', Rule::in(['daily', 'extra', 'review', 'vocabulary_test'])], 'review_mode' => ['sometimes', 'required', Rule::in(['manual', 'suggested']), 'prohibited_unless:kind,review'],
                'session_id' => ['nullable', 'string', 'max:128'], 'replace_assignment' => ['sometimes', 'boolean'], 'route_start' => ['sometimes', 'boolean'], 'timezone' => ['nullable', 'timezone:all'],
                'word_ids' => ['sometimes', 'array', 'min:1', 'max:5000'], 'word_ids.*' => ['integer', 'min:1', 'distinct'], 'filter' => ['sometimes', Rule::in(['all', 'errors'])], 'source_session' => ['sometimes', 'string', 'max:128'], 'continue_run_id' => ['sometimes', 'uuid']],
            'profileUpdate' => [...$event, 'current_level' => ['required', Rule::in(array_column(\App\Services\Learning\AvailableLevels::choices(), 'code'))], 'timezone' => ['required', 'timezone:all']],
            'reviewSettings' => [...$event, 'action' => ['required', Rule::in(['toggle', 'snooze_today'])],
                'enabled' => ['required_if:action,toggle', 'prohibited_unless:action,toggle', 'boolean', function ($attribute, $value, $fail) {
                    if (! is_bool($value)) { $fail('Нужно логическое JSON-значение.'); }
                }]],
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
