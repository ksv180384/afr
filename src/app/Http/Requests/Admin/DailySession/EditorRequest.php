<?php

namespace App\Http\Requests\Admin\DailySession;

use Illuminate\Validation\Rule;

class EditorRequest extends ContentOperationRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index', 'catalog', 'reports', 'media' => [
                'search' => ['nullable', 'string', 'max:200'],
                'level' => ['nullable', Rule::in(array_column(config('daily_practice.levels'), 'code'))],
                'unit' => ['nullable', 'string', 'max:128'],
                'status' => ['nullable', Rule::in(['draft', 'reviewed', 'published', 'archived', 'open', 'in_review', 'resolved', 'dismissed', 'pending', 'approved'])],
                'version' => ['nullable', 'integer', 'min:1'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
                'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'curricula_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            ],
            'renameSession', 'renameUnit' => ['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'], 'expected_slug' => ['required', 'string', 'max:200'], 'confirmed' => ['required', 'accepted']],
            'storeMedia' => ['file' => ['required', 'file', 'max:10240', 'mimetypes:audio/wav,audio/x-wav,audio/vnd.wave']],
            'approveMedia' => ['confirmed' => ['required', 'accepted']],
            'storeReport' => ['version_id' => ['required', 'integer', 'min:1'], 'task_id' => ['nullable', 'string', 'max:128'], 'text' => ['required', 'string', 'max:10000']],
            'updateReport' => ['status' => ['required', Rule::in(['open', 'in_review', 'resolved', 'dismissed'])], 'editor_notes' => ['nullable', 'string', 'max:10000']],
            'preview' => ['frame' => ['nullable', 'boolean'], 'scenario' => ['nullable', Rule::in(['session', 'load_error', 'empty', 'exhausted', 'save'])]],
            default => [],
        };
    }
}
