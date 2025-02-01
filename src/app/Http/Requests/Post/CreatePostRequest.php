<?php

namespace App\Http\Requests\Post;

use App\Rules\MinContentLength;
use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
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
            'content' => [
                'required',
                new  MinContentLength(),
            ],
            'status_id' => 'required|exists:post_statuses,id',
        ];
    }

    public function attributes()
    {
        return [
            'title' => 'заголовок',
            'content' => 'контент',
            'status_id' => 'статус',
        ];
    }
}
