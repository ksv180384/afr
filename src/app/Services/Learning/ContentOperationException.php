<?php

namespace App\Services\Learning;

use App\Enums\Learning\PracticeErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ContentOperationException extends RuntimeException
{
    public function __construct(public readonly PracticeErrorCode $errorCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['code' => $this->errorCode->value, 'message' => $this->getMessage(), 'details' => $this->details], $this->errorCode->httpStatus());
    }
}
