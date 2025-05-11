<?php

namespace App\Http\Requests\SongComment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateSongCommentRequest extends FormRequest
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
            'song_id' => 'required|exists:player_songs,id',
            'parent_comment_id' => 'nullable|exists:player_song_comments,id',
            'user_id' => 'required|exists:users,id',
            'comment' => 'required|min:2',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => Auth::check() ? Auth::id() : null,
        ]);
    }
}
