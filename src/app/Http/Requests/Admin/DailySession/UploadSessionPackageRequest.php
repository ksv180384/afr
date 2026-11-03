<?php

namespace App\Http\Requests\Admin\DailySession;

use App\DTOs\Learning\PackageValidationReport;
use App\Services\Learning\SessionPackageValidator;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use LogicException;

class UploadSessionPackageRequest extends FormRequest
{
    private ?PackageValidationReport $packageReport = null;

    public function authorize(): bool
    {
        app(\App\Services\Learning\ContentAccess::class)->check($this->user());

        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:'.(int) ceil(config('daily_practice.import.max_bytes') / 1024), 'extensions:json'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Выберите JSON-пакет.',
            'file.file' => 'Файл не был успешно загружен.',
            'file.max' => 'JSON-пакет превышает допустимый размер.',
            'file.extensions' => 'Выберите файл с расширением .json.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('file')) {
                    return;
                }
                $this->packageReport = app(SessionPackageValidator::class)->validateFile($this->file('file')->getRealPath());
                foreach ($this->packageReport->errors() as $issue) {
                    $validator->errors()->add('file'.$issue->path, $issue->message);
                }
            },
        ];
    }

    public function packageReport(): PackageValidationReport
    {
        return $this->packageReport ?? throw new LogicException('Package upload has not been validated.');
    }

    protected function failedValidation(ValidatorContract $validator): never
    {
        $report = $this->packageReport ?? new PackageValidationReport('');
        if ($this->packageReport === null) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $report->error('upload_invalid', '/'.$field, $message);
                }
            }
        }
        throw new HttpResponseException(response()->json([
            'code' => 'validation_failed',
            'message' => 'JSON-пакет не прошёл проверку.',
            ...$report->toArray(),
        ], 422));
    }
}
