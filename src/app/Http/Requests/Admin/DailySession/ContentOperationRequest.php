<?php

namespace App\Http\Requests\Admin\DailySession;

use App\Services\Learning\ContentAccess;
use Illuminate\Foundation\Http\FormRequest;

class ContentOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ContentAccess::class)->check($this->user());

        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'commit' => ['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'file_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']],
            'reviewVersion', 'reviewCatalog', 'reviewCurriculum' => [
                'checks' => ['required', 'array:'.implode(',', ContentAccess::REVIEW_CHECKS)],
                ...array_fill_keys(array_map(fn (string $key): string => 'checks.'.$key, ContentAccess::REVIEW_CHECKS), ['required', 'accepted']),
                'notes' => ['nullable', 'string', 'max:20000'],
            ],
            'changeStatus' => ['version_ids' => ['required', 'array', 'min:1', 'max:100'], 'version_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
                'status' => ['required', 'in:draft,reviewed,published,archived'], 'notes' => ['sometimes', 'nullable', 'string', 'max:20000']],
            'publishMany' => ['version_ids' => ['required', 'array', 'min:1', 'max:100'], 'version_ids.*' => ['required', 'integer', 'min:1', 'distinct']],
            'archiveSessions' => ['session_ids' => ['required', 'array', 'min:1', 'max:100'], 'session_ids.*' => ['required', 'integer', 'min:1', 'distinct']],
            default => [],
        };
    }

    public function checks(): array
    {
        return array_fill_keys(ContentAccess::REVIEW_CHECKS, true);
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): never
    {
        throw new \App\Services\Learning\ContentOperationException(\App\Enums\Learning\PracticeErrorCode::ValidationFailed, 'Проверьте поля запроса.', ['errors' => $validator->errors()->toArray()]);
    }

    public function messages(): array
    {
        return ['token.required' => 'Укажите token preview.', 'file_hash.required' => 'Укажите hash файла.',
            'checks.*.accepted' => 'Подтвердите каждый пункт редакторской проверки.',
            'version_ids.required' => 'Выберите версии.', 'session_ids.required' => 'Выберите сессии.'];
    }
}
