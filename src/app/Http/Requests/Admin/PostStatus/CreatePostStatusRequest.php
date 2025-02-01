<?php

namespace App\Http\Requests\Admin\PostStatus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CreatePostStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|min:2',
            'alias' => 'required|min:2',
            'for_create' => 'required',
        ];
    }

    public function prepareForValidation()
    {
        $requestData = $this->all();

        $this->merge([
            'alias' => !empty($requestData['alias']) ? $requestData['alias'] : Str::slug($requestData['title']),
        ]);
    }
}
