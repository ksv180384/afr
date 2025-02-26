<?php

namespace App\Http\Requests\PostComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreatePostCommentRequest extends FormRequest
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
            'post_id' => 'required|exists:posts,id',
            'user_id' => 'required',
            'comment' => 'required|min:2',
        ];
    }

    public function attributes()
    {
        return [
            'comment' => 'комментарий',
            'post_id' => 'пост',
            'user_id' => 'пользователь',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'user_id' => Auth::id(),
        ]);
    }
}
