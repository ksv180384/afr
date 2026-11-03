<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class PracticeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['client_report_id' => 'required|uuid', 'session_version_id' => 'required|integer|min:1',
            'task_id' => 'nullable|string|max:128', 'run_id' => 'nullable|uuid', 'text' => 'required|string|max:4000',
            'include_answer' => 'required|boolean', 'answer' => 'sometimes|array|max:4'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (strlen($this->getContent()) > 16384) {
                $validator->errors()->add('text', 'Сообщение слишком большое.');
            }
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Неизвестное поле.');
            }
            if ($this->has('answer') && ! $this->boolean('include_answer')) {
                $validator->errors()->add('answer', 'Ответ можно приложить только по явному выбору.');
            }
            if (is_array($this->input('answer')) && array_diff(array_keys($this->input('answer', [])), ['value', 'choice_id', 'pairs', 'token_ids'])) {
                $validator->errors()->add('answer', 'Некорректный формат ответа.');
            }
        });
    }
}
