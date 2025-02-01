<?php

namespace App\DTOs\Forum\Message;

use App\Traits\Makeable;
use Illuminate\Http\Request;

class NewMessageDTO
{
    use Makeable;

    public function __construct(
        protected readonly string $message,
        protected readonly int $topic_id,
        protected readonly int $user_id,
        protected readonly int $status_id,
    )
    {
    }

    public static function fromRequest(Request $request)
    {
        return static::make(...$request->only(['message', 'topic_id', 'user_id', 'status_id']));
    }
}